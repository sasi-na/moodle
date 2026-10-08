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
 * Hooks for local_candidature.
 *
 * @package   local_candidature
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_candidature\candidature;

/**
 * Is this account one the queue is allowed to capture?
 *
 * This is the safety property the whole feature rests on. submit() fires when a
 * profile is completed, and every existing user completes their profile sooner or
 * later -- so without a hard test here, enabling the queue would sweep the entire
 * user base into "pending" and lock the site. Three conditions, all required:
 *
 *   - the account was created on or after the configured start date, which defaults
 *     to unset and therefore admits nobody;
 *   - the account holds no role assignment anywhere, so no member of staff qualifies;
 *   - the account has no course enrolment, so no existing learner qualifies.
 *
 * @param stdClass $user
 * @return bool
 */
function local_candidature_is_candidate(stdClass $user): bool {
    global $DB;

    $startfrom = local_candidature_start_timestamp();
    if ($startfrom === null || (int) $user->timecreated < $startfrom) {
        return false;
    }
    if ($DB->record_exists('role_assignments', ['userid' => $user->id])) {
        return false;
    }
    if ($DB->record_exists('user_enrolments', ['userid' => $user->id])) {
        return false;
    }
    return true;
}

/**
 * The configured start date as a timestamp.
 *
 * Stored as YYYY-MM-DD because an admin should not have to hand-craft a Unix
 * timestamp to arm a feature this consequential. Anything unparseable is treated as
 * unset, which admits nobody -- the safe direction.
 *
 * @return int|null Null when unset or unparseable.
 */
function local_candidature_start_timestamp(): ?int {
    $raw = trim((string) get_config('local_candidature', 'startfrom'));
    if ($raw === '') {
        return null;
    }
    $ts = strtotime($raw . ' 00:00:00');
    return ($ts === false || $ts <= 0) ? null : $ts;
}

/**
 * Holds a pending candidate on the review page, and puts new ones in the queue.
 *
 * Runs on every require_login(). Kept cheap: the common case is a user with no
 * candidature row and an account older than the start date, which costs one
 * config read and returns.
 *
 * @param mixed $courseorid
 * @param mixed $autologinguest
 * @param mixed $cm
 * @param mixed $setwantsurltome
 * @param mixed $preventredirect
 */
function local_candidature_after_require_login($courseorid = null, $autologinguest = null,
        $cm = null, $setwantsurltome = null, $preventredirect = null) {
    global $USER, $CFG;

    if ($preventredirect || !candidature::enabled()) {
        return;
    }
    if (!isloggedin() || isguestuser() || is_siteadmin()) {
        return;
    }

    $rec = candidature::for_user((int) $USER->id);

    if (!$rec) {
        // Only a genuine new candidate may join, and only once their profile is done.
        // While it is incomplete, core's own redirect to the profile form applies and
        // this hook stays out of the way.
        if (local_candidature_is_candidate($USER) && candidature::profile_complete($USER)) {
            $rec = candidature::submit($USER);
        }
        if (!$rec) {
            return;
        }
    }

    if ($rec->status === candidature::APPROVED) {
        return;
    }

    // Pending and rejected candidates see the holding page and nothing else. The
    // exempt list must include the profile form -- a rejected candidate asked to
    // correct something has to be able to reach it -- and the logout and support
    // routes, or they would be trapped.
    $exempt = [
        '/local/candidature/pending.php',
        '/user/edit.php',
        '/user/editadvanced.php',
        '/login/logout.php',
        '/login/change_password.php',
        '/admin/tool/policy/index.php',
        '/local/helpdesk/raise.php',
        '/local/helpdesk/mytickets.php',
    ];
    $path = (string) parse_url(qualified_me() ?: '', PHP_URL_PATH);
    $relative = substr($path, strlen(parse_url($CFG->wwwroot, PHP_URL_PATH) ?: ''));
    foreach ($exempt as $allow) {
        if (strpos($relative, $allow) === 0) {
            return;
        }
    }

    redirect(new moodle_url('/local/candidature/pending.php'));
}

/**
 * Adds the review queue to the navigation for anyone who can work it.
 *
 * @param global_navigation $nav
 */
function local_candidature_extend_navigation(global_navigation $nav) {
    global $USER;

    if (!candidature::enabled() || !isloggedin() || isguestuser()) {
        return;
    }
    $scope = candidature::reviewable_categories((int) $USER->id);
    if ($scope !== true && empty($scope)) {
        return;
    }

    $nav->add(
        get_string('queue', 'local_candidature'),
        new moodle_url('/local/candidature/index.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_candidature',
        new pix_icon('i/checkpermissions', '')
    );
}
