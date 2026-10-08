<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Exams';

// Capabilities.
$string['exams:viewownresults']      = 'View own exam results';
$string['exams:downloadresultsheet'] = 'Download own result sheet';
$string['exams:viewanalytics']       = 'View exam cohort analytics';
$string['exams:export']              = 'Export exam analytics';

// Settings.
$string['setting_enabled']              = 'Enable Exams';
$string['setting_enabled_desc']         = 'Master switch. When off, the exam portal pages redirect away and nothing is exposed. Pages remain reachable by direct URL only when on but links are hidden.';
$string['setting_shownav']              = 'Show navigation links';
$string['setting_shownav_desc']         = 'Inject the exam portal links into the site navigation. When off, the portal is reachable by direct URL only.';
$string['setting_resultsreleased']      = 'Results released';
$string['setting_resultsreleased_desc'] = 'When on, students can see their exam results and download result sheets.';
$string['setting_facultyrole']          = 'Faculty role shortname';
$string['setting_facultyrole_desc']     = 'Optional role shortname that grants the faculty analytics view, in addition to the manager archetype.';

// Portal / pages.
$string['examportal']      = 'Exam portal';
$string['mydashboard']     = 'My exams';
$string['facultydashboard'] = 'Exam analytics';
$string['resulttitle']     = 'Exam result';
$string['attemptstitle']   = 'Attempt history';

// Messages.
$string['notenabled']      = 'The exam portal is not currently available.';
$string['noteligible']     = 'You do not have access to the exam portal.';
$string['resultsnotreleased'] = 'Your exam results have not been released yet.';
$string['noresults']       = 'No exam results to show yet.';

// Tasks.
$string['task_compute_attempt_stats'] = 'Compute exam attempt analytics';
$string['task_compute_cohort_stats']  = 'Compute exam cohort analytics';
$string['task_finalize_abandoned']    = 'Finalize abandoned exam-runner attempts';

// Exam runner.
$string['runner_enable']       = 'Use the Exams client-side runner';
$string['runner_enable_help']  = 'When enabled, students take this quiz in the low-load client-side exam runner: the whole quiz is delivered at once, answered offline-tolerantly, and submitted once for background grading. Supports deferred-feedback behaviour and standard question types.';
$string['runner_launch']       = 'Start exam';
$string['runner_instructions'] = 'Read the instructions, complete the system check, then start your exam. Your answers are saved automatically.';
$string['runner_systemcheck']  = 'System check';
$string['runner_submit']       = 'Submit exam';
$string['runner_submitconfirm']= 'Submit your exam? You will not be able to change your answers afterwards.';
$string['runner_submitted']    = 'Your exam has been submitted. Your result will be available once processing completes.';
$string['runner_timeup']       = 'Time is up — your exam is being submitted automatically.';
$string['runner_notenabled']   = 'The exam runner is not enabled for this quiz.';

// Privacy.
$string['privacy:metadata:local_exams_attempt_stats'] = 'Precomputed per-attempt analytics derived from quiz attempts.';
$string['privacy:metadata:local_exams_attempt_stats:userid'] = 'The user the analytics belong to.';
$string['privacy:metadata:local_exams_attempt_stats:statsjson'] = 'The computed statistics for the attempt.';
$string['privacy:metadata:local_exams_resultsheets'] = 'Generated PDF result sheets.';
$string['privacy:metadata:local_exams_resultsheets:userid'] = 'The user the result sheet belongs to.';
