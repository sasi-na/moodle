<?php

namespace local_journalgrader\task;

defined('MOODLE_INTERNAL') || die();

class process_queue extends \core\task\scheduled_task {

    /**
     * Task name.
     */
    public function get_name() {

        return 'Journal Auto Grader';
    }

    /**
     * Execute scheduled grading queue.
     */
    public function execute() {

        global $DB, $CFG;

        require_once(
            $CFG->dirroot .
            '/mod/assign/locallib.php'
        );

        $records =
            $DB->get_records(
                'local_journalgrader_queue',
                ['processed' => 0],
                'id ASC'
            );

        if (empty($records)) {

            mtrace(
                'No pending journal grading records'
            );

            return;
        }

        foreach ($records as $record) {

            try {

                mtrace(
                    'Processing queue ID: '
                    .
                    $record->id
                );

                $this->process_submission(
                    $record
                );

                $record->processed = 1;

                $DB->update_record(
                    'local_journalgrader_queue',
                    $record
                );

                mtrace(
                    'Successfully processed queue ID: '
                    .
                    $record->id
                );

            } catch (\Throwable $e) {

                mtrace(
                    'Journal grader error: '
                    .
                    $e->getMessage()
                );
            }
        }
    }

    /**
     * Process single submission.
     */
    private function process_submission(
        $record
    ): void {

        global $DB;

        $cmid =
            (int) $record->cmid;

        $userid =
            (int) $record->userid;

        /*
         * Validate module.
         */

        $cm =
            get_coursemodule_from_id(

                'assign',

                $cmid,

                0,

                false,

                MUST_EXIST
            );

        $assignmentid =
            (int) $cm->instance;

        $context =
            \context_module::instance(
                $cmid
            );

        /*
         * Ensure answer key exists.
         */

        $answerkey =
            \local_journalgrader\grader::load_answer_key(
                $cmid
            );

        if (!$answerkey) {

            throw new \moodle_exception(
                'Missing answer key'
            );
        }

        /*
         * Validate grading type.
         */

        $type =
            strtolower(
                trim(
                    $answerkey['type']
                    ?? 'journal'
                )
            );

        $supportedtypes = [

            'journal',

            'ledger',

            'profit_and_loss',

            'balance_sheet'
        ];

        if (
            !in_array(
                $type,
                $supportedtypes,
                true
            )
        ) {

            throw new \moodle_exception(
                'Unsupported grading type: '
                .
                $type
            );
        }

        /*
         * Get latest submitted attempt.
         */

        $submission =
            $DB->get_record_sql(

                "
                SELECT *
                FROM {assign_submission}
                WHERE assignment = ?
                AND userid = ?
                AND status = ?
                ORDER BY timemodified DESC
                ",

                [
                    $assignmentid,
                    $userid,
                    'submitted'
                ],

                IGNORE_MULTIPLE
            );

        if (!$submission) {

            throw new \moodle_exception(
                'No submitted assignment found'
            );
        }

        /*
         * Get uploaded files.
         */

        $fs =
            get_file_storage();

        $files =
            $fs->get_area_files(

                $context->id,

                'assignsubmission_file',

                'submission_files',

                $submission->id,

                'filename',

                false
            );

        if (empty($files)) {

            throw new \moodle_exception(
                'No submission files found'
            );
        }

        $graded = false;

        foreach ($files as $file) {

            /*
             * Only CSV supported.
             */

            $extension =
                strtolower(
                    pathinfo(
                        $file->get_filename(),
                        PATHINFO_EXTENSION
                    )
                );

            if ($extension !== 'csv') {
                continue;
            }

            /*
             * Create temp file.
             */

            $tempfile =
                tempnam(
                    sys_get_temp_dir(),
                    'journal_'
                );

            if (!$tempfile) {

                throw new \moodle_exception(
                    'Unable to create temp file'
                );
            }

            /*
             * Write file content.
             */

            file_put_contents(

                $tempfile,

                $file->get_content()
            );

            /*
             * Grade CSV.
             */

            $result =
                \local_journalgrader\grader::grade_csv(

                    $tempfile,

                    $cmid
                );

            /*
             * Cleanup.
             */

            if (
                file_exists($tempfile)
            ) {

                unlink($tempfile);
            }

            $grade =
                (float) (
                    $result['grade']
                    ?? 0
                );

            /*
             * Save grade.
             */

            $this->save_grade(

                $assignmentid,

                $userid,

                $grade
            );

            $graded = true;

            mtrace(
                'Grade saved for user '
                .
                $userid
                .
                ' => '
                .
                $grade
            );

            break;
        }

        if (!$graded) {

            throw new \moodle_exception(
                'No valid CSV file found'
            );
        }
    }

    /**
     * Save assignment grade.
     */
    private function save_grade(
        int $assignmentid,
        int $userid,
        float $grade
    ): void {

        global $DB;

        /*
         * Load course module.
         */

        $cm =
            get_coursemodule_from_instance(

                'assign',

                $assignmentid,

                0,

                false,

                MUST_EXIST
            );

        /*
         * Load context and course.
         */

        $context =
            \context_module::instance(
                $cm->id
            );

        $course =
            get_course(
                $cm->course
            );

        /*
         * Init assign object.
         */

        $assign =
            new \assign(

                $context,

                $cm,

                $course
            );

        /*
         * Get max grade.
         */

        $gradeitem =
            $DB->get_record(

                'grade_items',

                [

                    'itemmodule' =>
                        'assign',

                    'iteminstance' =>
                        $assignmentid,

                    'itemtype' =>
                        'mod'
                ],

                '*',

                MUST_EXIST
            );

        $maxgrade =
            (float) (
                $gradeitem->grademax
                ?? 100
            );

        /*
         * Clamp grade.
         */

        if ($grade < 0) {
            $grade = 0;
        }

        if ($grade > $maxgrade) {
            $grade = $maxgrade;
        }

        /*
         * Save grade using Moodle API.
         */

        $gradedata = (object) [

            'userid' =>
                $userid,

            'grade' =>
                $grade,

            'attemptnumber' =>
                -1,

            'addattempt' =>
                false
        ];

        $assign->save_grade(

            $userid,

            $gradedata
        );
    }
}