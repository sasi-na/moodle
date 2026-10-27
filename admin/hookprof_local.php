<?php
// Local read-only profiler: time every footer/head hook callback and a full page frame. CLI only.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
$users = ['admin' => get_admin(), 'student' => $DB->get_record('user', ['email' => 'anupamaacharya21@gmail.com'])];
$hm = \core\di::get(\core\hook\manager::class);
foreach ($users as $who => $u) {
    \core\session\manager::set_user($u);
    $PAGE = new moodle_page();
    $GLOBALS['PAGE'] = $PAGE;
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url('/calendar/view.php');
    $PAGE->set_pagelayout('standard');
    $PAGE->set_pagetype('calendar-view');
    $out = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);
    $GLOBALS['OUTPUT'] = $out;
    echo "== $who\n";
    foreach ([\core\hook\output\before_standard_head_html_generation::class, \core\hook\output\before_footer_html_generation::class,
              \core\hook\output\before_standard_top_of_body_html_generation::class, \core\hook\output\before_http_headers::class] as $hook) {
        foreach ($hm->get_callbacks_for_hook($hook) as $cb) {
            if (!empty($cb['disabled'])) { continue; }
            $q = $DB->perf_get_reads();
            $s = microtime(true);
            try {
                $h = new $hook($out);
                ob_start();
                call_user_func(explode('::', $cb['callback']), $h);
                ob_end_clean();
                $e = '';
            } catch (\Throwable $ex) { @ob_end_clean(); $e = ' (' . substr($ex->getMessage(), 0, 60) . ')'; }
            printf("  %6.0f ms %4d q  %s  %s%s\n", (microtime(true) - $s) * 1000, $DB->perf_get_reads() - $q, substr(strrchr($hook, '\\'), 1), $cb['callback'], $e);
        }
    }
    $s = microtime(true); $q = $DB->perf_get_reads();
    ob_start(); echo $out->header(); echo $out->footer(); ob_end_clean();
    printf("  full frame %.0f ms, %d queries\n", (microtime(true) - $s) * 1000, $DB->perf_get_reads() - $q);
}
