<?php
// Local read-only helper: the 10 active students with the most course activity. CLI only.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
$sql = "SELECT u.id, u.username, u.firstname, u.lastname, u.lastaccess,
               (SELECT COUNT(1) FROM {user_enrolments} ue WHERE ue.userid = u.id) enrols,
               (SELECT COUNT(1) FROM {grade_grades} g WHERE g.userid = u.id AND g.finalgrade IS NOT NULL) grades,
               (SELECT COUNT(1) FROM {course_modules_completion} c WHERE c.userid = u.id AND c.completionstate > 0) done
          FROM {user} u
         WHERE u.deleted = 0 AND u.suspended = 0 AND u.id > 2 AND u.auth <> 'nologin'
           AND u.username NOT LIKE 'student0%' AND u.username <> 'teststudent'
           AND EXISTS (SELECT 1 FROM {role_assignments} ra JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student' WHERE ra.userid = u.id)
           AND NOT EXISTS (SELECT 1 FROM {role_assignments} ra JOIN {role} r ON r.id = ra.roleid AND r.shortname IN ('editingteacher','teacher','manager','coursecreator') WHERE ra.userid = u.id)
           AND u.lastaccess > 0
      ORDER BY u.lastaccess DESC";
$cands = $DB->get_records_sql($sql, null, 0, 300);
usort($cands, fn($a, $b) => ($b->grades + $b->done + 5 * $b->enrols) <=> ($a->grades + $a->done + 5 * $a->enrols));
$pick = array_slice($cands, 0, 10);
$ids = [];
foreach ($pick as $u) {
    $ids[] = $u->id;
    printf("%-8d %-32s %-28s courses=%-3d grades=%-4d completed=%-4d last=%s\n", $u->id, $u->username, "$u->firstname $u->lastname",
        $u->enrols, $u->grades, $u->done, userdate($u->lastaccess, '%d %b %Y'));
}
file_put_contents(__DIR__ . '/keep10_real_ids.json', json_encode($ids));
echo "saved ids to keep10_real_ids.json\n";
