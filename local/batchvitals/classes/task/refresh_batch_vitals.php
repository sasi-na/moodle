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
 * Scheduled task that rebuilds the cached per-batch figures.
 *
 * @package   local_batchvitals
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_batchvitals\task;

use local_batchvitals\vitals;

defined('MOODLE_INTERNAL') || die();

/**
 * Sweeps every configured batch off-request so the report never queries.
 */
class refresh_batch_vitals extends \core\task\scheduled_task {

    /**
     * @return string
     */
    public function get_name() {
        return get_string('task_refresh', 'local_batchvitals');
    }

    /**
     * Recompute and cache all configured batches.
     */
    public function execute() {
        $ids = vitals::batch_ids();
        if (!$ids) {
            mtrace('local_batchvitals: no batch categories configured, nothing to do.');
            return;
        }

        $started = microtime(true);
        $payload = vitals::compute_all();

        foreach ($payload['batches'] as $b) {
            mtrace(sprintf('  %-28s learners=%-7d active=%-7d never=%-6d suspended=%-6d (%.2fs)',
                $b['name'], $b['learners'], $b['active'], $b['never'], $b['susp'], $b['secs']));
        }
        $progsecs = 0;
        foreach ($payload['programmes'] as $p) {
            $progsecs += $p['secs'];
        }
        mtrace(sprintf('  ... plus %d programme(s) in %.2fs',
            count($payload['programmes']), $progsecs));
        mtrace(sprintf('local_batchvitals: cached %d batch(es) and %d programme(s) in %.2fs.',
            count($payload['batches']), count($payload['programmes']),
            microtime(true) - $started));
    }
}
