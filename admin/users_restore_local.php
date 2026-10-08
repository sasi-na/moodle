<?php
// Local helper: bring back the 61,649 deleted accounts and their data from moodledb_orig (original production backup).
// Keeps everything else in moodledb as it is now. Run: php users_restore_local.php apply
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
@set_time_limit(0);
if (!in_array('apply', $argv, true)) { echo "dry run - pass 'apply'\n"; }
$t0 = time();
$say = function($m) use ($t0) { echo '[' . gmdate('H:i:s', time() - $t0) . "] $m\n"; @flush(); };
$db = new mysqli('127.0.0.1', 'root', 'sasi', '', 3305);
$db->set_charset('utf8mb4');
$q = function($sql) use ($db) { $r = $db->query($sql); if ($r === false) { throw new Exception($db->error . " in: " . substr($sql, 0, 200)); } return $r; };
$cols = function($t) use ($q) {
    $a = []; $r = $q("SELECT column_name FROM information_schema.columns WHERE table_schema='moodledb' AND table_name='$t'");
    while ($x = $r->fetch_row()) { $a[] = $x[0]; }
    $b = []; $r = $q("SELECT column_name FROM information_schema.columns WHERE table_schema='moodledb_orig' AND table_name='$t'");
    while ($x = $r->fetch_row()) { $b[] = $x[0]; }
    return array_values(array_intersect($a, $b));
};

// 1) Which accounts to bring back: deleted now, active in the backup.
$q("DROP TABLE IF EXISTS moodledb_orig.restore_ids");
$q("CREATE TABLE moodledb_orig.restore_ids (id BIGINT PRIMARY KEY) AS
    SELECT o.id FROM moodledb_orig.acu_user o JOIN moodledb.acu_user c ON c.id = o.id
     WHERE o.deleted = 0 AND c.deleted = 1 AND c.username LIKE 'deleted\\_%'");
$n = $q("SELECT COUNT(*) FROM moodledb_orig.restore_ids")->fetch_row()[0];
$say("accounts to restore: $n");
if (!in_array('apply', $argv, true) || $n == 0) { exit; }

// 2) Account rows back to their original values.
$uc = array_diff($cols('acu_user'), ['id']);
$set = implode(', ', array_map(fn($c) => "c.`$c` = o.`$c`", $uc));
$q("UPDATE moodledb.acu_user c JOIN moodledb_orig.acu_user o ON o.id = c.id JOIN moodledb_orig.restore_ids r ON r.id = c.id SET $set");
$say("accounts restored: " . $db->affected_rows);

// 3) Their data.
$tables = ['user_enrolments' => 'userid', 'role_assignments' => 'userid', 'cohort_members' => 'userid', 'groups_members' => 'userid',
    'grade_grades' => 'userid', 'user_preferences' => 'userid', 'user_info_data' => 'userid', 'user_lastaccess' => 'userid',
    'user_private_key' => 'userid', 'external_tokens' => 'userid', 'user_devices' => 'userid', 'favourite' => 'userid',
    'message_contacts' => 'userid', 'message_contact_requests' => 'userid', 'message_users_blocked' => 'userid',
    'notifications' => 'useridto', 'course_modules_completion' => 'userid', 'course_modules_viewed' => 'userid',
    'course_completions' => 'userid', 'context' => 'instanceid'];
foreach ($tables as $t => $uc) {
    $c = $cols("acu_$t");
    if (!$c) { $say("skip $t (missing)"); continue; }
    $list = '`' . implode('`,`', $c) . '`';
    $where = "o.`$uc` IN (SELECT id FROM moodledb_orig.restore_ids)" . ($t === 'context' ? ' AND o.contextlevel = 30' : '');
    $q("INSERT IGNORE INTO moodledb.acu_$t ($list) SELECT " . implode(',', array_map(fn($x) => "o.`$x`", $c)) . " FROM moodledb_orig.acu_$t o WHERE $where");
    $say("restored $t: " . $db->affected_rows . " rows");
}
$c = $cols('acu_files');
$q("INSERT IGNORE INTO moodledb.acu_files (`" . implode('`,`', $c) . "`) SELECT " . implode(',', array_map(fn($x) => "o.`$x`", $c)) . "
    FROM moodledb_orig.acu_files o JOIN moodledb_orig.acu_context x ON x.id = o.contextid
    WHERE x.instanceid IN (SELECT id FROM moodledb_orig.restore_ids)");
$say("restored user files: " . $db->affected_rows . " rows");

// 4) Refresh Moodle.
foreach (['acu_user', 'acu_user_enrolments', 'acu_role_assignments', 'acu_grade_grades', 'acu_context'] as $t) { $q("ANALYZE TABLE moodledb.$t"); }
purge_all_caches();
$say('active accounts now: ' . $DB->count_records_select('user', 'deleted = 0'));
$say('enrolments now: ' . $DB->count_records('user_enrolments'));
$say('DONE');
