<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Web services (AJAX).
 *
 * @package   local_coursetutor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_coursetutor_send' => [
        'classname' => 'local_coursetutor\external\send',
        'description' => 'Send a message to a course tutor and get the answer.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_coursetutor_history' => [
        'classname' => 'local_coursetutor\external\history',
        'description' => 'Get the current user\'s chat with a course tutor.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
];
