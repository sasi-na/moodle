<?php
// Local helper: fills admin settings that have no value (stops the endless "New settings" page). Admin only.
require_once(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_admin();
$root = admin_get_root(true, true);
admin_apply_default_settings(null, false);
$fixed = [];
$walk = function($node) use (&$walk, &$fixed) {
    if ($node instanceof admin_category) { foreach ($node->get_children() as $c) { $walk($c); } return; }
    if ($node instanceof admin_settingpage) {
        foreach ($node->settings as $s) {
            if (is_null($s->get_setting())) {
                $d = $s->get_defaultsetting();
                $s->config_write($s->name, is_null($d) ? '' : (is_array($d) ? '' : $d));
                $fixed[] = ($s->plugin ?: 'core') . '/' . $s->name;
            }
        }
    }
};
$walk(admin_get_root(true, true));
purge_all_caches();
echo '<pre>Filled ' . count($fixed) . " settings:\n" . s(implode("\n", $fixed)) . "\n\nRemaining new settings: " . (any_new_admin_settings(admin_get_root(true, true)) ? 'YES' : 'none') . '</pre>';
echo '<a href="' . $CFG->wwwroot . '/admin/index.php">Go to admin</a>';
