<?php

require_once '../../config.php';

require_login();

global $DB, $COURSE, $USER;

$action = required_param('action', PARAM_TEXT);

switch ($action) {
    case 'getcourses':
        $catid = optional_param('catid', 0, PARAM_INT);

        $SQL = "SELECT * FROM {course} "
                . " WHERE category = $catid";

        $records = $DB->get_records_sql($SQL);

        $option = '<option value="0">Select Course</option>';
        foreach ($records as $record) {
            $option .= '<option value="' . $record->id . '">' . $record->fullname . '</option>';
        }

        echo $option;

        break;

    case 'getquizzes':
        $courseid = optional_param('courseid', 0, PARAM_INT);

        $SQL = "SELECT * FROM {quiz} "
                . " WHERE course = $courseid";

        $records = $DB->get_records_sql($SQL);

        $option = '<option value="0">Select Quiz</option>';
        foreach ($records as $record) {
            $option .= '<option value="' . $record->id . '">' . $record->name . '</option>';
        }

        echo $option;
        break;

    case 'get_mods':
        $courseid = optional_param('courseid', 0, PARAM_INT);
        $mods = get_course_mods($courseid);
        $html = '';
        $count = 8;
        foreach ($mods as $mod) {
            $html .= '<th data-dt-column="' . $count . '" rowspan="1" colspan="1" class="dt-orderable-asc dt-orderable-desc" aria-label="Grade: Activate to sort" tabindex="0"><span class="dt-column-title" role="button">' . $mod->modname . '</span><span class="dt-column-order"></span></th>';
            $count++;
        }
        echo $html;
        break;

    case 'get_user_block_time_analytics':
        $userid = $USER->id;

        //calculate number of hours:minute spent by a user in course completion
        $sql = "SELECT cc.id, COUNT(cc.id) as cnt, SUM(cd.value) as totaltime FROM mdl_course_completions cc 
                JOIN mdl_customfield_data cd ON cd.instanceid = cc.course
                JOIN mdl_customfield_field cf ON cf.id = cd.fieldid
                WHERE cc.userid = :userid AND cc.timecompleted > 0 AND cf.shortname = 'course_duration'";

        $record = $DB->get_record_sql($sql, ['userid' => $userid]);
        if ($record) {
            $return['totaltime'] = ($record->totaltime > 0) ? convert_min_to_hour($record->totaltime) : '-';
            if ($record->cnt > 0) {
                $avgtime = round(($record->totaltime / $record->cnt), 2);
                $return['avgtime'] = convert_min_to_hour($avgtime);
            }
            $return['avgtime'] = '-';
        }

        //calculate number of hours:minute spent by a user in current FY

        if (date('m') > 3) {
            $startyear = date('Y');
            $endyear = $currentYear + 1;
            $startFY = strtotime("$startyear-04-01 00:00:00");
            $endFY = strtotime("$endyear-03-31 23:59:59");
        }
        if (date('m') <= 3) {
            $endyear = date('Y');
            $startyear = $endyear - 1;
            $startFY = strtotime("$startyear-04-01 00:00:00");
            $endFY = strtotime("$endyear-03-31 23:59:59");
        }

        $fysql = "SELECT cc.id, SUM(cd.value) as totaltime FROM mdl_course_completions cc 
                JOIN mdl_customfield_data cd ON cd.instanceid = cc.course
                JOIN mdl_customfield_field cf ON cf.id = cd.fieldid
                WHERE cc.userid = :userid AND cc.timecompleted > 0 AND cf.shortname = 'course_duration'
                AND cc.timecompleted > $startFY AND cc.timecompleted < $endFY";

        $fyrecord = $DB->get_record_sql($fysql, ['userid' => $userid]);
        if ($fyrecord) {
            $return['time_in_current_fy'] = ($fyrecord->totaltime > 0) ? convert_min_to_hour($fyrecord->totaltime) : '-';
        }

        //get user completed / not completed courses
        $courses = enrol_get_my_courses();
        $compsql = "SELECT COUNT(id) as completed FROM mdl_course_completions 
                    WHERE userid = :userid AND timecompleted > 0";
        $completed = $DB->get_record_sql($compsql, ['userid' => $userid]);
        $return['completed_course'] = ($completed->completed > 0) ? $completed->completed : '-';
        $notcompleted_course = count($courses) - $completed->completed;
        $return['enrolled_course'] = count($courses);
        $return['notcompleted_course'] = ($notcompleted_course > 0) ? $notcompleted_course : '-';

        echo json_encode($return);
        break;

    default:
        break;
}

function convert_min_to_hour($minutes) {
    intdiv($minutes, 60) . ' HR & ' . ($minutes % 60) . ' Min';
}
