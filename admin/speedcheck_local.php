<?php
// Local read-only helper: what makes the site slow (PHP, opcache, DB server, Moodle caches, page timings). CLI only.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
echo "PHP " . PHP_VERSION . " | memory_limit " . ini_get('memory_limit') . " | max_execution_time " . ini_get('max_execution_time') . "\n";
echo "xdebug loaded: " . (extension_loaded('xdebug') ? 'YES (slows every request)' : 'no') . "\n";
$oc = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;
echo "opcache (CLI view): enable=" . ini_get('opcache.enable') . " cli=" . ini_get('opcache.enable_cli') . " mem=" . ini_get('opcache.memory_consumption')
    . " files=" . ini_get('opcache.max_accelerated_files') . " revalidate=" . ini_get('opcache.revalidate_freq') . " php.ini=" . php_ini_loaded_file() . "\n";
echo "realpath_cache_size=" . ini_get('realpath_cache_size') . "\n";
foreach (['debug', 'debugdisplay', 'perfdebug', 'themedesignermode', 'cachejs', 'cachetemplates', 'langstringcache', 'session_handler_class', 'dbsessions', 'localcachedir', 'cachedir'] as $k) {
    echo str_pad($k, 22) . ': ' . (isset($CFG->$k) ? (is_scalar($CFG->$k) ? $CFG->$k : json_encode($CFG->$k)) : '(unset)') . "\n";
}
echo "\nDB server:\n";
foreach (['version', 'innodb_buffer_pool_size', 'innodb_log_file_size', 'innodb_flush_log_at_trx_commit', 'max_connections', 'query_cache_size', 'tmp_table_size', 'max_heap_table_size', 'innodb_file_per_table', 'skip_name_resolve'] as $v) {
    $r = $DB->get_record_sql("SHOW VARIABLES LIKE ?", [$v]);
    echo "  $v = " . ($r ? $r->value : '?') . "\n";
}
$size = $DB->get_field_sql("SELECT ROUND(SUM(data_length+index_length)/1048576) FROM information_schema.tables WHERE table_schema = DATABASE()");
echo "  database size: {$size} MB\n";
echo "\nMUC stores in use:\n";
$factory = cache_factory::instance();
$cfg = cache_config::instance();
foreach ($cfg->get_all_stores() as $name => $s) {
    echo "  $name ({$s['plugin']})\n";
}
foreach ($cfg->get_mode_mappings() as $m) {
    echo "  mode {$m['mode']} -> {$m['store']}\n";
}
echo "\nlogstore rows: " . $DB->count_records('logstore_standard_log') . "; sessions: " . $DB->count_records('sessions') . "; adhoc tasks: " . $DB->count_records('task_adhoc') . "\n";
echo "last cron run: " . userdate((int) get_config('tool_task', 'lastcronstart')) . "\n";
