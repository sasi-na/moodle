<?php
define('NO_MOODLE_COOKIES', true);
require_once(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/adminlib.php');
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) { die('Localhost only'); }
header('Content-Type: text/plain'); @set_time_limit(120);
\core\session\manager::set_user(get_admin());
$PAGE->set_context(context_system::instance()); $PAGE->set_url('/admin/upgradesettings.php');
try {
    $root = admin_get_root(true, true);
    $new = admin_output_new_settings_by_page($root);
    echo "pages with new settings: " . implode(', ', array_keys($new)) . "\n";
    if (!empty($_GET['apply'])) { admin_apply_default_settings(null, false); echo "defaults applied\n"; echo 'still new: ' . (any_new_admin_settings(admin_get_root(true, true)) ? 'yes' : 'no') . "\n"; }
} catch (\Throwable $e) {
    echo get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString();
}
