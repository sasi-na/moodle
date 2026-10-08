<?php
/**
 * Direct meeting join.
 *
 * Registers the logged-in user on the Zoom meeting behind a calendar event
 * (auto-approved, no form) and redirects them straight into the meeting with
 * their LMS identity. If the host has not started the meeting yet, students
 * see a waiting page that re-checks automatically instead.
 */

require('../../config.php');

use local_zoomattendance\helper\join_helper;

$eventid = required_param('eventid', PARAM_INT);

$event = $DB->get_record('event', ['id' => $eventid], '*', MUST_EXIST);

$course = null;
if ($event->courseid > 1) {
    // Course events are only joinable by people who can access the course...
    $course = get_course($event->courseid);
    require_login($course, false);
    if (!can_access_course($course)) {
        throw new \moodle_exception('nopermissions', 'error', '', 'join this meeting');
    }
    // ...and group-restricted events only by members of that group.
    if (!empty($event->groupid)) {
        $coursecontext = context_course::instance($course->id);
        if (!has_capability('moodle/site:accessallgroups', $coursecontext)
                && !groups_is_member($event->groupid, $USER->id)) {
            throw new \moodle_exception('nopermissions', 'error', '', 'join this meeting');
        }
    }
} else {
    // Site / category / personal events: restrict to the owner or admins so a
    // guessed eventid can't auto-register an arbitrary user into a meeting.
    require_login(null, false);
    if (!is_siteadmin() && (int)$event->userid !== (int)$USER->id) {
        throw new \moodle_exception('nopermissions', 'error', '', 'join this meeting');
    }
}

$rec = join_helper::ensure_event_record($eventid);
$meetingid = join_helper::resolve_meeting_id($rec);

if ($meetingid === null) {
    // Could not map the event to a meeting: keep the classic flow working.
    redirect($rec->zoom_url);
}

// Staff who run the session may grab the link before it starts.
$bypassgate = $course
    ? has_capability('local/zoomattendance:bypassjoingate', context_course::instance($course->id))
    : is_siteadmin();

if ($bypassgate || join_helper::is_meeting_live($meetingid)) {
    redirect(join_helper::get_join_url($meetingid, $rec, $USER));
}

/*
|----------------------------------------------------------------------------
| Waiting page: the meeting has not been started by the host yet.
|----------------------------------------------------------------------------
*/

$pageurl = new moodle_url('/local/zoomattendance/join.php', ['eventid' => $eventid]);
$eventname = format_string($event->name);

$PAGE->set_url($pageurl);
$PAGE->set_context($course ? context_course::instance($course->id) : context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('waitingtitle', 'local_zoomattendance'));
$PAGE->set_heading($course ? format_string($course->fullname) : $SITE->fullname);
$PAGE->set_periodic_refresh_delay(30);

echo $OUTPUT->header();

$clockicon = '<svg viewBox="0 0 24 24" width="56" height="56" fill="none" stroke="currentColor"
        stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <circle cx="12" cy="12" r="9"></circle>
    <polyline points="12 7 12 12 15.5 14"></polyline>
</svg>';

echo html_writer::start_div('lzs-wait');
echo html_writer::start_div('lzs-wait-card');

echo html_writer::div($clockicon, 'lzs-wait-icon');
echo html_writer::tag('h2', get_string('waitingheading', 'local_zoomattendance'), ['class' => 'lzs-wait-heading']);
echo html_writer::div($eventname, 'lzs-wait-session');
echo html_writer::div(
    get_string('scheduledfor', 'local_zoomattendance',
        userdate($event->timestart, get_string('strftimedaydatetime', 'langconfig'))),
    'lzs-wait-time');

echo html_writer::div('', 'lzs-wait-countdown', [
    'id' => 'lzs-wait-countdown',
    'data-start' => $event->timestart,
    'data-startingsoon' => get_string('startingsoon', 'local_zoomattendance'),
    'data-startsin' => get_string('startsin', 'local_zoomattendance')
]);

echo html_writer::div(get_string('waitingbody', 'local_zoomattendance'), 'lzs-wait-note');

echo html_writer::start_div('lzs-wait-actions');
echo html_writer::link($pageurl, get_string('checkagain', 'local_zoomattendance'),
    ['class' => 'btn btn-primary']);
if ($course) {
    echo html_writer::link(new moodle_url('/course/view.php', ['id' => $course->id]),
        get_string('backtocourse', 'local_zoomattendance'), ['class' => 'btn btn-secondary']);
}
echo html_writer::end_div();

echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::script("
(function() {
    var el = document.getElementById('lzs-wait-countdown');
    if (!el) { return; }
    var start = parseInt(el.dataset.start, 10) * 1000;
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function tick() {
        var left = Math.floor((start - Date.now()) / 1000);
        if (left <= 0) {
            el.textContent = el.dataset.startingsoon;
            el.classList.add('lzs-wait-countdown-soon');
            return;
        }
        var h = Math.floor(left / 3600), m = Math.floor((left % 3600) / 60), s = left % 60;
        var parts = (h > 0 ? h + ':' : '') + pad(m) + ':' + pad(s);
        el.textContent = el.dataset.startsin + ' ' + parts;
        setTimeout(tick, 1000);
    }
    tick();
})();
");

echo $OUTPUT->footer();
