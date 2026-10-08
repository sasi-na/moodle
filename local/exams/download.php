<?php
/**
 * Streams the cached PDF result sheet for the current student (generating it on
 * demand if missing). URL-only. Background pre-generation lands in a later task.
 *
 * @package   local_exams
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');

require_login();

$userid = optional_param('userid', 0, PARAM_INT);
$syscontext = context_system::instance();

if (isguestuser()) {
    redirect(get_login_url());
}
if (!\local_exams\manager::is_enabled()) {
    throw new moodle_exception('notenabled', 'local_exams');
}

$isstaff = has_capability('local/exams:viewanalytics', $syscontext);
$targetuser = ($userid && $isstaff) ? $userid : (int) $USER->id;

if ($targetuser === (int) $USER->id) {
    require_capability('local/exams:downloadresultsheet', $syscontext);
    if (!$isstaff && !\local_exams\manager::results_visible($USER->id)) {
        throw new moodle_exception('resultsnotreleased', 'local_exams');
    }
}

$file = \local_exams\result_sheet_generator::get_or_generate($targetuser);

// Track downloads for the owner only.
if ($targetuser === (int) $USER->id) {
    global $DB;
    $rec = $DB->get_record('local_exams_resultsheets', ['userid' => $targetuser]);
    if ($rec) {
        $rec->downloadcount++;
        $rec->lastdownloaded = time();
        $DB->update_record('local_exams_resultsheets', $rec);
    }
}

send_stored_file($file, 0, 0, true, ['filename' => 'result-sheet.pdf']);
