<?php

/**
 * Recording-session summaries review tab.
 *
 * Two views, both included from view.php:
 *  - Session overview (default): one row per session with submission counts
 *    (submitted / approved / rejected / pending) and a button to review it.
 *  - Drill-down ($meetingid set): every submission for one session, with
 *    student info, submission date and approve/reject actions.
 *
 * Expects $DB, $OUTPUT, $courseid, $page, $perpage, $offset, $meetingid
 * in scope.
 *
 * @package   local_zoomattendance
 */

defined('MOODLE_INTERNAL') || die();

global $DB, $OUTPUT;

if ($meetingid !== '') {

    /*
    |--------------------------------------------------------------------------
    | Drill-down: submissions for a single session
    |--------------------------------------------------------------------------
    */

    // Session name (topic) and conducted date (start_time) come from the
    // attendance data, keyed by meeting id. Match ignores whitespace so a
    // tag entered as "830 1234 5678" still resolves to "83012345678".
    $sessioninfo = $DB->get_record_sql("
        SELECT MAX(topic) AS sessionname, MAX(start_time) AS timestart
          FROM {local_zoom_attendance_summary}
         WHERE REPLACE(meeting_id, ' ', '') = :meetingid
    ", ['meetingid' => $meetingid]);

    $sessionname = (!empty($sessioninfo) && !empty($sessioninfo->sessionname))
        ? format_string($sessioninfo->sessionname)
        : get_string('unknownsession', 'local_zoomattendance');

    $backurl = new moodle_url('/local/zoomattendance/view.php', [
        'courseid' => $courseid,
        'tab'      => 'summaries'
    ]);

    echo html_writer::link(
        $backurl,
        '<i class="fa fa-arrow-left" aria-hidden="true"></i> ' .
            get_string('backtosessions', 'local_zoomattendance'),
        ['class' => 'lzs-back']
    );

    $conductedsub = (!empty($sessioninfo) && !empty($sessioninfo->timestart))
        ? userdate($sessioninfo->timestart)
        : '';

    echo '<div class="lzs-head">'
        . '<div class="lzs-icon"><i class="fa fa-file-lines" aria-hidden="true"></i></div>'
        . '<div><h3>' . $sessionname . '</h3>'
        . ($conductedsub !== '' ? '<div class="lzs-sub">' . $conductedsub . '</div>' : '')
        . '</div></div>';

    // Per-status counts for this session.
    $counts = $DB->get_records_sql("
        SELECT status, COUNT(*) AS c
          FROM {local_zoom_recording_summary}
         WHERE courseid = ?
           AND REPLACE(zoommeetingid, ' ', '') = ?
      GROUP BY status
    ", [$courseid, $meetingid]);

    $cpending  = isset($counts[0]) ? (int)$counts[0]->c : 0;
    $capproved = isset($counts[1]) ? (int)$counts[1]->c : 0;
    $crejected = isset($counts[2]) ? (int)$counts[2]->c : 0;
    $submissioncount = $cpending + $capproved + $crejected;

    echo '<div class="lzs-stats">'
        . '<div class="lzs-stat lzs-stat--total"><i class="fa fa-layer-group" aria-hidden="true"></i>'
        . '<div class="n">' . $submissioncount . '</div><div class="l">' .
            get_string('colsubmitted', 'local_zoomattendance') . '</div></div>'
        . '<div class="lzs-stat lzs-stat--approved"><i class="fa fa-circle-check" aria-hidden="true"></i>'
        . '<div class="n">' . $capproved . '</div><div class="l">' .
            get_string('colapproved', 'local_zoomattendance') . '</div></div>'
        . '<div class="lzs-stat lzs-stat--rejected"><i class="fa fa-circle-xmark" aria-hidden="true"></i>'
        . '<div class="n">' . $crejected . '</div><div class="l">' .
            get_string('colrejected', 'local_zoomattendance') . '</div></div>'
        . '<div class="lzs-stat lzs-stat--pending"><i class="fa fa-clock" aria-hidden="true"></i>'
        . '<div class="n">' . $cpending . '</div><div class="l">' .
            get_string('colpending', 'local_zoomattendance') . '</div></div>'
        . '</div>';

    // Summary Report export for this session.
    $exporturl = new moodle_url('/local/zoomattendance/export_summaries.php', [
        'courseid'  => $courseid,
        'meetingid' => $meetingid
    ]);

    echo html_writer::div(
        html_writer::link(
            $exporturl,
            '<i class="fa fa-file-excel" aria-hidden="true"></i> ' .
                get_string('downloadexcel', 'local_zoomattendance'),
            ['class' => 'btn btn-sm btn-outline-primary']
        ),
        '',
        ['style' => 'margin:4px 0 16px;']
    );

    $userfields = \core_user\fields::for_name()->get_sql('u')->selects;

    $rows = $DB->get_records_sql("
        SELECT rs.id, rs.userid, rs.summary, rs.wordcount, rs.status,
               rs.feedback, rs.submittedat,
               u.email $userfields
          FROM {local_zoom_recording_summary} rs
          JOIN {user} u ON u.id = rs.userid
         WHERE rs.courseid = :courseid
           AND REPLACE(rs.zoommeetingid, ' ', '') = :meetingid
      ORDER BY rs.status ASC, rs.submittedat DESC
    ", ['courseid' => $courseid, 'meetingid' => $meetingid], $offset, $perpage);

    // status -> [label, lzs-status class, icon].
    $lzsstatus = [
        0 => ['statuspending', 'lzs-status--pending', 'fa-clock'],
        1 => ['statusapproved', 'lzs-status--approved', 'fa-circle-check'],
        2 => ['statusrejected', 'lzs-status--rejected', 'fa-circle-xmark'],
    ];

    if (empty($rows)) {

        echo '<div class="lzs-empty"><i class="fa fa-inbox" aria-hidden="true"></i>' .
            get_string('nosummaries', 'local_zoomattendance') . '</div>';

    } else {

        echo '<table class="lzs-table">';
        echo '<thead><tr>';
        echo '<th>' . get_string('student', 'local_zoomattendance') . '</th>';
        echo '<th>' . get_string('email', 'local_zoomattendance') . '</th>';
        echo '<th>' . get_string('submittedon', 'local_zoomattendance') . '</th>';
        echo '<th>' . get_string('words', 'local_zoomattendance') . '</th>';
        echo '<th>' . get_string('status', 'local_zoomattendance') . '</th>';
        echo '<th>' . get_string('action', 'local_zoomattendance') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {

            $sm    = $lzsstatus[$row->status] ?? $lzsstatus[0];
            $badge = '<span class="lzs-status ' . $sm[1] . '">' .
                '<i class="fa ' . $sm[2] . '" aria-hidden="true"></i>' .
                get_string($sm[0], 'local_zoomattendance') . '</span>';

            echo '<tr>';
            echo '<td><strong>' . fullname($row) . '</strong></td>';
            echo '<td>' . s($row->email) . '</td>';
            echo '<td>' . userdate($row->submittedat) . '</td>';
            echo '<td><span class="lzs-pill lzs-pill--muted">' . (int)$row->wordcount . '</span></td>';
            echo '<td>' . $badge;

            if ((int)$row->status === 2 && !empty($row->feedback)) {
                echo '<div class="lzs-feednote"><strong>' .
                    get_string('feedback', 'local_zoomattendance') . ':</strong> ' .
                    s($row->feedback) . '</div>';
            }

            echo '</td>';

            echo '<td>';
            echo '<details>';
            echo '<summary class="btn btn-sm btn-outline-secondary">' .
                '<i class="fa fa-eye" aria-hidden="true"></i> ' .
                get_string('viewsummary', 'local_zoomattendance') . '</summary>';
            echo '<div class="lzs-summary-text">' .
                format_text($row->summary, FORMAT_PLAIN) . '</div>';

            if ((int)$row->status === 0) {

                echo '<div class="lzs-actions">';

                // Approve.
                echo '<form method="post" class="lzs-actions-row">';
                echo '<input type="hidden" name="courseid" value="' . $courseid . '">';
                echo '<input type="hidden" name="tab" value="summaries">';
                echo '<input type="hidden" name="meetingid" value="' . s($meetingid) . '">';
                echo '<input type="hidden" name="summaryid" value="' . $row->id . '">';
                echo '<input type="hidden" name="action" value="approve">';
                echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
                echo '<button type="submit" class="btn btn-sm btn-success">' .
                    '<i class="fa fa-check" aria-hidden="true"></i> ' .
                    get_string('approve', 'local_zoomattendance') . '</button>';
                echo '</form>';

                // Reject (requires a reason).
                echo '<form method="post">';
                echo '<input type="hidden" name="courseid" value="' . $courseid . '">';
                echo '<input type="hidden" name="tab" value="summaries">';
                echo '<input type="hidden" name="meetingid" value="' . s($meetingid) . '">';
                echo '<input type="hidden" name="summaryid" value="' . $row->id . '">';
                echo '<input type="hidden" name="action" value="reject">';
                echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
                echo '<textarea name="feedback" class="form-control" rows="2" required ' .
                    'placeholder="' . get_string('rejectreason', 'local_zoomattendance') . '"></textarea>';
                echo '<button type="submit" class="btn btn-sm btn-danger mt-1">' .
                    '<i class="fa fa-xmark" aria-hidden="true"></i> ' .
                    get_string('reject', 'local_zoomattendance') . '</button>';
                echo '</form>';

                echo '</div>';
            }

            echo '</details>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    $drillbaseurl = new moodle_url('/local/zoomattendance/view.php', [
        'courseid'  => $courseid,
        'tab'       => 'summaries',
        'meetingid' => $meetingid
    ]);

    echo $OUTPUT->paging_bar($submissioncount, $page, $perpage, $drillbaseurl);

} else {

    /*
    |--------------------------------------------------------------------------
    | Overview: one row per session
    |--------------------------------------------------------------------------
    */

    echo '<div class="lzs-head">'
        . '<div class="lzs-icon"><i class="fa fa-file-lines" aria-hidden="true"></i></div>'
        . '<div><h3>' . get_string('recordingsummaries', 'local_zoomattendance') . '</h3>'
        . '<div class="lzs-sub">Recorded-session summaries grouped by session</div></div>'
        . '</div>';

    $sessioncount = $DB->count_records_sql("
        SELECT COUNT(DISTINCT zoommeetingid)
          FROM {local_zoom_recording_summary}
         WHERE courseid = ?
    ", [$courseid]);

    if ($sessioncount == 0) {

        echo '<div class="lzs-empty"><i class="fa fa-inbox" aria-hidden="true"></i>' .
            get_string('nosummaries', 'local_zoomattendance') . '</div>';

    } else {

        // Aggregate per session, then attach the session name/date. Both
        // subqueries are pre-aggregated so the join stays one-to-one.
        $groups = $DB->get_records_sql("
            SELECT g.meetingid, g.submitted, g.approved, g.rejected, g.pending,
                   s.sessionname, s.timestart
              FROM (
                    SELECT REPLACE(zoommeetingid, ' ', '') AS meetingid,
                           COUNT(*) AS submitted,
                           SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS approved,
                           SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) AS rejected,
                           SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) AS pending
                      FROM {local_zoom_recording_summary}
                     WHERE courseid = ?
                  GROUP BY REPLACE(zoommeetingid, ' ', '')
              ) g
         LEFT JOIN (
                    SELECT REPLACE(meeting_id, ' ', '') AS meeting_id,
                           MAX(topic) AS sessionname,
                           MAX(start_time) AS timestart
                      FROM {local_zoom_attendance_summary}
                  GROUP BY REPLACE(meeting_id, ' ', '')
              ) s ON CONVERT(s.meeting_id USING utf8mb4) COLLATE utf8mb4_unicode_ci =
                     CONVERT(g.meetingid USING utf8mb4) COLLATE utf8mb4_unicode_ci
          ORDER BY s.timestart DESC
        ", [$courseid], $offset, $perpage);

        echo '<table class="lzs-table">';
        echo '<thead><tr>';
        echo '<th>' . get_string('colsession', 'local_zoomattendance') . '</th>';
        echo '<th>' . get_string('colconducted', 'local_zoomattendance') . '</th>';
        echo '<th>' . get_string('colsubmitted', 'local_zoomattendance') . '</th>';
        echo '<th>' . get_string('colapproved', 'local_zoomattendance') . '</th>';
        echo '<th>' . get_string('colrejected', 'local_zoomattendance') . '</th>';
        echo '<th>' . get_string('colpending', 'local_zoomattendance') . '</th>';
        echo '<th>' . get_string('action', 'local_zoomattendance') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($groups as $g) {

            if (!empty($g->sessionname)) {
                $sname = format_string($g->sessionname);
            } else if ($g->meetingid !== '') {
                $sname = s($g->meetingid);
            } else {
                $sname = get_string('unknownsession', 'local_zoomattendance');
            }

            $conducted = !empty($g->timestart) ? userdate($g->timestart) : '-';

            $reviewurl = new moodle_url('/local/zoomattendance/view.php', [
                'courseid'  => $courseid,
                'tab'       => 'summaries',
                'meetingid' => $g->meetingid
            ]);

            $haspending = ((int)$g->pending > 0);
            $btnclass   = $haspending ? 'btn-primary' : 'btn-outline-secondary';
            $btnicon    = $haspending ? 'fa-clipboard-check' : 'fa-eye';
            $btnlabel   = $haspending
                ? get_string('reviewsubmissions', 'local_zoomattendance') . ' (' . (int)$g->pending . ')'
                : get_string('viewsubmissions', 'local_zoomattendance');

            echo '<tr>';
            echo '<td><strong>' . $sname . '</strong></td>';
            echo '<td>' . $conducted . '</td>';
            echo '<td><span class="lzs-pill lzs-pill--info">' . (int)$g->submitted . '</span></td>';
            echo '<td><span class="lzs-pill lzs-pill--green">' . (int)$g->approved . '</span></td>';
            echo '<td><span class="lzs-pill lzs-pill--red">' . (int)$g->rejected . '</span></td>';
            echo '<td><span class="lzs-pill lzs-pill--amber">' . (int)$g->pending . '</span></td>';
            echo '<td><a href="' . $reviewurl . '" class="btn btn-sm ' . $btnclass . '">' .
                '<i class="fa ' . $btnicon . '" aria-hidden="true"></i> ' . $btnlabel . '</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        $overviewbaseurl = new moodle_url('/local/zoomattendance/view.php', [
            'courseid' => $courseid,
            'tab'      => 'summaries'
        ]);

        echo $OUTPUT->paging_bar($sessioncount, $page, $perpage, $overviewbaseurl);
    }
}
