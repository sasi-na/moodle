<?php
// Local read-only helper: performance-related settings. CLI only.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
foreach (['themedesignermode','cachejs','cachetemplates','langstringcache','yuicomboloading','slasharguments','debug','debugdisplay','perfdebug',
    'enablecourserelativedates','enablestats','enableanalytics','logstore_standard|loglifetime','session_handler_class','sessiontimeout',
    'navcourselimit','navshowallcourses','navshowmycoursecategories','enablecompletion','enableblogs','messaging','enablenotes',
    'theme','allowthemechangeonurl','enablegravatar','enableglobalsearch','dbsessions'] as $n) {
    if (strpos($n, '|')) { [$c, $k] = explode('|', $n); $v = get_config($c, $k); } else { $v = $CFG->$n ?? '(unset)'; }
    echo str_pad($n, 30) . ': ' . (is_scalar($v) ? $v : json_encode($v)) . "\n";
}
echo "\nlargest tables:\n";
foreach ($DB->get_records_sql("SELECT table_name, ROUND((data_length+index_length)/1048576) mb, table_rows FROM information_schema.tables
    WHERE table_schema = DATABASE() ORDER BY data_length+index_length DESC LIMIT 8") as $r) { echo "  {$r->table_name} {$r->mb} MB {$r->table_rows} rows\n"; }
echo 'adhoc tasks queued: ' . $DB->count_records('task_adhoc') . "\n";
