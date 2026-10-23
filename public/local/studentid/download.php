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

send_stored_file($file, null, 0, false, ['filename' => $card->idcard_number . '.pdf']);
