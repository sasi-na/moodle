<?php
// Local read-only helper: show Raison (local_corolair) setup state. Prints no secrets. CLI only.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
$cfg = (array) get_config('local_corolair');
foreach ($cfg as $k => $v) {
    $secret = preg_match('/key|token|secret|pass/i', $k);
    echo str_pad($k, 34) . ': ' . ($secret ? ($v === '' ? '(empty)' : '(set, ' . strlen($v) . ' chars)') : substr((string)$v, 0, 80)) . "\n";
}
echo "\nwebservices enabled: " . (int) $CFG->enablewebservices . ", rest: " . (strpos((string)$CFG->webserviceprotocols, 'rest') !== false ? 1 : 0) . "\n";
echo 'service account: ' . ($DB->record_exists('user', ['username' => 'corolair_webservice', 'deleted' => 0]) ? 'exists' : 'none') . "\n";
$tasks = $DB->get_records_select('task_adhoc', "component = 'local_corolair' OR classname LIKE '%corolair%'", null, '', 'id, classname, faildelay, nextruntime, timestarted');
echo 'pending Raison background tasks: ' . count($tasks) . "\n";
foreach ($tasks as $t) { echo "  {$t->classname} faildelay={$t->faildelay} next=" . userdate($t->nextruntime) . "\n"; }
echo 'cron last run: ' . (($l = get_config('tool_task', 'lastcronstart')) ? userdate($l) : 'never') . "\n";
