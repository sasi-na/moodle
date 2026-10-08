<?php
require('../../config.php');

$meetingid = required_param('meetingid', PARAM_TEXT);
$courseid  = required_param('courseid', PARAM_INT);

require_login($courseid);
/** @var \context $context */
$context = context_course::instance($courseid, MUST_EXIST);

// Only teacher/admin
require_capability('moodle/course:update', $context);

global $DB;

/**
 * ===========================
 * HEADERS
 * ===========================
 */
header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=attendance_{$meetingid}.csv");
header("Pragma: no-cache");
header("Expires: 0");

$output = fopen('php://output', 'w');

// UTF-8 BOM (Excel fix)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

/**
 * ===========================
 * HEADER ROW
 * ===========================
 */
fputcsv($output, [
    'Meeting ID',
    'Topic',
    'Email',
    'Name',
    'Session Start',
    'Session End',
    'Session Duration (mins)',
    'User Duration (mins)',
    'Attendance %',
    'Status'
]);

// Present requires at least this percentage of the session.
$presentthreshold = 50;

/**
 * ===========================
 * FETCH DATA
 * ===========================
 */
$sql = "
    SELECT *
    FROM {local_zoom_attendance_summary}
    WHERE meeting_id = ?
    ORDER BY total_duration DESC
";

$records = $DB->get_recordset_sql($sql, [$meetingid]);

$totalusers = 0;
$totalduration = 0;
$totalsession = 0;

/**
 * ===========================
 * WRITE DATA
 * ===========================
 */
foreach ($records as $r) {

    $totalusers++;

    $start = !empty($r->start_time)
        ? userdate($r->start_time, '%I:%M:%S %p')
        : '';

    $end = !empty($r->end_time)
        ? userdate($r->end_time, '%I:%M:%S %p')
        : '';

    $sessionmins = !empty($r->session_duration)
        ? ($r->session_duration / 60)
        : 0;

    $usermins = !empty($r->total_duration)
        ? ($r->total_duration / 60)
        : 0;

    $totalsession = $sessionmins; // same for all users
    $totalduration += $usermins;

    /**
     * ATTENDANCE %
     */
    $attendance = ($sessionmins > 0)
        ? round(($usermins / $sessionmins) * 100, 2)
        : 0;

    // Status from the persisted attendance decision (>=50% of the session),
    // consistent with the block / view-more / My Attendance. Falls back to an
    // inline computation if the stored flag is unavailable.
    if (isset($r->attended)) {
        $status = ((int)$r->attended === 1) ? 'Present' : 'Absent';
    } else if ($sessionmins > 0) {
        $status = ($attendance >= $presentthreshold) ? 'Present' : 'Absent';
    } else {
        $status = '-';
    }

    fputcsv($output, [
        $r->meeting_id,
        $r->topic ?? '',
        $r->user_email ?? '',
        $r->fullname ?? '',
        $start,
        $end,
        round($sessionmins, 2),
        round($usermins, 2),
        $attendance . '%',
        $status
    ]);
}

$records->close();

/**
 * ===========================
 * SUMMARY ROWS
 * ===========================
 */
if ($totalusers > 0) {

    $avgduration = $totalduration / $totalusers;

    fputcsv($output, []); // empty row

    fputcsv($output, [
        'TOTAL USERS',
        $totalusers,
        '',
        '',
        '',
        '',
        round($totalsession, 2),
        round($totalduration, 2),
        '',
        ''
    ]);

    fputcsv($output, [
        'AVERAGE DURATION (mins)',
        '',
        '',
        '',
        '',
        '',
        '',
        round($avgduration, 2),
        '',
        ''
    ]);
}

fclose($output);
exit;