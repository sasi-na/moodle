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
 * Outbound notifications for local_helpdesk.
 *
 * @package   local_helpdesk
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk;

defined('MOODLE_INTERNAL') || die();

/**
 * Sends every message the ticket lifecycle produces.
 *
 * The anonymity rule is enforced here as well as in the thread view: a message to
 * a student is always sent FROM the ticket's assignee, never from whoever actually
 * acted. Where there is no assignee yet, it comes from the no-reply address rather
 * than leaking a real name.
 *
 * Nothing in here is allowed to break the action that triggered it. Every send is
 * wrapped, because a mail transport failure must not roll back a resolved ticket.
 */
class notify {

    /**
     * The support identity a student is told about: name, and a contact number if configured.
     *
     * @param \stdClass $ticket
     * @return array [display name, contact string or '']
     */
    public static function responder_identity(\stdClass $ticket): array {
        $name = get_string('supportteam', 'local_helpdesk');
        $contact = (string) get_config('local_helpdesk', 'pmcontact');

        if (!empty($ticket->assigneeid)) {
            $pm = \core_user::get_user($ticket->assigneeid, '*', IGNORE_MISSING);
            if ($pm) {
                $name = fullname($pm);
                $field = (string) get_config('local_helpdesk', 'pmcontactfield');
                if ($field !== '') {
                    $own = self::profile_value((int) $pm->id, $field);
                    if ($own !== '') {
                        $contact = $own;
                    }
                }
            }
        }
        return [$name, $contact];
    }

    /**
     * Read a custom profile field by shortname, or a core user column.
     *
     * @param int $userid
     * @param string $shortname
     * @return string Empty when unset.
     */
    protected static function profile_value(int $userid, string $shortname): string {
        global $DB;

        $fieldid = $DB->get_field('user_info_field', 'id', ['shortname' => $shortname]);
        if ($fieldid) {
            $v = $DB->get_field('user_info_data', 'data',
                ['userid' => $userid, 'fieldid' => $fieldid]);
            return $v === false ? '' : trim((string) $v);
        }
        // Fall back to a core column such as phone1, so a site that stores the
        // number on the account rather than a profile field still works.
        if (in_array($shortname, ['phone1', 'phone2'], true)) {
            $v = $DB->get_field('user', $shortname, ['id' => $userid]);
            return $v === false ? '' : trim((string) $v);
        }
        return '';
    }

    /**
     * The user object a student-facing message appears to come from.
     *
     * @param \stdClass $ticket
     * @return \stdClass
     */
    protected static function public_sender(\stdClass $ticket): \stdClass {
        if (!empty($ticket->assigneeid)) {
            $pm = \core_user::get_user($ticket->assigneeid, '*', IGNORE_MISSING);
            if ($pm) {
                return $pm;
            }
        }
        return \core_user::get_noreply_user();
    }

    /**
     * Deep link to a ticket for staff, or to the student's own tracker.
     *
     * @param int $ticketid
     * @param bool $staff
     * @return \moodle_url
     */
    protected static function url(int $ticketid, bool $staff): \moodle_url {
        return $staff
            ? new \moodle_url('/local/helpdesk/ticket.php', ['id' => $ticketid])
            : new \moodle_url('/local/helpdesk/mytickets.php');
    }

