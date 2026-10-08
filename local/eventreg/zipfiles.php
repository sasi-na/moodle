<?php
/**
 * Streams every attachment on a registration form as one ZIP, one folder per
 * learner. Nothing is buffered: the archive is written straight to the client
 * (same approach as mod_assign's "download all submissions").
 *
 * @package   local_eventreg
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use core_files\archive_writer;
use local_eventreg\form_builder;

$formid = required_param('formid', PARAM_INT);

require_login();
$context = context_system::instance();
if (!local_eventreg_can_view_reports()) {
    throw new required_capability_exception($context, 'local/eventreg:viewreports', 'nopermissions', '');
}
require_sesskey();

$form = $DB->get_record('local_eventreg_forms', ['id' => $formid], '*', MUST_EXIST);
$reporturl = new moodle_url('/local/eventreg/report.php', ['formid' => $formid]);

// Only the file questions matter here.
$filefields = [];
foreach ($DB->get_records('local_eventreg_fields', ['formid' => $formid], 'sortorder ASC, id ASC') as $field) {
    if (form_builder::is_file_type($field->type)) {
        $filefields[$field->id] = $field;
    }
}
if (!$filefields) {
    redirect($reporturl, get_string('nofilefields', 'local_eventreg'), null, \core\output\notification::NOTIFY_INFO);
}

// One row per stored file, with the learner it belongs to. Directory records
// (filename '.') are skipped.
list($insql, $params) = $DB->get_in_or_equal(array_keys($filefields), SQL_PARAMS_NAMED);
$namefields = 'u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename';
$sql = "SELECT f.id, a.fieldid, s.userid, u.idnumber, $namefields
          FROM {files} f
          JOIN {local_eventreg_answers} a ON a.id = f.itemid
          JOIN {local_eventreg_submissions} s ON s.id = a.submissionid
          JOIN {user} u ON u.id = s.userid
         WHERE f.component = :component
           AND f.filearea = :filearea
           AND f.filename <> '.'
           AND s.formid = :formid
           AND a.fieldid $insql
      ORDER BY u.lastname ASC, u.firstname ASC, s.id ASC, a.fieldid ASC, f.filename ASC";
$params['component'] = form_builder::FILE_COMPONENT;
$params['filearea'] = form_builder::FILE_AREA;
$params['formid'] = $formid;
$records = $DB->get_records_sql($sql, $params);

if (!$records) {
    redirect($reporturl, get_string('noattachments', 'local_eventreg'), null, \core\output\notification::NOTIFY_INFO);
}

// Build path-in-zip => stored_file. The question name only becomes a subfolder
// when the form asks for more than one file, otherwise it is just noise.
$fs = get_file_storage();
$usequestionfolder = count($filefields) > 1;
$folders = [];
$filesforzipping = [];

foreach ($records as $record) {
    $file = $fs->get_file_by_id($record->id);
    if (!$file || $file->is_directory()) {
        continue;
    }

    // One folder per learner, made unique if two learners clean down to the same
    // name (same name and no ID number).
    if (!isset($folders[$record->userid])) {
        $base = fullname($record) . '_' . ($record->idnumber !== '' ? $record->idnumber : 'id' . $record->userid);
        $folder = clean_filename($base);
        if (in_array($folder, $folders, true)) {
            $folder .= '_u' . $record->userid;
        }
        $folders[$record->userid] = $folder;
    }

    $path = $folders[$record->userid];
    if ($usequestionfolder) {
        $label = format_string($filefields[$record->fieldid]->label);
        $path .= '/' . clean_filename(shorten_text($label, 40, true, ''));
    }
    $path .= $file->get_filepath() . $file->get_filename();

    $filesforzipping[clean_param($path, PARAM_PATH)] = $file;
}

if (!$filesforzipping) {
    redirect($reporturl, get_string('noattachments', 'local_eventreg'), null, \core\output\notification::NOTIFY_INFO);
}

$zipname = clean_filename('attachments-' . $formid . '-' . shorten_text(format_string($form->title), 40, true, '') . '.zip');

// Streaming the archive can take a while on a large event; let go of the session
// so the learner-facing site stays responsive for this admin.
\core\session\manager::write_close();

$zipwriter = archive_writer::get_stream_writer($zipname, archive_writer::ZIP_WRITER);
foreach ($filesforzipping as $pathinzip => $file) {
    $zipwriter->add_file_from_stored_file($pathinzip, $file);
}
$zipwriter->finish();
exit;
