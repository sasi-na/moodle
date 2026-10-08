<?php
// This file is part of VPL for Moodle
//
// VPL for Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// AI Grade individual submission
//
// @package    mod_vpl
// @copyright  2025
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

define('AJAX_SCRIPT', true);

require_once(dirname(__FILE__).'/../../../config.php');
require_once($CFG->dirroot.'/mod/vpl/locallib.php');
require_once($CFG->dirroot.'/mod/vpl/vpl.class.php');
require_once($CFG->dirroot.'/mod/vpl/vpl_submission_CE.class.php');

use mod_vpl\ai_grader;

global $DB, $USER;

$id = required_param('id', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$submissionid = optional_param('submissionid', 0, PARAM_INT);

require_sesskey();

$vpl = new mod_vpl($id);
$vpl->require_capability(VPL_GRADE_CAPABILITY);

try {
    // Get the submission.
    if ($submissionid) {
        $subinstance = $DB->get_record('vpl_submissions', ['id' => $submissionid]);
    } else {
        $subinstance = $vpl->last_user_submission($userid);
    }

    if (!$subinstance) {
        throw new Exception('Submission not found');
    }

    // The prompt, the API call and the grade write all live in ai_grader so
    // this endpoint and the background task grade identically.
    $grade = ai_grader::grade_one($vpl, $subinstance);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'grade' => $grade,
        'maxgrade' => $vpl->get_grade(),
    ]);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
