<?php
/**
 * Single-exam deep result page. URL-only (no portal links while dark).
 *
 * @package   local_exams
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');

require_login();

$attemptid = required_param('attempt', PARAM_INT);
$syscontext = context_system::instance();

if (isguestuser()) {
    redirect(get_login_url());
}
if (!\local_exams\manager::is_enabled()) {
    throw new moodle_exception('notenabled', 'local_exams');
}

$attemptobj = \mod_quiz\quiz_attempt::create($attemptid);
$isowner = ((int) $attemptobj->get_userid() === (int) $USER->id);
$isstaff = has_capability('local/exams:viewanalytics', $syscontext);

if (!$isowner && !$isstaff) {
    throw new moodle_exception('noteligible', 'local_exams');
}
// A student may only see their own result once results are released.
if ($isowner && !$isstaff && !\local_exams\manager::results_visible($USER->id)) {
    throw new moodle_exception('resultsnotreleased', 'local_exams');
}
// Only show finished attempts.
if (!$attemptobj->is_finished()) {
    throw new moodle_exception('noteligible', 'local_exams');
}

$PAGE->set_url('/local/exams/result.php', ['attempt' => $attemptid]);
$PAGE->set_context($syscontext);
$PAGE->set_title(get_string('resulttitle', 'local_exams'));
$PAGE->set_heading($SITE->fullname);
$PAGE->set_pagelayout('standard');
$PAGE->requires->css(new moodle_url('/local/exams/styles.css'));

/** @var \local_exams\output\renderer $output */
$output = $PAGE->get_renderer('local_exams');

echo $OUTPUT->header();
echo $output->single_result($attemptobj);
echo $OUTPUT->footer();
