<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Web service definitions for Edwiser Video Activity.
 *
 * @package    mod_edwiservideoactivity
 * @copyright  2025 Edwiser
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_edwiservideoactivity_save_progress' => [
        'classname'     => 'mod_edwiservideoactivity\external\api',
        'methodname'    => 'save_progress',
        'classpath'     => '',
        'description'   => 'Save video progress for current user',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],
    'mod_edwiservideoactivity_get_progress' => [
        'classname'     => 'mod_edwiservideoactivity\external\api',
        'methodname'    => 'get_progress',
        'classpath'     => '',
        'description'   => 'Get video progress for current user',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
    ],
    'mod_edwiservideoactivity_mark_complete' => [
        'classname'     => 'mod_edwiservideoactivity\external\api',
        'methodname'    => 'mark_complete',
        'classpath'     => '',
        'description'   => 'Mark video activity as complete for current user',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],
];

