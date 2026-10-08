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
 * JavaScript functions to help grade form
 * @copyright 2012 Juan Carlos Rodríguez-del-Pino, 2021 Astor Bizard
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function ($) {

    // Course module id, set by setup() so the AI proxy knows which activity is being graded.
    var vplCmid = 0;

    /**
     * Apply grade reduction comments to given grade.
     * @param {Number} grade Original grade.
     * @param {String} comments Comments with some grade reduction.
     * @returns {Number} The reduced grade.
     */
    function reduceGradeWithComments(grade, comments) {
        var regDiscount = /^-.+\((-[0-9.]+)\)\s*$/gm;
        var match;
        while ((match = regDiscount.exec(comments)) !== null) {
            var rest = parseFloat(match[1]);
            if (rest < 0) {
                grade += rest;
            }
        }
        return grade;
    }

    /**
     * Format grade (in points) to a displayable float.
     * @param {Number} grade Grade to be formatted.
     * @returns {Number} The formatted grade.
     */
    function formatGrade(grade) {
        // No negative grade.
        if (grade < 0) {
            return 0;
        }
        // Max two decimal points.
        return Math.round(100 * grade) / 100;
    }

    /**
     * Init method for grade buttons (Merge grade, Calculate).
     */
    function setupGradeButtons() {
        // Calculate button.
        $('button[data-role="calculategrade"]').click(function () {
            // Recalculate numeric grade from the max grade,
            // substracting points found at the end of lines.
            // Valid reduction format: "- text (-grade)".
            var $form = $(this).closest('form');
            var text = $form.find('[name="comments"]').val();
            var grade = parseFloat($(this).data('maxgrade'));
            grade = formatGrade(reduceGradeWithComments(grade, text));
            $form.find('[name="grade"]').val(grade);
        });

        // Merge grade button.
        $('button[data-role="mergegrade"]').click(function () {
            // Merge numeric grade from the proposed grade and advancedgrading grid,
            // substracting points found at the end of lines.
            // Valid reduction format: "- text (-grade)".
            var $form = $(this).closest('form');
            var gridpoints = 0;
            if ($form.find('.score .scorevalue').length > 0) {
                $form.find('.checked .score .scorevalue')
                    .each(function () {
                        gridpoints += Number($(this).text());
                    });
            } else {
                $form.find('.score input')
                    .each(function () {
                        gridpoints += Number($(this).val());
                    });
            }
            var advancedgradingcomments = '';
            $form.find('.criterion .remark textarea')
                .each(function () {
                    advancedgradingcomments += $(this).val() + "\n";
                });
            gridpoints = reduceGradeWithComments(gridpoints, advancedgradingcomments);

            var maxgrade = $(this).data('maxgrade');
            var currentgrade = $(this).data('currentgrade');
            var maxgridpoints = $(this).data('maxgridpoints');

            var grade = formatGrade(currentgrade - maxgridpoints * (currentgrade / maxgrade) + gridpoints);
            $form.find('[name="grade"]').val(grade);

            var $comments = $form.find('[name="comments"]');
            var text = $comments.val();

            var gridtag = '#Grid grade';
            var gridcomment = gridtag + ': ' + grade;
            if (text.search(gridtag) < 0) {
                text = gridcomment + "\n" + text;
            } else {
                text = text.replace(new RegExp(gridtag + '.*'), gridcomment);
            }
            var proposedtag = '#Proposed grade';
            var proposedcomment = proposedtag + ': ' + currentgrade;
            if (text.search(proposedtag) < 0) {
                text = proposedcomment + "\n" + text;
            } else {
                text = text.replace(new RegExp(proposedtag + '.*'), proposedcomment);
            }

            $comments.val(text);
        });
    }

    /**
     * Enhanced AI grading that integrates with existing grading workflow.
     * This follows the same pattern as calculategrade and mergegrade functions.
     */
    function setupAIGradingButton() {
        // eslint-disable-next-line no-console
        console.log('AI Grading button setup initialized');

        // Use event delegation to ensure the handler works even if button is added dynamically
        $(document).on('click', 'button[data-role="aigrading"], input[name="aigrading"]', function (e) {
            // eslint-disable-next-line no-console
            console.log('AI Grading button clicked!');
            e.preventDefault(); // Prevent form submission

            var $form = $(this).closest('form');
            var $button = $(this);

            // Check if we have a max grade to work with (like Calculate button does)
            var maxGrade = parseFloat($('button[data-role="calculategrade"]').data('maxgrade')) || 100;
            var mode = $(this).data('mode');

            // Store original button text
            var originalButtonText = $button.text();

            // Disable button during processing and update text
            $button.prop('disabled', true);
            if ($button.is('input')) {
                $button.val('Analyzing...');
            } else {
                $button.text('Analyzing...');
            }

            // Try to get student code from various sources
            var studentCode = extractStudentCode();

            // Get compilation and test results
            var compilationResults = extractCompilationResults();
            var testResults = extractTestResults();

            // If no code found, show error and restore button
            if (!studentCode.trim()) {
                alert('No student code found to analyze. Please ensure the submission is properly loaded.');
                restoreButton($button, originalButtonText);
                return;
            }

            // Call AI grading API with improved error handling
            performAIGrading(studentCode, compilationResults, testResults)
                .then(function (gradingResult) {
                    // Process the grading result similar to how Calculate button works
                    processAIGradingResult(gradingResult, $form, maxGrade, mode, studentCode, testResults);
                })
                .catch(function (error) {
                    // eslint-disable-next-line no-console
                    console.error('AI Grading Error:', error);
                    alert('Error generating AI grade: ' + error.message);
                })
                .finally(function () {
                    // Restore button state
                    restoreButton($button, originalButtonText);
                });
        });
    }

    /**
     * Extract student code from the submission display
     * @returns {string} The student's code
     */
    function extractStudentCode() {
        var studentCode = '';

        // Try multiple selectors to find the code
        var codeSelectors = [
            '.vpl_ide_file pre',
            '.vpl_sh_code',
            '.vpl_code',
            'pre code',
            '.highlight pre',
            '.submission-content pre',
            '.file-content pre',
            'pre'
        ];

        /**
         * Extracts code content from the first matching selector in the list
         * @param {Array<string>} selectors - Array of CSS selectors to search for code content
         * @returns {string} Extracted code content from the first matching selector
         */
        function extractCodeFromSelectors(selectors) {
            var code = '';
            for (var i = 0; i < selectors.length && !code.trim(); i++) {
                var elements = $(selectors[i]);
                for (var j = 0; j < elements.length; j++) {
                    var content = $(elements[j]).text();
                    if (content.trim() && content.length > 10) {
                        code += content + '\n\n';
                    }
                }
            }
            return code;
        }

        studentCode = extractCodeFromSelectors(codeSelectors);

        // If still no code, try to get from any visible text area or code block
        if (!studentCode.trim()) {
            var fallbackElements = $('textarea, .code, .source');
            for (var k = 0; k < fallbackElements.length; k++) {
                var element = $(fallbackElements[k]);
                var content = element.val() || element.text();
                if (content.trim() && content.length > 10) {
                    studentCode += content + '\n\n';
                }
            }
        }

        return studentCode;
    }

    /**
     * Extract compilation results from the page
     * @returns {string} Compilation results
     */
    function extractCompilationResults() {
        var compilationResults = '';

        // Look for compilation output in various containers
        var compilationSelectors = [
            '.vpl_compilation',
            '.vpl_output',
            '.compilation-result',
            '.build-output',
            '.compile-error',
            '.error-message'
        ];

        for (var i = 0; i < compilationSelectors.length; i++) {
            var element = $(compilationSelectors[i]);
            if (element.length > 0) {
                var text = element.text().trim();
                if (text) {
                    compilationResults += text + '\n\n';
                }
            }
        }

        return compilationResults;
    }

    /**
     * Extract test results from the page
     * @returns {string} Test results
     */
    function extractTestResults() {
        var testResults = '';

        // Priority order: Evaluation (table), Execution, then others
        var testSelectors = [
            '.vpl_evaluation',
            '.vpl_ce_table',
            '.vpl_execution',
            '.vpl_test_output',
            '.test-results',
            '.execution-result',
            '.test-summary'
        ];

        for (var i = 0; i < testSelectors.length; i++) {
            var element = $(testSelectors[i]);
            if (element.length > 0) {
                var text = element.text().trim();
                // If we found a meaningful Evaluation table, stop and use that to avoid mixed context
                if (text && (testSelectors[i] === '.vpl_evaluation' || testSelectors[i] === '.vpl_ce_table')) {
                    if (text.includes('Summary:') || text.includes('|')) {
                        return text;
                    }
                }
                if (text) {
                    testResults += text + '\n\n';
                }
            }
        }
        return testResults;
    }

    /**
     * Restore button to original state
     * @param {jQuery} $button The button element
     * @param {string} originalText The original button text
     */
    function restoreButton($button, originalText) {
        $button.prop('disabled', false);
        if ($button.is('input')) {
            $button.val(originalText);
        } else {
            $button.text(originalText);
        }
    }

    /**
     * Perform AI grading using external API
     * @param {string} studentCode The student's code
     * @param {string} compilationResults Compilation results
     * @param {string} testResults Test execution results
     * @returns {Promise} Promise that resolves with grading result
     */
    function performAIGrading(studentCode, compilationResults, testResults) {
        // The prompt, the activity's maximum and the practice rules are applied
        // server side, so the key never reaches the browser.
        return fetch(M.cfg.wwwroot + '/mod/vpl/views/ai_proxy.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + encodeURIComponent(vplCmid) +
                '&sesskey=' + encodeURIComponent(M.cfg.sesskey) +
                '&action=formgrading' +
                '&code=' + encodeURIComponent(studentCode) +
                '&compilation=' + encodeURIComponent(compilationResults) +
                '&tests=' + encodeURIComponent(testResults)
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('API request failed: ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                if (data.candidates && data.candidates[0] && data.candidates[0].content) {
                    return data.candidates[0].content.parts[0].text;
                } else {
                    throw new Error('No grading results received from API');
                }
            });
    }

    /**
     * Process AI grading result and update the form like Calculate button does
     * @param {string} gradingResult The AI grading result text
     * @param {jQuery} $form The form element
     * @param {number} maxGrade Maximum possible grade
     * @param {number} mode Activity mode
     * @param {string} studentCode Student's code
     * @param {string} testResults Test results
     */
    function processAIGradingResult(gradingResult, $form, maxGrade, mode, studentCode, testResults) {
        // Extract the numeric grade from the result
        var gradeMatch = gradingResult.match(/FINAL GRADE:\s*(\d+(?:\.\d+)?)/i);
        var extractedGrade = gradeMatch ? parseFloat(gradeMatch[1]) : null;

        if (mode === 0) { // Practice mode deterministic override
            var passCount = 0;
            var resultsLower = testResults.toLowerCase();

            // 1. Check for explicit "Summary: X/Y tests passed" - highest confidence
            // Stricter regex to ensure we have at least 1 pass
            var summaryMatch = testResults.match(/Summary:\s*([1-9]\d*)\s*\/\s*(\d+)/i);
            if (summaryMatch) {
                passCount = parseInt(summaryMatch[1]);
            }

            // 2. Scan lines specifically for PASS markers in tables
            if (passCount === 0) {
                var lines = testResults.split('\n');
                for (var j = 0; j < lines.length; j++) {
                    var line = lines[j].trim();
                    // Match "✅ PASS" or "| PASS |" but NOT "Summary: 0"
                    if ((line.match(/[✅✓]/) || line.match(/PASS/i)) && !line.match(/FAIL/i) && !line.match(/Summary:\s*0/i)) {
                        passCount = 1;
                        break;
                    }
                }
            }

            // 3. Fail-safe: Check if the AI's own response explicitly confirms a PASSING test case
            if (passCount === 0) {
                var aiLower = gradingResult.toLowerCase();
                // Only if AI clearly says a test passed
                if (aiLower.includes('test case passed') || aiLower.includes('test case passes') ||
                    aiLower.includes('receives full marks') || aiLower.includes('receive full marks')) {
                    passCount = 1;
                }
            }

            if (passCount > 0) {
                extractedGrade = maxGrade;
            } else if (studentCode.trim().length > 0) {
                extractedGrade = 1;
            } else {
                extractedGrade = 0;
            }

            // Sync the gradingResult text with enforced grade
            if (gradeMatch && parseFloat(gradeMatch[1]) !== extractedGrade) {
                gradingResult = gradingResult.replace(/FINAL GRADE:\s*(\d+(?:\.\d+)?)/i, "FINAL GRADE: " + extractedGrade);
            }
        }

        if (extractedGrade !== null && extractedGrade <= maxGrade) {
            // Set the grade field (similar to how Calculate button works)
            var $gradeField = $form.find('[name="grade"]');
            $gradeField.val(formatGrade(extractedGrade));
        }

        // Add AI grading comments to the comments field
        var $commentsField = $form.find('[name="comments"]');
        var currentComments = $commentsField.val();

        // Prepare AI grading comment with clear formatting
        var aiGradingComment = '\n=== AI GRADING ANALYSIS ===\n' + gradingResult + '\n';
        if (extractedGrade !== null) {
            aiGradingComment += '\n#AI Grade: ' + extractedGrade + '/' + maxGrade + '\n';
        }
        aiGradingComment += '=== END AI ANALYSIS ===\n';

        // Append to existing comments (similar to how other grade buttons work)
        if (currentComments) {
            $commentsField.val(currentComments + aiGradingComment);
        } else {
            $commentsField.val(aiGradingComment);
        }

        // Show success message
        if (extractedGrade !== null) {
            alert('AI grading completed successfully! Grade: ' + extractedGrade + '/' + maxGrade);
        } else {
            alert('AI grading completed. Please check the comments for detailed feedback.');
        }
    }

    /**
     * Init method for import buttons.
     */
    function setupImportButtons() {
        $('button[data-role="importfromsub"]').click(function () {
            var $form = $(this).closest('form');
            // Set grade and comments.
            $form.find('[name="grade"]').val($(this).data('grade'));
            $form.find('[name="comments"]').val($(this).data('comments'));

            // Set advanced grading data:

            // First, reset the fields.
            $('#advancedgrading-criteria .criterion .remark textarea').val('');
            $('#advancedgrading-criteria .criterion .score input').val('');
            // Handle rubric level selection slightly differently:
            // we trigger a click on it, so it updates its UI correctly.
            $('#advancedgrading-criteria .criterion .level.checked').click();

            // Then, set the fields to their new values.
            $(this).data('advgrading').forEach(function (criterion) {
                var id = criterion.criterionid;
                if (typeof (criterion.remark) !== 'undefined') {
                    $form.find('[name="advancedgrading[criteria][' + id + '][remark]"]').val(criterion.remark);
                }
                if (typeof (criterion.score) !== 'undefined') {
                    $form.find('[name="advancedgrading[criteria][' + id + '][score]"]').val(parseFloat(criterion.score));
                }
                // Handle rubric level selection slightly differently:
                // we trigger a click on it, so it updates its UI correctly.
                if (typeof (criterion.levelid) !== 'undefined') {
                    $('#advancedgrading-criteria-' + id + '-levels-' + criterion.levelid).click();
                }
            });
        });
    }

    return {
        setup: function (cmid) {
            vplCmid = cmid;
            setupGradeButtons();
            setupImportButtons();
            setupAIGradingButton();
        },
        updateSubmissionsList: function (submissionID, gradeData, nexturl) {
            if (opener !== null) {
                $(opener.document).find('#g' + submissionID).html(gradeData.grade);
                $(opener.document).find('#m' + submissionID).html(gradeData.grader);
                $(opener.document).find('#o' + submissionID).html(gradeData.gradedon);
                $(opener.document).find('#c' + submissionID).html(gradeData.comments);
                $(opener.document).find('.gd' + submissionID).css('color', '').css('backgroundColor', '');
            }
            if (nexturl) {
                if (opener === null) {
                    window.close();
                }
                var nextrow = $(opener.document).find('#g' + submissionID).closest('tr').next();
                if (nextrow.length == 0) {
                    window.close();
                }
                var nextid = nextrow.html().match(/user\/view\.php\?(.*?)id=([0-9]+)/)[2];
                if (nextid) {
                    location.replace(nexturl + nextid);
                } else {
                    window.close();
                }
            }
        },
        highlightSubmission: function (submissionID) {
            if (opener !== null) {
                $(opener.document).find('.gd' + submissionID).css('color', 'black').css('backgroundColor', 'yellow');
                window.onunload = function () {
                    $(opener.document).find('.gd' + submissionID).css('color', '').css('backgroundColor', '');
                };
            }
        }
    };
});
