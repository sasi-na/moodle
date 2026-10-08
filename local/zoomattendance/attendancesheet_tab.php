<?php

/**
 * Attendance sheet tab.
 *
 * Lists every conducted session (title + date) with a button to download a
 * per-session attendance sheet of all enrolled students.
 *
 * Included from view.php. Expects $DB, $OUTPUT, $courseid, $page, $perpage,
 * $offset in scope.
 *
 * @package   local_zoomattendance
 */

defined('MOODLE_INTERNAL') || die();

global $DB, $OUTPUT;

$now = time();

echo '<div class="lzs-head">'
    . '<div class="lzs-icon"><i class="fa fa-table-list" aria-hidden="true"></i></div>'
    . '<div><h3>' . get_string('conductedsessions', 'local_zoomattendance') . '</h3>'
    . '<div class="lzs-sub">Per-session attendance sheet for all enrolled students</div></div>'
    . '</div>';

$count = $DB->count_records_sql("
    SELECT COUNT(DISTINCT zm.meeting_id)
      FROM {local_zoom_event_map} zm
      JOIN {event} e ON e.id = zm.event_id
     WHERE e.courseid = ? AND e.timestart <= ? AND zm.skipped = 0
", [$courseid, $now]);

if ($count == 0) {

    echo '<div class="lzs-empty"><i class="fa fa-calendar" aria-hidden="true"></i>' .
        get_string('nosessions', 'local_zoomattendance') . '</div>';

} else {

    $sessions = $DB->get_records_sql("
        SELECT zm.meeting_id, MAX(e.name) AS name, MIN(e.timestart) AS timestart
          FROM {local_zoom_event_map} zm
          JOIN {event} e ON e.id = zm.event_id
         WHERE e.courseid = ? AND e.timestart <= ? AND zm.skipped = 0
      GROUP BY zm.meeting_id
      ORDER BY MIN(e.timestart) DESC
    ", [$courseid, $now], $offset, $perpage);

    echo '<table class="lzs-table">';
    echo '<thead><tr>';
    echo '<th>' . get_string('sessionname', 'local_zoomattendance') . '</th>';
    echo '<th>' . get_string('date', 'local_zoomattendance') . '</th>';
    echo '<th>' . get_string('action', 'local_zoomattendance') . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($sessions as $s) {

        $date = !empty($s->timestart) ? userdate($s->timestart) : '-';

        $dlurl = new moodle_url('/local/zoomattendance/export_attendance.php', [
            'courseid'  => $courseid,
            'meetingid' => $s->meeting_id
        ]);

        echo '<tr>';
        echo '<td><strong>' . format_string($s->name) . '</strong></td>';
        echo '<td>' . $date . '</td>';
        echo '<td><a href="' . $dlurl . '" class="btn btn-sm btn-primary">' .
            '<i class="fa fa-file-excel" aria-hidden="true"></i> ' .
            get_string('downloadattendance', 'local_zoomattendance') . '</a></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

    $baseurl = new moodle_url('/local/zoomattendance/view.php', [
        'courseid' => $courseid,
        'tab'      => 'sheet'
    ]);

    echo $OUTPUT->paging_bar($count, $page, $perpage, $baseurl);
}
