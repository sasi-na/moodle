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
 * Scheduled tasks for local_cagrades.
 *
 * @package    local_cagrades
 * @copyright  2026 JAIN Online
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        // Overnight: the pass reads every CA grade on the site, and nothing about it is urgent.
        'classname' => 'local_cagrades\task\hide_past_ca',
        'blocking' => 0,
        'minute' => '35',
        'hour' => '1',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*',

        // Ships DISABLED, and must stay that way.
        //
        // The first run on a site with history hides a large number of grades in one go - 382,674 on
        // jain - and that is not something a deploy should do by itself, before anyone has looked at
        // what the rule selects here. It happened exactly once, on 2026-09-05: the task shipped
        // enabled, the deploy registered it, and cron applied the whole sweep overnight ahead of the
        // sign-off it was supposed to wait for. The numbers were right; nobody had agreed to them yet.
        //
        // The intended order on any new site is: run cli/hide_past_ca.php with no --execute, read the
        // count, apply it with --execute --backup=<file>, then enable this task to keep it true.
        //
        // Enable it through \core\task\manager::configure_scheduled_task(), NOT by writing
        // task_scheduled.disabled directly: only the API marks the task customised, and
        // manager::reset_scheduled_tasks_for_component() re-reads this file on every upgrade and
        // resets any task that is not (see lib/classes/task/manager.php:153). A raw UPDATE would be
        // silently undone by the next deploy.
        'disabled' => 1,
    ],
];
