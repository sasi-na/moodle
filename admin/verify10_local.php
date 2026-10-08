<?php
// Local read-only helper: the 10 kept students' data, and course count. CLI only.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
require_once($CFG->dirroot . '/course/externallib.php');
$ids = json_decode(file_get_contents(__DIR__ . '/keep10_real_ids.json'), true);
foreach ($ids as $id) {
    $u = $DB->get_record('user', ['id' => $id], 'id, username, deleted, suspended');
    printf("%-32s active=%s courses=%-3d grades=%-4d completed=%-4d\n", $u->username, ($u->deleted || $u->suspended) ? 'NO' : 'yes',
        $DB->count_records('user_enrolments', ['userid' => $id]),
        $DB->count_records_select('grade_grades', 'userid = ? AND finalgrade IS NOT NULL', [$id]),
        $DB->count_records_select('course_modules_completion', 'userid = ? AND completionstate > 0', [$id]));
}
echo 'courses on site: ' . ($DB->count_records('course') - 1) . "\n";
echo 'active accounts: ' . $DB->count_records('user', ['deleted' => 0]) . "\n";
\core\session\manager::set_user($DB->get_record('user', ['id' => $ids[0]]));
$t = microtime(true); core_course_external::get_enrolled_courses_by_timeline_classification('all', 12, 0, 'fullname');
printf("student My courses list: %d ms\n", (microtime(true) - $t) * 1000);
\core\session\manager::set_user(get_admin());
$t = microtime(true); $PAGE->set_url('/my/'); $PAGE->set_context(context_system::instance()); (new global_navigation($PAGE))->initialise();
printf("admin navigation: %d ms\n", (microtime(true) - $t) * 1000);
