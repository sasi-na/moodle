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
 * Create share links for every attachment in a quiz and export them as a spreadsheet.
 *
 * @package   local_fileshare
 * @copyright 2026 onlinejain.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

use local_fileshare\manager;

$cmid = required_param('cmid', PARAM_INT);
$export = optional_param('export', 0, PARAM_BOOL);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'quiz');
$context = context_module::instance($cm->id);

require_login($course, false, $cm);
require_capability('local/fileshare:share', $context);
require_capability('mod/quiz:viewreports', $context);

$url = new moodle_url('/local/fileshare/quiz.php', ['cmid' => $cmid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('bulktitle', 'local_fileshare'));
$PAGE->set_heading(format_string($course->fullname));

/**
 * Every attachment in this quiz that the current user is actually allowed to review.
 *
 * Group filtering is not decoration: under SEPARATEGROUPS, quiz_attempt::is_review_allowed()
 * refuses a teacher access to another group's attempt, and core's own quiz reports filter the
 * same way. Listing every attempt here would disclose the other groups' students and filenames,
 * and would hand out the pathnamehash values needed to ask for their files.
 *
 * @param \cm_info $cm course module
 * @param \context $context module context
 * @return array of stdClass rows
 */
function local_fileshare_quiz_attachments(\cm_info $cm, \context $context): array {
    global $DB;

    $join = '';
    $params = ['ctx' => $context->id, 'quizid' => (int) $cm->instance];

    if (groups_get_activity_groupmode($cm) == SEPARATEGROUPS
            && !has_capability('moodle/site:accessallgroups', $context)) {
        $allowed = array_keys(groups_get_activity_allowed_groups($cm));
        if (!$allowed) {
            return [];
        }
        [$gsql, $gparams] = $DB->get_in_or_equal($allowed, SQL_PARAMS_NAMED, 'grp');
        $join = "JOIN {groups_members} gm ON gm.userid = qz.userid AND gm.groupid $gsql";
        $params += $gparams;
    }

    // DISTINCT because a student in two of the reviewer's groups would otherwise appear twice.
    $sql = "SELECT DISTINCT f.id, f.pathnamehash, f.filename, f.filesize, f.mimetype,
                   qz.id AS attemptid, qz.uniqueid AS qubaid, qz.userid,
                   qa.slot, u.firstname, u.lastname, u.email
              FROM {files} f
              JOIN {question_attempt_steps} qas ON qas.id = f.itemid
              JOIN {question_attempts} qa ON qa.id = qas.questionattemptid
              JOIN {quiz_attempts} qz ON qz.uniqueid = qa.questionusageid
              JOIN {user} u ON u.id = qz.userid
              $join
             WHERE f.component = 'question'
               AND f.filearea = 'response_attachments'
               AND f.filename <> '.'
               AND f.contextid = :ctx
               AND qz.quiz = :quizid
          ORDER BY u.lastname, u.firstname, f.filename";

    return $DB->get_records_sql($sql, $params);
}

$rows = local_fileshare_quiz_attachments($cm, $context);

if ($export && confirm_sesskey()) {
    if (!$rows) {
        redirect($url, get_string('nolinks', 'local_fileshare'));
    }
    $days = optional_param('expirydays', manager::default_expiry_days(), PARAM_INT);
    $maxdownloads = optional_param('maxdownloads', 0, PARAM_INT);
    $recipient = trim(optional_param('recipient', '', PARAM_TEXT));
    if ($recipient === '') {
        // Enforced server-side too: the HTML "required" attribute is only a courtesy, and a share
        // with no recorded recipient defeats the point of the audit trail.
        redirect($url, get_string('recipientrequired', 'local_fileshare'), null,
            \core\output\notification::NOTIFY_ERROR);
    }

    // Only include the email column if this context is configured to show it as an identity
    // field, the way core's quiz reports build theirs. The spreadsheet is made to be sent
    // outside the institution, so a student's address should not ride along by default.
    $showemail = in_array('email', \core_user\fields::get_identity_fields($context, false), true);

    $links = [];
    $skipped = 0;
    foreach ($rows as $row) {
        try {
            [$share, $link] = manager::create((int) $row->qubaid, (int) $row->slot, $row->pathnamehash, [
                'expirydays' => $days,
                'maxdownloads' => $maxdownloads,
                'recipient' => $recipient,
            ]);
            $links[] = [
                fullname($row),
                $showemail ? $row->email : '',
                $row->attemptid,
                $row->filename,
                display_size((int) $row->filesize),
                $link,
                userdate($share->validuntil, get_string('strftimedatetimeshort', 'core_langconfig')),
            ];
        } catch (\Throwable $e) {
            // A row can legitimately fail (is_review_allowed() refusing an attempt), so count
            // them and say so - silently short spreadsheets read as "everything was exported".
            $skipped++;
            debugging('local_fileshare bulk: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    if (!$links) {
        redirect($url, get_string('nolinkscreated', 'local_fileshare'), null,
            \core\output\notification::NOTIFY_ERROR);
    }

    require_once($CFG->libdir . '/phpspreadsheet/vendor/autoload.php');

    $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setTitle('Share links');
    $sheet->fromArray([[
        get_string('student', 'local_fileshare'), 'Email', 'Attempt',
        get_string('file', 'local_fileshare'), 'Size', 'Link', get_string('expires', 'local_fileshare'),
    ]], null, 'A1');

    // Bound as explicit strings rather than fromArray(): PhpSpreadsheet's default binder types
    // any cell starting with "=" as a formula, and student names reach this sheet unfiltered.
    $rownum = 2;
    foreach ($links as $link) {
        $col = 'A';
        foreach ($link as $value) {
            $sheet->setCellValueExplicit($col . $rownum, (string) $value,
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $col++;
        }
        $rownum++;
    }
    if ($skipped) {
        $sheet->setCellValueExplicit('A' . ($rownum + 1),
            get_string('bulkskipped', 'local_fileshare', $skipped),
            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    }
    $sheet->getStyle('A1:G1')->getFont()->setBold(true);
    foreach (range('A', 'G') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    $filename = clean_filename('share-links-' . $cm->instance . '-' . date('Ymd-Hi')) . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, no-store, max-age=0');
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save('php://output');
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('bulktitle', 'local_fileshare'), 3);

if (!$rows) {
    echo $OUTPUT->notification(get_string('noattachments', 'local_fileshare'), 'info');
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->notification(get_string('bulkwarning', 'local_fileshare', count($rows)), 'warning');

$table = new html_table();
$table->head = [get_string('student', 'local_fileshare'), 'Attempt', get_string('file', 'local_fileshare'), 'Size'];
$table->attributes['class'] = 'generaltable';
foreach ($rows as $row) {
    $table->data[] = [fullname($row), $row->attemptid, s($row->filename), display_size((int) $row->filesize)];
}
echo html_writer::table($table);

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false), 'class' => 'mt-3']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'export', 'value' => 1]);
echo html_writer::div(
    html_writer::label(get_string('expiry', 'local_fileshare'), 'expirydays') . ' ' .
    html_writer::empty_tag('input', ['type' => 'number', 'id' => 'expirydays', 'name' => 'expirydays',
        'value' => manager::default_expiry_days(), 'min' => 1, 'max' => manager::max_expiry_days(),
        'class' => 'form-control d-inline-block w-auto']), 'mb-2');
echo html_writer::div(
    html_writer::label(get_string('maxdownloads', 'local_fileshare'), 'maxdownloads') . ' ' .
    html_writer::empty_tag('input', ['type' => 'number', 'id' => 'maxdownloads', 'name' => 'maxdownloads',
        'value' => 0, 'min' => 0, 'class' => 'form-control d-inline-block w-auto']), 'mb-2');
echo html_writer::div(
    html_writer::label(get_string('recipient', 'local_fileshare'), 'recipient') . ' ' .
    html_writer::empty_tag('input', ['type' => 'text', 'id' => 'recipient', 'name' => 'recipient',
        'required' => 'required', 'class' => 'form-control d-inline-block w-auto']), 'mb-3');
echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-primary',
    'value' => get_string('bulkexport', 'local_fileshare')]);
echo html_writer::end_tag('form');

// Revoking otherwise means navigating to Site administration.
echo html_writer::link(new moodle_url('/local/fileshare/manage.php'),
    get_string('managelinks', 'local_fileshare'), ['class' => 'd-inline-block mt-3']);

echo $OUTPUT->footer();
