<?php
require('../../config.php');

$courseid = required_param('courseid', PARAM_INT);

// Filters
$fromdate = optional_param('fromdate', '', PARAM_TEXT);
$todate   = optional_param('todate', '', PARAM_TEXT);
$search   = optional_param('search', '', PARAM_TEXT);
$page     = optional_param('page', 0, PARAM_INT);
$tab      = optional_param('tab', 'attendance', PARAM_ALPHA);

// When set, the summaries tab drills into a single session's submissions.
$meetingid = optional_param('meetingid', '', PARAM_TEXT);

$perpage = 10;
$offset  = $page * $perpage;

require_login($courseid);

$context = context_course::instance($courseid, MUST_EXIST);

// Only teacher/admin
require_capability('moodle/course:update', $context);

// Reviewing summaries decides attendance, so it is gated separately.
$canreview = has_capability('local/zoomattendance:reviewsummaries', $context);

global $DB;

/**
 * ===========================
 * LIVE-ATTENDANCE MODERATION HANDLERS
 * ===========================
 *
 * Per-session teacher actions on the attendance tab:
 *  - dismiss      : hide the session from the list (soft skip)
 *  - markall      : grant every joined participant full-session attendance
 *  - markenrolled : grant every enrolled student full-session attendance,
 *                   including students who never joined (rows are created)
 *
 * All record an audit note in local_zoom_event_map.comments. Keyed off
 * meeting_id (not summaryid), so handle before the summary-review handler.
 */
$modaction = optional_param('action', '', PARAM_ALPHA);

