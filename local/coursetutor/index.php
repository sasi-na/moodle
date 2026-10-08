<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Tutors available to me (or in one course).
 *
 * @package   local_coursetutor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
require_login($courseid ?: null, false);
$PAGE->set_url(new moodle_url('/local/coursetutor/index.php', ['courseid' => $courseid]));
$PAGE->set_context($courseid ? context_course::instance($courseid) : context_system::instance());
$PAGE->set_title(get_string('pluginname', 'local_coursetutor'));
$PAGE->set_heading(get_string('pluginname', 'local_coursetutor'));

$courses = $courseid ? [$courseid => get_course($courseid)] : enrol_get_my_courses('id, fullname', 'fullname ASC');
$cards = '';
foreach ($courses as $c) {
    $ctx = context_course::instance($c->id);
    if (!has_capability('local/coursetutor:use', $ctx)) {
        continue;
    }
    $tutors = \local_coursetutor\tutor::for_course($c->id);
    $canmanage = has_capability('local/coursetutor:manage', $ctx);
    if (!$tutors && !$canmanage) {
        continue;
    }
    $cards .= html_writer::start_div('card p-3 mb-3');
    $cards .= html_writer::tag('h5', format_string($c->fullname));
    foreach ($tutors as $t) {
        $cards .= html_writer::link(new moodle_url('/local/coursetutor/chat.php', ['id' => $t->id]),
            '<i class="fa fa-comments"></i> ' . s($t->name), ['class' => 'btn btn-outline-primary me-2 mb-2']);
    }
    if (!$tutors) {
        $cards .= html_writer::div(get_string('notutorsyet', 'local_coursetutor'), 'text-muted mb-2');
    }
    if ($canmanage) {
        $cards .= html_writer::link(new moodle_url('/local/coursetutor/manage.php', ['courseid' => $c->id]),
            get_string('managetutors', 'local_coursetutor'), ['class' => 'btn btn-link']);
    }
    $cards .= html_writer::end_div();
}

echo $OUTPUT->header();
echo html_writer::tag('p', get_string('intro', 'local_coursetutor'), ['class' => 'lead']);
if (!\local_coursetutor\gemini::is_configured()) {
    echo $OUTPUT->notification(get_string('notconfigured', 'local_coursetutor'), 'warning');
}
echo $cards ?: $OUTPUT->notification(get_string('nonefound', 'local_coursetutor'), 'info');
echo $OUTPUT->footer();
