<?php
require('../../config.php');

require_login();
require_capability('moodle/site:viewreports', context_system::instance());

global $DB;

// Prevent memory + timeout issues for large exports
raise_memory_limit(MEMORY_HUGE);
set_time_limit(0);

// CSV headers
header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=zoom_attendance_" . date('Ymd_His') . ".csv");
header("Pragma: no-cache");
header("Expires: 0");

$output = fopen('php://output', 'w');

// Fix Excel UTF-8 issue
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV header row
fputcsv($output, [
    'Meeting ID',
    'Topic',
    'Email',
    'Name',
    'Session Start',
    'Session End',
    'Session Duration (mins)',
    'User Duration (mins)'
]);

// Efficient streaming query (NO memory load)
$sql = "SELECT *
        FROM {local_zoom_attendance_summary}
        ORDER BY timecreated DESC";

$records = $DB->get_recordset_sql($sql);

foreach ($records as $r) {

    // Safe null handling
    $start = !empty($r->start_time)
        ? date('Y-m-d H:i:s', $r->start_time)
        : '';

    $end = !empty($r->end_time)
        ? date('Y-m-d H:i:s', $r->end_time)
        : '';

    $sessionmins = !empty($r->session_duration)
        ? round($r->session_duration / 60, 2)
        : 0;

    $usermins = !empty($r->total_duration)
        ? round($r->total_duration / 60, 2)
        : 0;

    fputcsv($output, [
        clean_param($r->meeting_id ?? '', PARAM_TEXT),
        clean_param($r->topic ?? '', PARAM_TEXT),
        clean_param($r->user_email ?? '', PARAM_TEXT),
        clean_param($r->fullname ?? '', PARAM_TEXT),
        $start,
        $end,
        $sessionmins,
        $usermins
    ]);
}

// Clean up DB cursor
$records->close();
fclose($output);
exit;