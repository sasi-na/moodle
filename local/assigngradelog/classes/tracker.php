<?php
namespace local_assigngradelog;

defined('MOODLE_INTERNAL') || die();

class tracker {

    /**
     * Record a completed offline grade import.
     *
     * Called from mod/assign/feedback/offline/locallib.php::process_import_grades()
     * after the import loop finishes.
     *
     * @param \assign  $assignment         The assignment instance.
     * @param string   $filename           Original uploaded filename.
     * @param int      $totalrows          Total CSV data rows processed.
     * @param int      $updategradecount   Rows where the grade value was changed.
     * @param int      $updatefeedbackcount Rows where feedback/comments were changed.
     * @param array    $skipreasons        Array of ['fullname','email','reason'] for non-trivial skips.
     */
    public static function record(
        \assign $assignment,
        string  $filename,
        int     $totalrows,
        int     $updategradecount,
        int     $updatefeedbackcount,
        array   $skipreasons
    ): void {
        global $DB, $USER;

        $skipped = $totalrows - $updategradecount;

        $record                    = new \stdClass();
        $record->assignmentid      = $assignment->get_instance()->id;
        $record->cmid              = $assignment->get_course_module()->id;
        $record->courseid          = $assignment->get_course()->id;
        $record->uploaderid        = $USER->id;
        $record->timeuploaded      = time();
        $record->filename          = $filename;
        $record->totalrows         = $totalrows;
        $record->updated           = $updategradecount;
        $record->feedbackupdated   = $updatefeedbackcount;
        $record->skipped           = $skipped;
        $record->errordetail       = !empty($skipreasons) ? json_encode($skipreasons) : null;
        $record->status            = 'complete';

        $DB->insert_record('local_assigngradelog_upload', $record);
    }
}
