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
 * Requests forwarded to a teaching assistant or faculty member.
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
require_capability('local/helpdesk:respondforwarded', $context);

$PAGE->set_url(new moodle_url('/local/helpdesk/forwarded.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('forwardedtome', 'local_helpdesk'));
$PAGE->set_heading(get_string('forwardedtome', 'local_helpdesk'));

$waiting = $DB->get_records('local_helpdesk_ticket',
    ['forwardedto' => $USER->id, 'status' => \local_helpdesk\ticket::STATUS_ESCALATED],
    'forwardedtime ASC');

// Anything this person has already written on, so the history does not vanish when a
// ticket goes back to the queue.
$answered = $DB->get_records_sql(
    "SELECT t.*
       FROM {local_helpdesk_ticket} t
      WHERE t.id IN (SELECT DISTINCT m.ticketid FROM {local_helpdesk_message} m WHERE m.authorid = :me)
        AND (t.forwardedto IS NULL OR t.forwardedto <> :me2 OR t.status <> :escalated)
   ORDER BY t.timemodified DESC",
    ['me' => $USER->id, 'me2' => $USER->id, 'escalated' => \local_helpdesk\ticket::STATUS_ESCALATED],
    0, 50);

$records = $waiting + $answered;
$userids = [];
foreach ($records as $record) {
    foreach ([$record->userid, $record->assigneeid, $record->forwardedto] as $id) {
        if ($id) {
            $userids[(int) $id] = true;
        }
    }
}
$users = $userids ? $DB->get_records_list('user', 'id', array_keys($userids)) : [];
$flags = local_helpdesk_flags(array_keys($records));

$build = function(array $set) use ($users, $flags, $USER): array {
    $out = [];
    foreach ($set as $record) {
        $out[] = local_helpdesk_queue_row($record, $users, $flags[$record->id] ?? [], (int) $USER->id);
    }
    return $out;
};

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_helpdesk/forwarded', [
    'waiting'     => $build($waiting),
    'haswaiting'  => !empty($waiting),
    'answered'    => $build($answered),
    'hasanswered' => !empty($answered),
    'hasany'      => !empty($records),
    'hours'       => \local_helpdesk\sla::specialist_hours(),
]);
echo $OUTPUT->footer();
