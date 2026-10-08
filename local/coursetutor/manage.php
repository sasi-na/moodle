<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Teachers: list and switch tutors on/off for a course.
 *
 * @package   local_coursetutor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
require_login($courseid, false);
$context = context_course::instance($courseid);
require_capability('local/coursetutor:manage', $context);

$PAGE->set_url(new moodle_url('/local/coursetutor/manage.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('managetutors', 'local_coursetutor'));
$PAGE->set_heading(format_string(get_course($courseid)->fullname));

$toggle = optional_param('toggle', 0, PARAM_INT);
if ($toggle && confirm_sesskey()) {
    $t = $DB->get_record('local_coursetutor', ['id' => $toggle, 'courseid' => $courseid], '*', MUST_EXIST);
    $DB->update_record('local_coursetutor', (object) ['id' => $t->id, 'enabled' => $t->enabled ? 0 : 1,
        'usermodified' => $USER->id, 'timemodified' => time()]);
    redirect($PAGE->url);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managetutors', 'local_coursetutor'));
echo html_writer::tag('p', get_string('manageintro', 'local_coursetutor'));
$table = new html_table();
$table->head = [get_string('tutorname', 'local_coursetutor'), get_string('usecontent', 'local_coursetutor'),
    get_string('status'), get_string('chats', 'local_coursetutor'), ''];
foreach ($DB->get_records('local_coursetutor', ['courseid' => $courseid], 'name') as $t) {
    $chats = $DB->count_records_select('local_coursetutor_msg', 'tutorid = ? AND role = ?', [$t->id, 'user']);
    $table->data[] = [
        s($t->name),
        $t->usecontent ? get_string('yes') : get_string('no'),
        $t->enabled ? get_string('enabled', 'local_coursetutor') : get_string('disabled', 'local_coursetutor'),
        $chats,
        html_writer::link(new moodle_url('/local/coursetutor/edit.php', ['courseid' => $courseid, 'id' => $t->id]), get_string('edit'))
        . ' · ' . html_writer::link(new moodle_url($PAGE->url, ['toggle' => $t->id, 'sesskey' => sesskey()]),
            $t->enabled ? get_string('disable') : get_string('enable'))
        . ($t->enabled ? ' · ' . html_writer::link(new moodle_url('/local/coursetutor/chat.php', ['id' => $t->id]),
            get_string('try', 'local_coursetutor')) : ''),
    ];
}
echo $table->data ? html_writer::table($table) : $OUTPUT->notification(get_string('notutorsyet', 'local_coursetutor'), 'info');
echo $OUTPUT->single_button(new moodle_url('/local/coursetutor/edit.php', ['courseid' => $courseid]),
    get_string('addtutor', 'local_coursetutor'), 'get', ['type' => 'primary']);
echo ' ' . $OUTPUT->single_button(new moodle_url('/local/coursetutor/qgen.php', ['courseid' => $courseid]),
    get_string('qgen', 'local_coursetutor'), 'get');
echo $OUTPUT->footer();
