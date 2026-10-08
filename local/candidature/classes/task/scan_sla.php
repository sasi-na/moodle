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
 * Flags candidates left undecided past the review SLA.
 *
 * @package   local_candidature
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_candidature\task;

use local_candidature\candidature;
use local_candidature\notify;

defined('MOODLE_INTERNAL') || die();

/**
 * One indexed pass over overdue pending candidatures.
 */
class scan_sla extends \core\task\scheduled_task {

    /**
     * @return string
     */
    public function get_name() {
        return get_string('task_scansla', 'local_candidature');
    }

    /**
     * Flag anything overdue that has not been flagged already.
     */
    public function execute() {
        global $DB;

        if (!candidature::enabled()) {
            mtrace('local_candidature: queue disabled, nothing to scan.');
            return;
        }

        // flaggedtime NULL is what stops the same candidate being reported hourly.
        $overdue = $DB->get_records_select('local_candidature',
            'status = :status AND decisiondue < :now AND flaggedtime IS NULL',
            ['status' => candidature::PENDING, 'now' => time()]);

        foreach ($overdue as $rec) {
            $DB->set_field('local_candidature', 'flaggedtime', time(), ['id' => $rec->id]);
            candidature::log((int) $rec->id, 'flagged',
                'overdue by ' . format_time(time() - (int) $rec->decisiondue));
            notify::flagged($rec);
            mtrace('  flagged candidature ' . $rec->id . ' (user ' . $rec->userid . ')');
        }

        mtrace('local_candidature: flagged ' . count($overdue) . ' overdue candidate(s).');
    }
}
