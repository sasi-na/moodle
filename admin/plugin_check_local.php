<?php
// Local helper: lists additional plugins and any plugins the database expects but whose code is missing.
require_once(__DIR__ . '/../config.php');
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) { die('Localhost only'); }
header('Content-Type: text/plain');
$pm = core_plugin_manager::instance();
$missing = []; $extra = []; $upgrade = [];
foreach ($pm->get_plugins() as $type => $plugins) {
    foreach ($plugins as $name => $p) {
        $st = $p->get_status();
        if ($st === core_plugin_manager::PLUGIN_STATUS_MISSING) { $missing[] = "{$type}_{$name}"; continue; }
        if (!$p->is_standard()) { $extra[] = "{$type}_{$name} | " . $p->displayname . ' | v' . $p->versiondisk . ' | ' . ($p->is_enabled() === false ? 'disabled' : 'enabled'); }
        if (in_array($st, [core_plugin_manager::PLUGIN_STATUS_UPGRADE, core_plugin_manager::PLUGIN_STATUS_NEW])) { $upgrade[] = "{$type}_{$name} ($st)"; }
    }
}
echo "MISSING FROM DISK (in database, code not present): " . count($missing) . "\n  " . implode("\n  ", $missing) . "\n\n";
echo "NEEDS INSTALL/UPGRADE: " . count($upgrade) . "\n  " . implode("\n  ", $upgrade) . "\n\n";
echo "ADDITIONAL (custom) PLUGINS INSTALLED: " . count($extra) . "\n  " . implode("\n  ", $extra) . "\n";
