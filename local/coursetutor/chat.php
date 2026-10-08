<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Full-page chat with one tutor.
 *
 * @package   local_coursetutor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$t = $DB->get_record('local_coursetutor', ['id' => $id, 'enabled' => 1], '*', MUST_EXIST);
require_login($t->courseid, false);
\local_coursetutor\tutor::get_usable($id);

$PAGE->set_url(new moodle_url('/local/coursetutor/chat.php', ['id' => $id]));
$PAGE->set_title(format_string($t->name));
$PAGE->set_heading(format_string(get_course($t->courseid)->fullname));

echo $OUTPUT->header();
echo \local_coursetutor\ui::panel([$t->id => $t], (int) $t->courseid, true);
echo $OUTPUT->footer();
