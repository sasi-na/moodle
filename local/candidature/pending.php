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
 * Holding page shown to a candidate awaiting or refused a decision.
 *
 * @package   local_candidature
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use local_candidature\candidature;

require_login(null, false);

$context = context_system::instance();
$PAGE->set_url(new moodle_url('/local/candidature/pending.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pendingtitle', 'local_candidature'));
$PAGE->set_heading(get_string('pendingtitle', 'local_candidature'));

$rec = candidature::for_user((int) $USER->id);

// Anyone without a candidature, or already approved, has no business here.
if (!$rec || $rec->status === candidature::APPROVED) {
    redirect(new moodle_url('/my/'));
}

$rejected = $rec->status === candidature::REJECTED;
$supporturl = file_exists($CFG->dirroot . '/local/helpdesk/raise.php')
    ? (new moodle_url('/local/helpdesk/raise.php'))->out(false) : '';

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_candidature/pending', [
    'rejected'    => $rejected,
    'firstname'   => $USER->firstname,
    'submitted'   => userdate($rec->submittedtime, get_string('strftimedaydatetime', 'langconfig')),
    'hasreason'   => $rejected && trim((string) $rec->reason) !== '',
    'reason'      => format_text((string) $rec->reason, FORMAT_PLAIN),
    'hours'       => candidature::sla_hours(),
    'profileurl'  => (new moodle_url('/user/edit.php'))->out(false),
    'hassupport'  => $supporturl !== '',
    'supporturl'  => $supporturl,
    'logouturl'   => (new moodle_url('/login/logout.php', ['sesskey' => sesskey()]))->out(false),
]);
echo $OUTPUT->footer();
