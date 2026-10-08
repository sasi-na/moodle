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
 * Raise a support ticket.
 *
 * @package    local_helpdesk
 * @copyright  2026 onwards
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use local_helpdesk\form\raise_form;
use local_helpdesk\ticket;

require_login();
local_helpdesk_require_enabled();

$context = context_system::instance();
require_capability('local/helpdesk:raise', $context);

$PAGE->set_url(new moodle_url('/local/helpdesk/raise.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('raisetitle', 'local_helpdesk'));
$PAGE->set_heading(get_string('raisetitle', 'local_helpdesk'));

$form = new raise_form();

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/helpdesk/mytickets.php'));
}

if ($data = $form->get_data()) {
    $ticket = ticket::create($USER->id, $data->category, $data->subject,
        $data->description, 'lms', FORMAT_PLAIN,
        empty($data->courseid) ? null : (int) $data->courseid);

    file_save_draft_area_files($data->attachments, $context->id, 'local_helpdesk', 'attachment',
        $ticket->first_message_id(), raise_form::attachment_options());

    redirect(new moodle_url('/local/helpdesk/ticket.php', ['id' => $ticket->get_record()->id, 'new' => 1]));
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_helpdesk/raise', [
    'formhtml'    => $form->render(),
    'maxlength'   => raise_form::MAX_DESCRIPTION,
    'trackurl'    => (new moodle_url('/local/helpdesk/mytickets.php'))->out(false),
    'supportemail' => get_config('local_helpdesk', 'supportemail'),
    'slackurl'    => get_config('local_helpdesk', 'slackurl'),
    'responsehours' => \local_helpdesk\sla::first_response_hours(),
]);
echo $OUTPUT->footer();
