<?php
// Local read-only check: how big / slow the BAZ settings page is to build. CLI only.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/adminlib.php');
\core\session\manager::set_user(get_admin());
$PAGE->set_context(context_system::instance());
$PAGE->set_url('/admin/settings.php', ['section' => 'themesettingbaz']);
$s = microtime(true);
$root = admin_get_root();
$t1 = microtime(true);
$page = $root->locate('themesettingbaz');
$html = $page->output_html();
$t2 = microtime(true);
printf("admin tree %.0f ms; BAZ settings html %.0f ms, %d KB, %d inputs, %d selects, %d textareas, %d filepickers, %d images\n",
    ($t1 - $s) * 1000, ($t2 - $t1) * 1000, strlen($html) / 1024, substr_count($html, '<input'), substr_count($html, '<select'),
    substr_count($html, '<textarea'), substr_count($html, 'filemanager'), substr_count($html, '<img'));
