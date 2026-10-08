<?php
/**
 * Export a registration form's responses as CSV or Excel, one row per learner
 * and one column per question.
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
$format = optional_param('format', 'csv', PARAM_ALPHA);
if (!in_array($format, ['csv', 'excel', 'ods', 'json'], true)) {
    $format = 'csv';
}

require_login();
if (!local_eventreg_can_view_reports()) {
    throw new required_capability_exception(context_system::instance(),
        'local/eventreg:viewreports', 'nopermissions', '');
}
require_sesskey();

$form = $DB->get_record('local_eventreg_forms', ['id' => $formid], '*', MUST_EXIST);
// answerable(): a description field holds no answer, so it gets no CSV column.
$fields = form_builder::answerable(
    array_values($DB->get_records('local_eventreg_fields', ['formid' => $formid], 'sortorder ASC, id ASC')));

// Column definitions: fixed learner columns + one per field.
$columns = [
    'fullname' => get_string('col_fullname', 'local_eventreg'),
    'email'    => get_string('col_email', 'local_eventreg'),
    'mobile'   => get_string('col_mobile', 'local_eventreg'),
    'idnumber' => get_string('col_idnumber', 'local_eventreg'),
    'batch'    => get_string('col_batch', 'local_eventreg'),
    'time'     => get_string('col_time', 'local_eventreg'),
];
foreach ($fields as $field) {
    $columns['f' . $field->id] = form_builder::plain_label($field);
}

// Pre-load all answers (submissionid => fieldid => value). Registrations are
// bounded, so one array keeps the export streaming without a per-row query.
$answermap = [];
$ars = $DB->get_records_sql(
    "SELECT a.id, a.submissionid, a.fieldid, a.value
       FROM {local_eventreg_answers} a
       JOIN {local_eventreg_submissions} s ON s.id = a.submissionid
      WHERE s.formid = :formid", ['formid' => $formid]);
foreach ($ars as $a) {
    $answermap[$a->submissionid][$a->fieldid] = $a;
}
unset($ars);

// Fields by id for the callback's per-type formatting.
$fieldsbyid = [];
foreach ($fields as $field) {
    $fieldsbyid[$field->id] = $field;
}

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
$rs = $DB->get_recordset_sql($sql,
    ['sn' => manager::BATCH_FIELD, 'snm' => manager::MOBILE_FIELD, 'formid' => $formid]);

$callback = function($record) use ($fieldsbyid, $answermap) {
    $row = [
        'fullname' => fullname($record),
        'email'    => $record->email,
        'mobile'   => (string) $record->mobile,
        'idnumber' => $record->idnumber,
        'batch'    => (string) $record->batch,
        'time'     => userdate($record->timecreated),
    ];
    foreach ($fieldsbyid as $fid => $field) {
        $answer = $answermap[$record->id][$fid] ?? null;
        if ($answer && form_builder::is_file_type($field->type)) {
            // Filename plus its download URL, so the attachment is reachable from
            // the spreadsheet.
            $row['f' . $fid] = form_builder::file_export_value((int) $answer->id, $answer->value);
        } else {
            $row['f' . $fid] = form_builder::format_value($field, $answer->value ?? '');
        }
    }
    return $row;
};

\core\dataformat::download_data(
    'registrations-' . $formid, $format, $columns, $rs, $callback);
$rs->close();
