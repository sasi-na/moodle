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

namespace mod_assign\task;

use core\task\adhoc_task;
use mod_assign\notification_helper;

/**
 * Ad-hoc task to queue another task for notifying a user about an approaching due date.
 *
 * @package    mod_assign
 * @copyright  2024 David Woloszyn <david.woloszyn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class queue_assignment_due_soon_notification_tasks_for_users extends adhoc_task {

    /**
     * Execute the task.
     */
    public function execute(): void {
        $assignmentid = $this->get_custom_data()->id;
        $type = notification_helper::TYPE_DUE_SOON;
        $users = notification_helper::get_users_within_assignment($assignmentid, $type);
        $skipped = 0;
        foreach ($users as $user) {
            $task = new send_assignment_due_soon_notification_to_user();
            $task->set_custom_data([
                'assignmentid' => $assignmentid,
                'userid' => $user->id,
            ]);
            $task->set_userid($user->id);
            // MOODLE CORE PATCH. core\task\manager::queue_adhoc_task() calls
            // core_user::require_active_user($user, true, true), which THROWS for a
            // suspended, unconfirmed or auth=nologin account. get_enrolled_users()
            // filters suspended ENROLMENTS, not suspended USER ACCOUNTS, so those
            // users are still in this list -- and one exception aborted the whole
            // loop, so every remaining ACTIVE recipient was skipped too and the task
            // then retried forever. Known upstream (Moodle 4.5+); see the forum
            // thread "[4.5] New notifications cause failed tasks".
            //
            // Skipping the users core will not queue for keeps the rest queued.
            // RE-CHECK THIS PATCH ON CORE UPGRADE.
            try {
                \core\task\manager::queue_adhoc_task($task, true);
            } catch (\moodle_exception $e) {
                $skipped++;
            }
        }
        if ($skipped) {
            mtrace("  ... skipped {$skipped} user(s) that cannot receive notifications "
                . "(suspended, unconfirmed or nologin).");
        }
    }
}
