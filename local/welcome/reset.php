<?php
// This file is part of the Local welcome plugin
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Admin action: generate a new random password for a learner and email the updated
 * login details to their registered address, using the branded reset template.
 *
 * @package    local_welcome
 * @copyright  2026 JAIN Online
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$userid = required_param('id', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

require_login();

$user = core_user::get_user($userid, '*', MUST_EXIST);
if ($user->deleted) {
    throw new moodle_exception('userdeleted');
}

$usercontext = context_user::instance($user->id);
require_capability('moodle/user:update', $usercontext);

// Only internally-authenticated accounts have a Moodle-managed password to reset.
$auth = get_auth_plugin($user->auth);
if (!$auth->is_internal() || !$auth->can_change_password()) {
    throw new moodle_exception('cannotresetexternalauth', 'local_welcome', '', null, $user->auth);
}

$profileurl = new moodle_url('/user/profile.php', array('id' => $user->id));
$actionurl  = new moodle_url('/local/welcome/reset.php', array('id' => $user->id));

$PAGE->set_context($usercontext);
$PAGE->set_url($actionurl);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('resetcredentials', 'local_welcome'));
$PAGE->set_heading(fullname($user));
$PAGE->navbar->add(get_string('resetcredentials', 'local_welcome'));

if (!$confirm) {
    // Confirmation step.
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('resetpassword_confirm', 'local_welcome', fullname($user)),
        new single_button(new moodle_url($actionurl, array('confirm' => 1, 'sesskey' => sesskey())),
            get_string('resetcredentials', 'local_welcome'), 'post'),
        new single_button($profileurl, get_string('cancel'), 'get')
    );
    echo $OUTPUT->footer();
    exit;
}

require_sesskey();

// Take a fresh password, store its hash, and (per setting) force a change at next login.
$plainpassword = \local_welcome\message::new_password();
update_internal_user_password($user, $plainpassword);

if (\local_welcome\message::forcepasswordchange_enabled()) {
    set_user_preference('auth_forcepasswordchange', 1, $user);
}

// Log out the user's other sessions so the old password can no longer be used.
\core\session\manager::destroy_user_sessions($user->id);

$sent = \local_welcome\message::send_credentials_email($user, $plainpassword, 'reset');

if ($sent) {
    redirect($profileurl, get_string('resetpassword_done', 'local_welcome', $user->email),
        null, \core\output\notification::NOTIFY_SUCCESS);
} else {
    redirect($profileurl, get_string('resetpassword_mailfailed', 'local_welcome', $user->email),
        null, \core\output\notification::NOTIFY_WARNING);
}
