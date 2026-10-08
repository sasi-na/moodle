<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for local_reflectiveassessment.
 *
 * @package   local_reflectiveassessment
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Reflective Assessment';
$string['privacy:metadata'] = 'The Reflective Assessment guide summary stores no personal data of its own. It reports counts drawn from enrolments, quiz attempts and grading history held by core Moodle.';
$string['reportpage'] = 'Reflective Assessment: guide summary';
$string['settings'] = 'Reflective Assessment settings';

$string['reflectiveassessment:view'] = 'View the Reflective Assessment guide summary';

// Report page.
$string['period'] = 'Batch and semester';
$string['sortby'] = 'Sort by';
$string['sort_submitted'] = 'Submitted (highest first)';
$string['sort_guide'] = 'Guide (A-Z)';
$string['sort_evaluated'] = 'Evaluated % (lowest first)';
$string['sort_awaiting'] = 'Awaiting evaluation (highest first)';
$string['download'] = 'Download';
$string['generated'] = 'Figures as at {$a}.';
$string['totals'] = 'Total';
$string['noperiods'] = 'No Reflective Assessment guide courses were found. Check the course name pattern in the plugin settings.';
$string['nodata'] = 'No guide courses in this batch and semester.';
$string['invalidperiod'] = 'Unknown batch and semester.';
$string['unknownuser'] = 'Unknown user';
$string['guideunresolved'] = 'Not identified';
$string['sharedmarkernotice'] = '{$a} of these courses have marking done from a shared account rather than by the named guide. The Evaluated by column shows who.';
$string['deletedguidenotice'] = '{$a} guides no longer have an active Moodle account. Their marking is still counted, and the guide name comes from the marking history.';
$string['intro'] = 'One row per guide course. <strong>Submitted</strong> counts every finished attempt; <strong>Answered</strong> excludes the ones finished with no answer, which Moodle auto-scores zero and no guide ever sees. <strong>Evaluated</strong> counts attempts a guide has actually marked.';

// Views.
$string['view_guides'] = 'Guide summary';
$string['view_evaluators'] = 'Who evaluated';
$string['view_students'] = 'Student detail';
$string['filtercourse'] = 'Guide course';
$string['allcourses'] = 'All guide courses';
$string['invalidcourse'] = 'That guide course is not in this batch and semester.';
$string['evaluatorcounts'] = '{$a->evaluators} evaluators, {$a->graded} attempts evaluated.';
$string['sharedgradednotice'] = '{$a} of these evaluations were entered from a shared account rather than by a named faculty member.';
$string['detailcounts'] = '{$a->total} rows: {$a->evaluated} evaluated, {$a->awaiting} awaiting evaluation, {$a->blank} submitted blank, {$a->notattempted} not attempted.';
$string['markblanknote'] = 'The Mark column is filled only where a guide has evaluated the attempt. A blank submission carries a gradebook zero that no guide awarded, so it is left empty and shown as "Submitted blank" instead.';
$string['showingrows'] = 'Showing rows {$a->from}-{$a->to} of {$a->total}. Download for the full set.';

// Student status.
$string['status_evaluated'] = 'Evaluated';
$string['status_awaiting'] = 'Awaiting evaluation';
$string['status_blank'] = 'Submitted blank';
$string['status_notattempted'] = 'Not attempted';

// Guide account status.
$string['guidestatus_active'] = 'Active';
$string['guidestatus_suspended'] = 'Suspended';
$string['guidestatus_deleted'] = 'Deleted';
$string['guidestatus_unresolved'] = 'Not identified';

// Columns.
$string['col_batch'] = 'Batch';
$string['col_program'] = 'Program';
$string['col_semester'] = 'Semester';
$string['col_guide'] = 'Guide';
$string['col_guideemail'] = 'Guide Email';
$string['col_guidestatus'] = 'Guide Account';
$string['col_coursecode'] = 'Course Code';
$string['col_allocated'] = 'Allocated';
$string['col_submitted'] = 'Submitted';
$string['col_answered'] = 'Answered';
$string['col_evaluated'] = 'Evaluated';
$string['col_awaiting'] = 'Awaiting Evaluation';
$string['col_blank'] = 'Submitted Blank';
$string['col_notattempted'] = 'Not Attempted';
$string['col_pctsubmitted'] = '% Submitted';
$string['col_pctevaluated'] = '% Evaluated';
$string['col_avgmark'] = 'Avg Mark';
$string['col_maxmark'] = 'Max Mark';
$string['col_evaluatedby'] = 'Evaluated By';
$string['col_lastevaluated'] = 'Last Evaluated';
$string['col_evaluator'] = 'Evaluator';
$string['col_sharedaccount'] = 'Shared Account';
$string['col_graded'] = 'Evaluated';
$string['col_guidecourses'] = 'Guide Courses';
$string['col_coursecodes'] = 'Courses';
$string['col_avgmarkgiven'] = 'Avg Mark Given';
$string['col_firstevaluated'] = 'First Evaluated';
$string['col_studentname'] = 'Student Name';
$string['col_studentemail'] = 'Email';
$string['col_usn'] = 'USN';
$string['col_enrolmentno'] = 'Provisional Enrollment No';
$string['col_programreg'] = 'Program Registered For';
$string['col_status'] = 'Status';
$string['col_submittedon'] = 'Submitted On';
$string['col_mark'] = 'Mark';
$string['col_evaluatedon'] = 'Evaluated On';

// Settings.
$string['coursepattern'] = 'Guide course name pattern';
$string['coursepattern_desc'] = 'SQL LIKE pattern matched against the course full name to find the guide courses. Default: <code>%Reflective Assessment%</code>';
$string['quizpattern'] = 'Assessment name pattern';
$string['quizpattern_desc'] = 'SQL LIKE pattern matched against the quiz name inside each guide course. Default: <code>%Reflective Assessment%</code>';
$string['batchcategories'] = 'Batches to report on';
$string['batchcategories_desc'] = 'Comma-separated category ids of the batches shown in the batch/semester selector. Defaults to <code>17</code> (July, 2025 Batch). Add a category id to bring another batch in; leave empty to show every batch that has guide courses.';
$string['serviceaccounts'] = 'Non-guide accounts';
$string['serviceaccounts_desc'] = 'Email addresses that hold a teacher role in the guide courses but are not the guide - shared programme mailboxes, content team, site admin. One per line. These are never reported as the guide, and marking done from them is flagged in the Evaluated by column.';
