<?php
// Local read-only profiler: time the student and admin dashboard data, piece by piece. CLI only.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
$stu = $DB->get_record('user', ['email' => 'anupamaacharya21@gmail.com']);
\core\session\manager::set_user($stu);
$PAGE->set_context(context_user::instance($stu->id));
$PAGE->set_url('/my/');
$out = $PAGE->get_renderer('core');
$t = function($label, $fn) {
    global $DB;
    $q0 = $DB->perf_get_reads();
    $s = microtime(true);
    try { $fn(); $err = ''; } catch (\Throwable $e) { $err = ' ERR ' . $e->getMessage(); }
    printf("%-34s %7.0f ms  %5d queries%s\n", $label, (microtime(true) - $s) * 1000, $DB->perf_get_reads() - $q0, $err);
};
$t('student nt export (cold)', function() use ($out) { (new \theme_baz\output\nt_student_dashboard())->export_for_template($out); });
$t('student nt export (warm)', function() use ($out) { (new \theme_baz\output\nt_student_dashboard())->export_for_template($out); });
$t('student classic export', function() use ($out) { (new \theme_baz\output\student_dashboard())->export_for_template($out); });
$o = new \theme_baz\output\nt_student_dashboard();
$call = function($m, ...$a) use ($o) { $r = new ReflectionMethod($o, $m); $r->setAccessible(true); return $r->invoke($o, ...$a); };
$now = time();
foreach ([['get_courses', [$stu->id]], ['get_recent_items', []], ['get_fee', [$stu->id]], ['get_levelup', [$stu->id]],
        ['get_streak', [$stu->id, $now]], ['get_week_activity', [$stu->id, $now]], ['get_dashboard_banners', []],
        ['render_block', ['rss_client', 'business']], ['render_block', ['rss_client', 'podcast']], ['render_block', ['weather']],
        ['study_time', [$stu->id, $now]], ['coming_up', [$stu->id, $now]], ['nt_recent_grades', [$stu->id]],
        ['nt_week_stats', [$stu->id, $now]], ['nt_announcements', [$stu->id]]] as [$m, $a]) {
    if (method_exists($o, $m)) {
        $t("  $m(" . implode(',', array_map(function($x) { return is_scalar($x) ? (string) $x : '..'; }, array_slice($a, 0, 1))) . ")", function() use ($call, $m, $a) { $call($m, ...$a); });
    }
}
\core\session\manager::set_user(get_admin());
$t('admin nt export', function() use ($out) { (new \theme_baz\output\nt_admin_dashboard())->export_for_template($out); });
if (class_exists('\theme_baz\output\admin_dashboard')) {
    $t('admin classic export', function() use ($out) { (new \theme_baz\output\admin_dashboard())->export_for_template($out); });
}
