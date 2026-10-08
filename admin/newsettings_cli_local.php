<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/adminlib.php');
error_reporting(E_ALL); ini_set('display_errors', '1');
\core\session\manager::set_user(get_admin());
try {
    $root = admin_get_root(true, true);
    echo "tree ok\n";
    $new = admin_output_new_settings_by_page($root);
    echo "pages with new settings: " . implode(', ', array_keys($new)) . "\n";
    if (in_array('apply', $argv)) { admin_apply_default_settings(null, false); echo "defaults applied; still new: " . (any_new_admin_settings(admin_get_root(true, true)) ? 'yes' : 'no') . "\n"; }
} catch (\Throwable $e) { echo get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n"; }
