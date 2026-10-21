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
 * Event observers for local_authpopup.
 *
 * @package   local_authpopup
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    ['eventname' => '\core\event\user_loggedin', 'callback' => '\local_authpopup\observer::loggedin'],
    ['eventname' => '\core\event\user_loggedinas', 'callback' => '\local_authpopup\observer::loggedinas'],
    ['eventname' => '\core\event\user_loggedout', 'callback' => '\local_authpopup\observer::loggedout'],
];
