<?php
require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);

$course = get_course($id);
require_course_login($course);

$context = context_course::instance($course->id);

// Page setup
$PAGE->set_url('/mod/customteams/index.php', ['id' => $id]);
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'customteams'));

// Go through modinfo rather than straight to the table.
//
// The previous version selected every row for the course and printed its
// externalurl. That handed out the join link for hidden activities, for
// activities restricted by an availability condition, and for meetings that had
// not started yet -- the last one silently undoing the countdown gate that
// view.php enforces, since the link here worked immediately.
//
// Linking to the activity instead of the meeting keeps one place in charge of
// when a join link is handed out.
$modinfo = get_fast_modinfo($course);
$instances = $modinfo->get_instances_of('customteams');

$rows = [];

foreach ($instances as $cm) {
    if (!$cm->uservisible) {
        continue;
    }

    $rows[] = html_writer::tag('li', html_writer::link(
        new moodle_url('/mod/customteams/view.php', ['id' => $cm->id]),
        format_string($cm->name)
    ));
}

if (!empty($rows)) {
    echo html_writer::tag('ul', implode('', $rows));
} else {
    echo $OUTPUT->notification(
        get_string('nomeetings', 'customteams'),
        'notifymessage'
    );
}

echo $OUTPUT->footer();
