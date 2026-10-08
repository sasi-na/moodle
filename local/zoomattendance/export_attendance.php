<?php

/**
 * Per-session attendance sheet export.
 *
 * Excel (.xlsx) listing every enrolled student for a course with, for the
 * given session: Attendance (Yes/No) and Source (Live / Summary / -).
 *
 * @package   local_zoomattendance
 */

require('../../config.php');

$courseid  = required_param('courseid', PARAM_INT);
$meetingid = required_param('meetingid', PARAM_TEXT);

require_login($courseid);

$context = context_course::instance($courseid, MUST_EXIST);

require_capability('moodle/course:update', $context);

global $DB;

raise_memory_limit(MEMORY_HUGE);

/*
|--------------------------------------------------------------------------
| Credit sources for this meeting
|--------------------------------------------------------------------------
*/

// Live: emails with the >=50% attended flag (whitespace-insensitive meeting).
$liveemails = [];
$lr = $DB->get_recordset_sql("
    SELECT DISTINCT user_email
      FROM {local_zoom_attendance_summary}
     WHERE REPLACE(meeting_id, ' ', '') = :mid
       AND attended = 1
", ['mid' => $meetingid]);
foreach ($lr as $x) {
    $liveemails[core_text::strtolower(trim($x->user_email))] = true;
}
$lr->close();

// Summary: user ids with an approved summary for this meeting.
$sumusers = [];
$sr = $DB->get_recordset_sql("
    SELECT DISTINCT userid
      FROM {local_zoom_recording_summary}
     WHERE courseid = :courseid
       AND REPLACE(zoommeetingid, ' ', '') = :mid
       AND status = 1
", ['courseid' => $courseid, 'mid' => $meetingid]);
foreach ($sr as $x) {
    $sumusers[(int)$x->userid] = true;
}
$sr->close();

/*
|--------------------------------------------------------------------------
| Enrolled students (users with the student archetype role in the course)
|--------------------------------------------------------------------------
*/

$students = [];
foreach (array_keys(get_archetype_roles('student')) as $roleid) {
    $rusers = get_role_users(
        $roleid,
        $context,
        false,
        'u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, ' .
            'u.middlename, u.alternatename, u.email',
        'u.lastname, u.firstname'
    );
    foreach ($rusers as $u) {
        $students[$u->id] = $u;
    }
}

$students = array_values($students);
usort($students, function($a, $b) {
    return strcasecmp(fullname($a), fullname($b));
});

/*
|--------------------------------------------------------------------------
| Stream as Excel
|--------------------------------------------------------------------------
*/

$columns = ['Name', 'Email', 'Attendance', 'Source'];

$filemeeting = clean_param(str_replace(' ', '', $meetingid), PARAM_ALPHANUMEXT);
$filename = "attendance_sheet_{$courseid}_{$filemeeting}_" . date('Ymd_His');

\core\dataformat::download_data(
    $filename,
    'excel',
    $columns,
    $students,
    function($u) use ($liveemails, $sumusers) {

        $email = core_text::strtolower(trim($u->email));

        if (isset($liveemails[$email])) {
            $attendance = 'Yes';
            $source = 'Live';
        } else if (isset($sumusers[(int)$u->id])) {
            $attendance = 'Yes';
            $source = 'Summary';
        } else {
            $attendance = 'No';
            $source = '-';
        }

        return [fullname($u), $u->email, $attendance, $source];
    }
);