if (in_array($modaction, ['dismiss', 'markall', 'markenrolled'], true) && confirm_sesskey()) {

    // moodle/course:update is already required above.
    $mid = required_param('meetingid', PARAM_TEXT);

    $returnurl = new moodle_url('/local/zoomattendance/view.php', [
        'courseid' => $courseid,
        'tab'      => 'attendance',
    ]);

    // Resolve the map rows for this meeting that belong to THIS course.
    $maprows = $DB->get_records_sql(
        "SELECT zm.id
           FROM {local_zoom_event_map} zm
           JOIN {event} e ON e.id = zm.event_id
          WHERE zm.meeting_id = ? AND e.courseid = ?",
        [$mid, $courseid]
    );

    if (empty($maprows)) {
        redirect(
            $returnurl,
            get_string('nosessions', 'local_zoomattendance'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    $who  = fullname($USER) . ' (' . $USER->email . ')';
    $when = userdate(time());

    if ($modaction === 'dismiss') {

        $comment = "Session dismissed by {$who} on {$when}";
        foreach ($maprows as $row) {
            $DB->set_field('local_zoom_event_map', 'skipped', 1, ['id' => $row->id]);
            $DB->set_field('local_zoom_event_map', 'comments', $comment, ['id' => $row->id]);
        }

        redirect(
            $returnurl,
            get_string('sessiondismissed', 'local_zoomattendance'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );

    } else if ($modaction === 'markall') {

        $maxdur = (int) $DB->get_field_sql(
            "SELECT MAX(session_duration)
               FROM {local_zoom_attendance_summary}
              WHERE meeting_id = ?",
            [$mid]
        );

        // Bump joined students to the full session length and mark attended.
        $DB->execute(
            "UPDATE {local_zoom_attendance_summary}
                SET total_duration = session_duration, attended = 1
              WHERE meeting_id = ?",
            [$mid]
        );

        $comment = "Attendance marked for all joined by {$who} on {$when}";
        foreach ($maprows as $row) {
            $DB->set_field('local_zoom_event_map', 'comments', $comment, ['id' => $row->id]);
        }

        // session_duration unknown (0) means durations could not be raised.
        $notice = ($maxdur > 0)
            ? get_string('attendancemarkedall', 'local_zoomattendance')
            : get_string('nosessionduration', 'local_zoomattendance');

        redirect(
            $returnurl,
            $notice,
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );

    } else { // markenrolled

        // Mark EVERY enrolled student present for this session, including those
        // who never joined (their summary rows are created). Attendance is keyed
        // by email string, so we translate each enrolled user -> email and upsert.

        // Enrolled students: same canonical pattern as export_attendance.php.
        $students = [];
        foreach (array_keys(get_archetype_roles('student')) as $roleid) {
            $rusers = get_role_users(
                $roleid,
                $context,
                false,
                'u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, ' .
                    'u.middlename, u.alternatename, u.email'
            );
            foreach ($rusers as $u) {
                $students[$u->id] = $u;
            }
        }

        // Existing summary rows for this meeting, keyed by normalized email.
        $existing = [];
        $srs = $DB->get_recordset('local_zoom_attendance_summary', ['meeting_id' => $mid],
            '', 'id, user_email');
        foreach ($srs as $r) {
            $existing[core_text::strtolower(trim($r->user_email))] = $r->id;
        }
        $srs->close();

        // Template values for inserted rows: source from any existing row for
        // this meeting; fall back to the mapped event's name when nobody joined.
        $template = $DB->get_record_sql(
            "SELECT uuid, topic, session_duration, start_time, end_time
               FROM {local_zoom_attendance_summary}
              WHERE meeting_id = ?",
            [$mid],
            IGNORE_MULTIPLE
        );
        if ($template) {
            $tpluuid  = $template->uuid;
            $tpltopic = $template->topic;
            $tpldur   = (int) $template->session_duration;
            $tplstart = (int) $template->start_time;
            $tplend   = (int) $template->end_time;
        } else {
            $tpluuid  = '';
            $tpltopic = (string) $DB->get_field_sql(
                "SELECT e.name
                   FROM {local_zoom_event_map} zm
                   JOIN {event} e ON e.id = zm.event_id
                  WHERE zm.meeting_id = ? AND e.courseid = ?",
                [$mid, $courseid],
                IGNORE_MULTIPLE
            );
            $tpldur   = 0;
            $tplstart = 0;
            $tplend   = 0;
        }

        $now = time();
        $transaction = $DB->start_delegated_transaction();

        foreach ($students as $u) {
            $email = core_text::strtolower(trim($u->email));
            if ($email === '') {
                continue;
            }

            if (isset($existing[$email])) {
                // Already has a row for this meeting: mark fully present.
                $DB->set_field_select(
                    'local_zoom_attendance_summary',
                    'total_duration', $tpldur,
                    'id = ?', [$existing[$email]]
                );
                $DB->set_field_select(
                    'local_zoom_attendance_summary',
                    'attended', 1,
                    'id = ?', [$existing[$email]]
                );
            } else {
                // No row: create a fully-present attendance record.
                $sum = new stdClass();
                $sum->meeting_id       = $mid;
                $sum->uuid             = $tpluuid;
                $sum->topic            = $tpltopic;
                $sum->user_email       = $email;
                $sum->fullname         = fullname($u);
                $sum->total_duration   = $tpldur;
                $sum->start_time       = $tplstart;
                $sum->end_time         = $tplend;
                $sum->session_duration = $tpldur;
                $sum->attended         = 1;
                $sum->timecreated      = $now;

                $newid = $DB->insert_record('local_zoom_attendance_summary', $sum);
                // Guard against a duplicate insert if the same email recurs.
                $existing[$email] = $newid;
            }
        }

        $comment = "Enrolled students marked present by {$who} on {$when}";
        foreach ($maprows as $row) {
            $DB->set_field('local_zoom_event_map', 'comments', $comment, ['id' => $row->id]);
        }

        $transaction->allow_commit();

        // session_duration unknown (0) means durations could not be set.
        $notice = ($tpldur > 0)
            ? get_string('attendancemarkedenrolled', 'local_zoomattendance')
            : get_string('nosessionduration', 'local_zoomattendance');

        redirect(
            $returnurl,
            $notice,
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

/**
 * ===========================
 * REVIEW ACTION HANDLER
 * ===========================
 *
 * Approve (status 1) or reject (status 2) a recorded-session summary.
 * Must run before any output so we can redirect cleanly.
 */
$action = optional_param('action', '', PARAM_ALPHA);

if ($action !== '' && confirm_sesskey()) {

    require_capability('local/zoomattendance:reviewsummaries', $context);

    $summaryid = required_param('summaryid', PARAM_INT);

    $summary = $DB->get_record(
        'local_zoom_recording_summary',
        ['id' => $summaryid, 'courseid' => $courseid],
        '*',
        MUST_EXIST
    );

    // Return to the same session's submission list after reviewing.
    $returnparams = ['courseid' => $courseid, 'tab' => 'summaries'];
    if ($meetingid !== '') {
        $returnparams['meetingid'] = $meetingid;
    }
    $returnurl = new moodle_url('/local/zoomattendance/view.php', $returnparams);

    if ($action === 'reject') {

        $feedback = trim(optional_param('feedback', '', PARAM_TEXT));

        // FRD: rejection requires a reason.
        if ($feedback === '') {
            redirect(
                $returnurl,
                get_string('rejectreasonrequired', 'local_zoomattendance'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }

        $summary->status   = 2;
        $summary->feedback = $feedback;

    } else if ($action === 'approve') {

        $summary->status   = 1;
        $summary->feedback = '';
    }

    if ($action === 'approve' || $action === 'reject') {

        $summary->reviewedby = $USER->id;
        $summary->reviewedat = time();

        $DB->update_record('local_zoom_recording_summary', $summary);

        redirect(
            $returnurl,
            get_string('reviewsaved', 'local_zoomattendance'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

$PAGE->set_url('/local/zoomattendance/view.php', [
    'courseid' => $courseid,
    'fromdate' => $fromdate,
    'todate'   => $todate,
    'search'   => $search,
    'page'     => $page,
    'tab'      => $tab
]);

$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('livesessionattendance', 'local_zoomattendance'));
$PAGE->set_heading(format_string($COURSE->fullname));

// Confirmation modal for the per-session moderation buttons.
$PAGE->requires->js_call_amd('local_zoomattendance/confirmaction', 'init');

echo $OUTPUT->header();

echo '<div class="lzs-wrap">';

/**
 * ===========================
 * TAB NAVIGATION
 * ===========================
 */
$attendanceurl = new moodle_url('/local/zoomattendance/view.php', [
    'courseid' => $courseid,
    'tab'      => 'attendance'
]);
$summariesurl = new moodle_url('/local/zoomattendance/view.php', [
    'courseid' => $courseid,
    'tab'      => 'summaries'
]);
$sheeturl = new moodle_url('/local/zoomattendance/view.php', [
    'courseid' => $courseid,
    'tab'      => 'sheet'
]);

echo '<div class="lzs-tabbar">';
echo '<a class="' . ($tab === 'attendance' ? 'active' : '') . '" href="' . $attendanceurl . '">' .
    '<i class="fa fa-users" aria-hidden="true"></i>' .
    get_string('tabattendance', 'local_zoomattendance') . '</a>';
if ($canreview) {
    echo '<a class="' . ($tab === 'summaries' ? 'active' : '') . '" href="' . $summariesurl . '">' .
        '<i class="fa fa-file-lines" aria-hidden="true"></i>' .
        get_string('tabsummaries', 'local_zoomattendance') . '</a>';
}
echo '<a class="' . ($tab === 'sheet' ? 'active' : '') . '" href="' . $sheeturl . '">' .
    '<i class="fa fa-table-list" aria-hidden="true"></i>' .
    get_string('tabattendancesheet', 'local_zoomattendance') . '</a>';
echo '</div>';

/**
 * ===========================
 * ATTENDANCE SHEET TAB
 * ===========================
 */
if ($tab === 'sheet') {

    require(__DIR__ . '/attendancesheet_tab.php');

    echo '</div>';
    echo $OUTPUT->footer();
    exit;
}

/**
 * ===========================
 * SUMMARIES TAB
 * ===========================
 */
if ($tab === 'summaries' && $canreview) {

    require(__DIR__ . '/summaries_tab.php');

    echo '</div>';
    echo $OUTPUT->footer();
    exit;
}

/**
 * ===========================
 * FILTER CONDITIONS
 * ===========================
 */
$params = [$courseid, time()];
// Only list sessions that have actually happened AND have captured attendance:
// hide not-yet-started meetings (e.timestart in the future) and any mapped
// meeting that has no attendance rows yet (e.g. freshly scheduled).
$where  = "WHERE e.courseid = ?
             AND zm.skipped = 0
             AND e.timestart <= ?
             AND EXISTS (
                 SELECT 1
                   FROM {local_zoom_attendance_summary} eas
                  WHERE eas.meeting_id = zm.meeting_id
             )";

if (!empty($fromdate)) {
    $where .= " AND e.timestart >= ?";
    $params[] = strtotime($fromdate . ' 00:00:00');
}

if (!empty($todate)) {
    $where .= " AND e.timestart <= ?";
    $params[] = strtotime($todate . ' 23:59:59');
}

if (!empty($search)) {
    $where .= " AND " . $DB->sql_like('e.name', '?', false);
    $params[] = '%' . $search . '%';
}

/**
 * ===========================
 * TOTAL COUNT
 * ===========================
 */
$countsql = "
    SELECT COUNT(DISTINCT zm.meeting_id)
    FROM {local_zoom_event_map} zm
    JOIN {event} e ON e.id = zm.event_id
    $where
";

$totalcount = $DB->count_records_sql($countsql, $params);

/**
 * ===========================
 * MAIN QUERY
 * ===========================
 */
$sql = "
    SELECT 
        zm.meeting_id,
        e.name,
        e.timestart,
        COUNT(s.id) AS usercount
    FROM {local_zoom_event_map} zm
    JOIN {event} e ON e.id = zm.event_id
    LEFT JOIN {local_zoom_attendance_summary} s 
        ON s.meeting_id = zm.meeting_id
    $where
    GROUP BY zm.meeting_id, e.name, e.timestart
    ORDER BY e.timestart DESC
";

$sessions = $DB->get_records_sql($sql, $params, $offset, $perpage);

/**
 * ===========================
 * UI HEADER
 * ===========================
 */
echo '<div class="lzs-head">'
    . '<div class="lzs-icon"><i class="fa fa-users" aria-hidden="true"></i></div>'
    . '<div><h3>' . get_string('livesessionattendance', 'local_zoomattendance') . '</h3>'
    . '<div class="lzs-sub">Live session attendance by meeting</div></div>'
    . '</div>';

/**
 * ===========================
 * FILTER UI
 * ===========================
 */
echo '
<form method="get" class="lzs-toolbar">

    <input type="hidden" name="courseid" value="'.$courseid.'">

    <div class="lzs-field" style="flex:1 1 200px;">
        <label>Search</label>
        <input type="text" name="search" placeholder="Search session..."
               value="'.s($search).'" class="form-control">
    </div>

    <div class="lzs-field">
        <label>From</label>
        <input type="date" name="fromdate" value="'.$fromdate.'" class="form-control">
    </div>

    <div class="lzs-field">
        <label>To</label>
        <input type="date" name="todate" value="'.$todate.'" class="form-control">
    </div>

    <button type="submit" class="btn btn-primary btn-sm">
        <i class="fa fa-filter" aria-hidden="true"></i> Apply
    </button>

    <a href="?courseid='.$courseid.'&tab=attendance" class="btn btn-outline-secondary btn-sm">
        Reset
    </a>

</form>
';

/**
 * ===========================
 * TABLE
 * ===========================
 */
if (empty($sessions)) {

    echo '<div class="lzs-empty"><i class="fa fa-calendar" aria-hidden="true"></i>' .
        get_string('nosessions', 'local_zoomattendance') . '</div>';

} else {

    echo '<table class="lzs-table">';
    echo '<thead>
            <tr>
                <th>Session Name</th>
                <th>Date</th>
                <th>Attendees</th>
                <th>Report</th>
                <th>' . get_string('actions', 'local_zoomattendance') . '</th>
            </tr>
          </thead><tbody>';

    $actionurl = new moodle_url('/local/zoomattendance/view.php');
    $sesskey   = sesskey();

    // Confirmation modal copy (rendered by amd/src/confirmaction.js).
    $dismisstitle = s(get_string('confirmdismisstitle', 'local_zoomattendance'));
    $dismissbody  = s(get_string('confirmdismiss', 'local_zoomattendance'));
    $dismissyes   = s(get_string('dismisssession', 'local_zoomattendance'));
    $markalltitle = s(get_string('confirmmarkalltitle', 'local_zoomattendance'));
    $markallbody  = s(get_string('confirmmarkall', 'local_zoomattendance'));
    $markallyes   = s(get_string('markallpresent', 'local_zoomattendance'));
    $markenrolledtitle = s(get_string('confirmmarkenrolledtitle', 'local_zoomattendance'));
    $markenrolledbody  = s(get_string('confirmmarkenrolled', 'local_zoomattendance'));
    $markenrolledyes   = s(get_string('markallenrolled', 'local_zoomattendance'));
    $cancel       = s(get_string('cancel'));

    foreach ($sessions as $s) {

        $date = !empty($s->timestart)
            ? userdate($s->timestart)
            : '-';

        $downloadurl = new moodle_url('/local/zoomattendance/download.php', [
            'meetingid' => $s->meeting_id,
            'courseid'  => $courseid
        ]);

        echo '<tr>';
        echo '<td><strong>' . format_string($s->name) . '</strong></td>';
        echo '<td>' . $date . '</td>';
        echo '<td><span class="lzs-pill lzs-pill--info">' . $s->usercount . '</span></td>';
        echo '<td>
                <a href="' . $downloadurl . '" class="btn btn-sm btn-outline-primary">
                   <i class="fa fa-download" aria-hidden="true"></i> Download
                </a>
              </td>';

        $mid = s($s->meeting_id);

        echo '<td><div class="lzs-actions">';

        // Mark all present.
        echo '<form method="post" action="' . $actionurl . '" class="d-inline lzs-confirm"'
            . ' data-confirm-title="' . $markalltitle . '"'
            . ' data-confirm-body="' . $markallbody . '"'
            . ' data-confirm-yes="' . $markallyes . '"'
            . ' data-confirm-no="' . $cancel . '">'
            . '<input type="hidden" name="sesskey" value="' . $sesskey . '">'
            . '<input type="hidden" name="courseid" value="' . $courseid . '">'
            . '<input type="hidden" name="meetingid" value="' . $mid . '">'
            . '<input type="hidden" name="action" value="markall">'
            . '<button type="submit" class="btn btn-sm btn-outline-success">'
            . '<i class="fa fa-check-double" aria-hidden="true"></i> '
            . get_string('markallpresent', 'local_zoomattendance')
            . '</button></form>';

        // Mark enrolled students present (enrolled roster, joined or not).
        echo '<form method="post" action="' . $actionurl . '" class="d-inline lzs-confirm"'
            . ' data-confirm-title="' . $markenrolledtitle . '"'
            . ' data-confirm-body="' . $markenrolledbody . '"'
            . ' data-confirm-yes="' . $markenrolledyes . '"'
            . ' data-confirm-no="' . $cancel . '">'
            . '<input type="hidden" name="sesskey" value="' . $sesskey . '">'
            . '<input type="hidden" name="courseid" value="' . $courseid . '">'
            . '<input type="hidden" name="meetingid" value="' . $mid . '">'
            . '<input type="hidden" name="action" value="markenrolled">'
            . '<button type="submit" class="btn btn-sm btn-outline-primary">'
            . '<i class="fa fa-user-check" aria-hidden="true"></i> '
            . get_string('markallenrolled', 'local_zoomattendance')
            . '</button></form>';

        // Dismiss session.
        echo '<form method="post" action="' . $actionurl . '" class="d-inline lzs-confirm"'
            . ' data-confirm-title="' . $dismisstitle . '"'
            . ' data-confirm-body="' . $dismissbody . '"'
            . ' data-confirm-yes="' . $dismissyes . '"'
            . ' data-confirm-no="' . $cancel . '">'
            . '<input type="hidden" name="sesskey" value="' . $sesskey . '">'
            . '<input type="hidden" name="courseid" value="' . $courseid . '">'
            . '<input type="hidden" name="meetingid" value="' . $mid . '">'
            . '<input type="hidden" name="action" value="dismiss">'
            . '<button type="submit" class="btn btn-sm btn-outline-danger">'
            . '<i class="fa fa-eye-slash" aria-hidden="true"></i> '
            . get_string('dismisssession', 'local_zoomattendance')
            . '</button></form>';

        echo '</div></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
}

/**
 * ===========================
 * PAGINATION
 * ===========================
 */
$baseurl = new moodle_url('/local/zoomattendance/view.php', [
    'courseid' => $courseid,
    'fromdate' => $fromdate,
    'todate'   => $todate,
    'search'   => $search
]);

echo $OUTPUT->paging_bar(
    $totalcount,
    $page,
    $perpage,
    $baseurl
);

echo '</div>';
echo $OUTPUT->footer();
