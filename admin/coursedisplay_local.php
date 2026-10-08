<?php
// Local helper: set "one section per page" (coursedisplay=1) for big courses. Records changed course ids so it can be reverted.
// php coursedisplay_local.php test        -> only course 1045
// php coursedisplay_local.php apply       -> all courses with more than 80 activities
// php coursedisplay_local.php revert      -> undo everything this script changed
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
$mode = $argv[1] ?? 'test';
$logfile = __DIR__ . '/coursedisplay_changed.json';
$changed = is_file($logfile) ? json_decode(file_get_contents($logfile), true) : [];
if ($mode === 'revert') {
    foreach ($changed as $cid) { $DB->set_field('course_format_options', 'value', 0, ['courseid' => $cid, 'format' => 'topics', 'name' => 'coursedisplay']); rebuild_course_cache($cid, true); }
    file_put_contents($logfile, '[]'); echo 'reverted ' . count($changed) . " courses\n"; exit;
}
$ids = $mode === 'apply'
    ? $DB->get_fieldset_sql("SELECT course FROM {course_modules} WHERE course > 1 GROUP BY course HAVING COUNT(1) > 80")
    : [1045];
$n = 0;
foreach ($ids as $cid) {
    $opt = $DB->get_record('course_format_options', ['courseid' => $cid, 'format' => 'topics', 'sectionid' => 0, 'name' => 'coursedisplay']);
    if ($opt && (int) $opt->value === 1) { continue; }
    if ($opt) { $DB->set_field('course_format_options', 'value', 1, ['id' => $opt->id]); }
    else { $DB->insert_record('course_format_options', (object) ['courseid' => $cid, 'format' => 'topics', 'sectionid' => 0, 'name' => 'coursedisplay', 'value' => 1]); }
    rebuild_course_cache($cid, true);
    $changed[] = (int) $cid; $n++;
}
file_put_contents($logfile, json_encode(array_values(array_unique($changed))));
echo "set one-section-per-page on $n course(s) ($mode)\n";
