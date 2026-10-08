<?php
// This file is part of VPL for Moodle - http://vpl.dis.ulpgc.es/
//
// VPL for Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// VPL for Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with VPL for Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * AI Bulk Grade - Grade all student submissions at once
 *
 * @package mod_vpl
 * @copyright 2025
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../../config.php');
require_once($CFG->dirroot.'/mod/vpl/locallib.php');
require_once($CFG->dirroot.'/mod/vpl/vpl.class.php');
require_once($CFG->dirroot.'/mod/vpl/vpl_submission_CE.class.php');

use mod_vpl\task\ai_grade_batch;

global $CFG, $USER, $DB, $OUTPUT, $PAGE;

$id = required_param('id', PARAM_INT); // Course module ID
$confirm = optional_param('confirm', 0, PARAM_INT);

$vpl = new mod_vpl($id);
$vpl->require_capability(VPL_GRADE_CAPABILITY);

// Prevent bulk AI grading in Practice (No Grades) mode.
if ($vpl->is_practice_no_grades_mode()) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('aigrading_bulk', 'vpl'));
    echo '<div class="alert alert-danger">';
    echo '<strong>' . get_string('grading_disabled', VPL) . '</strong><br>';
    echo get_string('grading_disabled_desc', VPL);
    echo '</div>';
    echo $OUTPUT->single_button(new moodle_url('/mod/vpl/view.php', ['id' => $id]), get_string('continue'));
    echo $OUTPUT->footer();
    exit;
}

$course = $vpl->get_course();
$cm = $vpl->get_course_module();

require_login($course, false, $cm);

$background = optional_param('background', 0, PARAM_INT);
$cancel = optional_param('cancel', 0, PARAM_INT);
$returnurl = new moodle_url('/mod/vpl/views/aibulkgrade.php', ['id' => $id]);

if ($cancel) {
    require_sesskey();
    $job = ai_grade_batch::get_active_job($id);
    if ($job) {
        $job->status = 'cancelled';
        $job->timemodified = time();
        $DB->update_record('vpl_ai_grading_job', $job);
    }
    redirect($returnurl, get_string('aigrading_cancelled', VPL));
}

if ($background) {
    require_sesskey();
    if (ai_grade_batch::get_active_job($id)) {
        redirect($returnurl);
    }
    $regradeall = optional_param('regradeall', 0, PARAM_INT);
    ai_grade_batch::queue_job($vpl, (bool) $regradeall, $USER->id);
    redirect($returnurl);
}

$PAGE->set_url(new moodle_url('/mod/vpl/views/aibulkgrade.php', ['id' => $id]));
$PAGE->set_title(get_string('aigrading_bulk', 'vpl'));
$PAGE->set_heading($course->fullname);
$PAGE->requires->jquery();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('aigrading_bulk', 'vpl'));

