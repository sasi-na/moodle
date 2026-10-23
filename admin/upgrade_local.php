<?php
// Local helper: run Moodle's own CLI upgrade non-interactively (refreshes the plugin version hash). CLI only.
$_SERVER['argv'] = ['upgrade.php', '--non-interactive'];
$argv = $_SERVER['argv'];
require(__DIR__ . '/cli/upgrade.php');
