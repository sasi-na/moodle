<?php
// Local helper: compile theme_ednxt CSS once and compare with Baz (CLI only).
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
@set_time_limit(600);
foreach (['ednxt', 'baz'] as $name) {
    $t0 = microtime(true);
    $theme = theme_config::load($name);
    $css = $theme->get_css_content();
    printf("%s: %d bytes in %.1fs; brand navy present: %s\n", $name, strlen($css), microtime(true) - $t0,
        substr_count($css, 'Brand palette') ? 'y' : '?');
    $hashes[$name] = md5(preg_replace('#/theme/(baz|ednxt)/#', '/theme/x/', preg_replace('#theme/(image|font|yui_image)\.php/(baz|ednxt)/#', '', $css)));
}
echo "same CSS (ignoring theme name in urls): " . ($hashes['ednxt'] === $hashes['baz'] ? 'yes' : 'no') . "\n";
