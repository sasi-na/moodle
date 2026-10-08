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
 * Message providers for local_helpdesk.
 *
 * Student-facing providers carry no capability: the recipient is the person who
 * raised the ticket. Staff-facing providers are gated on the capability that
 * defines the audience, so a role gains the alerts by gaining the capability.
 *
 * @package   local_helpdesk
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$messageproviders = [

    // To the student who raised it: reference number, TAT and who will respond.
    'ticketcreated' => [
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],

    // To the student: a public reply landed on their ticket.
    'ticketreplied' => [
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],

    // To the student: status moved to resolved or closed.
    'ticketstatus' => [
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],

    // To everyone who works the queue.
    'ticketnew' => [
        'capability' => 'local/helpdesk:manage',
    ],

    // To the TA or faculty member a ticket has just been forwarded to.
    'ticketforwarded' => [
        'capability' => 'local/helpdesk:respondforwarded',
    ],

    // To oversight: an SLA flag was raised and nobody has acknowledged it.
    'slaflag' => [
        'capability' => 'local/helpdesk:oversee',
    ],
];
