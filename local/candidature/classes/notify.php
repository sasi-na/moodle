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
 * Outbound notifications for local_candidature.
 *
 * @package   local_candidature
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_candidature;

defined('MOODLE_INTERNAL') || die();

/**
 * Every message the approval lifecycle produces.
 *
 * Sends are wrapped: a mail transport failure must never roll back a decision that
 * has already been written.
 */
class notify {

    /**
     * Build and send, swallowing transport failures.
     *
     * @param string $provider
     * @param \stdClass $to
     * @param string $subject
     * @param string $body
     * @param \moodle_url $url
     * @return bool
     */
    protected static function send(string $provider, \stdClass $to, string $subject,
                                   string $body, \moodle_url $url): bool {
        try {
            $m = new \core\message\message();
            $m->component = 'local_candidature';
            $m->name = $provider;
            $m->userfrom = \core_user::get_noreply_user();
            $m->userto = $to;
            $m->subject = $subject;
            $m->fullmessage = $body;
            $m->fullmessageformat = FORMAT_PLAIN;
            $m->fullmessagehtml = text_to_html($body);
            $m->smallmessage = shorten_text($body, 160);
            $m->notification = 1;
            $m->contexturl = $url->out(false);
            $m->contexturlname = $subject;
            return (bool) message_send($m);
        } catch (\Throwable $e) {
            debugging('local_candidature notify (' . $provider . '): ' . $e->getMessage(),
                DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * A candidate joined the queue: tell whoever reviews that programme.
     *
     * @param \stdClass $rec
     * @param \stdClass $user
     */
    public static function submitted(\stdClass $rec, \stdClass $user): void {
        $url = new \moodle_url('/local/candidature/index.php');
        $subject = get_string('notify_submitted_subject', 'local_candidature',
            (object) ['name' => fullname($user)]);
        $body = get_string('notify_submitted_body', 'local_candidature', (object) [
            'name'      => fullname($user),
            'email'     => $user->email,
            'programme' => self::category_name($rec->categoryid),
            'hours'     => candidature::sla_hours(),
        ]);
        foreach (self::reviewers($rec->categoryid) as $to) {
            self::send('submitted', $to, $subject, $body, $url);
        }
    }

    /**
     * A decision landed: tell the candidate.
     *
     * @param \stdClass $rec
     */
    public static function decided(\stdClass $rec): void {
        $user = \core_user::get_user($rec->userid, '*', IGNORE_MISSING);
        if (!$user) {
            return;
        }
        $approved = $rec->status === candidature::APPROVED;
        $body = get_string($approved ? 'notify_approved_body' : 'notify_rejected_body',
            'local_candidature', (object) [
                'firstname' => $user->firstname,
                'reason'    => trim((string) $rec->reason) !== '' ? $rec->reason : '-',
                'sitename'  => format_string(get_site()->fullname),
            ]);
        self::send('decision', $user,
            get_string($approved ? 'notify_approved_subject' : 'notify_rejected_subject',
                'local_candidature'),
            $body, new \moodle_url('/'));
    }

    /**
     * A candidate passed the SLA undecided: tell oversight.
     *
     * @param \stdClass $rec
     */
    public static function flagged(\stdClass $rec): void {
        $user = \core_user::get_user($rec->userid, '*', IGNORE_MISSING);
        $pending = max(0, time() - (int) $rec->submittedtime);
        $subject = get_string('notify_flag_subject', 'local_candidature',
            (object) ['name' => $user ? fullname($user) : '#' . $rec->userid]);
        $body = get_string('notify_flag_body', 'local_candidature', (object) [
            'name'      => $user ? fullname($user) : '#' . $rec->userid,
            'programme' => self::category_name($rec->categoryid),
            'pending'   => format_time($pending),
            'hours'     => candidature::sla_hours(),
        ]);
        $url = new \moodle_url('/local/candidature/index.php', ['filter' => 'flagged']);
        foreach (self::capability_holders('local/candidature:oversee') as $to) {
            self::send('slaflag', $to, $subject, $body, $url);
        }
    }

    /**
     * Reviewers for a programme: those with the category capability, plus site-wide.
     *
     * @param int|null $categoryid
     * @return array
     */
    protected static function reviewers(?int $categoryid): array {
        $out = self::capability_holders('local/candidature:reviewall');
        if ($categoryid) {
            $ctx = \context_coursecat::instance($categoryid, IGNORE_MISSING);
            if ($ctx) {
                foreach (self::capability_holders('local/candidature:review', $ctx) as $id => $u) {
                    $out[$id] = $u;
                }
            }
        }
        return $out;
    }

    /**
     * Users holding a capability, keyed by id.
     *
     * @param string $capability
     * @param \context|null $context Defaults to system.
     * @return array
     */
    protected static function capability_holders(string $capability, ?\context $context = null): array {
        $users = get_users_by_capability($context ?: \context_system::instance(), $capability,
            'u.id, u.username, u.email, u.firstname, u.lastname, u.lang, u.maildisplay, u.mailformat,
             u.auth, u.suspended, u.deleted, u.emailstop, u.timezone, u.picture, u.imagealt,
             u.middlename, u.alternatename, u.firstnamephonetic, u.lastnamephonetic');
        return array_filter($users, fn($u) => empty($u->suspended) && empty($u->deleted));
    }

    /**
     * Readable category name, tolerating a deleted category.
     *
     * @param int|null $categoryid
     * @return string
     */
    protected static function category_name(?int $categoryid): string {
        global $DB;

        if (empty($categoryid)) {
            return get_string('unassigned', 'local_candidature');
        }
        $n = $DB->get_field('course_categories', 'name', ['id' => $categoryid]);
        return $n === false ? get_string('unassigned', 'local_candidature')
            : trim(format_string($n));
    }
}