    /**
     * Build and send one notification, swallowing transport failures.
     *
     * @param string $provider Message provider name.
     * @param \stdClass $from
     * @param \stdClass $to
     * @param string $subject
     * @param string $body Plain text.
     * @param \moodle_url $url
     * @return bool Whether it was handed to the message API.
     */
    protected static function send(string $provider, \stdClass $from, \stdClass $to,
                                   string $subject, string $body, \moodle_url $url): bool {
        try {
            $message = new \core\message\message();
            $message->component = 'local_helpdesk';
            $message->name = $provider;
            $message->userfrom = $from;
            $message->userto = $to;
            $message->subject = $subject;
            $message->fullmessage = $body;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = text_to_html($body);
            $message->smallmessage = shorten_text($body, 160);
            $message->notification = 1;
            $message->contexturl = $url->out(false);
            $message->contexturlname = $subject;
            return (bool) message_send($message);
        } catch (\Throwable $e) {
            debugging('local_helpdesk notify (' . $provider . '): ' . $e->getMessage(),
                DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Confirmation to the student, and an alert to everyone working the queue.
     *
     * @param \stdClass $ticket
     */
    public static function ticket_created(\stdClass $ticket): void {
        $student = \core_user::get_user($ticket->userid, '*', IGNORE_MISSING);
        if ($student) {
            [$pmname, $contact] = self::responder_identity($ticket);
            $args = (object) [
                'firstname'  => $student->firstname,
                'reference'  => $ticket->reference,
                'subject'    => format_string($ticket->subject),
                'pmname'     => $pmname,
                'hours'      => sla::resolution_hours(),
                'created'    => userdate($ticket->timecreated),
            ];
            $body = get_string('notify_created_body', 'local_helpdesk', $args);
            if ($contact !== '') {
                $body .= "\n\n" . get_string('notify_created_contact', 'local_helpdesk',
                    (object) ['pmname' => $pmname, 'contact' => $contact]);
            }
            self::send('ticketcreated', self::public_sender($ticket), $student,
                get_string('notify_created_subject', 'local_helpdesk',
                    (object) ['reference' => $ticket->reference]),
                $body, self::url($ticket->id, false));
        }

        // The queue does not poll, so tell the people who work it.
        $subject = get_string('notify_new_subject', 'local_helpdesk',
            (object) ['reference' => $ticket->reference, 'category' => $ticket->category]);
        $body = get_string('notify_new_body', 'local_helpdesk', (object) [
            'reference' => $ticket->reference,
            'student'   => $student ? fullname($student) : '-',
            'category'  => $ticket->category,
            'channel'   => $ticket->channel,
            'subject'   => format_string($ticket->subject),
            'hours'     => sla::first_response_hours(),
        ]);
        foreach (self::staff('local/helpdesk:manage') as $user) {
            self::send('ticketnew', \core_user::get_noreply_user(), $user,
                $subject, $body, self::url($ticket->id, true));
        }
    }

    /**
     * A public reply reached the thread: tell the student.
     *
     * @param \stdClass $ticket
     * @param string $body The reply text.
     */
    public static function public_reply(\stdClass $ticket, string $body): void {
        $student = \core_user::get_user($ticket->userid, '*', IGNORE_MISSING);
        if (!$student) {
            return;
        }
        [$pmname] = self::responder_identity($ticket);
        $text = get_string('notify_replied_body', 'local_helpdesk', (object) [
            'firstname' => $student->firstname,
            'reference' => $ticket->reference,
            'pmname'    => $pmname,
            'reply'     => html_to_text($body, 0, false),
        ]);
        self::send('ticketreplied', self::public_sender($ticket), $student,
            get_string('notify_replied_subject', 'local_helpdesk',
                (object) ['reference' => $ticket->reference]),
            $text, self::url($ticket->id, false));
    }

    /**
     * Status moved to resolved or closed: tell the student.
     *
     * @param \stdClass $ticket
     * @param string $status
     */
    public static function status_changed(\stdClass $ticket, string $status): void {
        $student = \core_user::get_user($ticket->userid, '*', IGNORE_MISSING);
        if (!$student) {
            return;
        }
        [$pmname] = self::responder_identity($ticket);
        $text = get_string('notify_status_body', 'local_helpdesk', (object) [
            'firstname' => $student->firstname,
            'reference' => $ticket->reference,
            'status'    => get_string('status_' . $status, 'local_helpdesk'),
            'pmname'    => $pmname,
        ]);
        self::send('ticketstatus', self::public_sender($ticket), $student,
            get_string('notify_status_subject', 'local_helpdesk', (object) [
                'reference' => $ticket->reference,
                'status'    => get_string('status_' . $status, 'local_helpdesk'),
            ]),
            $text, self::url($ticket->id, false));
    }

    /**
     * A ticket was forwarded: tell the specialist it landed with them.
     *
     * @param \stdClass $ticket
     * @param int $specialistid
     * @param string $note The forwarding note.
     */
    public static function forwarded(\stdClass $ticket, int $specialistid, string $note): void {
        $to = \core_user::get_user($specialistid, '*', IGNORE_MISSING);
        if (!$to) {
            return;
        }
        $remaining = max(0, (int) $ticket->resolutiondue - time());
        $body = get_string('notify_forwarded_body', 'local_helpdesk', (object) [
            'reference' => $ticket->reference,
            'subject'   => format_string($ticket->subject),
            'category'  => $ticket->category,
            'note'      => $note !== '' ? $note : '-',
            'remaining' => format_time($remaining),
        ]);
        self::send('ticketforwarded', \core_user::get_noreply_user(), $to,
            get_string('notify_forwarded_subject', 'local_helpdesk',
                (object) ['reference' => $ticket->reference]),
            $body, self::url($ticket->id, true));
    }

    /**
     * An SLA flag was raised: tell oversight.
     *
     * @param \stdClass $ticket
     * @param string $flagtype noresponse|noaction|specialistsilent
     */
    public static function flag_raised(\stdClass $ticket, string $flagtype): void {
        $pending = max(0, time() - (int) $ticket->timecreated);
        $body = get_string('notify_flag_body', 'local_helpdesk', (object) [
            'reference' => $ticket->reference,
            'reason'    => get_string('flag_' . $flagtype, 'local_helpdesk'),
            'pending'   => format_time($pending),
            'subject'   => format_string($ticket->subject),
        ]);
        $subject = get_string('notify_flag_subject', 'local_helpdesk', (object) [
            'reference' => $ticket->reference,
            'reason'    => get_string('flag_' . $flagtype, 'local_helpdesk'),
        ]);
        foreach (self::staff('local/helpdesk:oversee') as $user) {
            self::send('slaflag', \core_user::get_noreply_user(), $user,
                $subject, $body, self::url($ticket->id, true));
        }
    }

    /**
     * Users holding a capability at system level.
     *
     * @param string $capability
     * @return array
     */
    protected static function staff(string $capability): array {
        $users = get_users_by_capability(\context_system::instance(), $capability,
            'u.id, u.username, u.email, u.firstname, u.lastname, u.lang, u.maildisplay, u.mailformat,
             u.auth, u.suspended, u.deleted, u.emailstop, u.timezone, u.picture, u.imagealt,
             u.middlename, u.alternatename, u.firstnamephonetic, u.lastnamephonetic');
        return array_filter($users, fn($u) => empty($u->suspended) && empty($u->deleted));
    }
}
