<?php
// Local helper: keep the JAIN Online branding on in both layouts (theme_baz/nt_f_branding = top). CLI only.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
set_config('nt_f_branding', 'top', 'theme_baz');
echo "nt_f_branding = " . get_config('theme_baz', 'nt_f_branding') . "\n";
