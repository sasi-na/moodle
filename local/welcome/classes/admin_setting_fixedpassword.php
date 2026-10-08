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
 * Admin setting for the fixed account-creation password.
 *
 * @package   local_welcome
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_welcome;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/adminlib.php');

/**
 * A masked password field that refuses a value the site's own password policy would reject.
 *
 * Passwords set from here are written with update_internal_user_password(), which does not
 * consult the policy. Without this check an admin could save a value that every new account
 * receives happily and that the forced-change screen then refuses, leaving learners holding a
 * password they cannot replace with anything until they work out why.
 */
class admin_setting_fixedpassword extends \admin_setting_configpasswordunmask {

    /**
     * Validate before storage.
     *
     * @param string $data
     * @return mixed True when acceptable, otherwise the error string to show.
     */
    public function validate($data) {
        $parent = parent::validate($data);
        if ($parent !== true) {
            return $parent;
        }

        // Empty is the whole point of the setting: it means "generate a random password".
        if (trim((string) $data) === '') {
            return true;
        }

        $errmsg = '';
        if (!check_password_policy($data, $errmsg)) {
            return $errmsg;
        }

        return true;
    }
}
