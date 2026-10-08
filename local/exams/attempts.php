<?php
/**
 * Attempt-history page for the current student on a quiz. URL-only.
 *
 * @package   local_exams
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');

require_login();

$quizid = required_param('quizid', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$syscontext = context_system::instance();

if (isguestuser()) {
    redirect(get_login_url());
}
if (!\local_exams\manager::is_enabled()) {
    throw new moodle_exception('notenabled', 'local_exams');
}

$isstaff = has_capability('local/exams:viewanalytics', $syscontext);
$targetuser = ($userid && $isstaff) ? $userid : (int) $USER->id;

if ($targetuser === (int) $USER->id && !$isstaff && !\local_exams\manager::results_visible($USER->id)) {
    throw new moodle_exception('resultsnotreleased', 'local_exams');
}

$PAGE->set_url('/local/exams/attempts.php', ['quizid' => $quizid]);
$PAGE->set_context($syscontext);
$PAGE->set_title(get_string('attemptstitle', 'local_exams'));
$PAGE->set_heading($SITE->fullname);
$PAGE->set_pagelayout('standard');
$PAGE->requires->css(new moodle_url('/local/exams/styles.css'));

/** @var \local_exams\output\renderer $output */
$output = $PAGE->get_renderer('local_exams');

echo $OUTPUT->header();
echo $output->attempt_history($targetuser, $quizid);
echo $OUTPUT->footer();
