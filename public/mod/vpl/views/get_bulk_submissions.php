<?php
// This file is part of VPL for Moodle
//
// VPL for Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Get submissions for bulk grading
//
// @package    mod_vpl
// @copyright  2025
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

define('AJAX_SCRIPT', true);

require_once(dirname(__FILE__).'/../../../config.php');
require_once($CFG->dirroot.'/mod/vpl/locallib.php');
require_once($CFG->dirroot.'/mod/vpl/vpl.class.php');

global $DB;

$id = required_param('id', PARAM_INT);
$regradeall = optional_param('regradeall', 0, PARAM_INT);

require_sesskey();

$vpl = new mod_vpl($id);
$vpl->require_capability(VPL_GRADE_CAPABILITY);

$allsubmissions = $vpl->all_last_user_submission();
$submissions = [];

foreach ($allsubmissions as $userid => $sub) {
    if (!$sub) {
        continue;
    }
    
    // Skip already graded submissions unless regradeall is set
    if (!$regradeall && !is_null($sub->grade) && $sub->grade != '') {
        continue;
    }
    
    $user = $DB->get_record('user', ['id' => $userid]);
    if ($user) {
        $submissions[] = [
            'id' => $sub->id,
            'userid' => $userid,
            'studentname' => fullname($user),
            'datesubmitted' => $sub->datesubmitted
        ];
    }
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'submissions' => $submissions,
    'count' => count($submissions)
]);
