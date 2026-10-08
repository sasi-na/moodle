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
 * Event observer for local_useraudit.
 *
 * @package   local_useraudit
 * @copyright 2026 EdNxt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_useraudit;

defined('MOODLE_INTERNAL') || die();

/**
 * Observes user_updated and user_created events to maintain an accurate
 * audit trail of profile changes, suspensions and activations.
 *
 * Suspension detection uses a shadow table (local_useraudit_suspended) that
 * mirrors the last-known suspended value per user. Seeded at install time for
 * existing users and updated by user_created for new users going forward.
 *
 * Change types written to local_useraudit_log:
 *   profile_updated — any field change that is not a suspension state change
 *   suspended       — suspended changed 0 → 1
 *   activated       — suspended changed 1 → 0
 *
 * Note: Moodle fires user_updated even when the admin saves a profile form
 * with no actual changes. These produce spurious 'profile_updated' rows which
 * cannot be filtered out with the current event API (it carries no field diff).
 */
class observer {

    /**
     * Handle \core\event\user_updated.
     *
     * All errors are caught and sent to debugging() so an audit failure can
     * never disrupt the user management operation that triggered the event.
     *
     * @param \core\event\user_updated $event
     */
    public static function user_updated(\core\event\user_updated $event): void {
        try {
            self::process_update($event);
        } catch (\Throwable $e) {
            debugging(
                'local_useraudit: failed to record user_updated event'
                . ' (userid=' . $event->objectid . '): ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }

    /**
     * Handle \core\event\user_created.
     *
     * Seeds the shadow suspended state for the new user so that any future
     * user_updated event (including a first edit of a user created with
     * suspended=1) produces the correct change type.
     *
     * @param \core\event\user_created $event
     */
    public static function user_created(\core\event\user_created $event): void {
        global $DB;

        try {
            $userid = $event->objectid;

            $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], 'id, suspended');
            if (!$user) {
                return;
            }

            // Guard against the install.php seed window: if the record already
            // exists (virtually impossible, but defensive), skip the insert.
            if ($DB->record_exists('local_useraudit_suspended', ['userid' => $userid])) {
                return;
            }

            $DB->insert_record('local_useraudit_suspended', (object) [
                'userid'       => $userid,
                'suspended'    => (int) $user->suspended,
                'timemodified' => time(),
            ]);

        } catch (\Throwable $e) {
            debugging(
                'local_useraudit: failed to seed shadow state for new user'
                . ' (userid=' . $event->objectid . '): ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }

    /**
     * Core update-processing logic.
     *
     * Reads the current suspended state from mdl_user, compares it with the
     * shadow state, resolves the change type, then atomically updates the
     * shadow table and appends a row to the audit log — all within a single
     * delegated transaction.
     *
     * On any exception the transaction is explicitly rolled back (via
     * moodle_transaction::rollback, which re-throws) so the caller's
     * catch(\Throwable) can log it. This guarantees the transaction is never
     * left open, which would corrupt subsequent DB operations in the request.
     *
     * Race condition note: two simultaneous user_updated events for the same
     * never-before-audited user can both read shadow = null and race to INSERT.
     * The loser hits the UNIQUE constraint, the transaction rolls back, and the
     * exception is swallowed by user_updated()'s catch. That single event's
     * log entry is lost but shadow state (written by the winner) remains
     * correct — all subsequent events work fine. This window is sub-millisecond
     * and requires two admins editing the same brand-new user simultaneously.
     *
     * @param \core\event\user_updated $event
     */
    private static function process_update(\core\event\user_updated $event): void {
        global $DB;

        $userid  = $event->objectid;
        $actorid = $event->userid;

        // Read the user record outside the transaction — internal=>false
        // guarantees the triggering write is already committed, so this is
        // consistent. Avoids holding a lock on mdl_user unnecessarily.
        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], 'id, suspended');
        if (!$user) {
            // User was deleted between the event firing and this observer running.
            return;
        }

        $newsuspended = (int) $user->suspended;

        // Wrap both the shadow mutation and the log insert in one transaction
        // so a crash between them can never leave the tables in an inconsistent
        // state (Bug 1 fix). Rollback on any exception then re-throws so the
        // outer handler can log it (Bug 3 handling).
        $transaction = $DB->start_delegated_transaction();
        try {
            $shadow      = $DB->get_record('local_useraudit_suspended', ['userid' => $userid]);
            $oldsuspended = $shadow ? (int) $shadow->suspended : 0;

            if ($oldsuspended !== $newsuspended) {
                $changetype = ($newsuspended === 1) ? 'suspended' : 'activated';

                $now = time();
                if ($shadow) {
                    $DB->update_record('local_useraudit_suspended', (object) [
                        'id'           => $shadow->id,
                        'suspended'    => $newsuspended,
                        'timemodified' => $now,
                    ]);
                } else {
                    // No shadow record: user was created after install and the
                    // user_created observer missed it (or a race with another
                    // simultaneous event). Insert now.
                    // If a concurrent transaction already inserted this row the
                    // UNIQUE constraint fires → exception → rollback → re-throw
                    // → swallowed by user_updated's catch. Rare but safe.
                    $DB->insert_record('local_useraudit_suspended', (object) [
                        'userid'       => $userid,
                        'suspended'    => $newsuspended,
                        'timemodified' => $now,
                    ]);
                }
            } else {
                $changetype = 'profile_updated';
            }

            // getremoteaddr(null) returns null when REMOTE_ADDR is absent
            // (CLI/cron), matching the nullable ip column rather than storing ''.
            $ip = getremoteaddr(null) ?: null;

            $DB->insert_record('local_useraudit_log', (object) [
                'userid'      => $userid,
                'actorid'     => $actorid,
                'changetype'  => $changetype,
                'ip'          => $ip,
                'timecreated' => $event->timecreated,
            ]);

            $transaction->allow_commit();

        } catch (\Exception $e) {
            // rollback() performs the DB rollback then re-throws so the
            // transaction is never left open on exception paths.
            $transaction->rollback($e);
        }
        // \Error (programming mistakes) propagates naturally; Moodle's global
        // exception handler calls force_transaction_rollback() before halting.
    }
}
