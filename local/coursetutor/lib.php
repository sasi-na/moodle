<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Library callbacks.
 *
 * @package   local_coursetutor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add "AI Question Generator" to the course "More" menu for teachers.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function local_coursetutor_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context) {
    if ($course->id > 1 && has_capability('local/coursetutor:manage', $context) && has_capability('moodle/question:add', $context)) {
        $navigation->add(get_string('qgen', 'local_coursetutor'),
            new moodle_url('/local/coursetutor/qgen.php', ['courseid' => $course->id]),
            navigation_node::TYPE_SETTING, null, 'local_coursetutor_qgen', new pix_icon('i/questions', ''));
    }
}
