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
 * Create a share link for one question attachment.
 *
 * @package   local_fileshare
 * @copyright 2026 onlinejain.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

use local_fileshare\manager;

$qubaid = required_param('qubaid', PARAM_INT);
$slot = required_param('slot', PARAM_INT);
$pathnamehash = required_param('file', PARAM_ALPHANUM);

$attemptobj = \mod_quiz\quiz_attempt::create_from_usage_id($qubaid);
$context = context_module::instance($attemptobj->get_cmid());

require_login($attemptobj->get_course(), false, $attemptobj->get_cm());
require_capability('local/fileshare:share', $context);
$attemptobj->check_review_capability();
// check_review_capability() does not look at whose attempt this is; is_review_allowed() is the
// gate core pairs it with, including in quiz_attempt::check_file_access(). manager::create()
// re-checks this, but failing here keeps the refusal on the page rather than mid-form.
if (!$attemptobj->is_review_allowed()) {
    throw new required_capability_exception($context, 'mod/quiz:viewreports', 'nopermissions', '');
}

$url = new moodle_url('/local/fileshare/share.php',
    ['qubaid' => $qubaid, 'slot' => $slot, 'file' => $pathnamehash]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('createlink', 'local_fileshare'));
$PAGE->set_heading(format_string($attemptobj->get_quiz_name()));

$file = manager::find_attachment($qubaid, $slot, $pathnamehash);
if (!$file) {
    throw new moodle_exception('filenotfound', 'local_fileshare');
}

$form = new \local_fileshare\form\share_form($url, [
    'filename' => $file->get_filename(),
    'filesize' => $file->get_filesize(),
]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/quiz/reviewquestion.php',
        ['attempt' => $attemptobj->get_attemptid(), 'slot' => $slot]));
}

echo $OUTPUT->header();

if ($data = $form->get_data()) {
    [$share, $link] = manager::create($qubaid, $slot, $pathnamehash, [
        'expirydays' => $data->expirydays,
        'maxdownloads' => $data->maxdownloads,
        'iprestriction' => $data->iprestriction,
        'recipient' => $data->recipient,
    ]);

    echo $OUTPUT->heading(get_string('linkcreated', 'local_fileshare'), 3);
    echo html_writer::tag('p', get_string('linkcreated_desc', 'local_fileshare',
        userdate($share->validuntil)));
    echo html_writer::start_div('box generalbox p-3 mb-3', ['style' => 'word-break:break-all']);
    echo html_writer::tag('code', s($link));
    echo html_writer::end_div();
    echo $OUTPUT->notification(get_string('linkonce', 'local_fileshare'), 'warning');
    echo $OUTPUT->single_button(new moodle_url('/mod/quiz/reviewquestion.php',
        ['attempt' => $attemptobj->get_attemptid(), 'slot' => $slot]),
        get_string('backtoreview', 'local_fileshare'), 'get');
    echo html_writer::link(new moodle_url('/local/fileshare/manage.php'),
        get_string('managelinks', 'local_fileshare'), ['class' => 'd-inline-block mt-3']);
} else {
    echo $OUTPUT->heading(get_string('createlink', 'local_fileshare'), 3);
    echo $OUTPUT->notification(get_string('sharewarning', 'local_fileshare'), 'info');
    $form->display();
}

echo $OUTPUT->footer();
