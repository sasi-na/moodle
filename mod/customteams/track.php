<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('customteams', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);

// Login required
require_login($course, false, $cm);

$completion = new completion_info($course);

if ($completion->is_enabled($cm)) {
    $completion->set_module_viewed($cm);
}

echo json_encode(['status' => 'ok']);
