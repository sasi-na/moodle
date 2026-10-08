<?php
// Local helper: creates a fake test student (no real person) and enrols it in course 1045.
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/enrollib.php');
global $DB;
$u = $DB->get_record('user', ['username' => 'teststudent', 'mnethostid' => $CFG->mnet_localhost_id]);
if (!$u) {
    $id = user_create_user((object)['username' => 'teststudent', 'auth' => 'manual', 'confirmed' => 1, 'mnethostid' => $CFG->mnet_localhost_id,
        'firstname' => 'Test', 'lastname' => 'Student', 'email' => 'teststudent@example.invalid', 'password' => 'Test@1234'], true, false);
    $u = $DB->get_record('user', ['id' => $id]);
}
$instance = $DB->get_record('enrol', ['courseid' => 1045, 'enrol' => 'manual'], '*', IGNORE_MULTIPLE);
$role = $DB->get_record('role', ['shortname' => 'student']);
enrol_get_plugin('manual')->enrol_user($instance, $u->id, $role->id);
echo "teststudent id {$u->id} enrolled in 1045 as student\n";
