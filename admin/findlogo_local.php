<?php
// Local helper: list stored image files that look like a logo (plain mysqli). CLI only.
define('CLI_SCRIPT', true);
define('ABORT_AFTER_CONFIG', true);
require(__DIR__ . '/../config.php');
$port = !empty($CFG->dboptions['dbport']) ? (int) $CFG->dboptions['dbport'] : 3306;
$db = new mysqli($CFG->dbhost, $CFG->dbuser, $CFG->dbpass, $CFG->dbname, $port);
$p = $CFG->prefix;
$r = $db->query("SELECT id, component, filearea, itemid, contextid, filepath, filename, mimetype, filesize, contenthash FROM {$p}files
  WHERE filename <> '.' AND mimetype LIKE 'image/%' AND (LOWER(filename) LIKE '%logo%' OR LOWER(filename) LIKE '%jain%' OR filearea LIKE '%logo%')
  ORDER BY filesize DESC LIMIT 40");
while ($row = $r->fetch_assoc()) { echo implode(' | ', $row) . (is_file($CFG->dataroot . '/filedir/' . substr($row['contenthash'],0,2) . '/' . substr($row['contenthash'],2,2) . '/' . $row['contenthash']) ? ' [on disk]' : ' [missing]') . "\n"; }
$r = $db->query("SELECT fullname, shortname FROM {$p}course WHERE id = 1"); print_r($r->fetch_assoc());
