<?php
/**
 * Responses report for a single registration form: one column per question.
 *
 * @package   local_eventreg
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use local_eventreg\manager;
use local_eventreg\form_builder;

$formid = required_param('formid', PARAM_INT);

require_login();
$context = context_system::instance();
if (!local_eventreg_can_view_reports()) {
    throw new required_capability_exception($context, 'local/eventreg:viewreports', 'nopermissions', '');
}

$form = $DB->get_record('local_eventreg_forms', ['id' => $formid], '*', MUST_EXIST);
$ismanager = has_capability('local/eventreg:manage', $context);
$backurl = new moodle_url($ismanager ? '/local/eventreg/manage.php' : '/my/');

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/eventreg/report.php', ['formid' => $formid]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('report', 'local_eventreg', format_string($form->title)));
$PAGE->set_heading(get_string('reportresults', 'local_eventreg'));
$PAGE->navbar->add(get_string('manageforms', 'local_eventreg'), $backurl);
$PAGE->navbar->add(format_string(shorten_text($form->title, 40)));

// answerable(): a description field holds no answer, so it must not become an
// always-empty report column.
$fields = form_builder::answerable(
    array_values($DB->get_records('local_eventreg_fields', ['formid' => $formid], 'sortorder ASC, id ASC')));

// Submissions with user identity + batch.
$namefields = 'u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename';
// Mobile comes from the custom `mobile` profile field, falling back to the core
// "Mobile phone" field (user.phone2) under the Optional section.
$sql = "SELECT s.id, s.userid, s.timecreated, u.email, u.idnumber, $namefields,
               batch.data AS batch, COALESCE(NULLIF(mob.data, ''), u.phone2) AS mobile
          FROM {local_eventreg_submissions} s
          JOIN {user} u ON u.id = s.userid
     LEFT JOIN {user_info_field} uif ON uif.shortname = :sn
     LEFT JOIN {user_info_data} batch ON batch.userid = u.id AND batch.fieldid = uif.id
     LEFT JOIN {user_info_field} uifm ON uifm.shortname = :snm
     LEFT JOIN {user_info_data} mob ON mob.userid = u.id AND mob.fieldid = uifm.id
         WHERE s.formid = :formid
      ORDER BY s.timecreated ASC";
$submissions = $DB->get_records_sql($sql,
    ['sn' => manager::BATCH_FIELD, 'snm' => manager::MOBILE_FIELD, 'formid' => $formid]);

// Answers keyed by submissionid => fieldid => record (the answer id is needed to
// build download links for file answers).
$answermap = [];
$ars = $DB->get_records_sql(
    "SELECT a.id, a.submissionid, a.fieldid, a.value
       FROM {local_eventreg_answers} a
       JOIN {local_eventreg_submissions} s ON s.id = a.submissionid
      WHERE s.formid = :formid", ['formid' => $formid]);
foreach ($ars as $a) {
    $answermap[$a->submissionid][$a->fieldid] = $a;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($form->title));

// Summary line.
$total = count($submissions);
$summary = manager::audience_label($form)
    . ' &nbsp;|&nbsp; ' . get_string('totalresponses', 'local_eventreg') . ': '
    . html_writer::tag('strong', $total);
$size = manager::audience_size($form);
if ($size > 0) {
    $rate = round($total * 100 / $size);
    $summary .= ' &nbsp;|&nbsp; ' . $rate . '% (' . $total . '/' . $size . ')';
}
echo html_writer::div($summary, 'mb-3');

// Download buttons.
$default = get_config('local_eventreg', 'defaultformat') ?: 'csv';
$csvbtn = $OUTPUT->single_button(
    new moodle_url('/local/eventreg/export.php', ['formid' => $formid, 'format' => 'csv', 'sesskey' => sesskey()]),
    get_string('downloadcsv', 'local_eventreg'), 'get');
$xlsbtn = $OUTPUT->single_button(
    new moodle_url('/local/eventreg/export.php', ['formid' => $formid, 'format' => 'excel', 'sesskey' => sesskey()]),
    get_string('downloadexcel', 'local_eventreg'), 'get');
$buttons = $default === 'excel' ? $xlsbtn . ' ' . $csvbtn : $csvbtn . ' ' . $xlsbtn;

// Only offer the ZIP when there is something in it, and say how big it is — the
// archive streams in one request, so the admin should know what they are asking for.
$attachments = manager::attachment_stats($formid);
if ($attachments->numfiles > 0) {
    $buttons .= ' ' . $OUTPUT->single_button(
        new moodle_url('/local/eventreg/zipfiles.php', ['formid' => $formid, 'sesskey' => sesskey()]),
        get_string('downloadattachments', 'local_eventreg', (object) [
            'count' => $attachments->numfiles,
            'size'  => display_size($attachments->totalbytes),
        ]), 'get');
}

echo html_writer::div($buttons, 'mb-3 d-flex align-items-center');

if (!$submissions) {
    echo $OUTPUT->notification(get_string('noresponses', 'local_eventreg'), 'info');
} else {
    $table = new html_table();
    $head = [
        get_string('col_fullname', 'local_eventreg'),
        get_string('col_email', 'local_eventreg'),
        get_string('col_mobile', 'local_eventreg'),
        get_string('col_idnumber', 'local_eventreg'),
        get_string('col_batch', 'local_eventreg'),
        get_string('col_time', 'local_eventreg'),
    ];
    foreach ($fields as $field) {
        $head[] = form_builder::plain_label($field);
    }
    $table->head = $head;
    $table->attributes['class'] = 'generaltable';

    foreach ($submissions as $s) {
        $row = [
            fullname($s),
            s($s->email),
            s((string) $s->mobile),
            s($s->idnumber),
            s((string) $s->batch),
            userdate($s->timecreated),
        ];
        foreach ($fields as $field) {
            $answer = $answermap[$s->id][$field->id] ?? null;
            if ($answer && form_builder::is_file_type($field->type)) {
                $row[] = form_builder::file_links((int) $answer->id, $answer->value);
            } else {
                $row[] = s(form_builder::format_value($field, $answer->value ?? ''));
            }
        }
        $table->data[] = $row;
    }
    echo html_writer::table($table);
}

echo html_writer::div(
    html_writer::link($backurl, get_string('back', 'local_eventreg'), ['class' => 'btn btn-link']),
    'mt-3');

echo $OUTPUT->footer();
