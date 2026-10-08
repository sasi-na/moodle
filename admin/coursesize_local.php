<?php
// Local read-only helper: activities per course and course layout. CLI only.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
$rows = $DB->get_records_sql("SELECT c.id, c.format, COUNT(cm.id) n FROM {course} c LEFT JOIN {course_modules} cm ON cm.course = c.id
    WHERE c.id > 1 GROUP BY c.id, c.format");
$b = ['<=30' => 0, '31-80' => 0, '81-150' => 0, '>150' => 0];
foreach ($rows as $r) { $n = $r->n; $b[$n <= 30 ? '<=30' : ($n <= 80 ? '31-80' : ($n <= 150 ? '81-150' : '>150'))]++; }
echo "courses by number of activities: " . json_encode($b) . "\n";
$fmt = $DB->get_records_sql("SELECT format, COUNT(1) n FROM {course} WHERE id > 1 GROUP BY format");
echo 'formats: ' . json_encode(array_map(fn($r) => $r->n, $fmt)) . "\n";
$cd = $DB->get_records_sql("SELECT value, COUNT(1) n FROM {course_format_options} WHERE name = 'coursedisplay' GROUP BY value");
echo 'coursedisplay (0 = all sections on one page, 1 = one section per page): ' . json_encode(array_map(fn($r) => $r->n, $cd)) . "\n";
