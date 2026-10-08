<?php
// Local helper: clean reinstall of Tutor AI with the Moodle 4.5 build of the Datacurso provider.
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/adminlib.php');
@ini_set('display_errors', '1'); error_reporting(E_ALL & ~E_WARNING & ~E_DEPRECATED);
register_shutdown_function(function() { $e = error_get_last(); if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR])) { echo "FATAL: {$e['message']} in {$e['file']}:{$e['line']}\n"; } });
echo "start {$argv[1]}\n";
require_once($CFG->libdir . '/upgradelib.php');
$mode = $argv[1] ?? '';
\core\session\manager::set_user(get_admin());
$pm = core_plugin_manager::instance();
if ($mode === 'uninstall') {
    foreach (['local_dttutor', 'aiprovider_datacurso'] as $c) {
        if (get_config($c, 'version') === false) { echo "$c not installed\n"; continue; }
        $progress = new progress_trace_buffer(new text_progress_trace(), false);
        $ok = $pm->uninstall_plugin($c, $progress);
        $progress->finished();
        echo "$c uninstall: " . ($ok ? 'OK' : 'FAILED') . "\n" . $progress->get_buffer() . "\n";
    }
    purge_all_caches();
}
if ($mode === 'download') {
    $api = \core\update\api::client();
    $info = $api->find_plugin('aiprovider_datacurso', ANY_VERSION, 405);
    if (!$info || empty($info->version)) { echo 'lookup returned: ' . var_export($info, true) . "\n"; exit(1); }
    $v = $info->version;
    echo "Downloading datacurso {$v->release} ({$v->version}) from {$v->downloadurl}\n";
    $cm = new \core\update\code_manager();
    $zip = $cm->get_remote_plugin_zip($v->downloadurl, $v->downloadmd5);
    if (!$zip) { echo "download failed\n"; exit(1); }
    $tmp = make_request_directory();
    $files = $cm->unzip_plugin_file($zip, $tmp, 'datacurso');
    echo "unzipped " . count($files) . " files into $tmp\n";
    $src = "$tmp/datacurso"; $dst = $CFG->dirroot . '/ai/provider/datacurso';
    if (file_exists($dst)) { echo "$dst already exists\n"; exit(1); }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    @mkdir($dst, 0777, true);
    foreach ($it as $f) { $t = $dst . '/' . $it->getSubPathName(); $f->isDir() ? @mkdir($t, 0777, true) : copy($f->getPathname(), $t); }
    echo "copied to $dst\n";
    $plugin = new stdClass(); include("$dst/version.php"); echo "on disk: {$plugin->component} {$plugin->release} {$plugin->version} requires {$plugin->requires}\n";
}
