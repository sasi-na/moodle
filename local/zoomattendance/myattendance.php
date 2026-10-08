<?php

/**
 * Student attendance summary for a course (FRD user story).
 *
 * Shows, per live session, whether the student is present via the live
 * session (>=90% attended) or via an approved recorded-session summary,
 * plus an overall tally.
 *
 * @package   local_zoomattendance
 */

require('../../config.php');
require_once($CFG->dirroot . '/local/videotracker/lib.php');

$courseid = required_param('courseid', PARAM_INT);

require_login($courseid);

$context = context_course::instance($courseid, MUST_EXIST);

$PAGE->set_url('/local/zoomattendance/myattendance.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('myattendance', 'local_zoomattendance'));
$PAGE->set_heading(get_string('myattendance', 'local_zoomattendance'));

global $DB, $USER, $OUTPUT;

/*
|--------------------------------------------------------------------------
| Live sessions mapped to this course
|--------------------------------------------------------------------------
*/

$sessions = $DB->get_records_sql("
    SELECT zm.meeting_id, e.name AS sessionname, MIN(e.timestart) AS timestart
      FROM {local_zoom_event_map} zm
      JOIN {event} e ON e.id = zm.event_id
     WHERE e.courseid = :courseid AND zm.skipped = 0
  GROUP BY zm.meeting_id, e.name
  ORDER BY MIN(e.timestart) DESC
", ['courseid' => $courseid]);

/*
|--------------------------------------------------------------------------
| This student's recorded-session summaries for the course, keyed by meeting
|--------------------------------------------------------------------------
*/

$summaries = $DB->get_records('local_zoom_recording_summary',
    ['userid' => $USER->id, 'courseid' => $courseid],
    '',
    'id, zoommeetingid, status, feedback'
);

$summarybymeeting = [];
foreach ($summaries as $s) {
    if (!empty($s->zoommeetingid)) {
        $skey = core_text::strtolower(preg_replace('/\s+/', '', (string)$s->zoommeetingid));
        $summarybymeeting[$skey] = $s;
    }
}

/*
|--------------------------------------------------------------------------
| Recording pages: map normalised meeting id -> page cmid from the
| zoom_recording_<meetingid> tags, so missed sessions can offer "Watch
| recording".
|--------------------------------------------------------------------------
*/

$recordingmap = [];
$likepat = $DB->sql_like_escape('zoom_recording_') . '%';
$tagrs = $DB->get_recordset_sql("
    SELECT ti.itemid AS cmid, t.rawname
      FROM {tag_instance} ti
      JOIN {tag} t ON t.id = ti.tagid
      JOIN {course_modules} cm ON cm.id = ti.itemid
     WHERE ti.itemtype = 'course_modules'
       AND ti.component = 'core'
       AND cm.course = :courseid
       AND " . $DB->sql_like('t.rawname', ':pat', false), [
    'courseid' => $courseid,
    'pat' => $likepat
]);
foreach ($tagrs as $t) {
    if (preg_match('/^zoom_recording_(.+)$/i', trim($t->rawname), $m)) {
        $key = core_text::strtolower(preg_replace('/\s+/', '', $m[1]));
        if ($key !== '' && !isset($recordingmap[$key])) {
            $recordingmap[$key] = (int)$t->cmid;
        }
    }
}
$tagrs->close();

echo $OUTPUT->header();

if (empty($sessions)) {

    echo html_writer::div(
        get_string('nosessions', 'local_zoomattendance'),
        'alert alert-info'
    );

    echo $OUTPUT->footer();
    exit;
}

/*
|--------------------------------------------------------------------------
| Build rows + tally
|--------------------------------------------------------------------------
*/

$presentcount = 0;
$rows = [];

foreach ($sessions as $session) {

    $meetingid = $session->meeting_id;
    $key = core_text::strtolower(preg_replace('/\s+/', '', (string)$meetingid));

    // $canwatch: offer the recording only for Rejected / Absent (not Pending,
    // not already credited).
    $canwatch = false;

    // 1) Present via live session (>=50%).
    if (local_videotracker_attended_live($USER->email, $meetingid)) {

        $present = true;
        $label = get_string('presentlive', 'local_zoomattendance');
        $cls = 'lzs-status--present';

    } else if (isset($summarybymeeting[$key])) {

        // 2) Decided via recorded-session summary.
        $status = (int)$summarybymeeting[$key]->status;

        if ($status === 1) {
            $present = true;
            $label = get_string('presentrecording', 'local_zoomattendance');
            $cls = 'lzs-status--present';
        } else if ($status === 0) {
            $present = false;
            $label = get_string('statuspending', 'local_zoomattendance');
            $cls = 'lzs-status--pending';
        } else {
            $present = false;
            $label = get_string('statusrejected', 'local_zoomattendance');
            $cls = 'lzs-status--rejected';
            $canwatch = true;
        }

    } else {

        // 3) No live attendance and no summary.
        $present = false;
        $label = get_string('absent', 'local_zoomattendance');
        $cls = 'lzs-status--absent';
        $canwatch = true;
    }

    if ($present) {
        $presentcount++;
    }

    // Recording link for Rejected / Absent sessions (when a tagged page exists).
    $recordingurl = '';
    if ($canwatch && isset($recordingmap[$key])) {
        $recordingurl = new moodle_url('/mod/page/view.php', [
            'id'        => $recordingmap[$key],
            'forceview' => 1
        ]);
    }

    $rows[] = [
        'name' => $session->sessionname,
        'date' => !empty($session->timestart) ? userdate($session->timestart) : '-',
        'label' => $label,
        'cls' => $cls,
        'recordingurl' => $recordingurl
    ];
}

$total = count($sessions);
$pct = $total > 0 ? round(($presentcount / $total) * 100) : 0;

/*
|--------------------------------------------------------------------------
| Overall summary
|--------------------------------------------------------------------------
*/

echo html_writer::start_div('lzs-wrap');

echo html_writer::div(
    get_string('attendanceoverall', 'local_zoomattendance', (object)[
        'present' => $presentcount,
        'total' => $total,
        'percent' => $pct
    ]),
    'lzs-tally'
);

/*
|--------------------------------------------------------------------------
| Per-session table
|--------------------------------------------------------------------------
*/

echo '<table class="lzs-table">';
echo '<thead><tr>';
echo '<th>' . get_string('sessionname', 'local_zoomattendance') . '</th>';
echo '<th>' . get_string('date', 'local_zoomattendance') . '</th>';
echo '<th>' . get_string('status', 'local_zoomattendance') . '</th>';
echo '<th>' . get_string('action', 'local_zoomattendance') . '</th>';
echo '</tr></thead><tbody>';

foreach ($rows as $row) {

    $action = '';
    if (!empty($row['recordingurl'])) {
        $action = '<a href="' . $row['recordingurl'] . '" class="btn btn-sm btn-primary">' .
            '<i class="fa fa-play" aria-hidden="true"></i> ' .
            get_string('watchrecording', 'local_zoomattendance') . '</a>';
    }

    echo '<tr>';
    echo '<td>' . format_string($row['name']) . '</td>';
    echo '<td>' . $row['date'] . '</td>';
    echo '<td><span class="lzs-status ' . $row['cls'] . '">' . $row['label'] . '</span></td>';
    echo '<td>' . $action . '</td>';
    echo '</tr>';
}

echo '</tbody></table>';

echo html_writer::end_div();

echo $OUTPUT->footer();
