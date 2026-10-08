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
 * @package    local_welcome
 * @copyright  2017 Bas Brands
 * @author     Bas Brands, basbrands.nl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_welcome;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/user/lib.php');

class message {

    public $defaultfields;
    public $welcomefields;
    public $welcomevalues;
    public $customfields;

    public function __construct() {
        $this->defaultfields = $this->get_default_fields();
        $this->welcomefields = $this->get_welcome_fields();
        $this->welcomevalues = $this->get_welcome_values();
        $this->customfields = $this->get_custom_fields();
    }


    private function get_default_fields() {
        $defaultfields = array('username', 'fullname', 'firstname', 'lastname', 'email',
            'address', 'phone1', 'phone2', 'icq', 'skype', 'yahoo', 'aim', 'msn', 'department',
            'institution', 'interests', 'idnumber', 'lang', 'timezone', 'description',
            'city', 'url', 'country'
        );

        return $defaultfields;
    }

    private function get_welcome_fields() {
        $welcomefields = array('sitelink', 'sitename', 'resetpasswordlink', 'loginurl');

        return $welcomefields;
    }

    private function get_custom_fields() {
        $customfields = profile_get_custom_fields(true);
        $returnfields = array();
        foreach ($customfields as $field) {
            $returnfields[] = $field->shortname;
        }
        return $returnfields;
    }

    public function get_user_default_values($user) {
        $values = array();
        foreach ($this->defaultfields as $field) {
            if (isset($user->$field)) {
                $values[$field] = $user->$field;
            } else {
                $values[$field] = '';
            }
            if ($field == 'fullname') {
                $values[$field] = fullname($user);
            }
            if (!empty($user->$field) && $field == 'country') {
                $values[$field]  = get_string($user->country, 'countries');
            }
        }
        return $values;
    }

    public function get_user_custom_values($user) {
        $userinfo = profile_user_record($user->id);
        $values = array();
        foreach ($this->customfields as $field) {
            $fieldname = $field;
            if (isset($userinfo->$fieldname)) {
                $values[$field] = $userinfo->$fieldname;
            } else {
                $values[$field] = '';
            }
        }
        return $values;
    }

    public function get_welcome_values() {
        global $SITE, $CFG;

        $values = array();
        $sitelink = \html_writer::link(new \moodle_url('/'), $SITE->fullname);
        $sitename = $SITE->fullname;
        $resetpasswordlink = \html_writer::link(
            new \moodle_url('/login/forgot_password.php'), get_string('resetpass', 'local_welcome'));
        $loginurl = $CFG->wwwroot . '/login/';
        foreach ($this->welcomefields as $field) {
            $values[$field] = $$field;
        }
        return $values;
    }

    /**
     * Replace all template placeholders in a message with the recipient's values.
     *
     * @param stdClass $user The recipient user record.
     * @param string $message The template body/subject containing [[tag]] placeholders.
     * @param array $extravars Per-send dynamic values, e.g. ['password' => 'abc']. The
     *                         [[password]] tag is always resolved (blanked if not supplied)
     *                         so a raw tag never leaks into an email.
     * @return string
     */
    public function replace_values($user, $message, array $extravars = array()) {
        $cususervars = $this->get_user_custom_values($user);
        $defuservars = $this->get_user_default_values($user);

        foreach ($this->defaultfields as $field) {
            $message = str_replace('[['.$field.']]', $defuservars[$field], $message);
        }

        foreach ($this->customfields as $field) {
            $message = str_replace('[['.$field.']]', $cususervars[$field], $message);
        }

        foreach ($this->welcomefields as $field) {
            $message = str_replace('[['.$field.']]', $this->welcomevalues[$field], $message);
        }

        // Per-send dynamic values. 'password' is always resolved so the tag never
        // leaks unfilled; callers pass the plaintext only when there is one to show.
        $extras = array_merge(array('password' => ''), $extravars);
        foreach ($extras as $key => $value) {
            $message = str_replace('[['.$key.']]', (string) $value, $message);
        }

        return $message;
    }

    /**
     * Build and send the branded credentials email (welcome on account creation, or a
     * password-reset notice) to a single user, rendering the admin-configured template.
     *
     * This is the single send point used by every channel that needs to hand a learner
     * their login details: the user_created observer (CSV upload / self-registration),
     * the AMS create/reset web services, and the admin "reset & email" action. Keeping it
     * here means the [[password]] plaintext is only ever handled in-request and never
     * persisted.
     *
     * @param stdClass $user The recipient (must have a real email).
     * @param string $plainpassword The plaintext password to embed via [[password]] ('' to omit).
     * @param string $variant 'welcome' (default) or 'reset' — selects which template pair to use.
     * @return bool Whether the mail was accepted for delivery.
     */
    public static function send_credentials_email($user, $plainpassword = '', $variant = 'welcome') {
        global $CFG;

        if (empty($user->email)) {
            return false;
        }

        $config = get_config('local_welcome');

        if ($variant === 'reset') {
            $subject = isset($config->message_reset_subject) ? $config->message_reset_subject : '';
            $body    = isset($config->message_reset) ? $config->message_reset : '';
            if (trim(html_to_text($body)) === '') {
                // Fall back to packaged defaults if the admin never configured the reset template.
                $subject = get_string('default_reset_email_subject', 'local_welcome');
                $body    = get_string('default_reset_email', 'local_welcome');
            }
        } else {
            $subject = isset($config->message_user_subject) ? $config->message_user_subject : '';
            $body    = isset($config->message_user) ? $config->message_user : '';
            if (trim(html_to_text($body)) === '') {
                $subject = get_string('default_user_email_subject', 'local_welcome');
                $body    = get_string('default_user_email', 'local_welcome');
            }
        }

        // Sender: the site admin, overridden by the configured local_welcome sender identity.
        $sender = get_admin();
        if ($sender === false) {
            return false;
        }
        $sender = clone($sender);
        if (!empty($config->sender_email)) {
            $sender->email = $config->sender_email;
        }
        if (!empty($config->sender_firstname)) {
            $sender->firstname = $config->sender_firstname;
        }
        if (!empty($config->sender_lastname)) {
            $sender->lastname = $config->sender_lastname;
        }

        $welcome = new self();
        $extravars = array('password' => (string) $plainpassword);
        $bodyhtml = $welcome->replace_values($user, $body, $extravars);
        $subject  = $welcome->replace_values($user, $subject, $extravars);

        return email_to_user($user, $sender, $subject, html_to_text($bodyhtml), $bodyhtml);
    }

    /**
     * Whether new/reset generated passwords should require a change on first login.
     *
     * @return bool
     */
    public static function forcepasswordchange_enabled() {
        $val = get_config('local_welcome', 'forcepasswordchange');
        // Default to enabled (secure) when the setting has never been saved.
        return ($val === false) ? true : (bool) $val;
    }

    /**
     * The password to issue for a new account or a reset.
     *
     * Every password this platform hands out comes from here, so that the choice between a
     * generated one and a configured fixed one is made in a single place. An unset or empty
     * local_welcome/fixedpassword means generate - which is both the default and what a
     * fresh tenant gets, the plugin having no db/install.php to seed config with.
     *
     * @return string The plain-text password.
     */
    public static function new_password(): string {
        $fixed = trim((string) get_config('local_welcome', 'fixedpassword'));

        return ($fixed === '') ? generate_password() : $fixed;
    }
}
