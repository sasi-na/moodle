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
 * Navigation hook for local_batchvitals.
 *
 * @package   local_batchvitals
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Adds the report to the sidebar drawer for anyone entitled to see it.
 *
 * In Moodle 5 this hook reaches the drawer only -- the primary nav bar is built
 * by a class plugins cannot extend. The gate is a single memoised query (see
 * access::has_any()) because this runs on every page load.
 *
 * @param global_navigation $nav
 */
function local_batchvitals_extend_navigation(global_navigation $nav) {
    global $USER;

    if (!isloggedin() || isguestuser()) {
        return;
    }
    if (!\local_batchvitals\access::has_any((int) $USER->id)) {
        return;
    }

    $nav->add(
        get_string('navlink', 'local_batchvitals'),
        new moodle_url('/local/batchvitals/index.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_batchvitals',
        new pix_icon('i/report', '')
    );
}
