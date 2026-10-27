<?php
// Local read-only timing probe (no data changes; only rebuilds caches). CLI only.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/adminlib.php');
\core\session\manager::set_user(get_admin());
$t = microtime(true);
$root = admin_get_root(true, true);
printf("admin tree build: %.0f ms\n", (microtime(true) - $t) * 1000);
$t = microtime(true);
$page = $root->locate('themesettingbaz');
$html = $page ? $page->output_html() : '';
printf("BAZ settings page html: %.0f ms, %d KB, file pickers: %d, settings: %d\n", (microtime(true) - $t) * 1000, strlen($html) / 1024,
    substr_count($html, 'filemanager'), substr_count($html, 'id="admin-'));
$t = microtime(true);
$theme = theme_config::load('baz');
$css = $theme->get_css_content();
printf("BAZ CSS from cache: %.0f ms, %d KB\n", (microtime(true) - $t) * 1000, strlen($css) / 1024);
$t = microtime(true);
$c2 = $theme->get_css_content_debug('scss', null, null);
printf("BAZ SCSS full compile (what a theme cache reset costs on the next page): %.0f ms\n", (microtime(true) - $t) * 1000);
printf("themerev=%s jsrev=%s\n", $CFG->themerev, $CFG->jsrev);
