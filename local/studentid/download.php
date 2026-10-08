<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/local/studentid/classes/manager.php');

require_login();

$cardid = required_param('cardid', PARAM_INT);

$syscontext = context_system::instance();
$card = $DB->get_record('local_studentid_cards', ['id' => $cardid], '*', MUST_EXIST);

// Only the card owner or a verifier can download.
if ($card->userid != $USER->id && !has_capability('local/studentid:verify', $syscontext)) {
    throw new moodle_exception('errorinvalidaccess', 'local_studentid');
}
// Students may only download if they belong to an eligible batch (verifiers exempt).
if (!has_capability('local/studentid:verify', $syscontext)
        && !\local_studentid\manager::is_eligible_batch($USER->id)) {
    throw new moodle_exception('noteligible', 'local_studentid', '',
        implode(', ', \local_studentid\manager::get_eligible_batches()));
}

$fs   = get_file_storage();
$file = $fs->get_file($syscontext->id, 'local_studentid', 'idcard', $cardid, '/', $card->file_path);

if (!$file) {
    throw new moodle_exception('filenotfound', 'error');
}

// Track downloads by the card owner only (not verifier previews).
if ($card->userid == $USER->id) {
    try {
        \local_studentid\manager::increment_download_count($cardid);
    } catch (\Exception $ignored) {
        // Never block a download because of a stats update failure.
    }
}

// Serve with no caching (lifetime 0): cards can be regenerated in place, so a
// long browser/proxy cache would otherwise keep showing a stale copy for up to
// $CFG->filelifetime (24h) after a regeneration.
send_stored_file($file, 0, 0, false, ['filename' => $card->idcard_number . '.pdf']);
