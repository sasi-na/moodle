<?php
// Moodle performance diagnostic — read-only. Reports the app-side signals that
// determine whether the site is query-bound or config/infra-bound.
//
// Run INSIDE a running Moodle pod/container (it bootstraps Moodle CLI):
//   kubectl -n jo-moodle exec deploy/moodle -- php /var/www/html/diagnostics/perfcheck.php
//   # or locally:  php diagnostics/perfcheck.php
//
// It changes nothing. Safe to run on production.

define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/adminlib.php');

global $DB, $CFG;

function hdr($s) { cli_writeln("\n=== {$s} ==="); }
function row($k, $v, $warn = null) {
    $flag = $warn ? '  <-- ' . $warn : '';
    cli_writeln(sprintf('%-34s %s%s', $k, $v, $flag));
}
function b($v) { return $v ? 'true' : 'false'; }

cli_writeln('Moodle performance check — ' . date('c'));
row('wwwroot', $CFG->wwwroot);
row('Moodle version', $CFG->release ?? '?');

// ---------------------------------------------------------------------------
hdr('Debug flags (these MUST be off in production)');
$debugon = ($CFG->debug ?? 0) > 0;
row('$CFG->debug', (int)($CFG->debug ?? 0), $debugon ? 'NON-ZERO: heavy per-page overhead' : null);
row('$CFG->debugdisplay', b($CFG->debugdisplay ?? false), !empty($CFG->debugdisplay) ? 'leaks errors + slows pages' : null);
row('$CFG->debugdeveloper', b($CFG->debugdeveloper ?? false), !empty($CFG->debugdeveloper) ? 'DEVELOPER MODE — biggest single slowdown' : null);
row('$CFG->perfdebug', (int)($CFG->perfdebug ?? 0), !empty($CFG->perfdebug) ? 'adds timing/logging to every request' : null);
row('$CFG->themedesignermode', b($CFG->themedesignermode ?? false), !empty($CFG->themedesignermode) ? 'recompiles CSS every request' : null);
row('$CFG->cachejs', b($CFG->cachejs ?? true), empty($CFG->cachejs) ? 'JS not cached' : null);
row('$CFG->cachetemplates', b($CFG->cachetemplates ?? true), empty($CFG->cachetemplates) ? 'templates not cached' : null);

// ---------------------------------------------------------------------------
hdr('Cache (MUC) store mapping');
row('$CFG->cachedir', $CFG->cachedir ?? '(default)',
    (!empty($CFG->cachedir) && strpos($CFG->cachedir, '/tmp') === 0)
        ? 'per-pod /tmp: NOT shared across replicas -> repeated rebuilds' : null);
row('$CFG->localcachedir', $CFG->localcachedir ?? '(default)');
try {
    $config = cache_config::instance();
    $stores = $config->get_all_stores();
    $defs = $config->get_definitions();
    $usingfile = false;
    foreach ($stores as $name => $store) {
        row('store: ' . $name, $store['plugin']);
        if ($store['plugin'] === 'file') { $usingfile = true; }
    }
    // Where do the hot definitions actually map?
    $hot = ['core/string', 'core/databasemeta', 'core/config', 'core/coursemodinfo'];
    $mappings = $config->get_definition_mappings ?? null;
    cli_writeln('  (application/session/request default stores below)');
    foreach ($config->get_mode_mappings() as $m) {
        row('  mode ' . $m['mode'], $m['store']);
    }
    if ($usingfile) {
        cli_writeln('  NOTE: a Redis is deployed — consider mapping the Application');
        cli_writeln('        cache mode to a Redis store for multi-pod coherence.');
    }
} catch (Throwable $e) {
    row('cache config', 'could not read: ' . $e->getMessage());
}

// ---------------------------------------------------------------------------
hdr('Session handler');
row('session_handler_class', $CFG->session_handler_class ?? '(file - default)',
    empty($CFG->session_handler_class) ? 'file sessions do not scale across pods' : null);

