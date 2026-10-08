<?php
/**
 * Exam portal entry point — role-aware (student dashboard vs faculty analytics).
 *
 * Ships dark: reachable by direct URL only. Redirects away when the feature is
 * not enabled. No portal links are surfaced until the `shownav` setting is on.
 *
 * @package   local_exams
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');

require_login();

$syscontext = context_system::instance();

if (isguestuser()) {
    redirect(get_login_url());
}
if (!\local_exams\manager::is_enabled()) {
    throw new moodle_exception('notenabled', 'local_exams');
}

$forcefaculty = optional_param('view', '', PARAM_ALPHA) === 'faculty';
$isfaculty = $forcefaculty
    ? has_capability('local/exams:viewanalytics', $syscontext)
    : \local_exams\manager::is_faculty($USER->id);

$PAGE->set_url('/local/exams/index.php', $forcefaculty ? ['view' => 'faculty'] : []);
$PAGE->set_context($syscontext);
$PAGE->set_title(get_string($isfaculty ? 'facultydashboard' : 'mydashboard', 'local_exams'));
$PAGE->set_heading($SITE->fullname);
$PAGE->set_pagelayout('standard');
$PAGE->requires->css(new moodle_url('/local/exams/styles.css'));

/** @var \local_exams\output\renderer $output */
$output = $PAGE->get_renderer('local_exams');

echo $OUTPUT->header();

if ($isfaculty) {
    $quizid = optional_param('quizid', 0, PARAM_INT);
    echo $quizid ? $output->faculty_exam($quizid) : $output->faculty_dashboard();
} else {
    echo $output->student_dashboard($USER->id);
}

echo $OUTPUT->footer();
