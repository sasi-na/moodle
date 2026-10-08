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
 * Event observer registration for local_useraudit.
 *
 * Both observers use internal => false so they fire only after the
 * triggering transaction commits. If the triggering operation rolls back,
 * our observers are never called.
 *
 * @package   local_useraudit
 * @copyright 2026 EdNxt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\user_updated',
        'callback'  => '\local_useraudit\observer::user_updated',
        'internal'  => false,
    ],
    // Seeds the shadow suspended state for every newly created user,
    // including users created with suspended=1 (via admin form or CSV upload).
    // Without this, the first user_updated on such a user would incorrectly
    // log a false 'suspended' change type.
    [
        'eventname' => '\core\event\user_created',
        'callback'  => '\local_useraudit\observer::user_created',
        'internal'  => false,
    ],
];
