<?php
// Local helper: keep admin + 10 students, permanently delete all other accounts (local copy only).
// Usage: php users_keep10_local.php            -> dry run (no changes)
//        php users_keep10_local.php apply      -> do it
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/enrollib.php');
@set_time_limit(0);
raise_memory_limit(MEMORY_HUGE);
$apply = in_array('apply', $argv, true);
$admin = get_admin();

// 10 students: existing teststudent + student01..student09 (fictional).
$students = ['teststudent'];
for ($i = 1; $i <= 9; $i++) { $students[] = sprintf('student%02d', $i); }

echo "main admin: {$admin->username} (id {$admin->id})\n";
echo 'other site admins listed: ' . (count(explode(',', $CFG->siteadmins)) - 1) . "\n";
$total = $DB->count_records_select('user', 'deleted = 0 AND id <> ?', [$CFG->siteguest]);
$existingkeep = $DB->get_records_list('user', 'username', $students, '', 'id, username, deleted');
echo "active accounts now (excluding guest): $total\n";
echo 'students already existing: ' . count($existingkeep) . ' -> ' . implode(', ', array_map(fn($u) => $u->username, $existingkeep)) . "\n";
echo 'enrolments now: ' . $DB->count_records('user_enrolments') . "\n";
if (!$apply) {
    echo "DRY RUN: would keep admin + 10 students and delete about " . ($total - 1 - count($existingkeep)) . " accounts.\n";
    exit(0);
}

// ---------------- APPLY ----------------
$t0 = time();
$say = function($m) use ($t0) { echo '[' . gmdate('H:i:s', time() - $t0) . "] $m\n"; @ob_flush(); flush(); };

// 1) Make sure the 10 students exist (fictional accounts), enrolled as students in course 1045.
$pass = 'Student@' . random_int(1000, 9999);
$manual = enrol_get_plugin('manual');
$inst = $DB->get_record('enrol', ['courseid' => 1045, 'enrol' => 'manual'], '*', IGNORE_MULTIPLE);
$studentrole = $DB->get_field('role', 'id', ['shortname' => 'student']);
$keepids = [(int) $admin->id, (int) $CFG->siteguest];
foreach ($students as $i => $un) {
    $u = $DB->get_record('user', ['username' => $un, 'mnethostid' => $CFG->mnet_localhost_id]);
    if (!$u) {
        $id = user_create_user((object) ['username' => $un, 'password' => $pass, 'firstname' => 'Student',
            'lastname' => sprintf('%02d', $i), 'email' => "$un@example.invalid", 'auth' => 'manual', 'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id], true, false);
        $u = $DB->get_record('user', ['id' => $id]);
    } else if ($u->deleted || $u->suspended) {
        $DB->update_record('user', (object) ['id' => $u->id, 'deleted' => 0, 'suspended' => 0]);
    }
    if ($inst) {
        $manual->enrol_user($inst, $u->id, $studentrole);
    }
    $keepids[] = (int) $u->id;
}
$say('students ready: ' . implode(', ', $students) . " (new ones get password $pass)");

// 2) Users to delete.
[$notin, $params] = $DB->get_in_or_equal($keepids, SQL_PARAMS_QM, 'p', false);
$sel = "SELECT id FROM {user} WHERE deleted = 0 AND id $notin";
$count = $DB->count_records_sql("SELECT COUNT(1) FROM {user} WHERE deleted = 0 AND id $notin", $params);
$say("deleting $count accounts");

// 3) Their data (same tables Moodle's delete_user() cleans, plus their notifications/completion tracking).
$tables = [
    'user_enrolments' => 'userid', 'role_assignments' => 'userid', 'cohort_members' => 'userid',
    'groups_members' => 'userid', 'grade_grades' => 'userid', 'user_preferences' => 'userid',
    'user_info_data' => 'userid', 'user_lastaccess' => 'userid', 'user_private_key' => 'userid',
    'external_tokens' => 'userid', 'sessions' => 'userid', 'user_devices' => 'userid', 'favourite' => 'userid',
    'message_contacts' => 'userid', 'message_contact_requests' => 'userid', 'message_users_blocked' => 'userid',
    'notifications' => 'useridto', 'course_modules_completion' => 'userid', 'course_modules_viewed' => 'userid',
    'course_completions' => 'userid',
];
$dbman = $DB->get_manager();
foreach ($tables as $table => $col) {
    if ($col === null || !$dbman->table_exists($table)) {
        continue;
    }
    $DB->execute("DELETE t FROM {{$table}} t JOIN {user} u ON u.id = t.$col WHERE u.deleted = 0 AND u.id $notin", $params);
    $say("cleaned $table");
}
// User contexts and files in them.
$DB->execute("DELETE f FROM {files} f JOIN {context} c ON c.id = f.contextid AND c.contextlevel = " . CONTEXT_USER . "
              JOIN {user} u ON u.id = c.instanceid WHERE u.deleted = 0 AND u.id $notin", $params);
$DB->execute("DELETE c FROM {context} c JOIN {user} u ON u.id = c.instanceid
              WHERE c.contextlevel = " . CONTEXT_USER . " AND u.deleted = 0 AND u.id $notin", $params);
$say('cleaned user contexts and files');

// 4) Mark the accounts deleted the way delete_user() does (username/email scrambled, cannot log in).
$DB->execute("UPDATE {user} SET deleted = 1, suspended = 1, username = CONCAT('deleted_', id, '_', ?),
              email = MD5(CONCAT('deleted_', id)), idnumber = '', picture = 0, password = '', timemodified = ?
              WHERE deleted = 0 AND id $notin", array_merge([time(), time()], $params));
$say('accounts marked deleted');

// 5) Refresh.
foreach (['user', 'user_enrolments', 'role_assignments', 'grade_grades', 'context'] as $t) {
    $DB->execute("ANALYZE TABLE {{$t}}");
}
purge_all_caches();
$left = $DB->get_records_select('user', 'deleted = 0', null, 'id', 'id, username');
$say('remaining accounts: ' . implode(', ', array_map(fn($u) => $u->username, $left)));
$say('enrolments now: ' . $DB->count_records('user_enrolments'));
$say('DONE');
