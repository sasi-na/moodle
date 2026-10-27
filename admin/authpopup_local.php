<?php
// Local helper: lint local_authpopup and show whether it is installed. CLI only.
define('CLI_SCRIPT', true);
$root = dirname(__DIR__);
foreach (glob("$root/local/authpopup/{*,*/*,*/*/*,*/*/*/*}.php", GLOB_BRACE) as $f) {
    $out = shell_exec('"' . PHP_BINARY . '" -l "' . $f . '" 2>&1');
    echo (strpos($out, 'No syntax errors') !== false ? 'ok   ' : 'FAIL ') . substr($f, strlen($root)) . (strpos($out, 'No syntax errors') !== false ? '' : "\n$out") . "\n";
}
require($root . '/config.php');
echo "installed version: " . var_export(get_config('local_authpopup', 'version'), true) . "\n";
