<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_reflectiveassessment;

defined('MOODLE_INTERNAL') || die();

/**
 * Per-student detail: one row per student per guide course, including students
 * who never attempted.
 *
 * Mark is filled only where a guide evaluated the attempt - a blank submission
 * carries a gradebook zero no guide awarded, so it is left empty.
 *
 * @package   local_reflectiveassessment
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class detail {

    /** @var string Student has no finished attempt. */
    const STATUS_NOTATTEMPTED = 'notattempted';

    /** @var string[] Profile field shortnames pulled onto each row. */
    const PROFILE_FIELDS = ['usn', 'provisional_enrollment_number', 'program_registered_for'];

    /**
     * Build the per-student detail for one batch/semester period.
     *
     * @param string $periodkey key from {@see summary::get_periods()}
     * @param int $courseid optional single guide course to limit to
     * @return \stdClass {period, rows, counts, generated}
     */
    public static function build(string $periodkey, int $courseid = 0): \stdClass {
        global $DB;

        // Reuse the summary so guide resolution cannot diverge between views.
        $report = summary::build($periodkey);
        $courseinfo = [];
        foreach ($report->rows as $row) {
            $courseinfo[$row->courseid] = $row;
        }

        $courseids = array_keys($courseinfo);
        if ($courseid) {
            if (!isset($courseinfo[$courseid])) {
                throw new \moodle_exception('invalidcourse', 'local_reflectiveassessment');
            }
            $courseids = [$courseid];
        }
        if (!$courseids) {
            return (object) [
                'period' => $report->period, 'rows' => [],
                'counts' => self::counts([]), 'generated' => time(),
            ];
        }

        $rows = self::fetch_enrolments($courseids);
        $attempts = self::fetch_attempt_states(array_column($rows, 'attemptid'));
        $profiles = self::fetch_profiles(array_column($rows, 'userid'));

        $graderids = [];
        foreach ($attempts as $attempt) {
            if ($attempt->graderid) {
                $graderids[(int) $attempt->graderid] = true;
            }
        }
        $graders = summary::get_users(array_keys($graderids));

        $out = [];
        foreach ($rows as $row) {
            $course = $courseinfo[$row->courseid];
            $attempt = $row->attemptid ? ($attempts[$row->attemptid] ?? null) : null;

            if (!$attempt) {
                $status = self::STATUS_NOTATTEMPTED;
            } else {
                $status = summary::classify_state($attempt->finalstate);
            }
            $evaluated = $status === 'evaluated';
            $grader = ($evaluated && $attempt->graderid) ? ($graders[$attempt->graderid] ?? null) : null;

            $profile = $profiles[$row->userid] ?? [];
            $out[] = (object) [
                'batch'        => $course->batch,
                'program'      => $course->program,
                'semester'     => $course->semester,
                'guide'        => $course->guide,
                'guideemail'   => $course->guideemail,
                'courseid'     => (int) $row->courseid,
                'coursecode'   => $course->coursecode,
                'userid'       => (int) $row->userid,
                'studentname'  => fullname($row),
                'email'        => $row->email,
                'usn'          => $profile['usn'] ?? '',
                'enrolmentno'  => $profile['provisional_enrollment_number'] ?? '',
                'programreg'   => $profile['program_registered_for'] ?? '',
                'status'       => $status,
                'submittedon'  => $attempt ? (int) $row->timefinish : 0,
                'mark'         => $evaluated ? (float) $row->sumgrades : null,
                'maxmark'      => $course->maxmark,
                'evaluatedby'  => $grader ? fullname($grader) : '',
                'evaluatedon'  => ($evaluated && $attempt->gradedtime) ? (int) $attempt->gradedtime : 0,
            ];
        }

        usort($out, function($a, $b) {
            return [$a->coursecode, $a->studentname] <=> [$b->coursecode, $b->studentname];
        });

        return (object) [
            'period'    => $report->period,
            'rows'      => $out,
            'counts'    => self::counts($out),
            'generated' => time(),
        ];
    }

    /**
     * Allocated students in the given guide courses, with any finished attempt.
     *
     * @param int[] $courseids
     * @return \stdClass[]
     */
    protected static function fetch_enrolments(array $courseids): array {
        global $DB;

        list($insql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');
        $params['student'] = 'student';
        $params['finished'] = 'finished';
        $params['qpattern'] = summary::DEFAULT_QUIZ_PATTERN;
        $configured = trim((string) get_config('local_reflectiveassessment', 'quizpattern'));
        if ($configured !== '') {
            $params['qpattern'] = $configured;
        }
        $like = $DB->sql_like('q.name', ':qpattern', false);
        $namefields = \core_user\fields::for_name()->get_sql('u')->selects;

        $rs = $DB->get_recordset_sql("
                SELECT DISTINCT ctx.instanceid AS courseid, u.id AS userid {$namefields},
                       u.email, qa.id AS attemptid, qa.timefinish, qa.sumgrades
                  FROM {context} ctx
                  JOIN {role_assignments} ra ON ra.contextid = ctx.id
                  JOIN {role} r ON r.id = ra.roleid AND r.archetype = :student
                  JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
             LEFT JOIN {quiz} q ON q.course = ctx.instanceid AND {$like}
             LEFT JOIN {quiz_attempts} qa ON qa.id = (
                           SELECT MAX(qa2.id)
                             FROM {quiz_attempts} qa2
                            WHERE qa2.quiz = q.id AND qa2.userid = u.id
                              AND qa2.state = :finished)
                 WHERE ctx.contextlevel = 50 AND ctx.instanceid {$insql}", $params);

        $rows = [];
        foreach ($rs as $row) {
            $row->courseid = (int) $row->courseid;
            $row->userid = (int) $row->userid;
            $row->attemptid = $row->attemptid ? (int) $row->attemptid : 0;
            $rows[] = $row;
        }
        $rs->close();

        return $rows;
    }

    /**
     * Final question state and grading authorship per attempt.
     *
     * Reduced to the first question so multi-question assessments stay one row.
     *
     * @param int[] $attemptids
     * @return \stdClass[] keyed by attempt id
     */
    protected static function fetch_attempt_states(array $attemptids): array {
        global $DB;

        $attemptids = array_values(array_filter($attemptids));
        if (!$attemptids) {
            return [];
        }

        $states = [];
        foreach (array_chunk($attemptids, 5000) as $chunk) {
            list($insql, $params) = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'a');
            $params['manual'] = 'mangr%';
            $params['gaveup'] = 'mangaveup';
            $like = $DB->sql_like('s3.state', ':manual', false);

            $rs = $DB->get_recordset_sql("
                    SELECT qa.id, fs.state AS finalstate,
                           ms.userid AS graderid, ms.timecreated AS gradedtime
                      FROM {quiz_attempts} qa
                      JOIN {question_attempts} qat ON qat.id = (
                               SELECT MIN(qat2.id)
                                 FROM {question_attempts} qat2
                                WHERE qat2.questionusageid = qa.uniqueid)
                      JOIN {question_attempt_steps} fs ON fs.id = (
                               SELECT MAX(s2.id)
                                 FROM {question_attempt_steps} s2
                                WHERE s2.questionattemptid = qat.id)
                 LEFT JOIN {question_attempt_steps} ms ON ms.id = (
                               SELECT MAX(s3.id)
                                 FROM {question_attempt_steps} s3
                                WHERE s3.questionattemptid = qat.id
                                  AND ({$like} OR s3.state = :gaveup))
                     WHERE qa.id {$insql}", $params);
            foreach ($rs as $row) {
                $states[(int) $row->id] = $row;
            }
            $rs->close();
        }

        return $states;
    }

    /**
     * The reported profile fields for the given users.
     *
     * @param int[] $userids
     * @return array userid => [shortname => value]
     */
    protected static function fetch_profiles(array $userids): array {
        global $DB;

        $userids = array_values(array_unique(array_filter($userids)));
        if (!$userids) {
            return [];
        }

        list($fsql, $fparams) = $DB->get_in_or_equal(self::PROFILE_FIELDS, SQL_PARAMS_NAMED, 'f');
        $fields = $DB->get_records_sql_menu("
                SELECT id, shortname FROM {user_info_field} WHERE shortname {$fsql}", $fparams);
        if (!$fields) {
            return [];
        }

        $profiles = [];
        foreach (array_chunk($userids, 5000) as $chunk) {
            list($usql, $params) = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'u');
            list($idsql, $idparams) = $DB->get_in_or_equal(array_keys($fields), SQL_PARAMS_NAMED, 'i');
            $params += $idparams;

            $rs = $DB->get_recordset_sql("
                    SELECT id, userid, fieldid, data
                      FROM {user_info_data}
                     WHERE userid {$usql} AND fieldid {$idsql}", $params);
            foreach ($rs as $row) {
                $profiles[(int) $row->userid][$fields[$row->fieldid]] = $row->data;
            }
            $rs->close();
        }

        return $profiles;
    }

    /**
     * Row counts per status, for the on-screen summary line.
     *
     * @param \stdClass[] $rows
     * @return \stdClass
     */
    protected static function counts(array $rows): \stdClass {
        $counts = (object) [
            'total' => count($rows), 'evaluated' => 0, 'awaiting' => 0,
            'blank' => 0, 'notattempted' => 0,
        ];
        foreach ($rows as $row) {
            $key = $row->status === self::STATUS_NOTATTEMPTED ? 'notattempted' : $row->status;
            $counts->{$key}++;
        }
        return $counts;
    }

    /**
     * Column headings for the export, in report order.
     *
     * @return string[]
     */
    public static function export_columns(): array {
        return [
            get_string('col_batch', 'local_reflectiveassessment'),
            get_string('col_program', 'local_reflectiveassessment'),
            get_string('col_semester', 'local_reflectiveassessment'),
            get_string('col_guide', 'local_reflectiveassessment'),
            get_string('col_guideemail', 'local_reflectiveassessment'),
            get_string('col_coursecode', 'local_reflectiveassessment'),
            get_string('col_studentname', 'local_reflectiveassessment'),
            get_string('col_studentemail', 'local_reflectiveassessment'),
            get_string('col_usn', 'local_reflectiveassessment'),
            get_string('col_enrolmentno', 'local_reflectiveassessment'),
            get_string('col_programreg', 'local_reflectiveassessment'),
            get_string('col_status', 'local_reflectiveassessment'),
            get_string('col_submittedon', 'local_reflectiveassessment'),
            get_string('col_mark', 'local_reflectiveassessment'),
            get_string('col_maxmark', 'local_reflectiveassessment'),
            get_string('col_evaluatedby', 'local_reflectiveassessment'),
            get_string('col_evaluatedon', 'local_reflectiveassessment'),
        ];
    }

    /**
     * Flatten one row for the export, in the same order as the headings.
     *
     * @param \stdClass $row
     * @return array
     */
    public static function export_row(\stdClass $row): array {
        $datefmt = get_string('strftimedate', 'langconfig');
        return [
            $row->batch,
            $row->program,
            $row->semester,
            $row->guide,
            $row->guideemail,
            $row->coursecode,
            $row->studentname,
            $row->email,
            $row->usn,
            $row->enrolmentno,
            $row->programreg,
            get_string('status_' . $row->status, 'local_reflectiveassessment'),
            $row->submittedon ? userdate($row->submittedon, $datefmt) : '',
            $row->mark === null ? '' : $row->mark,
            $row->maxmark,
            $row->evaluatedby,
            $row->evaluatedon ? userdate($row->evaluatedon, $datefmt) : '',
        ];
    }
}
