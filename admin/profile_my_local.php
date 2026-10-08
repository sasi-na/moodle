<?php
// Local helper: renders the real /my/ page as site admin without a browser session, and reports the time.
define('NO_MOODLE_COOKIES', true);
require_once(__DIR__ . '/../config.php');
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) { die('Localhost only'); }
@set_time_limit(120);
\core\session\manager::set_user(get_admin());
$GLOBALS['__t0'] = microtime(true); $GLOBALS['__q0'] = $DB->perf_get_queries();
register_shutdown_function(function() {
    global $DB;
    $html = ob_get_clean();
    header('Content-Type: text/plain');
    echo 'my/index.php rendered in ' . round((microtime(true) - $GLOBALS['__t0']) * 1000) . " ms, " . ($DB->perf_get_queries() - $GLOBALS['__q0']) . " DB queries, " . round(strlen($html) / 1024) . " KB\n";
    $e = error_get_last(); if ($e) echo "last error: {$e['message']} in {$e['file']}:{$e['line']}\n";
    if (preg_match('/<title>([^<]*)/', $html, $m)) echo "title: $m[1]\n";
});
ob_start();
chdir($CFG->dirroot . '/my');
require($CFG->dirroot . '/my/index.php');
