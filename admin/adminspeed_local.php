<?php
// Local read-only helper: time the parts of a page that depend on the admin's 1,596 enrolments. CLI only.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
require_once($CFG->dirroot . '/course/externallib.php');
require_once($CFG->dirroot . '/course/lib.php');
\core\session\manager::set_user(get_admin());
$t = microtime(true);
$lap = function($m) use (&$t) { printf("%6.0f ms  %s\n", (microtime(true) - $t) * 1000, $m); $t = microtime(true); };
$my = enrol_get_my_courses('id, fullname'); $lap('enrol_get_my_courses: ' . count($my) . ' courses');
$r = core_course_external::get_enrolled_courses_by_timeline_classification('all', 12, 0, 'fullname'); $lap('My courses page, first 12 (all)');
$r = core_course_external::get_enrolled_courses_by_timeline_classification('inprogress', 12, 0, 'fullname'); $lap('My courses page, first 12 (in progress)');
$r = core_course_external::get_recent_courses(2, 10); $lap('recently accessed courses');
$PAGE->set_url('/my/'); $PAGE->set_context(context_system::instance());
$nav = new global_navigation($PAGE); $nav->initialise(); $lap('global navigation (menus)');
$c = get_course(1045); $mi = get_fast_modinfo($c); $lap('course 1045 modinfo');
// same for a student for comparison
\core\session\manager::set_user($DB->get_record('user', ['username' => 'student01']));
$r = core_course_external::get_enrolled_courses_by_timeline_classification('all', 12, 0, 'fullname'); $lap('student01 My courses first 12');