// ---------------------------------------------------------------------------
hdr('Scheduled / adhoc task backlog (cron health)');
$now = time();
$overdue = $DB->get_records_select('task_scheduled',
    'disabled = 0 AND nextruntime < :now', ['now' => $now - 300], 'nextruntime ASC', '*', 0, 10);
row('overdue scheduled tasks (>5m)', count($overdue), count($overdue) ? 'cron may be stalled' : null);
foreach ($overdue as $t) {
    cli_writeln('   - ' . $t->classname . '  (' . ($now - $t->nextruntime) . 's late)');
}
$adhoc = $DB->count_records('task_adhoc');
$adhocold = $DB->get_field_sql('SELECT MIN(nextruntime) FROM {task_adhoc}');
row('adhoc tasks queued', $adhoc, $adhoc > 500 ? 'large backlog' : null);
if ($adhocold) {
    row('oldest adhoc task age', ($now - $adhocold) . 's', ($now - $adhocold) > 600 ? 'not draining' : null);
}
$faillast = $DB->get_field_sql('SELECT MAX(lastruntime) FROM {task_scheduled} WHERE lastruntime > 0');
row('last scheduled-task run', $faillast ? (($now - $faillast) . 's ago') : 'never',
    ($faillast && ($now - $faillast) > 600) ? 'cron not running recently' : null);

// ---------------------------------------------------------------------------
hdr('Database size signals');
foreach (['logstore_standard_log', 'sessions', 'task_adhoc', 'local_useraudit_log', 'local_studentid_log'] as $tbl) {
    try {
        $n = $DB->count_records($tbl);
        row('rows: ' . $tbl, number_format($n),
            ($tbl === 'logstore_standard_log' && $n > 50_000_000) ? 'huge log table — slows reads/writes' : null);
    } catch (Throwable $e) { /* table may not exist */ }
}

// ---------------------------------------------------------------------------
hdr('Live query timing (round-trip to DB)');
$t = microtime(true);
$DB->get_record_sql('SELECT 1');
row('SELECT 1 round-trip', round((microtime(true) - $t) * 1000, 2) . ' ms',
    ((microtime(true) - $t) * 1000 > 5) ? 'high DB latency (network?)' : null);

$t = microtime(true);
$DB->count_records('user', ['deleted' => 0]);
row('COUNT active users', round((microtime(true) - $t) * 1000, 2) . ' ms');

// OPcache (note: CLI opcache is separate from PHP-FPM's; for the real web
// figure, hit a one-line opcache_get_status() page served through PHP-FPM).
hdr('OPcache (CLI view — see note)');
if (function_exists('opcache_get_status')) {
    $s = @opcache_get_status(false);
    if ($s) {
        row('opcache.enable', b($s['opcache_enabled'] ?? false));
        if (!empty($s['memory_usage'])) {
            $used = $s['memory_usage']['used_memory'] ?? 0;
            $free = $s['memory_usage']['free_memory'] ?? 0;
            row('opcache mem used/free', round($used/1048576).'MB / '.round($free/1048576).'MB');
        }
        if (!empty($s['opcache_statistics'])) {
            row('opcache cached scripts', $s['opcache_statistics']['num_cached_scripts'] ?? '?');
            $hits = $s['opcache_statistics']['hits'] ?? 0;
            $miss = $s['opcache_statistics']['misses'] ?? 0;
            $rate = ($hits + $miss) ? round($hits / ($hits + $miss) * 100, 1) : 0;
            row('opcache hit rate', $rate . '%', $rate < 95 && ($hits + $miss) > 1000 ? 'low — undersized?' : null);
        }
    } else {
        row('opcache', 'not enabled for CLI (this is normal)');
    }
}
cli_writeln('  NOTE: CLI OPcache != PHP-FPM OPcache. For the web figure, serve a');
cli_writeln('        page that calls opcache_get_status() through PHP-FPM.');

cli_writeln("\nDone.\n");
