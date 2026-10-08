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

namespace local_rpreport\task;

use local_rpreport\report;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task: generate and email the Research Project submission report.
 *
 * @package   local_rpreport
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_report extends \core\task\scheduled_task {

    /**
     * @return string human-readable task name for the scheduler UI
     */
    public function get_name(): string {
        return get_string('taskname', 'local_rpreport');
    }

    /**
     * Generate the workbook and email it to the configured recipients.
     */
    public function execute(): void {
        $recipients = report::get_recipients();
        if (empty($recipients)) {
            mtrace('local_rpreport: no valid recipients configured — skipping.');
            return;
        }

        \core_php_time_limit::raise(600);
        raise_memory_limit(MEMORY_HUGE);

        mtrace('local_rpreport: building workbook for ' . count($recipients) . ' recipient(s)...');
        $result = report::generate_and_send();

        $s = $result['summary'];
        mtrace(sprintf('local_rpreport: %d course(s), %d student(s).',
            $s['courses'] ?? 0, $s['students'] ?? 0));
        mtrace('local_rpreport: emailed to ' . $result['sent'] . ' of '
            . count($result['recipients']) . ' recipient(s).');
        if (!empty($result['failed'])) {
            mtrace('local_rpreport: FAILED for: ' . implode(', ', $result['failed']));
        }
    }
}
