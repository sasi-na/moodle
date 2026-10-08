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
 * A student's own tickets.
 *
 * @package    local_helpdesk
 * @copyright  2026 onwards
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
local_helpdesk_require_enabled();

$context = context_system::instance();
require_capability('local/helpdesk:raise', $context);

$PAGE->set_url(new moodle_url('/local/helpdesk/mytickets.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('mytickets', 'local_helpdesk'));
$PAGE->set_heading(get_string('mytickets', 'local_helpdesk'));

$rows = $DB->get_records('local_helpdesk_ticket', ['userid' => $USER->id], 'timecreated DESC');

$open = $closed = [];
foreach ($rows as $row) {
    $item = local_helpdesk_ticket_summary($row);
    if (in_array($row->status, ['resolved', 'closed', 'merged'], true)) {
        $closed[] = $item;
    } else {
        $open[] = $item;
    }
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_helpdesk/mytickets', [
    'open'      => $open,
    'closed'    => $closed,
    'hasopen'   => (bool) $open,
    'hasclosed' => (bool) $closed,
    'hasany'    => (bool) $rows,
    'raiseurl'  => (new moodle_url('/local/helpdesk/raise.php'))->out(false),
]);
echo $OUTPUT->footer();
