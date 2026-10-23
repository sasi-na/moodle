// This file is part of VPL for Moodle
//
// VPL for Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Bulk AI Grading JavaScript Module
//
// @package    mod_vpl
// @copyright  2025
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {
    
    var BulkAIGrading = {
        cmid: null,
        regradeAll: false,
        submissions: [],
        currentIndex: 0,
        successCount: 0,
        failCount: 0,
        
        init: function(cmid) {
            this.cmid = cmid;
            
            // Check if we're on the processing page
            if (typeof bulkGradingData !== 'undefined') {
                this.regradeAll = bulkGradingData.regradeall == 1;
                this.startBulkGrading();
            }
        },
        
        startBulkGrading: function() {
            var self = this;
            
            // First, get all submissions
            self.log('Fetching submissions...', 'info');
            
            Ajax.call([{
                methodname: 'mod_vpl_get_submissions_list',
                args: {
                    cmid: self.cmid
                },
                done: function(response) {
                    self.submissions = response.submissions || [];
                    self.log('Found ' + self.submissions.length + ' submissions', 'success');
                    self.processNextSubmission();
                },
                fail: function(error) {
                    self.log('Error fetching submissions: ' + error.message, 'danger');
                    // Fallback: try to get submissions via AJAX directly
                    self.getSubmissionsViaAjax();
                }
            }]);
        },
        
        getSubmissionsViaAjax: function() {
            var self = this;
            
            $.ajax({
                url: M.cfg.wwwroot + '/mod/vpl/views/get_bulk_submissions.php',
                method: 'POST',
                data: {
                    id: self.cmid,
                    sesskey: M.cfg.sesskey,
                    regradeall: self.regradeAll ? 1 : 0
                },
                dataType: 'json',
                success: function(data) {
                    if (data.success) {
                        self.submissions = data.submissions;
                        self.log('Found ' + self.submissions.length + ' submissions to grade', 'success');
                        self.processNextSubmission();
                    } else {
                        self.log('Error: ' + (data.error || 'Unknown error'), 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    self.log('AJAX Error: ' + error, 'danger');
                }
            });
        },
        
        processNextSubmission: function() {
            var self = this;
            
            if (self.currentIndex >= self.submissions.length) {
                self.showSummary();
                return;
            }
            
            var submission = self.submissions[self.currentIndex];
            var progress = Math.round((self.currentIndex / self.submissions.length) * 100);
            
            self.updateProgress(progress);
            self.log('Grading submission ' + (self.currentIndex + 1) + ' of ' + self.submissions.length + 
                     ' - Student: ' + submission.studentname, 'info');
            
            // Call AI grading for this submission
            self.gradeSubmission(submission);
        },
        
        gradeSubmission: function(submission) {
            var self = this;
            
            $.ajax({
                url: M.cfg.wwwroot + '/mod/vpl/views/ai_grade_submission.php',
                method: 'POST',
                data: {
                    id: self.cmid,
                    userid: submission.userid,
                    submissionid: submission.id,
                    sesskey: M.cfg.sesskey
                },
                dataType: 'json',
                success: function(data) {
                    if (data.success) {
                        self.successCount++;
                        self.log('✓ Successfully graded: ' + submission.studentname + 
                                 ' - Grade: ' + data.grade, 'success');
                    } else {
                        self.failCount++;
                        self.log('✗ Failed to grade: ' + submission.studentname + 
                                 ' - ' + (data.error || 'Unknown error'), 'warning');
                    }
                    
                    self.currentIndex++;
                    // Add delay to avoid overwhelming the API
                    setTimeout(function() {
                        self.processNextSubmission();
                    }, 1000);
                },
                error: function(xhr, status, error) {
                    self.failCount++;
                    self.log('✗ Error grading: ' + submission.studentname + ' - ' + error, 'danger');
                    self.currentIndex++;
                    setTimeout(function() {
                        self.processNextSubmission();
                    }, 1000);
                }
            });
        },
        
        updateProgress: function(percent) {
            $('#progress-bar').css('width', percent + '%').text(percent + '%');
        },
        
        log: function(message, type) {
            type = type || 'info';
            var alertClass = 'alert-' + type;
            var icon = type === 'success' ? '✓' : type === 'danger' ? '✗' : 'ℹ';
            
            $('#grading-log').append(
                '<div class="alert ' + alertClass + ' alert-sm" style="padding: 5px 10px; margin-bottom: 5px;">' +
                '<small>' + icon + ' ' + message + '</small>' +
                '</div>'
            );
            
            // Auto-scroll to bottom
            var logDiv = document.getElementById('grading-log');
            logDiv.scrollTop = logDiv.scrollHeight;
        },
        
        showSummary: function() {
            var self = this;
            
            self.updateProgress(100);
            
            var summaryHtml = '<div class="alert alert-success">' +
                '<h4><i class="fa fa-check-circle"></i> Bulk Grading Complete!</h4>' +
                '<p><strong>Summary:</strong></p>' +
                '<ul>' +
                '<li>Total submissions processed: ' + self.submissions.length + '</li>' +
                '<li>Successfully graded: ' + self.successCount + '</li>' +
                '<li>Failed: ' + self.failCount + '</li>' +
                '</ul>' +
                '</div>';
            
            $('#grading-summary').html(summaryHtml).show();
            $('#backtosubmissions').show();
            
            self.log('All submissions have been processed!', 'success');
        }
    };
    
    return {
        init: function(cmid) {
            BulkAIGrading.init(cmid);
        }
    };
});
