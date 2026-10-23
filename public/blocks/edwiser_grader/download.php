<?php
// File: blocks/edwiser_grader/download.php
global $CFG;
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/blocks/edwiser_grader/lib.php');
require_login();

$cmid = required_param('id', PARAM_INT);
$format = required_param('download', PARAM_ALPHA);
$mode = optional_param('mode', 'overview', PARAM_ALPHA);
$attempts = optional_param('attempts', 'enrolled_with', PARAM_ALPHA);
$onlygraded = optional_param('onlygraded', 0, PARAM_INT);
$onlyregraded = optional_param('onlyregraded', 0, PARAM_INT);
$slotmarks = optional_param('slotmarks', 1, PARAM_INT);

$PAGE->set_url('/blocks/edwiser_grader/download.php', [
    'id' => $cmid,
    'download' => $format,
    'mode' => $mode,
    'attempts' => $attempts,
    'onlygraded' => $onlygraded,
    'onlyregraded' => $onlyregraded,
    'slotmarks' => $slotmarks
]);
require_once($CFG->libdir . '/tablelib.php');
if (!$cm = get_coursemodule_from_id('quiz', $cmid)) {
    throw new moodle_exception('invalidcoursemodule');
}
if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
    throw new moodle_exception('coursemisconf');
}
require_login($course, false, $cm);
$context = context_module::instance($cm->id);

if (!has_capability('mod/quiz:grade', $context)) {
    throw new required_capability_exception($context, 'mod/quiz:grade', 'nopermissions', 'grade');
}

$quiz = $DB->get_record('quiz', array('id' => $cm->instance), '*', MUST_EXIST);

// Get all users who have attempted the quiz (remove the 5-user limit for export).
$userids = [];
$allusers = get_enrolled_users(context_course::instance($course->id));
foreach ($allusers as $user) {
    $attempts = $DB->get_records('quiz_attempts', ['quiz' => $quiz->id, 'userid' => $user->id, 'preview' => 0], 'timefinish DESC');
    if (!empty($attempts)) {
        $userids[] = $user->id;
    }
}

$rows = [];
$headers = ['lastname', 'firstname', 'emailaddress', 'status', 'started', 'completed', 'duration', 'grade'];
foreach ($userids as $userid) {
    $user = $DB->get_record('user', ['id' => $userid]);
    if (!$user) continue;
    // Get latest finished attempt for this user.
    $attempt = $DB->get_record_sql('SELECT * FROM {quiz_attempts} WHERE quiz = ? AND userid = ? AND preview = 0 ORDER BY timefinish DESC, id DESC', [$quiz->id, $userid]);
    if (!$attempt) continue;
    $status = $attempt->state;
    $started = $attempt->timestart ? userdate($attempt->timestart, '%d %B %Y  %I:%M %p') : '';
    $completed = $attempt->timefinish ? userdate($attempt->timefinish, '%d %B %Y  %I:%M %p') : '';
    $duration = ($attempt->timefinish && $attempt->timestart) ? format_time($attempt->timefinish - $attempt->timestart) : '';
    $grade = get_attempt_grade($attempt->id, $quiz->id);
    $rows[] = [
        'lastname' => $user->lastname,
        'firstname' => $user->firstname,
        'emailaddress' => $user->email,
        'status' => $status,
        'started' => $started,
        'completed' => $completed,
        'duration' => $duration,
        'grade' => $grade,
    ];
}

$filename = clean_filename($quiz->name . '-grader-report');

switch ($format) {
    case 'csv':
    case 'ods':
    case 'excel':
    case 'html':
        // Use Moodle's flexible_table and dataformat plugins for export.
        $table = new flexible_table('edwiser_grader_export');
        $table->define_columns($headers);
        $table->define_headers($headers);
        $table->define_baseurl($PAGE->url->out(false));
        $table->setup();
        if ($table->is_downloading($format, $filename)) {
            foreach ($rows as $row) {
                $table->add_data(array_values($row));
            }
            $table->finish_output();
            exit;
        }
        break;
    case 'json':
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '.json"');
        echo json_encode($rows);
        exit;
    default:
        throw new moodle_exception('unsupportedexportformat', 'block_edwiser_grader', '', $format);
}