if (!$confirm) {
    // Show confirmation page
    echo '<div style="max-width: 800px; margin: 20px auto;">';
    echo '<div class="alert alert-info">';
    echo '<h4>' . get_string('aigrading_bulk_help', 'vpl') . '</h4>';
    echo '<p>This feature will:</p>';
    echo '<ul>';
    echo '<li>Process all student submissions for this activity</li>';
    echo '<li>Use AI to analyze code quality, logic, and test results</li>';
    echo '<li>Generate grades and detailed feedback automatically</li>';
    echo '<li>Skip submissions that are already graded (optionally)</li>';
    echo '</ul>';
    echo '</div>';

    $activejob = ai_grade_batch::get_active_job($id);
    if ($activejob) {
        $a = (object) ['processed' => $activejob->processed, 'failed' => $activejob->failed];
        echo '<div class="alert alert-success">';
        echo '<h5>' . get_string('aigrading_jobstatus', VPL) . '</h5>';
        echo '<p>' . get_string('aigrading_inprogress', VPL, $a) . '</p>';
        if ($activejob->total > 0) {
            $pct = (int) round(100 * $activejob->processed / max(1, $activejob->total));
            echo '<div class="progress" style="height: 20px;"><div class="progress-bar" role="progressbar" '
               . 'style="width: ' . $pct . '%">' . $pct . '%</div></div>';
        }
        $cancelurl = new moodle_url('/mod/vpl/views/aibulkgrade.php',
                ['id' => $id, 'cancel' => 1, 'sesskey' => sesskey()]);
        echo '<p style="margin-top: 10px;"><a class="btn btn-sm btn-outline-danger" href="' . $cancelurl . '">'
           . get_string('aigrading_cancel', VPL) . '</a></p>';
        echo '</div>';
    }
    
    // Get submission statistics
    $allsubmissions = $vpl->all_last_user_submission();
    $totalsubmissions = count($allsubmissions);
    $gradedcount = 0;
    $ungradedcount = 0;
    
    foreach ($allsubmissions as $sub) {
        if ($sub && !is_null($sub->grade) && $sub->grade != '') {
            $gradedcount++;
        } else {
            $ungradedcount++;
        }
    }
    
    echo '<div class="well">';
    echo '<h5>Submission Statistics:</h5>';
    echo '<ul>';
    echo '<li><strong>Total submissions:</strong> ' . $totalsubmissions . '</li>';
    echo '<li><strong>Already graded:</strong> ' . $gradedcount . '</li>';
    echo '<li><strong>Ungraded:</strong> ' . $ungradedcount . '</li>';
    echo '</ul>';
    echo '</div>';
    
    if ($ungradedcount > 0) {
        echo '<form method="post" action="aibulkgrade.php" id="aibulkgradeform">';
        echo '<input type="hidden" name="id" value="' . $id . '" />';
        echo '<input type="hidden" name="confirm" value="1" />';
        echo '<input type="hidden" name="sesskey" value="' . sesskey() . '" />';
        
        echo '<div class="form-check" style="margin-bottom: 15px;">';
        echo '<input class="form-check-input" type="checkbox" name="regradeall" id="regradeall" value="1">';
        echo '<label class="form-check-label" for="regradeall">';
        echo 'Re-grade all submissions (including already graded ones)';
        echo '</label>';
        echo '</div>';
        
        echo '<div style="margin-top: 20px;">';
        echo '<button type="submit" class="btn btn-primary btn-lg" id="startbulkgrading">';
        echo '<i class="fa fa-robot"></i> Start AI Bulk Grading';
        echo '</button> ';
        echo '<button type="submit" name="background" value="1" class="btn btn-success btn-lg">';
        echo '<i class="fa fa-clock-o"></i> ' . get_string('aigrading_background', VPL);
        echo '</button> ';
        echo '<a href="submissionslist.php?id=' . $id . '" class="btn btn-secondary">Cancel</a>';
        echo '</div>';
        echo '</form>';
    } else {
        echo '<div class="alert alert-warning">';
        echo 'All submissions have already been graded. Check the "Re-grade all" option if you want to re-grade them.';
        echo '</div>';
        echo '<a href="submissionslist.php?id=' . $id . '" class="btn btn-secondary">Back to Submissions</a>';
    }
    
    echo '</div>';
} else {
    // Process bulk grading
    require_sesskey();
    
    $regradeall = optional_param('regradeall', 0, PARAM_INT);
    
    echo '<div id="bulkgrading-progress" style="max-width: 800px; margin: 20px auto;">';
    echo '<div class="alert alert-info">';
    echo '<h4><i class="fa fa-spinner fa-spin"></i> Processing AI Bulk Grading...</h4>';
    echo '<p>Please wait while we grade all submissions. This may take a few minutes.</p>';
    echo '</div>';
    
    echo '<div class="progress" style="height: 30px;">';
    echo '<div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%">0%</div>';
    echo '</div>';
    
    echo '<div id="grading-log" style="margin-top: 20px; max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 15px; background: #f9f9f9;">';
    echo '</div>';
    
    echo '<div id="grading-summary" style="margin-top: 20px; display: none;">';
    echo '</div>';
    
    echo '<div style="margin-top: 20px;">';
    echo '<a href="submissionslist.php?id=' . $id . '" class="btn btn-primary" id="backtosubmissions" style="display: none;">Back to Submissions List</a>';
    echo '</div>';
    
    echo '</div>';
    
    // Pass data to JavaScript for processing and add inline script
    echo '<script>';
    echo 'var bulkGradingData = {';
    echo '  cmid: ' . $id . ',';
    echo '  regradeall: ' . $regradeall . ',';
    echo '  sesskey: "' . sesskey() . '",';
    echo '  wwwroot: "' . $CFG->wwwroot . '"';
    echo '};';
    echo '
    
// Bulk AI Grading Implementation
(function() {
    var submissions = [];
    var currentIndex = 0;
    var successCount = 0;
    var failCount = 0;
    
    function log(message, type) {
        type = type || "info";
        var alertClass = "alert-" + type;
        var icon = type === "success" ? "✓" : type === "danger" ? "✗" : "ℹ";
        
        jQuery("#grading-log").append(
            "<div class=\"alert " + alertClass + " alert-sm\" style=\"padding: 5px 10px; margin-bottom: 5px;\">" +
            "<small>" + icon + " " + message + "</small>" +
            "</div>"
        );
        
        var logDiv = document.getElementById("grading-log");
        logDiv.scrollTop = logDiv.scrollHeight;
    }
    
    function updateProgress(percent) {
        jQuery("#progress-bar").css("width", percent + "%").text(percent + "%");
    }
    
    function getSubmissions() {
        log("Fetching submissions...", "info");
        
        jQuery.ajax({
            url: bulkGradingData.wwwroot + "/mod/vpl/views/get_bulk_submissions.php",
            method: "POST",
            data: {
                id: bulkGradingData.cmid,
                sesskey: bulkGradingData.sesskey,
                regradeall: bulkGradingData.regradeall
            },
            dataType: "json",
            success: function(data) {
                if (data.success) {
                    submissions = data.submissions;
                    log("Found " + submissions.length + " submissions to grade", "success");
                    if (submissions.length > 0) {
                        processNextSubmission();
                    } else {
                        log("No submissions to process", "warning");
                        showSummary();
                    }
                } else {
                    log("Error: " + (data.error || "Unknown error"), "danger");
                }
            },
            error: function(xhr, status, error) {
                log("AJAX Error: " + error, "danger");
            }
        });
    }
    
    function processNextSubmission() {
        if (currentIndex >= submissions.length) {
            showSummary();
            return;
        }
        
        var submission = submissions[currentIndex];
        var progress = Math.round((currentIndex / submissions.length) * 100);
        
        updateProgress(progress);
        log("Grading submission " + (currentIndex + 1) + " of " + submissions.length + 
             " - Student: " + submission.studentname, "info");
        
        gradeSubmission(submission);
    }
    
    function gradeSubmission(submission) {
        jQuery.ajax({
            url: bulkGradingData.wwwroot + "/mod/vpl/views/ai_grade_submission.php",
            method: "POST",
            data: {
                id: bulkGradingData.cmid,
                userid: submission.userid,
                submissionid: submission.id,
                sesskey: bulkGradingData.sesskey
            },
            dataType: "json",
            success: function(data) {
                if (data.success) {
                    successCount++;
                    log("✓ Successfully graded: " + submission.studentname + 
                         " - Grade: " + data.grade + "/" + data.maxgrade, "success");
                } else {
                    failCount++;
                    log("✗ Failed to grade: " + submission.studentname + 
                         " - " + (data.error || "Unknown error"), "warning");
                }
                
                currentIndex++;
                setTimeout(function() {
                    processNextSubmission();
                }, 2000); // 2 second delay between submissions
            },
            error: function(xhr, status, error) {
                failCount++;
                log("✗ Error grading: " + submission.studentname + " - " + error, "danger");
                currentIndex++;
                setTimeout(function() {
                    processNextSubmission();
                }, 2000);
            }
        });
    }
    
    function showSummary() {
        updateProgress(100);
        
        var summaryHtml = "<div class=\"alert alert-success\">" +
            "<h4><i class=\"fa fa-check-circle\"></i> Bulk Grading Complete!</h4>" +
            "<p><strong>Summary:</strong></p>" +
            "<ul>" +
            "<li>Total submissions processed: " + submissions.length + "</li>" +
            "<li>Successfully graded: " + successCount + "</li>" +
            "<li>Failed: " + failCount + "</li>" +
            "</ul>" +
            "</div>";
        
        jQuery("#grading-summary").html(summaryHtml).show();
        jQuery("#backtosubmissions").show();
        
        log("All submissions have been processed!", "success");
    }
    
    // Start the process when page loads
    jQuery(document).ready(function() {
        getSubmissions();
    });
})();
    ';
    echo '</script>';
}

echo $OUTPUT->footer();
