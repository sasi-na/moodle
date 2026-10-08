<?php
// Local helper: renames the main site admin's username to "admin" (password unchanged).
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../config.php');
require_once($CFG->dirroot . '/user/lib.php');
global $DB;
$mainid = (int)explode(',', $CFG->siteadmins)[0];
$main = $DB->get_record('user', ['id' => $mainid], '*', MUST_EXIST);
echo "main admin: id {$main->id} username {$main->username}\n";
$clash = $DB->get_record('user', ['username' => 'admin', 'mnethostid' => $CFG->mnet_localhost_id]);
if ($clash && $clash->id != $main->id) {
    // Free the name: rename the other account so nothing is lost.
    $DB->set_field('user', 'username', 'admin_old_' . $clash->id, ['id' => $clash->id]);
    echo "existing 'admin' account (id {$clash->id}, deleted={$clash->deleted}) renamed to admin_old_{$clash->id}\n";
}
$DB->set_field('user', 'username', 'admin', ['id' => $main->id]);
\core\session\manager::kill_user_sessions($main->id);
echo "done: username is now " . $DB->get_field('user', 'username', ['id' => $main->id]) . " (password unchanged)\n";
