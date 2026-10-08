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

use core_user;
use stdClass;

/**
 * A support ticket and the operations that move it through its lifecycle.
 *
 * @package    local_helpdesk
 * @copyright  2026 onwards
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ticket {

    /** @var string Raised, nobody has picked it up. */
    public const STATUS_NEW = 'new';
    /** @var string Assigned, awaiting a first public reply. */
    public const STATUS_OPEN = 'open';
    /** @var string A public reply has been sent; conversation is live. */
    public const STATUS_INPROGRESS = 'inprogress';
    /** @var string Sitting with a teaching assistant or faculty member. */
    public const STATUS_ESCALATED = 'escalated';
    /** @var string An answer was given. */
    public const STATUS_RESOLVED = 'resolved';
    /** @var string Resolved and nobody objected. */
    public const STATUS_CLOSED = 'closed';
    /** @var string Folded into another ticket. */
    public const STATUS_MERGED = 'merged';

    /** @var string Visible to the student. */
    public const VIS_PUBLIC = 'public';
    /** @var string Staff only -- never rendered to the student, never satisfies the SLA. */
    public const VIS_INTERNAL = 'internal';

    /** @var stdClass The ticket record. */
    protected $record;

    /**
     * Wrap an existing ticket record.
     *
     * @param stdClass $record Row from local_helpdesk_ticket.
     */
    public function __construct(stdClass $record) {
        $this->record = $record;
    }

    /**
     * Load a ticket by id.
     *
     * @param int $id
     * @return static
     */
    public static function instance(int $id): static {
        global $DB;
        return new static($DB->get_record('local_helpdesk_ticket', ['id' => $id], '*', MUST_EXIST));
    }

    /**
     * The underlying record.
     *
     * @return stdClass
     */
    public function get_record(): stdClass {
        return $this->record;
    }

    /**
     * Raise a new ticket.
     *
     * @param int    $userid   Student raising it.
     * @param string $category
     * @param string $subject
     * @param string $body     First message.
     * @param string $channel  lms|email|slack.
     * @param int    $format   Text format of $body.
     * @return static
     */
    public static function create(int $userid, string $category, string $subject,
                                  string $body, string $channel = 'lms',
                                  int $format = FORMAT_HTML, ?int $courseid = null): static {
        global $DB;

        $now = time();
        $rec = (object) [
            'reference'        => self::generate_reference(),
            'userid'           => $userid,
            'category'         => $category,
            'subject'          => $subject,
            'status'           => self::STATUS_NEW,
            'priority'         => 1,
            'channel'          => $channel,
            'courseid'         => $courseid ?: null,
            'assigneeid'       => null,
            'firstresponsedue' => $now + (sla::first_response_hours() * HOURSECS),
            'resolutiondue'    => $now + (sla::resolution_hours() * HOURSECS),
            'legalhold'        => 0,
            'timecreated'      => $now,
            'timemodified'     => $now,
        ];
        $rec->id = $DB->insert_record('local_helpdesk_ticket', $rec);

        $ticket = new static($rec);
        $ticket->add_message($userid, $body, self::VIS_PUBLIC, 'student', $format);
        $ticket->log('created', $channel);
        notify::ticket_created($ticket->get_record());

        return $ticket;
    }

    /**
     * A ticket number a student can quote.
     *
     * @return string
     */
    protected static function generate_reference(): string {
        global $DB;

        $prefix = get_config('local_helpdesk', 'referenceprefix') ?: 'TKT';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $max = (int) $DB->get_field_sql('SELECT MAX(id) FROM {local_helpdesk_ticket}');
            $candidate = $prefix . '-' . str_pad((string) ($max + 1 + $attempt), 5, '0', STR_PAD_LEFT);
            if (!$DB->record_exists('local_helpdesk_ticket', ['reference' => $candidate])) {
                return $candidate;
            }
        }
        return $prefix . '-' . strtoupper(substr(uniqid(), -6));
    }

    /**
     * Add a message to the thread.
     *
     * Public staff replies are attributed to the ticket assignee, never the real author.
     *
     * @param int    $authorid   Who is really writing.
     * @param string $body
     * @param string $visibility public|internal.
     * @param string $authorrole student|pm|ta|faculty|admin.
     * @param int    $format
     * @return int The new message id.
     */
    public function add_message(int $authorid, string $body, string $visibility,
                                string $authorrole, int $format = FORMAT_HTML): int {
        global $DB;

        $presentedas = null;
        if ($visibility === self::VIS_PUBLIC && $authorrole !== 'student') {
            $presentedas = $this->record->assigneeid ?: $authorid;
        }

        $now = time();
        $id = $DB->insert_record('local_helpdesk_message', (object) [
            'ticketid'      => $this->record->id,
            'authorid'      => $authorid,
            'authorrole'    => $authorrole,
            'presentedas'   => $presentedas,
            'visibility'    => $visibility,
            'message'       => $body,
            'messageformat' => $format,
            'timecreated'   => $now,
        ]);

        // Internal notes do not satisfy the first-response clock.
        if ($visibility === self::VIS_PUBLIC && $authorrole !== 'student'
                && empty($this->record->firstresponsetime)) {
            $this->update(['firstresponsetime' => $now, 'status' => self::STATUS_INPROGRESS]);
        }

        $this->log($visibility === self::VIS_INTERNAL ? 'noted' : 'replied', null, $authorid);

        // Only public staff replies are announced. Internal notes stay internal, and
        // the student's own message is not echoed back to them.
        if ($visibility === self::VIS_PUBLIC && $authorrole !== 'student') {
            notify::public_reply($this->record, $body);
        }
        return $id;
    }

    /**
     * The identity to show against a message. Never read authorid directly.
     *
     * @param stdClass $message Row from local_helpdesk_message.
     * @return int|null User id to display, or null if the message must not be shown.
     */
    public static function presented_author(stdClass $message): ?int {
        if ($message->visibility === self::VIS_INTERNAL) {
            return null;
        }
        return $message->presentedas ?: $message->authorid;
    }

    /**
     * The opening message, which owns the ticket's attachments.
     *
     * @return int
     */
    public function first_message_id(): int {
        global $DB;
        return (int) $DB->get_field_sql(
            'SELECT MIN(id) FROM {local_helpdesk_message} WHERE ticketid = ?', [$this->record->id]);
    }

    /**
     * Assign the ticket to a program manager.
     *
     * @param int $pmid
     */
    public function assign(int $pmid): void {
        $update = ['assigneeid' => $pmid];
        if ($this->record->status === self::STATUS_NEW) {
            $update['status'] = self::STATUS_OPEN;
        }
        $this->update($update);
        $this->log('assigned', fullname(core_user::get_user($pmid)));
    }

    /**
     * Forward for specialist input. Transfers the work, not the clock.
     *
     * @param int    $specialistid
     * @param string $note Internal context for the specialist.
     * @param int    $actorid
     */
    public function forward(int $specialistid, string $note, int $actorid): void {
        $this->update([
            'forwardedto'   => $specialistid,
            'forwardedtime' => time(),
            'status'        => self::STATUS_ESCALATED,
        ]);
        if (trim($note) !== '') {
            $this->add_message($actorid, $note, self::VIS_INTERNAL, 'pm');
        }
        $this->log('forwarded', fullname(core_user::get_user($specialistid)), $actorid);
        notify::forwarded($this->record, $specialistid, $note);
    }

    /**
     * Hand a forwarded ticket back.
     *
     * @param string $reason
     * @param int    $actorid
     */
    public function return_to_queue(string $reason, int $actorid): void {
        $this->update([
            'forwardedto'   => null,
            'forwardedtime' => null,
            'status'        => $this->record->firstresponsetime ? self::STATUS_INPROGRESS : self::STATUS_OPEN,
        ]);
        if (trim($reason) !== '') {
            $this->add_message($actorid, $reason, self::VIS_INTERNAL, 'ta');
        }
        $this->log('returned', null, $actorid);
    }

    /**
     * Mark an answer as given.
     *
     * @param int $actorid
     */
    public function resolve(int $actorid): void {
        $this->update(['status' => self::STATUS_RESOLVED, 'resolvedtime' => time()]);
        $this->log('resolved', null, $actorid);
        notify::status_changed($this->record, self::STATUS_RESOLVED);
    }

    /**
     * Close a resolved ticket.
     *
     * @param int|null $actorid Null when the auto-close task acts.
     */
    public function close(?int $actorid = null): void {
        $this->update(['status' => self::STATUS_CLOSED, 'closedtime' => time()]);
        $this->log('closed', null, $actorid);
        notify::status_changed($this->record, self::STATUS_CLOSED);
    }

    /**
     * Reopen after the student says it is not fixed.
     *
     * @param int $actorid
     */
    public function reopen(int $actorid): void {
        $this->update([
            'status'       => self::STATUS_INPROGRESS,
            'resolvedtime' => null,
            'closedtime'   => null,
        ]);
        $this->log('reopened', null, $actorid);
    }

    /**
     * Exempt from, or return to, the retention purge.
     *
     * @param bool $on
     * @param int  $actorid
     */
    public function set_legal_hold(bool $on, int $actorid): void {
        $this->update(['legalhold' => $on ? 1 : 0]);
        $this->log('held', $on ? 'set' : 'released', $actorid);
    }

    /**
     * Apply field changes to the record and the database together.
     *
     * @param array $fields
     */
    protected function update(array $fields): void {
        global $DB;

        $fields['id'] = $this->record->id;
        $fields['timemodified'] = time();
        $DB->update_record('local_helpdesk_ticket', (object) $fields);
        foreach ($fields as $k => $v) {
            $this->record->$k = $v;
        }
    }

    /**
     * Append to the audit trail.
     *
     * @param string      $action
     * @param string|null $detail
     * @param int|null    $actorid Null means the system acted.
     */
    public function log(string $action, ?string $detail = null, ?int $actorid = null): void {
        global $DB, $USER;

        if ($actorid === null && !CLI_SCRIPT && !empty($USER->id)) {
            $actorid = $USER->id;
        }
        $DB->insert_record('local_helpdesk_event', (object) [
            'ticketid'    => $this->record->id,
            'actorid'     => $actorid,
            'action'      => $action,
            'detail'      => $detail,
            'timecreated' => time(),
        ]);
    }
}
