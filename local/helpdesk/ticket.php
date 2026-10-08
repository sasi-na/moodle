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
 * One ticket thread.
 *
 * @package    local_helpdesk
 * @copyright  2026 onwards
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use local_helpdesk\ticket;

require_login();
local_helpdesk_require_enabled();

$id = required_param('id', PARAM_INT);
$isnew = optional_param('new', 0, PARAM_BOOL);

$context = context_system::instance();
$record = $DB->get_record('local_helpdesk_ticket', ['id' => $id], '*', MUST_EXIST);

$canmanage = has_capability('local/helpdesk:manage', $context);

// A specialist reaches a ticket because it was forwarded to them, or because they have
// already written on it. The capability alone is not a key to the whole queue.
$isspecialist = !$canmanage
    && has_capability('local/helpdesk:respondforwarded', $context)
    && ((int) $record->forwardedto === (int) $USER->id
        || $DB->record_exists('local_helpdesk_message', ['ticketid' => $id, 'authorid' => $USER->id]));

$isstaff = $canmanage || $isspecialist;
$isowner = (int) $record->userid === (int) $USER->id;

if (!$isstaff && !$isowner) {
    throw new moodle_exception('nopermissions', 'error', '', 'view this ticket');
}

$PAGE->set_url(new moodle_url('/local/helpdesk/ticket.php', ['id' => $id]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title($record->reference);
$PAGE->set_heading($record->subject);

$obj = new ticket($record);

if ($reply = optional_param('reply', '', PARAM_TEXT)) {
    require_sesskey();
    if ($isstaff) {
        $internal = optional_param('internal', 0, PARAM_BOOL);
        $obj->add_message($USER->id, $reply,
            $internal ? ticket::VIS_INTERNAL : ticket::VIS_PUBLIC,
            $canmanage ? 'pm' : 'ta', FORMAT_PLAIN);
    } else {
        $obj->add_message($USER->id, $reply, ticket::VIS_PUBLIC, 'student', FORMAT_PLAIN);
        if (in_array($record->status, [ticket::STATUS_RESOLVED, ticket::STATUS_CLOSED], true)) {
            $obj->reopen($USER->id);
        }
    }
    redirect($PAGE->url);
}

if ($action = optional_param('action', '', PARAM_ALPHA)) {
    require_sesskey();
    if (!$isstaff) {
        throw new moodle_exception('nopermissions', 'error', '', 'act on this ticket');
    }

    switch ($action) {
        case 'take':
            require_capability('local/helpdesk:manage', $context);
            $obj->assign($USER->id);
            break;

        case 'forward':
            require_capability('local/helpdesk:manage', $context);
            $to = required_param('specialist', PARAM_INT);
            if (!array_key_exists($to, local_helpdesk_specialists((int) ($record->courseid ?? 0)))) {
                throw new moodle_exception('nosuchspecialist', 'local_helpdesk');
            }
            $obj->forward($to, optional_param('note', '', PARAM_TEXT), $USER->id);
            break;

        case 'returnqueue':
            $obj->return_to_queue(optional_param('note', '', PARAM_TEXT), $USER->id);
            break;

        case 'resolve':
            $obj->resolve($USER->id);
            break;

        case 'close':
            require_capability('local/helpdesk:manage', $context);
            $obj->close($USER->id);
            break;

        case 'reopen':
            require_capability('local/helpdesk:manage', $context);
            $obj->reopen($USER->id);
            break;

        case 'hold':
        case 'release':
            require_capability('local/helpdesk:setlegalhold', $context);
            $obj->set_legal_hold($action === 'hold', $USER->id);
            break;
    }
    redirect($PAGE->url);
}

$specialists = [];
if ($canmanage) {
    foreach (local_helpdesk_specialists((int) ($record->courseid ?? 0)) as $userid => $name) {
        $specialists[] = ['value' => $userid, 'label' => $name,
                          'selected' => (int) $record->forwardedto === (int) $userid];
    }
}

$open = !in_array($record->status, [ticket::STATUS_RESOLVED, ticket::STATUS_CLOSED,
                                    ticket::STATUS_MERGED], true);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_helpdesk/ticket', [
    'ticket'    => local_helpdesk_ticket_summary($record),
    'messages'  => local_helpdesk_thread($record, $isstaff),
    'isstaff'   => $isstaff,
    'canmanage' => $canmanage,
    'isnew'     => $isnew,
    'created'   => get_string('ticketcreated_detail', 'local_helpdesk', (object) [
        'reference' => $record->reference,
        'hours'     => \local_helpdesk\sla::first_response_hours(),
    ]),
    'canreply'  => $record->status !== ticket::STATUS_CLOSED,
    'actionurl' => $PAGE->url->out(false),
    'sesskey'   => sesskey(),
    'backurl'   => $isstaff
        ? (new moodle_url($canmanage ? '/local/helpdesk/queue.php' : '/local/helpdesk/forwarded.php'))->out(false)
        : (new moodle_url('/local/helpdesk/mytickets.php'))->out(false),
    'backlabel' => $isstaff
        ? get_string($canmanage ? 'queue' : 'forwardedtome', 'local_helpdesk')
        : get_string('mytickets', 'local_helpdesk'),
    'student'   => $record->userid ? fullname(\core_user::get_user($record->userid)) : '',
    'assignee'  => $record->assigneeid ? fullname(\core_user::get_user($record->assigneeid)) : '',
    'unassigned' => empty($record->assigneeid),
    'specialists' => $specialists,
    'hasspecialists' => !empty($specialists),
    'isforwarded' => !empty($record->forwardedto),
    'forwardedto' => $record->forwardedto ? fullname(\core_user::get_user($record->forwardedto)) : '',
    'isopen'    => $open,
    'canhold'   => has_capability('local/helpdesk:setlegalhold', $context),
    'onhold'    => !empty($record->legalhold),
]);
echo $OUTPUT->footer();
