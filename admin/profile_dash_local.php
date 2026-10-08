<?php
// Local helper: times the parts of the admin Dashboard. No login needed (localhost only, runs as site admin).
define('NO_MOODLE_COOKIES', true);
require_once(__DIR__ . '/../config.php');
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) { die('Localhost only'); }
@set_time_limit(300);
\core\session\manager::set_user(get_admin());
$PAGE->set_context(context_system::instance()); $PAGE->set_url('/my/index.php'); $PAGE->set_pagetype('my-index'); $PAGE->set_pagelayout('mydashboard');
$T = []; $t = microtime(true);
$lap = function($name) use (&$T, &$t) { $T[] = sprintf("%7.0f ms  %s", (microtime(true) - $t) * 1000, $name); $t = microtime(true); };
$q0 = $DB->perf_get_queries();
$ad = new \theme_baz\output\admin_dashboard();
$ref = new ReflectionClass($ad);
$now = time(); $daystart = usergetmidnight($now);
foreach (['get_kpis' => [$daystart], 'get_engagement' => [$daystart, $daystart - 6*DAYSECS, $daystart - 29*DAYSECS], 'get_login_chart' => [$now]] as $m => $args) {
    if ($ref->hasMethod($m)) { $mm = $ref->getMethod($m); $mm->setAccessible(true); try { $mm->invokeArgs($ad, $args); } catch (\Throwable $e) { $T[] = "$m error: " . $e->getMessage(); } $lap("admin_dashboard::$m"); }
}
try { $ad->export_for_template($OUTPUT); } catch (\Throwable $e) { $T[] = 'export error: ' . $e->getMessage(); } $lap('admin_dashboard::export_for_template (all)');
echo ''; \block_weather\api::dashboard_widget(); $lap('weather widget');
$ts = new \theme_baz\util\theme_settings(); $ts->global_settings(); $lap('theme_settings::global_settings');
ob_start(); try { echo $OUTPUT->header(); } catch (\Throwable $e) { $T[] = 'header error: ' . $e->getMessage(); } ob_end_clean(); $lap('OUTPUT->header() (whole page render incl. navigation)');
header('Content-Type: text/plain');
echo implode("\n", $T) . "\n\nDB queries: " . ($DB->perf_get_queries() - $q0) . "\n";
