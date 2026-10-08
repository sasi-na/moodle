<?php
/**
 * Client-side exam runner page. Builds the single exam payload (starting/
 * resuming a real Moodle attempt) and renders the .exui runner. URL-only.
 *
 * @package   local_exams
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');

require_login();

$cmid = required_param('cmid', PARAM_INT);

if (isguestuser()) {
    redirect(get_login_url());
}
if (!\local_exams\manager::is_enabled()) {
    throw new moodle_exception('notenabled', 'local_exams');
}

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'quiz');
$context = context_module::instance($cm->id);

require_login($course, false, $cm);

$quizid = (int) $cm->instance;
if (!\local_exams\runner\payload_builder::runner_enabled($quizid)) {
    throw new moodle_exception('runner_notenabled', 'local_exams');
}

$pageurl = new moodle_url('/local/exams/start.php', ['cmid' => $cmid]);

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_cm($cm, $course);
$PAGE->set_pagelayout('embedded');
$PAGE->set_title(format_string($cm->name));
$PAGE->requires->css(new moodle_url('/local/exams/styles.css'));

// Build the payload — this starts or resumes the attempt and renders questions.
$payload = \local_exams\runner\payload_builder::build($cmid, $USER->id, $pageurl);

// Proctoring config (if the plugin is installed + enabled for this quiz). The
// runner orchestrates the pre-exam system check and starts the monitor itself
// (the runner bypasses the native attempt page where setup_attempt_page runs).
$proctorcfg = null;
if (get_config('quizaccess_proctor', 'version')) {
    $proctor = $DB->get_record('quizaccess_proctor_settings', ['quizid' => $quizid]);
    if ($proctor && $proctor->enabled && !has_capability('quizaccess/proctor:bypass', $context)) {
        $proctorcfg = \quizaccess_proctor::client_config($proctor, (int) $payload['attemptid']);
    }
}

$PAGE->requires->js_call_amd('local_exams/runner', 'init', [$proctorcfg]);

/** @var \local_exams\output\renderer $output */
$output = $PAGE->get_renderer('local_exams');

echo $OUTPUT->header();
echo $output->runner($payload, $proctorcfg);
echo $OUTPUT->footer();
