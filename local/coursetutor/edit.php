<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Teachers: create / edit a tutor.
 *
 * @package   local_coursetutor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$id = optional_param('id', 0, PARAM_INT);
require_login($courseid, false);
$context = context_course::instance($courseid);
require_capability('local/coursetutor:manage', $context);

$PAGE->set_url(new moodle_url('/local/coursetutor/edit.php', ['courseid' => $courseid, 'id' => $id]));
$PAGE->set_title(get_string($id ? 'edittutor' : 'addtutor', 'local_coursetutor'));
$PAGE->set_heading(format_string(get_course($courseid)->fullname));
$back = new moodle_url('/local/coursetutor/manage.php', ['courseid' => $courseid]);

$form = new \local_coursetutor\form\tutor_form($PAGE->url);
if ($id) {
    $form->set_data($DB->get_record('local_coursetutor', ['id' => $id, 'courseid' => $courseid], '*', MUST_EXIST));
} else {
    $form->set_data(['courseid' => $courseid, 'name' => get_string('defaultname', 'local_coursetutor'),
        'instructions' => get_string('defaultinstructions', 'local_coursetutor'), 'usecontent' => 1, 'enabled' => 1]);
}

if ($form->is_cancelled()) {
    redirect($back);
} else if ($data = $form->get_data()) {
    $rec = (object) ['courseid' => $courseid, 'name' => $data->name, 'instructions' => $data->instructions,
        'knowledge' => $data->knowledge, 'usecontent' => (int) $data->usecontent, 'enabled' => (int) $data->enabled,
        'usermodified' => $USER->id, 'timemodified' => time()];
    if ($id) {
        $rec->id = $id;
        $DB->update_record('local_coursetutor', $rec);
    } else {
        $rec->timecreated = time();
        $DB->insert_record('local_coursetutor', $rec);
    }
    redirect($back, get_string('saved', 'local_coursetutor'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($id ? 'edittutor' : 'addtutor', 'local_coursetutor'));
$form->display();
echo $OUTPUT->footer();
