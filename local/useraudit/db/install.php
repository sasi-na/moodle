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
 * Post-install hook: seed the shadow suspended state for all existing users.
 *
 * @package   local_useraudit
 * @copyright 2026 EdNxt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Populate local_useraudit_suspended with the current suspended state of
 * every non-deleted user so the observer can detect changes from day one.
 *
 * Uses chunked processing to avoid memory exhaustion on large installations.
 */
function xmldb_local_useraudit_install(): bool {
    global $DB;

    $now       = time();
    $chunksize = 500;
    $offset    = 0;

    do {
        $users = $DB->get_records_select(
            'user',
            'deleted = 0',
            [],
            'id ASC',
            'id, suspended',
            $offset,
            $chunksize
        );

        if (empty($users)) {
            break;
        }

        $records = [];
        foreach ($users as $user) {
            $records[] = [
                'userid'       => $user->id,
                'suspended'    => (int) $user->suspended,
                'timemodified' => $now,
            ];
        }

        $DB->insert_records('local_useraudit_suspended', $records);

        $offset += $chunksize;

    } while (count($users) === $chunksize);

    return true;
}
