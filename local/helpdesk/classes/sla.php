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

/**
 * Turnaround policy and breach detection. Wall-clock: no working calendar, no pausing.
 *
 * @package    local_helpdesk
 * @copyright  2026 onwards
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sla {

    /** @var string No public reply within the first-response window. */
    public const FLAG_NORESPONSE = 'noresponse';
    /** @var string Needed a specialist and was never forwarded. */
    public const FLAG_NOACTION = 'noaction';
    /** @var string Forwarded, and the specialist has not answered. */
    public const FLAG_SPECIALIST_SILENT = 'specialistsilent';

    /**
     * Hours left on the resolution clock at which a ticket counts as "nearing breach".
     *
     * Shared by urgency() and the queue's nearing-breach card so the badge a user
     * sees and the number in the card can never disagree.
     */
    public const NEARING_HOURS = 6;

    /**
     * Hours allowed for the first public reply.
     *
     * @return int
     */
    public static function first_response_hours(): int {
        return (int) (get_config('local_helpdesk', 'firstresponsehours') ?: 6);
    }

    /**
     * Hours allowed to resolve, end to end including any forwarding.
     *
     * @return int
     */
    public static function resolution_hours(): int {
        return (int) (get_config('local_helpdesk', 'resolutionhours') ?: 24);
    }

    /**
     * Hours a forwarded ticket may sit unanswered. Below the resolution window on purpose.
     *
     * @return int
     */
    public static function specialist_hours(): int {
        return (int) (get_config('local_helpdesk', 'specialisthours') ?: 12);
    }

    /**
     * Seconds until resolution is due. Negative once breached.
     *
     * @param \stdClass $ticket
     * @return int
     */
    public static function time_remaining(\stdClass $ticket): int {
        return (int) $ticket->resolutiondue - time();
    }

    /**
     * Urgency band for the queue, so sorting and colour agree.
     *
     * @param \stdClass $ticket
     * @return string breached|critical|warning|ok
     */
    public static function urgency(\stdClass $ticket): string {
        if (in_array($ticket->status, [ticket::STATUS_RESOLVED, ticket::STATUS_CLOSED,
                                       ticket::STATUS_MERGED], true)) {
            return 'ok';
        }
        $left = self::time_remaining($ticket);
        if ($left < 0) {
            return 'breached';
        }
        if ($left < 2 * HOURSECS) {
            return 'critical';
        }
        if ($left < self::NEARING_HOURS * HOURSECS) {
            return 'warning';
        }
        return 'ok';
    }

    /**
     * Raise any breach flags that now apply. Idempotent.
     *
     * @return int Number of flags raised.
     */
    public static function scan(): int {
        global $DB;

        $now = time();
        $raised = 0;
        $open = [ticket::STATUS_NEW, ticket::STATUS_OPEN, ticket::STATUS_INPROGRESS,
                 ticket::STATUS_ESCALATED];
        [$insql, $params] = $DB->get_in_or_equal($open, SQL_PARAMS_NAMED);

        $params['now'] = $now;
        $sql = "SELECT id FROM {local_helpdesk_ticket}
                 WHERE status $insql AND firstresponsetime IS NULL AND firstresponsedue < :now";
        foreach ($DB->get_fieldset_sql($sql, $params) as $id) {
            $raised += self::raise($id, self::FLAG_NORESPONSE) ? 1 : 0;
        }

        // Nobody triaged it. Does not attempt to judge whether a specialist was needed.
        $sql = "SELECT id FROM {local_helpdesk_ticket}
                 WHERE status $insql AND assigneeid IS NULL AND forwardedto IS NULL
                   AND firstresponsedue < :now";
        foreach ($DB->get_fieldset_sql($sql, $params) as $id) {
            $raised += self::raise($id, self::FLAG_NOACTION) ? 1 : 0;
        }

        // Silence measured from the forward, not from creation.
        $params['cutoff'] = $now - (self::specialist_hours() * HOURSECS);
        $sql = "SELECT t.id
                  FROM {local_helpdesk_ticket} t
                 WHERE t.status = :escalated AND t.forwardedtime < :cutoff
                   AND NOT EXISTS (
                       SELECT 1 FROM {local_helpdesk_message} m
                        WHERE m.ticketid = t.id AND m.authorid = t.forwardedto
                          AND m.timecreated > t.forwardedtime)";
        foreach ($DB->get_fieldset_sql($sql, ['escalated' => ticket::STATUS_ESCALATED,
                                              'cutoff' => $params['cutoff']]) as $id) {
            $raised += self::raise($id, self::FLAG_SPECIALIST_SILENT) ? 1 : 0;
        }

        return $raised;
    }

    /**
     * Record a flag, unless it is already outstanding on this ticket.
     *
     * @param int    $ticketid
     * @param string $type
     * @return bool True if a new flag was raised.
     */
    protected static function raise(int $ticketid, string $type): bool {
        global $DB;

        if ($DB->record_exists('local_helpdesk_flag', ['ticketid' => $ticketid, 'flagtype' => $type])) {
            return false;
        }
        $DB->insert_record('local_helpdesk_flag', (object) [
            'ticketid'   => $ticketid,
            'flagtype'   => $type,
            'timeraised' => time(),
        ]);
        $ticket = ticket::instance($ticketid);
        $ticket->log('flagged', $type);
        notify::flag_raised($ticket->get_record(), $type);
        return true;
    }
}
