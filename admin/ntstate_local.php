<?php
// Local check (read only): current site theme, theme override settings, login picture and logo settings.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
echo "theme={$CFG->theme} allowuserthemes=" . (int)($CFG->allowuserthemes ?? 0) . " allowcoursethemes=" . (int)($CFG->allowcoursethemes ?? 0) . " allowcategorythemes=" . (int)($CFG->allowcategorythemes ?? 0) . " themedesignermode=" . (int)($CFG->themedesignermode ?? 0) . "\n";
foreach (['loginbg', 'loginlogo', 'customlogo', 'customsidebarlogo', 'logo', 'favicon'] as $k) {
    echo "theme_baz/$k = " . var_export(get_config('theme_baz', $k), true) . "\n";
}
echo "core logo=" . var_export(get_config('core_admin', 'logo'), true) . " logocompact=" . var_export(get_config('core_admin', 'logocompact'), true) . "\n";
echo "themerev={$CFG->themerev}\n";
$fs = get_file_storage();
foreach ($DB->get_records_select('files', "component IN ('theme_baz','theme_ednxt','core_admin') AND filename <> '.'", null, 'timemodified DESC', 'id,component,filearea,filename,timemodified,contenthash', 0, 15) as $f) {
    $exists = is_readable($CFG->dataroot . '/filedir/' . substr($f->contenthash, 0, 2) . '/' . substr($f->contenthash, 2, 2) . '/' . $f->contenthash);
    echo "{$f->component}/{$f->filearea}/{$f->filename} " . userdate($f->timemodified, '%d %b %H:%M') . ($exists ? '' : ' [MISSING ON DISK]') . "\n";
}
