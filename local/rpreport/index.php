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
 * On-demand page to email the report now, or download it directly.
 *
 * @package   local_rpreport
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
// send_file() lives here, and lib/setup.php only pulls filelib in when $CFG->proxyfixunsafe is set.
require_once($CFG->libdir . '/filelib.php');

use local_rpreport\report;

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$action = optional_param('action', '', PARAM_ALPHA);

// Building the workbook scans every research project course and all enrolled
// students, so give the request room to breathe.
if ($action === 'download' || $action === 'send') {
    \core_php_time_limit::raise(600);
    raise_memory_limit(MEMORY_HUGE);
}

$PAGE->set_url(new moodle_url('/local/rpreport/index.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('runpage', 'local_rpreport'));
$PAGE->set_heading(get_string('runpage', 'local_rpreport'));

// Download the workbook straight to the browser.
if ($action === 'download') {
    require_sesskey();
    $dir = make_request_directory();
    $filename = 'Research Project Submission Status ' . date('Y-m-d') . '.xlsx';
    $filepath = $dir . '/' . clean_filename($filename);
    report::build_xlsx($filepath);
    send_file($filepath, $filename, 0, 0, false, true,
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    // send_file() exits.
}

// Generate and email now.
if ($action === 'send' && confirm_sesskey()) {
    $result = report::generate_and_send();
    if (empty($result['recipients'])) {
        \core\notification::error(get_string('norecipients', 'local_rpreport'));
    } else {
        \core\notification::success(get_string('sentok', 'local_rpreport',
            (object) ['sent' => $result['sent'], 'total' => count($result['recipients'])]));
        if (!empty($result['failed'])) {
            \core\notification::warning(get_string('sentfailed', 'local_rpreport',
                implode(', ', $result['failed'])));
        }
    }
}

echo $OUTPUT->header();

$recipients = report::get_recipients();
if (empty($recipients)) {
    echo $OUTPUT->notification(get_string('norecipients_config', 'local_rpreport',
        (new moodle_url('/admin/settings.php', ['section' => 'local_rpreport_settings']))->out()),
        \core\output\notification::NOTIFY_WARNING);
} else {
    echo html_writer::tag('p', get_string('currentrecipients', 'local_rpreport',
        s(implode(', ', $recipients))));
}

$sendurl = new moodle_url('/local/rpreport/index.php', ['action' => 'send', 'sesskey' => sesskey()]);
$downloadurl = new moodle_url('/local/rpreport/index.php', ['action' => 'download', 'sesskey' => sesskey()]);

echo html_writer::start_div('mt-3');
echo $OUTPUT->single_button($sendurl, get_string('sendnow', 'local_rpreport'), 'post',
    ['disabled' => empty($recipients)]);
echo ' ';
echo $OUTPUT->single_button($downloadurl, get_string('downloadnow', 'local_rpreport'), 'get');
echo html_writer::end_div();

echo $OUTPUT->footer();
