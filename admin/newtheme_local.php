<?php
// Local helper for the theme_baz "New theme": lint the new files, refresh the class
// map + string cache (no full purge), and give the new setting its default. CLI only.
define('CLI_SCRIPT', true);
$root = dirname(__DIR__);
$files = ["$root/theme/baz/classes/local/newtheme.php", "$root/theme/baz/classes/output/nt_student_dashboard.php",
    "$root/theme/baz/classes/output/nt_admin_dashboard.php", "$root/theme/baz/lib.php",
    "$root/theme/baz/settings.php", "$root/theme/baz/layout/tmpl-dashboard.php", "$root/theme/baz/lang/en/theme_baz.php", "$root/theme/baz/db/hooks.php", "$root/theme/ednxt/lib.php", "$root/theme/ednxt/settings.php", "$root/theme/ednxt/db/hooks.php", "$root/theme/baz/classes/local/nt_hooks.php", "$root/theme/baz/settings/topbar.php", "$root/theme/baz/classes/local/newtheme.php", "$root/theme/baz/layout/tmpl-login.php", "$root/theme/baz/layout/tmpl-frontpage.php", "$root/theme/baz/classes/local/askjo.php", "$root/theme/baz/classes/output/student_dashboard.php", "$root/theme/baz/navigation.php", "$root/theme/baz/settings.php", "$root/theme/baz/mystyle.php", "$root/theme/baz/classes/local/sms.php", "$root/theme/baz/classes/local/contact.php", "$root/theme/baz/contact_otp.php", "$root/theme/baz/classes/output/core_renderer.php", "$root/local/authpopup/classes/hook_callbacks.php"];
$bad = 0;
foreach ($files as $f) {
    $out = shell_exec('"' . PHP_BINARY . '" -l "' . $f . '" 2>&1');
    if (strpos($out, 'No syntax errors') === false) { $bad++; echo $out; }
}
echo count($files) . " files checked, $bad with errors\n";
if ($bad) { exit(1); }
require($root . '/config.php');
@unlink($CFG->cachedir . '/core_component.php');
remove_dir($CFG->localcachedir . '/mustache', true); echo "mustache cache cleared\n";
cache_helper::purge_by_definition('core', 'string');
cache_helper::purge_by_definition('core', 'langmenu');
try { cache_helper::purge_by_definition('core', 'hookcallbacks'); echo "hook callbacks cache purged\n"; } catch (\Throwable $e) { echo 'hook cache: ' . $e->getMessage() . "\n"; }
unset_config('newthemedesign', 'theme_baz'); // Old switch, replaced by the Themes page.
if (get_config('theme_baz', 'navlayout') === false) { set_config('navlayout', 'left', 'theme_baz'); echo "navlayout default saved: left\n"; }
echo "Site theme: {$CFG->theme}; navigation layout: " . get_config('theme_baz', 'navlayout') . "\n";
$defaults = ['scope' => 'all', 'showtabs' => 1, 'cardstyle' => 'outlined', 'dockposition' => 'right',
    'barcolour' => '#0f1f3d', 'accentcolour' => '#f4b23e', 'pagecolour' => '#eef2f7', 'customcss' => '', 'brandname' => 'JAIN Online', 'brandlogo' => ''];
$set = 0;
$defaults['holidays'] = get_string('ntset_holidays_default', 'theme_baz');
foreach ($defaults as $k => $v) {
    if (get_config('theme_baz', 'nt_' . $k) === false) { set_config('nt_' . $k, $v, 'theme_baz'); $set++; }
}
if ($set) { theme_reset_all_caches(); echo "top bar layout: $set default settings saved, theme caches reset\n"; }
require_once($CFG->dirroot . '/theme/baz/lib.php');
foreach (theme_baz_nt_features() as $f => $d) {
    $cur = get_config('theme_baz', 'nt_f_' . $f);
    if ($cur === false || !in_array($cur, ['auto', 'left', 'top'], true) || file_exists(__DIR__ . '/.ntfauto')) {
        // All features follow Navigation (user request 2026-10-14).
        $nv = $d;
        set_config('nt_f_' . $f, $nv, 'theme_baz'); echo "feature $f: " . var_export($cur, true) . " -> $nv\n";
    }
}
echo "class map + string cache refreshed\n";
echo get_string('settingsnewtheme', 'theme_baz') . "\n";
require_once($CFG->libdir . '/adminlib.php');
\core\session\manager::set_user(get_admin());
$root = admin_get_root(true, true);
$page = $root->locate('themesettingbaz');
echo $page ? "Baz settings page found (" . get_class($page) . ")\n" : "Baz settings page NOT found\n";
$new = admin_output_new_settings_by_page($root);
echo "Unset new settings: " . count($new) . "\n";

