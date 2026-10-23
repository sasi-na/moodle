<?php
// Local read-only helper: which plugins differ between code and database. CLI only.
define('CLI_SCRIPT', true);
define('NO_UPGRADE_CHECK', true);
require(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/adminlib.php');
echo "dirroot: $CFG->dirroot\nneeds upgrade: " . (moodle_needs_upgrading() ? 'YES' : 'no') . "\n";
$bad = [];
foreach (core_component::get_plugin_types() as $type => $dir) {
    foreach (core_component::get_plugin_list($type) as $name => $pdir) {
        if (!is_file("$pdir/version.php")) { continue; }
        $plugin = new stdClass(); $module = $plugin;
        include("$pdir/version.php");
        $db = get_config("{$type}_{$name}", 'version');
        if ((string)$db !== (string)($plugin->version ?? '')) { $bad[] = "{$type}_{$name} ($pdir): disk " . ($plugin->version ?? '?') . " db " . var_export($db, true); }
    }
}
echo "plugins differing: " . count($bad) . "\n" . implode("\n", $bad) . "\n";
