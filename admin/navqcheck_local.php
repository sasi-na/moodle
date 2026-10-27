<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/adminlib.php');
\core\session\manager::set_user(get_admin());
$root = admin_get_root(true, false);
$p = $root->locate('theme_baz_navigation');
echo "external page in admin tree: " . ($p ? get_class($p) . ' -> ' . $p->url : 'MISSING') . "\n";
$PAGE->set_context(context_system::instance());
$PAGE->set_url('/theme/baz/navigation.php');
$cards = [];
foreach (\theme_baz\local\newtheme::LAYOUTS as $i => $k) {
    $cards[] = ['key' => $k, 'num' => $i + 1, 'name' => get_string('ntf_' . $k, 'theme_baz'), 'desc' => get_string('nvq_' . $k, 'theme_baz'),
        'c1' => '#000', 'c2' => '#111', 'bg' => '#fff', 'current' => $i === 0, 'sesskey' => 'x', 'action' => 'y'];
}
$t = microtime(true);
$html = $OUTPUT->render_from_template('theme_baz/nt-navswitch', ['cards' => $cards, 'intro' => get_string('nvq_intro', 'theme_baz'), 'settingsurl' => 'z']);
printf("template ok: %d bytes in %.0f ms, cards=%d\n", strlen($html), (microtime(true) - $t) * 1000, substr_count($html, 'nvq-card'));
echo get_string('nvq_applied', 'theme_baz', get_string('ntf_dock', 'theme_baz')) . "\n";
