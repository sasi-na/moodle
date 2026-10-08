<?php
// Local helper: pre-build Moodle's course caches (modinfo) for every course so first visits are fast. Read-only for data.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
@set_time_limit(0);
$t0 = microtime(true);
$ids = $DB->get_fieldset_select('course', 'id', 'id > 1', [], 'id');
$n = 0; $slow = [];
foreach ($ids as $id) {
    $t = microtime(true);
    try { course_modinfo::instance($id); } catch (Throwable $e) { echo "course $id: " . $e->getMessage() . "\n"; }
    $d = microtime(true) - $t;
    if ($d > 2) { $slow[] = "$id (" . round($d, 1) . "s)"; }
    if (++$n % 200 === 0) { echo "[" . round(microtime(true) - $t0) . "s] $n / " . count($ids) . " courses cached\n"; @flush(); }
}
echo "[" . round(microtime(true) - $t0) . "s] all " . count($ids) . " course caches built\n";
echo 'slowest to build: ' . (implode(', ', array_slice($slow, 0, 10)) ?: 'none over 2s') . "\nDONE\n";
