<?php

require_once '../../config.php';
require_once $CFG->dirroot . '/lib/gradelib.php';
require_once $CFG->dirroot . '/grade/querylib.php';
require_login();

define('AJAX_SCRIPT', true);

global $DB, $COURSE, $CFG;

$type = required_param('type', PARAM_TEXT);
$catid = optional_param('catid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$contextid = optional_param('contextid', 0, PARAM_INT);
$email = optional_param('email', '', PARAM_TEXT);
$fromdate = optional_param('fromdate', '', PARAM_TEXT);
$todate = optional_param('todate', '', PARAM_TEXT);
$action = optional_param('action', '', PARAM_TEXT);

$start = optional_param('start', 0, PARAM_INT);
$length = optional_param('length', 0, PARAM_INT);

switch ($type) {

    case 'course_progress':
        $columns = "c.*, cc.name ";
        $from = " {course} c JOIN {course_categories} cc ON cc.id = c.category ";
        $where = " c.category = :catid";
        if ($courseid > 0) {
            $where .= " AND c.id = :courseid ";
        }

        $sql = "SELECT $columns FROM $from WHERE $where";

       $countsql = $records = $DB->get_records_sql($sql, ['catid' => $catid, 'courseid' => $courseid]);

        $data = false;
        $count = 0;
        foreach ($records as $course) {

            $context = context_course::instance($course->id);

// Get the list of enrolled users
            $users = get_enrolled_users($context, 'mod/assign:submit');
            foreach ($users as $user) {
                $profile_fields = profile_user_record($user->id);

                $groupsql = "SELECT c.id, GROUP_CONCAT(c.name) as batches FROM {cohort} c
                            JOIN {cohort_members} m ON m.cohortid = c.id
                            WHERE m.userid = :userid";
                $usergroup = $DB->get_record_sql($groupsql, ['userid' => $user->id]);
                $progress = \core_completion\progress::get_course_progress_percentage($course, $user->id);
                $data[] = [
                    'id' => $user->id,
                    'enrollment' => $profile_fields->provisional_enrollment_number,
                    'email' => $user->email,
                    'fullname' => $user->firstname . ' ' . $user->lastname,
                    'university' => $profile_fields->usn,
                    'program' => $profile_fields->program_registered_for,
                    'archived' => '',
                    'specialization' => '',
                    'studentid' => $profile_fields->debStudentID,
                    'usergroups' => ($usergroup) ? $usergroup->batches : '',
                    'shortname' => $course->shortname,
                    'coursename' => $course->fullname,
                    'progress' => $progress,
                    'starttime' => ($course->startdate > 0) ? date('d-m-Y H:i', $course->startdate) : '',
                    'endtime' => ($course->enddate > 0) ? date('d-m-Y H:i', $course->enddate) : '',
                ];
            }

            $count++;
        }
        break;
    case 'course_progress_old':
        if ($catid > 0) {
            if ($email != '') {
                $where = " AND u.email = :email";
            }
            $sql = "SELECT u.*
        FROM {user} u
        JOIN {role_assignments} ra ON ra.userid = u.id
        WHERE ra.roleid = :roleid $where GROUP BY u.id";

            $params = array('roleid' => 5, 'email' => $email);
// Execute the query
            $students = $DB->get_records_sql($sql, $params);

            foreach ($students as $student) {
                $profile_fields = profile_user_record($student->id);

                $courses = enrol_get_all_users_courses($student->id);
                foreach ($courses as $course) {

                    $progress = \core_completion\progress::get_course_progress_percentage($course, $student->id);
                    $data[] = [
                        'id' => $user->id,
                        'enrollment' => $profile_fields->provisional_enrollment_number,
                        'email' => $student->email,
                        'fullname' => $student->firstname . ' ' . $student->lastname,
                        'university' => $profile_fields->usn,
                        'program' => $profile_fields->program_registered_for,
                        'archived' => '',
                        'specialization' => '',
                        'studentid' => $profile_fields->debStudentID,
                        'shortname' => $course->shortname,
                        'coursename' => $course->fullname,
                        'progress' => $progress,
                        'starttime' => ($course->startdate > 0) ? date('d-m-Y H:i', $course->startdate) : '',
                    ];
                }
            }
        }
        break;

    case 'time_spent_report_old':
        $count = 1;
        if ($courseid > 0) {
            $context = context_course::instance($courseid);
            $course = $DB->get_record('course', ['id' => $courseid]);
// Get the list of enrolled users
            $users = get_enrolled_users($context, '', 0, 'u.*', null, $start, $length, true);

            foreach ($users as $user) {
                $groupsql = "SELECT c.id, GROUP_CONCAT(c.name) as batches FROM {cohort} c
                            JOIN {cohort_members} m ON m.cohortid = c.id
                            WHERE m.userid = :userid";
                $usergroup = $DB->get_record_sql($groupsql, ['userid' => $user->id]);
                $userid = $user->id;
                $mods = get_course_mods($courseid);
                foreach ($mods as $mod) {

                    $totaltime = 0;
//echo $count.'-';
                    $count++;
//continue;
                    $activity = $DB->get_record($mod->modname, ['id' => $mod->instance]);

//calculate the time spent
                    if ($mod->modname == 'quiz') {
                        $quizsql = "SELECT id, SUM(timefinish - timestart) as totaltime FROM {quiz_attempts} 
                                    WHERE quiz = :quizid AND userid = :userid LIMIT 1";
                        $record = $DB->get_record_sql($quizsql, ['quizid' => $mod->instance, 'userid' => $userid]);
                        $totaltime = ($record->totaltime) ? $record->totaltime : 0;
                    }
                    /* if ($mod->modname == 'scorm') {
                      $scormsql = "SELECT SUM(TIME_TO_SEC(value)) as totaltime FROM {scorm_scoes_track}
                      WHERE element = 'cmi.core.total_time' AND scormid = :scormid AND userid = :userid LIMIT 1";
                      $record = $DB->get_record_sql($scormsql, ['scormid' => $mod->instance, 'userid' => $userid]);
                      $totaltime = ($record->totaltime) ? $record->totaltime : 0;
                      } */

                    if ($mod->modname == 'h5pactivity') {
                        $h5psql = "SELECT SUM(duration) as totaltime FROM {h5pactivity_attempts} 
                                    WHERE h5pactivityid = :h5pactivityid AND userid = :userid LIMIT 1";
                        $record = $DB->get_record_sql($h5psql, ['h5pactivityid' => $mod->instance, 'userid' => $userid]);
                        $totaltime = ($record->totaltime) ? $record->totaltime : 0;
                    }

                    if ($mod->modname == 'resource' || $mod->modname == 'page' || $mod->modname == 'feedback' || $mod->modname == 'pdfannotator' || $mod->modname == 'scorm' || $mod->modname == 'book') {
                        $filesql = "SELECT SUM(timespent) as totaltime FROM {local_timetrack} 
                                    WHERE cmid = :cmid AND userid = :userid LIMIT 1";
                        $record = $DB->get_record_sql($filesql, ['cmid' => $mod->id, 'userid' => $userid]);
                        $totaltime = ($record->totaltime) ? $record->totaltime : 0;
                    }

                    $data[] = [
                        'count' => $count,
                        'id' => $user->id,
                        'shortname' => $course->shortname,
                        'coursename' => $course->fullname,
                        'email' => $user->email,
                        'fullname' => $user->firstname . ' ' . $user->lastname,
                        'usergroups' => ($usergroup) ? $usergroup->batches : '',
                        'module' => $activity->name,
                        'timespent' => format_seconds($totaltime),
                    ];
                }
            }
        }
        break;

    case 'time_spent_report':
        ini_set('memory_limit', '1G');
        if ($fromdate != '') {
            $fromdate = strtotime($fromdate);
        }

        if ($todate != '') {
            $todate = strtotime($todate);
        }

        $count = 1;
        if ($courseid > 0) {
            $data = false;
            if ($action == 'csvdownload') {
                $limit = "";
            } else {
                $limit = " LIMIT $start, $length ";
            }

            $course = $DB->get_record('course', ['id' => $courseid]);
            $timecondition = '';
            if ($fromdate > 0 && $todate > 0) {
                $timecondition = " AND (t.timecreated >= $fromdate AND t.timecreated <= $todate ) ";
            }

            $sql = "SELECT ANY_VALUE(t.id) as id, t.userid, SUM(t.timespent) as totaltime, ANY_VALUE(u.id) as user_id, 
                    ANY_VALUE(u.firstname) AS firstname, ANY_VALUE(u.lastname) as lastname, ANY_VALUE(u.email) as email,
                    ANY_VALUE(cm.instance) as instance, ANY_VALUE(m.name) as modname
                    FROM {local_timetrack} t
                    JOIN {user} u ON u.id = t.userid
                    JOIN {course_modules} cm ON cm.id = t.cmid
                    JOIN {modules} m ON m.id = cm.module
                   WHERE t.courseid = :courseid $timecondition GROUP BY t.cmid, t.userid ORDER BY t.userid";

            $countsql = $DB->get_records_sql($sql, ['courseid' => $courseid]);
            $records = $DB->get_records_sql($sql . $limit, ['courseid' => $courseid]);

            foreach ($records as $user) {

                $profile_fields = profile_user_record($user->user_id);

                $groupsql = "SELECT c.id, GROUP_CONCAT(c.name) as batches FROM {cohort} c
                            JOIN {cohort_members} m ON m.cohortid = c.id
                            WHERE m.userid = :userid";
                $usergroup = $DB->get_record_sql($groupsql, ['userid' => $user->user_id]);
                $userid = $user->user_id;

                $activity = $DB->get_record($user->modname, ['id' => $user->instance]);

                $data[] = [
                    'count' => $count,
                    'id' => $user->id,
                    'shortname' => $course->shortname,
                    'coursename' => $course->fullname,
                    'email' => $user->email,
                    'usn' => $profile_fields->usn,
                    'enrollment' => $profile_fields->provisional_enrollment_number,
                    'fullname' => $user->firstname . ' ' . $user->lastname,
                    'usergroups' => ($usergroup) ? $usergroup->batches : '',
                    'module' => $activity->name,
                    'timespent' => format_seconds($user->totaltime),
                ];
                $count++;
            }
        }
        break;

    case 'course_time_spent':
        ini_set('memory_limit', '1G');
        if ($fromdate != '') {
            $fromdate = strtotime($fromdate);
        }

        if ($todate != '') {
            $todate = strtotime($todate);
        }

        $count = 1;
        $condition = '';
        if ($catid > 0) {
            $condition .= " AND c.category = $catid";
        }
        $data = false;
        if ($action == 'csvdownload') {
            $limit = "";
        } else {
            $limit = " LIMIT $start, $length ";
        }

        if ($courseid > 0) {
            $condition .= " AND c.id = $courseid";
        }


        $timecondition = '';
        if ($fromdate > 0 && $todate > 0) {
            $timecondition = " AND (t.timecreated >= $fromdate AND t.timecreated <= $todate ) ";
        }

        $sql = "SELECT t.id, c.shortname, c.fullname, SUM(t.timespent) AS totaltime,
                    u.id as user_id, u.firstname, u.lastname, u.email
                    FROM {local_timetrack} t
                    JOIN {course} c ON c.id = t.courseid
                    JOIN {user} u ON u.id = t.userid
                    WHERE 1=1 $condition $timecondition
                    GROUP BY t.userid, t.courseid";

        $countsql = $DB->get_records_sql($sql, ['courseid' => $courseid]);
        $records = $DB->get_records_sql($sql . $limit, ['courseid' => $courseid]);

        foreach ($records as $record) {
            $profile_fields = profile_user_record($record->user_id);

            $groupsql = "SELECT c.id, GROUP_CONCAT(c.name) as batches FROM {cohort} c
                            JOIN {cohort_members} m ON m.cohortid = c.id
                            WHERE m.userid = :userid";
            $usergroup = $DB->get_record_sql($groupsql, ['userid' => $record->user_id]);
            $userid = $user->user_id;

            $data[] = [
                'id' => $count,
                'shortname' => $record->shortname,
                'coursename' => $record->fullname,
                'fullname' => $record->firstname . ' ' . $record->lastname,
                'email' => $record->email,
                'usn' => $profile_fields->usn,
                'enrollment' => $profile_fields->provisional_enrollment_number,
                'usergroups' => ($usergroup) ? $usergroup->batches : '',
                'timespent' => format_seconds($record->totaltime)
            ];
            $count++;
        }

        break;

    default:
        break;
}



if ($action == 'csvdownload') {

    header('Content-Type: text/csv');
    header("Content-Disposition: attachment;filename=time_spent_report.csv");
    $output = fopen('php://output', 'w');
    if ($type == 'time_spent_report') {
        fputcsv($output, [
            'Sl No',
            'Course Name',
            'Course Code',
            'Email',
            'USN',
            'Enrollment Number',
            'Fullname',
            'Student Batch',
            'Module Title',
            'Time Spent',
        ]);

        foreach ($data as $row) {
            $array = [
                $row["count"],
                $row["coursename"],
                $row["shortname"],
                $row["email"],
                $row["usn"],
                $row["enrollment"],
                $row["fullname"],
                $row["usergroups"],
                $row["module"],
                $row["timespent"]
            ];
            fputcsv($output, $array);
        }
    }

    if ($type == 'course_time_spent') {
        fputcsv($output, [
            'Sl No',
            'Course Name',
            'Course Code',
            'Email',
            'USN',
            'Enrollment Number',
            'Fullname',
            'Student Batch',
            'Time Spent',
        ]);

        foreach ($data as $row) {
            $array = [
                $row["id"],
                $row["coursename"],
                $row["shortname"],
                $row["email"],
                $row["usn"],
                $row["enrollment"],
                $row["fullname"],
                $row["usergroups"],
                $row["timespent"]
            ];
            fputcsv($output, $array);
        }
    }
    fclose($output);
} else {

    if ($data) {
        echo json_encode([
            "draw" => $_GET['draw'],
            "recordsTotal" => count($countsql),
            "recordsFiltered" => count($countsql),
            "data" => $data
        ]);
    } else {
        echo json_encode([
            "draw" => $_GET['draw'],
            "recordsTotal" => 0,
            "recordsFiltered" => 0,
            "data" => []
        ]);
    }
}

function get_key_value($twodarray, $key, $value) {
    $index = array_search($value, array_column($twodarray, $key));

    if ($index !== false) {
        return date('d-m-Y H:i', $twodarray[$index + 1]['value']);
    }
    return '';
}

function format_seconds($seconds) {
    // Calculate hours, minutes, and seconds
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $seconds = $seconds % 60;

    // Format and return the result
    return sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
}
