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

namespace local_helpdesk;

use context_system;

/**
 * Retention: anonymise rather than delete. Personal data goes, ticket shape stays for
 * reporting. Legal hold and deleted users are outside the clock.
 *
 * @package    local_helpdesk
 * @copyright  2026 onwards
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class retention {

    /**
     * Days a closed ticket is kept in full.
     *
     * @return int
     */
    public static function retention_days(): int {
        return (int) (get_config('local_helpdesk', 'retentiondays') ?: 365);
    }

    /**
     * Anonymise every ticket whose retention period has elapsed.
     *
     * @param int|null $now Override for testing.
     * @return int Tickets processed.
     */
    public static function apply(?int $now = null): int {
        global $DB;

        $now = $now ?? time();
        $cutoff = $now - (self::retention_days() * DAYSECS);

        $sql = "SELECT id FROM {local_helpdesk_ticket}
                 WHERE legalhold = 0 AND anonymisedtime IS NULL
                   AND closedtime IS NOT NULL AND closedtime < :cutoff";
        $ids = $DB->get_fieldset_sql($sql, ['cutoff' => $cutoff]);

        foreach ($ids as $id) {
            self::anonymise_ticket((int) $id, $now);
        }
        return count($ids);
    }

    /**
     * Strip one ticket of personal data.
     *
     * @param int $ticketid
     * @param int $now
     */
    public static function anonymise_ticket(int $ticketid, int $now): void {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        self::delete_attachments($ticketid);

        // Keep the shape of the conversation, drop what it said.
        $DB->execute("UPDATE {local_helpdesk_message}
                         SET message = '', authorid = 0, presentedas = NULL
                       WHERE ticketid = ?", [$ticketid]);

        $DB->execute("UPDATE {local_helpdesk_event} SET actorid = NULL, detail = NULL
                       WHERE ticketid = ?", [$ticketid]);

        $DB->update_record('local_helpdesk_ticket', (object) [
            'id'             => $ticketid,
            'userid'         => 0,
            'subject'        => '',
            'assigneeid'     => null,
            'forwardedto'    => null,
            'anonymisedtime' => $now,
            'timemodified'   => $now,
        ]);

        $transaction->allow_commit();
    }

    /**
     * Anonymise one user's tickets regardless of age. Legal hold still wins.
     *
     * @param int $userid
     * @return int Tickets processed.
     */
    public static function anonymise_for_user(int $userid): int {
        global $DB;

        $now = time();
        $ids = $DB->get_fieldset_select('local_helpdesk_ticket', 'id',
            'userid = ? AND legalhold = 0 AND anonymisedtime IS NULL', [$userid]);
        foreach ($ids as $id) {
            self::anonymise_ticket((int) $id, $now);
        }
        return count($ids);
    }

    /**
     * Remove every stored file attached to a ticket's messages.
     *
     * @param int $ticketid
     */
    protected static function delete_attachments(int $ticketid): void {
        global $DB;

        $fs = get_file_storage();
        $context = context_system::instance();
        $messageids = $DB->get_fieldset_select('local_helpdesk_message', 'id', 'ticketid = ?', [$ticketid]);
        foreach ($messageids as $messageid) {
            $fs->delete_area_files($context->id, 'local_helpdesk', 'attachment', (int) $messageid);
        }
    }

    /**
     * Tickets due for anonymisation, for the admin summary.
     *
     * @return int
     */
    public static function count_due(): int {
        global $DB;

        $cutoff = time() - (self::retention_days() * DAYSECS);
        return $DB->count_records_select('local_helpdesk_ticket',
            'legalhold = 0 AND anonymisedtime IS NULL AND closedtime IS NOT NULL AND closedtime < ?',
            [$cutoff]);
    }
}
