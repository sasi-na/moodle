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

namespace local_helpdesk\task;

use core\task\scheduled_task;
use local_helpdesk\retention;

/**
 * Apply the retention policy to closed tickets.
 *
 * @package    local_helpdesk
 * @copyright  2026 onwards
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class apply_retention extends scheduled_task {

    /**
     * Task name for the admin task list.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_retention', 'local_helpdesk');
    }

    /**
     * Run the purge.
     */
    public function execute(): void {
        if (!get_config('local_helpdesk', 'enabled')) {
            return;
        }
        $done = retention::apply();
        mtrace("local_helpdesk: anonymised {$done} expired ticket(s)");
    }
}
