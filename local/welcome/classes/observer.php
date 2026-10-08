<?php
// This file is part of Moodle - http://moodle.org/
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
 * This plugin sends users a welcome message after logging in
 * and notify a moderator a new user has been added
 * it has a settings page that allow you to configure the messages
 * send.
 *
 * @package    local
 * @subpackage welcome
 * @copyright  2017 Bas Brands, basbrands.nl, bas@sonsbeekmedia.nl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_welcome;

defined('MOODLE_INTERNAL') || die();

class observer {

    public static function send_welcome(\core\event\user_created $event) {
        global $CFG, $SITE;

        $eventdata = $event->get_data();

        $user = \core_user::get_user($eventdata['objectid']);

        $sender = get_admin();

        // Sender can be false when unit tests are running.
        if ($sender === false) {
            return;
        }

        if (!empty($user->email)) {

            $config = get_config('local_welcome');

            $moderator = clone($sender);

            if (!empty($config->auth_plugins)) {
                $auths = explode(',', $config->auth_plugins);
                if (!in_array($user->auth, $auths)) {
                    return '';
                }
            } else {
                return '';
            }

            $moderator->email = $config->moderator_email;

            $sender->email = $config->sender_email;
            $sender->firstname = $config->sender_firstname;
            $sender->lastname = $config->sender_lastname;

            $messageuserenabled = $config->message_user_enabled;

            $messagemoderatorenabled = $config->message_moderator_enabled;
            $messagemoderator = $config->message_moderator;
            $messagemoderatorsubject = $config->message_moderator_subject;

            $welcome = new \local_welcome\message();

            // ── User welcome email, carrying the login password ──────────────────
            //
            // The plaintext password is only knowable at this instant. Core flags
            // accounts that need a generated password with the 'create_password'
            // user preference (set by CSV upload's "to be generated", admin-UI
            // "generate password and notify", and auth/db). When that flag is set we
            // generate here, store the hash, and email the plaintext through the
            // branded template — then clear the flag so core's send_new_user_passwords
            // cron task does NOT also send its (unbranded) copy.
            //
            // Accounts created with an admin-chosen password (no flag) still get the
            // welcome email, just with [[password]] blanked.
            if (!empty($sender->email) && $messageuserenabled) {
                $plainpassword = '';

                if (get_user_preferences('create_password', 0, $user) == 1) {
                    $plainpassword = \local_welcome\message::new_password();
                    update_internal_user_password($user, $plainpassword);

                    if (\local_welcome\message::forcepasswordchange_enabled()) {
                        set_user_preference('auth_forcepasswordchange', 1, $user);
                    }

                    // We are delivering the password now; stop the core cron duplicate.
                    unset_user_preference('create_password', $user);
                }

                \local_welcome\message::send_credentials_email($user, $plainpassword, 'welcome');
            }

            // ── Moderator notification (unchanged) ───────────────────────────────
            $messagemoderator = $welcome->replace_values($user, $messagemoderator);
            $messagemoderatorsubject = $welcome->replace_values($user, $messagemoderatorsubject);

            if (!empty($messagemoderator) && !empty($sender->email) && $messagemoderatorenabled) {
                email_to_user($moderator, $sender, $messagemoderatorsubject,
                    html_to_text($messagemoderator), $messagemoderator);
            }
        }
    }

    /**
     * Generate, store and email a new password when an account that already exists is
     * flagged for one — currently only by a CSV upload run with password generation on.
     *
     * This mirrors the generation half of send_welcome() but sends the 'reset' template:
     * the recipient already has an account and did not ask for the change, so the welcome
     * copy ("your account has been created", orientation session) would be wrong for them.
     * It is kept separate from send_welcome() rather than sharing a helper so that the live
     * new-account path is not touched, and it sends no moderator copy — a bulk upload would
     * otherwise mail the moderator once per row.
     *
     * @param \core\event\user_updated $event
     * @return void
     */
    public static function send_reset_on_update(\core\event\user_updated $event) {
        $config = get_config('local_welcome');

        // Off by default: regenerating invalidates a working password for a learner who
        // did not ask for it, so this stays behind an explicit switch.
        if (empty($config->resetexistingonupload)) {
            return;
        }

        if (empty($config->message_user_enabled) || empty($config->sender_email)) {
            return;
        }

        $eventdata = $event->get_data();
        $userid = $eventdata['objectid'];

        // One send per user per request, whichever else touches the account. Also stops
        // the password write below from re-entering this observer.
        static $handled = array();
        if (isset($handled[$userid])) {
            return;
        }

        $user = \core_user::get_user($userid);

        if (!$user || $user->deleted || $user->suspended || empty($user->email)) {
            return;
        }

        if (empty($config->auth_plugins) || !in_array($user->auth, explode(',', $config->auth_plugins))) {
            return;
        }

        // Only Moodle-held passwords can be regenerated; an external plugin owns its own.
        try {
            if (!get_auth_plugin($user->auth)->is_internal()) {
                return;
            }
        } catch (\Exception $e) {
            return;
        }

        // The flag is what distinguishes an account waiting for credentials from an ordinary
        // profile edit. A just-created account never reaches here: send_welcome() clears the
        // flag, and where it does not the auth/enabled gates above have already matched.
        if (get_user_preferences('create_password', 0, $user) != 1) {
            return;
        }

        $handled[$userid] = true;

        $plainpassword = \local_welcome\message::new_password();
        update_internal_user_password($user, $plainpassword);

        if (\local_welcome\message::forcepasswordchange_enabled()) {
            set_user_preference('auth_forcepasswordchange', 1, $user);
        }

        // We are delivering the password now; stop the core cron duplicate.
        unset_user_preference('create_password', $user);

        \local_welcome\message::send_credentials_email($user, $plainpassword, 'reset');
    }

}
