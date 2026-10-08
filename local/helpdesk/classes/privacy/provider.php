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

namespace local_helpdesk\privacy;

use context;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_helpdesk\retention;

/**
 * Deletion runs the same anonymisation as the retention policy. Legal hold is excluded.
 *
 * @package    local_helpdesk
 * @copyright  2026 onwards
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\core_userlist_provider,
        \core_privacy\local\request\plugin\provider {

    /**
     * Describe the personal data this plugin stores.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_helpdesk_ticket', [
            'subject'     => 'privacy:metadata:ticket:subject',
            'category'    => 'privacy:metadata:ticket:category',
            'timecreated' => 'privacy:metadata:ticket:timecreated',
        ], 'privacy:metadata:ticket');

        $collection->add_database_table('local_helpdesk_message', [
            'message'     => 'privacy:metadata:message:message',
            'timecreated' => 'privacy:metadata:message:timecreated',
        ], 'privacy:metadata:message');

        return $collection;
    }

    /**
     * Tickets live in the system context, so that is the only context to report.
     *
     * @param int $userid
     * @return \core_privacy\local\request\contextlist
     */
    public static function get_contexts_for_userid(int $userid): \core_privacy\local\request\contextlist {
        global $DB;

        $contextlist = new \core_privacy\local\request\contextlist();
        $has = $DB->record_exists_select('local_helpdesk_ticket', 'userid = ?', [$userid])
            || $DB->record_exists_select('local_helpdesk_message', 'authorid = ?', [$userid]);
        if ($has) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Everyone who has raised or written on a ticket.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        if (!$userlist->get_context() instanceof context_system) {
            return;
        }
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_helpdesk_ticket}', []);
        $userlist->add_from_sql('authorid', 'SELECT authorid FROM {local_helpdesk_message}', []);
    }

    /**
     * Export the user's tickets. Internal staff notes are never disclosed.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (!count($contextlist)) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        $context = context_system::instance();

        $tickets = $DB->get_records('local_helpdesk_ticket', ['userid' => $userid], 'timecreated');
        foreach ($tickets as $ticket) {
            $data = (object) [
                'reference'   => $ticket->reference,
                'subject'     => $ticket->subject,
                'category'    => $ticket->category,
                'status'      => $ticket->status,
                'channel'     => $ticket->channel,
                'timecreated' => transform::datetime($ticket->timecreated),
            ];

            $messages = $DB->get_records_select('local_helpdesk_message',
                'ticketid = ? AND (visibility = ? OR authorid = ?)',
                [$ticket->id, 'public', $userid], 'timecreated');
            $data->messages = array_values(array_map(function($m) use ($userid) {
                return (object) [
                    'mine'        => $m->authorid == $userid,
                    'message'     => $m->message,
                    'timecreated' => transform::datetime($m->timecreated),
                ];
            }, $messages));

            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_helpdesk'), $ticket->reference], $data);
        }
    }

    /**
     * Anonymise every ticket in the context.
     *
     * @param context $context
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if (!$context instanceof context_system) {
            return;
        }
        $ids = $DB->get_fieldset_select('local_helpdesk_ticket', 'userid',
            'legalhold = 0 AND anonymisedtime IS NULL', []);
        foreach (array_unique($ids) as $userid) {
            retention::anonymise_for_user((int) $userid);
        }
    }

    /**
     * Anonymise one user's tickets.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        if (!count($contextlist)) {
            return;
        }
        retention::anonymise_for_user($contextlist->get_user()->id);
    }

    /**
     * Anonymise tickets for a set of users.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if (!$userlist->get_context() instanceof context_system) {
            return;
        }
        foreach ($userlist->get_userids() as $userid) {
            retention::anonymise_for_user((int) $userid);
        }
    }
}
