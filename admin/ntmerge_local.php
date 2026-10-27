<?php
// Local helper: merge the "Ednxt Top Bar" theme into BAZ (one theme, two navigation layouts).
// 1) copy theme_ednxt settings to theme_baz/nt_* (+ the brand logo file), 2) if the site used
// ednxt, switch to BAZ with Navigation layout = Top bar, 3) uninstall theme_ednxt. CLI only.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/adminlib.php');
\core\session\manager::set_user(get_admin());
$keys = ['scope', 'showtabs', 'cardstyle', 'dockposition', 'barcolour', 'accentcolour', 'pagecolour',
    'holidays', 'customcss', 'brandname', 'brandlogo'];
$old = get_config('theme_ednxt');
$n = 0;
foreach ($keys as $k) {
    if (isset($old->$k) && get_config('theme_baz', 'nt_' . $k) === false) {
        set_config('nt_' . $k, $old->$k, 'theme_baz');
        $n++;
    }
}
echo "settings copied to theme_baz/nt_*: $n\n";
$fs = get_file_storage();
$sys = context_system::instance();
$files = $fs->get_area_files($sys->id, 'theme_ednxt', 'brandlogo', 0, 'id', false);
foreach ($files as $f) {
    if (!$fs->file_exists($sys->id, 'theme_baz', 'nt_brandlogo', 0, $f->get_filepath(), $f->get_filename())) {
        $fs->create_file_from_storedfile(['component' => 'theme_baz', 'filearea' => 'nt_brandlogo'], $f);
        echo "brand logo copied: " . $f->get_filename() . "\n";
    }
}
if ($CFG->theme === 'ednxt') {
    set_config('navlayout', 'top', 'theme_baz');
    set_config('theme', 'baz');
    echo "site theme was Ednxt Top Bar -> now BAZ with Navigation layout = Top bar\n";
} else {
    echo "site theme: {$CFG->theme}; navlayout: " . get_config('theme_baz', 'navlayout') . "\n";
}
// Users/courses/categories that had the child theme forced fall back to the site theme.
foreach (['user', 'course', 'course_categories'] as $t) {
    $c = $DB->count_records($t, ['theme' => 'ednxt']);
    if ($c) {
        $DB->set_field($t, 'theme', '', ['theme' => 'ednxt']);
        echo "$t: $c reset from ednxt to the site theme\n";
    }
}
if (file_exists(__DIR__ . '/.ntuninstall')) {
    $pm = core_plugin_manager::instance();
    if ($pm->get_plugin_info('theme_ednxt')) {
        $progress = new progress_trace_buffer(new text_progress_trace(), false);
        core_plugin_manager::instance()->uninstall_plugin('theme_ednxt', $progress);
        $progress->finished();
        echo "theme_ednxt uninstalled\n";
    }
}
theme_reset_all_caches();
echo "theme caches reset\n";
