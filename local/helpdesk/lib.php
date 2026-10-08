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
 * Helpers shared by the helpdesk pages.
 *
 * @package    local_helpdesk
 * @copyright  2026 onwards
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Stop a page dead when the site has not enabled the helpdesk.
 */
function local_helpdesk_require_enabled(): void {
    if (get_config('local_helpdesk', 'enabled') !== '1') {
        throw new moodle_exception('notenabled', 'local_helpdesk');
    }
}

/**
 * Presentation fields for one ticket in a list.
 *
 * @param stdClass $row
 * @return array
 */
/**
 * Short name of the course a ticket is about.
 *
 * Falls back to an empty string rather than throwing when a course has since been
 * deleted -- a ticket outlives the thing it was about.
 *
 * @param int|null $courseid
 * @return string
 */
function local_helpdesk_course_name(?int $courseid): string {
    global $DB;

    if (empty($courseid)) {
        return '';
    }
    $name = $DB->get_field('course', 'fullname', ['id' => $courseid]);
    return $name === false ? '' : format_string($name);
}

function local_helpdesk_ticket_summary(stdClass $row): array {
    $urgency = \local_helpdesk\sla::urgency($row);
    $left = \local_helpdesk\sla::time_remaining($row);

    // A ticket that has been answered has no deadline left to run.
    $running = !in_array($row->status, [\local_helpdesk\ticket::STATUS_RESOLVED,
        \local_helpdesk\ticket::STATUS_CLOSED, \local_helpdesk\ticket::STATUS_MERGED], true);

    return [
        'id'          => $row->id,
        'reference'   => $row->reference,
        'subject'     => $row->subject,
        'category'    => get_string('cat_' . $row->category, 'local_helpdesk'),
        'coursename'  => local_helpdesk_course_name($row->courseid ?? null),
        'hascourse'   => !empty($row->courseid),
        'status'      => get_string('status_' . $row->status, 'local_helpdesk'),
        'statuskey'   => $row->status,
        'channel'     => $row->channel,
        'urgency'     => $urgency,
        'isbreached'  => $urgency === 'breached',
        'timeleft'    => $running ? local_helpdesk_format_remaining($left) : '',
        'hastimeleft' => $running,
        'created'     => userdate($row->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
        'url'         => (new moodle_url('/local/helpdesk/ticket.php', ['id' => $row->id]))->out(false),
    ];
}

/**
 * Human phrasing for a countdown, or how long ago it lapsed.
 *
 * @param int $seconds
 * @return string
 */
function local_helpdesk_format_remaining(int $seconds): string {
    if ($seconds < 0) {
        return get_string('overdueby', 'local_helpdesk', format_time(abs($seconds)));
    }
    return get_string('duein', 'local_helpdesk', format_time($seconds));
}

/**
 * Presentation fields for one ticket in a staff queue.
 *
 * @param stdClass $row
 * @param array    $users  Users keyed by id, covering student, assignee and specialist.
 * @param array    $flags  Flag type strings for this ticket.
 * @param int      $viewerid
 * @return array
 */
function local_helpdesk_queue_row(stdClass $row, array $users, array $flags, int $viewerid): array {
    $name = function($id) use ($users): string {
        return $id && isset($users[$id]) ? fullname($users[$id]) : '';
    };

    return local_helpdesk_ticket_summary($row) + [
        'student'     => $name($row->userid) ?: get_string('anonymised', 'local_helpdesk'),
        'assignee'    => $name($row->assigneeid),
        'unassigned'  => empty($row->assigneeid),
        'mine'        => (int) $row->assigneeid === $viewerid,
        'forwardedto' => $name($row->forwardedto),
        'flags'       => array_map(function($type) {
            return get_string('flag_' . $type, 'local_helpdesk');
        }, array_values($flags)),
        'hasflags'    => !empty($flags),
    ];
}

/**
 * Outstanding flags for a set of tickets, keyed by ticket id.
 *
 * @param array $ticketids
 * @return array
 */
function local_helpdesk_flags(array $ticketids): array {
    global $DB;

    if (!$ticketids) {
        return [];
    }
    [$insql, $params] = $DB->get_in_or_equal($ticketids, SQL_PARAMS_NAMED);
    $rows = $DB->get_records_select('local_helpdesk_flag', "ticketid $insql", $params);

    $out = [];
    foreach ($rows as $row) {
        $out[$row->ticketid][] = $row->flagtype;
    }
    return $out;
}

/**
 * Staff a ticket may be forwarded to.
 *
 * @return array User id to full name.
 */
function local_helpdesk_specialists(?int $courseid = null): array {
    $users = get_users_by_capability(context_system::instance(), 'local/helpdesk:respondforwarded',
        'u.id, u.firstname, u.lastname, u.alternatename, u.firstnamephonetic, u.lastnamephonetic, u.middlename');

    $out = [];
    foreach ($users as $user) {
        $out[$user->id] = fullname($user);
    }
    natcasesort($out);

    if (empty($courseid)) {
        return $out;
    }

    // Anyone holding a role on the course itself, or on a category above it, is the
    // person the PRD means by "the relevant TA or Mentor/Faculty". They are offered
    // first and labelled; everyone else stays selectable, because a small site may
    // have no specialist attached to a given course and the ticket must still move.
    $attached = local_helpdesk_course_role_holders($courseid);

    $first = [];
    $rest = [];
    foreach ($out as $id => $name) {
        if (isset($attached[$id])) {
            $first[$id] = $name . ' — ' . get_string('specialist_thiscourse', 'local_helpdesk');
        } else {
            $rest[$id] = $name;
        }
    }
    return $first + $rest;
}

/**
 * User ids holding any role on a course or on a category above it.
 *
 * Deliberately excludes the system context: a site-wide role assignment says
 * nothing about which course someone teaches, and including it would mark every
 * specialist as attached to everything.
 *
 * @param int $courseid
 * @return array User id => true.
 */
function local_helpdesk_course_role_holders(int $courseid): array {
    global $DB;

    $context = context_course::instance($courseid, IGNORE_MISSING);
    if (!$context) {
        return [];
    }

    $contextids = [];
    foreach ($context->get_parent_context_ids(true) as $id) {
        $parent = context::instance_by_id($id, IGNORE_MISSING);
        if ($parent && $parent->contextlevel !== CONTEXT_SYSTEM) {
            $contextids[] = $id;
        }
    }
    if (!$contextids) {
        return [];
    }

    list($insql, $params) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx');
    $ids = $DB->get_fieldset_sql(
        "SELECT DISTINCT ra.userid FROM {role_assignments} ra WHERE ra.contextid $insql", $params);

    return array_fill_keys(array_map('intval', $ids), true);
}

/**
 * Thread messages a given viewer is allowed to see.
 *
 * Students never see internal notes, and every public message is attributed to the
 * ticket's assignee rather than whoever wrote it.
 *
 * @param stdClass $ticket
 * @param bool     $isstaff
 * @return array
 */
function local_helpdesk_thread(stdClass $ticket, bool $isstaff): array {
    global $DB, $OUTPUT;

    $messages = $DB->get_records('local_helpdesk_message', ['ticketid' => $ticket->id], 'timecreated');
    $out = [];
    foreach ($messages as $message) {
        $internal = $message->visibility === \local_helpdesk\ticket::VIS_INTERNAL;
        if ($internal && !$isstaff) {
            continue;
        }

        $showid = \local_helpdesk\ticket::presented_author($message);
        if ($internal) {
            $showid = $message->authorid;
        }
        $author = $showid ? \core_user::get_user($showid) : null;

        $out[] = [
            'id'       => $message->id,
            'internal' => $internal,
            'mine'     => $message->authorrole === 'student',
            'author'   => $author ? fullname($author) : get_string('supportteam', 'local_helpdesk'),
            'realauthor' => $isstaff && !$internal && $message->authorid != $showid
                ? fullname(\core_user::get_user($message->authorid)) : null,
            'body'     => format_text($message->message, $message->messageformat),
            'time'     => userdate($message->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
        ];
    }
    return $out;
}
