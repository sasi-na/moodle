<?php
// Local helper: keep admin + the 10 real students in keep10_real_ids.json (all their data), permanently remove all other accounts.
// Courses are not touched. The original accounts remain in database moodledb_orig for restore. Run with "apply".
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
@set_time_limit(0);
raise_memory_limit(MEMORY_HUGE);
$apply = in_array('apply', $argv, true);
$admin = get_admin();
$t0 = time();
$say = function($m) use ($t0) { echo '[' . gmdate('H:i:s', time() - $t0) . "] $m\n"; @ob_flush(); flush(); };
$keep = json_decode(file_get_contents(__DIR__ . '/keep10_real_ids.json'), true);
if (!is_array($keep) || count($keep) !== 10) { exit("keep list missing\n"); }
$keepids = array_merge([(int) $admin->id, (int) $CFG->siteguest], array_map('intval', $keep));
$kept = $DB->get_records_list('user', 'id', $keepids, 'id', 'id, username, deleted');
$say('keeping: ' . implode(', ', array_map(fn($u) => $u->username . ($u->deleted ? '(DELETED!)' : ''), $kept)));
if (!$apply) { exit("dry run - pass apply\n"); }
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
