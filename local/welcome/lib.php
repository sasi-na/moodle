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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add a "Reset password & email new credentials" action to a user's profile page.
 *
 * Shown to staff who can update the target account (moodle/user:update on the user
 * context) for internally-authenticated, non-deleted accounts — never for one's own
 * profile. Clicking it lands on local/welcome/reset.php which generates a fresh
 * password and emails the branded reset notice.
 *
 * @param \core_user\output\myprofile\tree $tree The myprofile tree to add nodes to.
 * @param stdClass $user The user whose profile is being viewed.
 * @param bool $iscurrentuser Whether the profile belongs to the logged-in user.
 * @param stdClass $course The current course (may be null).
 * @return void
 */
function local_welcome_myprofile_navigation(\core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    if ($iscurrentuser || !empty($user->deleted)) {
        return;
    }

    // Only for internally-authenticated accounts (manual, email, ...) where a
    // Moodle-managed password actually exists to reset.
    $auth = get_auth_plugin($user->auth);
    if (!$auth->is_internal()) {
        return;
    }

    $usercontext = context_user::instance($user->id);
    if (!has_capability('moodle/user:update', $usercontext)) {
        return;
    }

    $url = new moodle_url('/local/welcome/reset.php', array('id' => $user->id));
    $node = new \core_user\output\myprofile\node(
        'administration',
        'local_welcome_reset',
        get_string('resetcredentials', 'local_welcome'),
        null,
        $url
    );
    $tree->add_node($node);
}
