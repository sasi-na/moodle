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

global $DB, $USER;

$id = required_param('id', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$submissionid = optional_param('submissionid', 0, PARAM_INT);

require_sesskey();

$vpl = new mod_vpl($id);
$vpl->require_capability(VPL_GRADE_CAPABILITY);

// Prevent AI grading in Practice (No Grades) mode.
if ($vpl->is_practice_no_grades_mode()) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => get_string('grading_disabled_desc', VPL)
    ]);
    exit;
}

$isPractice = $vpl->is_practice_mode();
$isCA = $vpl->is_continuous_assessment_mode();

try {
    // Get the submission
    if ($submissionid) {
        $subinstance = $DB->get_record('vpl_submissions', ['id' => $submissionid]);
    } else {
        $subinstance = $vpl->last_user_submission($userid);
    }
    
    if (!$subinstance) {
        throw new Exception('Submission not found');
    }
    
    $submission = new mod_vpl_submission_CE($vpl, $subinstance);
    
    // Get student code
    $files = $submission->get_submitted_fgm()->getallfiles();
    $studentCode = '';
    foreach ($files as $filename => $filedata) {
        $studentCode .= "// File: $filename\n";
        $studentCode .= $filedata . "\n\n";
    }
    
    // Get compilation and execution results
    $ce = $submission->getCE();
    $compilationResults = isset($ce['compilation']) ? $ce['compilation'] : 'No compilation results';
    $executionResults = isset($ce['execution']) ? $ce['execution'] : 'No execution results';
    
    $maxGrade = $vpl->get_grade();
    
    // Call AI API
    $apiKey = 'AIzaSyBWmhP_9nS9jWwv3bWP0tFx6A35bkkedDI';
    $apiUrl = 'https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key=' . $apiKey;
    
    if ($isPractice) {
        $promptText = "Please grade this programming assignment using the keyword **teempt**. provide a numeric grade out of {$maxGrade} points.

**GRADING RULES for PRACTICE:**
1. If the student has submitted any code (at least one line), give at least 1 mark.
2. If at least one test case has passed, give the FULL marks ({$maxGrade}).

**STUDENT CODE:**
{$studentCode}

**COMPILATION RESULTS:**
{$compilationResults}

**TEST CASE RESULTS:**
{$executionResults}

**INSTRUCTIONS:**
1. Analyze the code for logic correctness and provide feedback.
2. If any code is present, ensure the final grade is at least 1.
3. If at least one test case passed in the TEST CASE RESULTS, the final grade MUST be {$maxGrade}.
4. Provide specific feedback for the student.
5. **IMPORTANT: End your response with \"FINAL GRADE: X/{$maxGrade}\" where X is the numeric grade based on the above rules**

Please provide detailed feedback followed by the final numeric grade.";
    } else {
        $promptText = "Please grade this programming assignment and provide a numeric grade out of {$maxGrade} points.

**GRADING CRITERIA (Total: {$maxGrade} Points)**

1. **Logic and Correctness (" . floor($maxGrade * 0.7) . " Points)**
   - Code completeness and implementation
   - Memory allocation and management  
   - Code readability and structure
   - Performance and efficiency

2. **Execution and Output (" . ceil($maxGrade * 0.3) . " Points)**
   - Correct output and functionality
   - Handles edge cases properly
   - No runtime errors

**STUDENT CODE:**
{$studentCode}

**COMPILATION RESULTS:**
{$compilationResults}

**TEST CASE RESULTS:**
{$executionResults}

**INSTRUCTIONS:**
1. Analyze the code for logic correctness, completeness, readability, and performance
2. Check compilation and test results for execution quality
3. Provide specific feedback for each criteria
4. Give constructive suggestions for improvement
5. **IMPORTANT: End your response with \"FINAL GRADE: X/{$maxGrade}\" where X is the numeric grade**

Please provide detailed feedback followed by the final numeric grade.";
    }

    $requestData = [
        'contents' => [[
            'parts' => [[
                'text' => $promptText
            ]]
        ]]
    ];
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode != 200) {
        throw new Exception('AI API request failed with code: ' . $httpCode);
    }
    
    $data = json_decode($response, true);
    
    if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        throw new Exception('Invalid AI API response');
    }
    
    $gradingResult = $data['candidates'][0]['content']['parts'][0]['text'];
    
    // Extract grade
    preg_match('/FINAL GRADE:\s*(\d+(?:\.\d+)?)/i', $gradingResult, $matches);
    $grade = isset($matches[1]) ? floatval($matches[1]) : null;

    // Apply deterministic rules for Practice mode if AI failed or to ensure consistency
    if ($isPractice) {
        $testResults = $vpl->parse_test_case_results($executionResults);
        if ($testResults['passed'] > 0) {
            $grade = $maxGrade;
        } else if (trim($studentCode) !== '') {
            $grade = 1; // Strict 1 mark for attempt in Practice mode
        } else {
            $grade = 0;
        }
        
        // Update the grading result text to match the enforced grade if it was overridden
        if (isset($matches[1]) && floatval($matches[1]) != $grade) {
            $gradingResult = preg_replace('/FINAL GRADE:\s*(\d+(?:\.\d+)?)/i', "FINAL GRADE: $grade", $gradingResult);
        }
    }
    
    if ($grade === null) {
        throw new Exception('Could not extract grade from AI response');
    }
    
    // Ensure grade is within bounds
    if ($grade > $maxGrade) {
        $grade = $maxGrade;
    }
    if ($grade < 0) {
        $grade = 0;
    }
    
    // Prepare grade info object (same format as gradesubmission form)
    $gradeinfo = new stdClass();
    $gradeinfo->grade = $grade;
    
    // Add AI grading comment
    $aiComment = "\n=== AI GRADING ANALYSIS ===\n" . $gradingResult . "\n";
    $aiComment .= "\n#AI Grade: " . $grade . "/" . $maxGrade . "\n";
    $aiComment .= "=== END AI ANALYSIS ===\n";
    $gradeinfo->comments = $aiComment;
    
    // Save the grade using the submission's set_grade method (same as manual grading)
    if ($submission->set_grade($gradeinfo, false)) {
        // Log the event
        $params = [
            'objectid' => $submission->get_instance()->id,
            'context' => $vpl->get_context(),
            'relateduserid' => $userid,
            'other' => [
                'vplid' => $vpl->get_instance()->id
            ]
        ];
        
        if ($subinstance->grader != 0 && $subinstance->dategraded > 0) {
            $event = \mod_vpl\event\submission_grade_updated::create($params);
        } else {
            $event = \mod_vpl\event\submission_graded::create($params);
        }
        $event->trigger();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'grade' => $grade,
            'maxgrade' => $maxGrade,
            'feedback' => $gradingResult
        ]);
    } else {
        throw new Exception('Failed to save grade to database');
    }
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
