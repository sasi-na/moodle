<?php
// Local helper: list plugins whose DB version is older than the code (schema already up to date); with "apply" set the DB version to the code version. CLI only.
define('CLI_SCRIPT', true);
define('NO_UPGRADE_CHECK', true);
require(__DIR__ . '/../config.php');
$apply = in_array('apply', $argv, true);
$n = 0;
foreach (core_component::get_plugin_types() as $type => $dir) {
    foreach (core_component::get_plugin_list($type) as $name => $pdir) {
        if (!is_file("$pdir/version.php")) { continue; }
        $plugin = new stdClass(); $module = $plugin;
        include("$pdir/version.php");
        $comp = "{$type}_{$name}";
        $db = get_config($comp, 'version');
        $disk = $plugin->version ?? null;
        if ($db !== false && $disk !== null && (float)$db < (float)$disk) {
            $n++;
            echo "$comp: db $db -> code $disk" . ($apply ? ' (set)' : '') . "\n";
            if ($apply) { set_config('version', $disk, $comp); }
        }
    }
}
echo "$n plugin(s)\n";
