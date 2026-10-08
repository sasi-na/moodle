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
 * Retire spent share keys.
 *
 * @package   local_fileshare
 * @copyright 2026 onlinejain.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_fileshare\task;

use local_fileshare\manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Deletes keys that can no longer serve anything, and prunes very old share rows.
 *
 * validate_user_key() already refuses an expired key, so this is hygiene rather than
 * enforcement - but user_private_key is a table that only ever grows otherwise, and a dead
 * credential is better deleted than merely ignored.
 */
class cleanup extends \core\task\scheduled_task {

    /** @var int Keep share rows this long after expiry, as an audit trail. */
    const AUDIT_RETENTION = 365 * DAYSECS;

    /**
     * Task name shown in the scheduled tasks admin screen.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_cleanup', 'local_fileshare');
    }

    /**
     * Run the cleanup.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        $now = time();

        // Delete keys whose share has expired, been used up, or been revoked.
        $sql = "SELECT s.keyid
                  FROM {local_fileshare} s
                 WHERE s.keyid > 0
                   AND (s.validuntil < :now
                        OR s.revoked = 1
                        OR (s.maxdownloads > 0 AND s.downloads >= s.maxdownloads))";
        $keyids = $DB->get_fieldset_sql($sql, ['now' => $now]);
        if ($keyids) {
            foreach (array_chunk($keyids, 500) as $chunk) {
                [$insql, $inparams] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED);
                // Separate arrays: passing the extra :script parameter to a statement that does
                // not use it leaves the parameter count out of step with the placeholders.
                $DB->delete_records_select('user_private_key', "id $insql AND script = :script",
                    $inparams + ['script' => manager::SCRIPT]);
                $DB->set_field_select('local_fileshare', 'keyid', 0, "keyid $insql", $inparams);
            }
            mtrace('local_fileshare: retired ' . count($keyids) . ' share key(s).');
        }

        // Prune share rows long past their usefulness as an audit record.
        $deleted = $DB->count_records_select('local_fileshare', 'validuntil < :cutoff',
            ['cutoff' => $now - self::AUDIT_RETENTION]);
        if ($deleted) {
            $DB->delete_records_select('local_fileshare', 'validuntil < :cutoff',
                ['cutoff' => $now - self::AUDIT_RETENTION]);
            mtrace('local_fileshare: pruned ' . $deleted . ' expired share row(s).');
        }
    }
}
