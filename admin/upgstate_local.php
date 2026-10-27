<?php
// Local helper: show (and with "clear", reset) the upgrade-running flag (plain mysqli, no Moodle bootstrap). CLI only.
define('CLI_SCRIPT', true);
define('ABORT_AFTER_CONFIG', true);
require(__DIR__ . '/../config.php');
$port = !empty($CFG->dboptions['dbport']) ? (int) $CFG->dboptions['dbport'] : 3306;
$db = new mysqli($CFG->dbhost, $CFG->dbuser, $CFG->dbpass, $CFG->dbname, $port);
$p = $CFG->prefix;
$q = fn($sql) => ($r = $db->query($sql)) && ($row = $r->fetch_row()) ? $row[0] : null;
$v = $q("SELECT value FROM {$p}config WHERE name='upgraderunning'");
echo "upgraderunning: " . var_export($v, true) . ($v ? ' (' . ((int) $v - time()) . "s left)" : '') . "\n";
echo "theme_ednxt version: " . var_export($q("SELECT value FROM {$p}config_plugins WHERE plugin='theme_ednxt' AND name='version'"), true) . "\n";
echo "theme_ednxt scope: " . var_export($q("SELECT value FROM {$p}config_plugins WHERE plugin='theme_ednxt' AND name='scope'"), true) . "\n";
echo "site theme: " . $q("SELECT value FROM {$p}config WHERE name='theme'") . "\n";
$r2 = $db->query("SELECT filearea, filename, mimetype, filesize FROM {$p}files WHERE component='theme_baz' AND filearea IN ('customlogo','customsidebarlogo','customdmlogo','customsidebardmlogo') AND filename<>'.'"); while ($row = $r2->fetch_row()) { echo implode(" | ", $row) . "\n"; }
if (in_array('clear', $argv ?? [], true) && $v) {
    $db->query("UPDATE {$p}config SET value='0' WHERE name='upgraderunning'");
    echo "cleared\n";
}
