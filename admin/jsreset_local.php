<?php
// Local helper: refresh the JavaScript caches (after an AMD build file change). CLI only.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
js_reset_all_caches();
echo "js caches reset, jsrev=" . $CFG->jsrev . "\n";
