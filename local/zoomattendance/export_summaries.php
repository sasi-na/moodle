<?php

/**
 * Summary Report export.
 *
 * Excel (.xlsx) of recorded-session summaries for a single session
 * (meeting id), for faculty review/record-keeping.
 *
 * @package   local_zoomattendance
 */

require('../../config.php');

$courseid  = required_param('courseid', PARAM_INT);
$meetingid = required_param('meetingid', PARAM_TEXT);

require_login($courseid);

$context = context_course::instance($courseid, MUST_EXIST);

require_capability('local/zoomattendance:reviewsummaries', $context);

global $DB;

// Resolve the session name (topic) from the attendance data, matching the
// meeting id ignoring whitespace (consistent with the review tab).
$sessionname = $DB->get_field_sql("
    SELECT MAX(topic)
      FROM {local_zoom_attendance_summary}
     WHERE REPLACE(meeting_id, ' ', '') = ?
", [$meetingid]);

if (empty($sessionname)) {
    $sessionname = $meetingid;
}

$statuslabels = [
    0 => 'Pending',
    1 => 'Approved',
    2 => 'Rejected'
];

$userfields = \core_user\fields::for_name()->get_sql('u')->selects;

$records = $DB->get_recordset_sql("
    SELECT rs.id, rs.wordcount, rs.status, rs.summary, rs.feedback,
           rs.submittedat, rs.reviewedat, rs.attempts,
           u.email $userfields
      FROM {local_zoom_recording_summary} rs
      JOIN {user} u ON u.id = rs.userid
     WHERE rs.courseid = :courseid
       AND REPLACE(rs.zoommeetingid, ' ', '') = :meetingid
  ORDER BY rs.status ASC, rs.submittedat DESC
", ['courseid' => $courseid, 'meetingid' => $meetingid]);

$columns = [
    'Student',
    'Email',
    'Session',
    'Word Count',
    'Summary',
    'Submitted',
    'Status',
    'Attempts',
    'Reviewed At',
    'Feedback'
];

$filemeeting = clean_param(str_replace(' ', '', $meetingid), PARAM_ALPHANUMEXT);
$filename = "summary_report_{$courseid}_{$filemeeting}_" . date('Ymd_His');

\core\dataformat::download_data(
    $filename,
    'excel',
    $columns,
    $records,
    function($record) use ($sessionname, $statuslabels) {
        return [
            fullname($record),
            $record->email ?? '',
            $sessionname,
            (int)$record->wordcount,
            (string)$record->summary,
            userdate($record->submittedat),
            $statuslabels[$record->status] ?? (string)$record->status,
            (int)$record->attempts,
            !empty($record->reviewedat) ? userdate($record->reviewedat) : '',
            (string)$record->feedback
        ];
    }
);

$records->close();
