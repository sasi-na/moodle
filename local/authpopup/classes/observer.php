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

namespace local_authpopup;

/**
 * Remembers a login / logout so the next page can show a pop-up.
 *
 * Login: a flag in the session. Logout: the session is destroyed, so a short-lived
 * cookie carries the message to the page the user lands on.
 *
 * @package   local_authpopup
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /** Cookie set on logout. */
    const COOKIE = 'MoodleAuthPopup';

    /**
     * @param \core\event\user_loggedin $event
     */
    public static function loggedin(\core\event\user_loggedin $event): void {
        global $SESSION;
        $SESSION->local_authpopup = 'in';
        $SESSION->local_authpopup_time = time();
    }

    /**
     * @param \core\event\user_loggedinas $event
     */
    public static function loggedinas(\core\event\user_loggedinas $event): void {
        global $SESSION;
        $SESSION->local_authpopup = 'as';
        $SESSION->local_authpopup_time = time();
    }

    /**
     * @param \core\event\user_loggedout $event
     */
    public static function loggedout(\core\event\user_loggedout $event): void {
        global $CFG;
        if (headers_sent() || CLI_SCRIPT) {
            return;
        }
        $path = !empty($CFG->sessioncookiepath) ? $CFG->sessioncookiepath : '/';
        setcookie(self::COOKIE, 'out', [
            'expires' => time() + 120,
            'path' => $path,
            'secure' => is_https(),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }
}
