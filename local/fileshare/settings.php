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
 * Admin settings for local_fileshare.
 *
 * @package   local_fileshare
 * @copyright 2026 onlinejain.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_fileshare',
        get_string('pluginname', 'local_fileshare'));

    $settings->add(new admin_setting_configcheckbox('local_fileshare/enabled',
        get_string('setting_enabled', 'local_fileshare'),
        get_string('setting_enabled_desc', 'local_fileshare'), 0));

    $settings->add(new admin_setting_configtext('local_fileshare/defaultexpirydays',
        get_string('setting_defaultexpiry', 'local_fileshare'),
        get_string('setting_defaultexpiry_desc', 'local_fileshare'), 7, PARAM_INT));

    $settings->add(new admin_setting_configtext('local_fileshare/maxexpirydays',
        get_string('setting_maxexpiry', 'local_fileshare'),
        get_string('setting_maxexpiry_desc', 'local_fileshare'), 30, PARAM_INT));

    $ADMIN->add('localplugins', $settings);
}

// Registered OUTSIDE the $hassiteconfig block on purpose.
//
// admin_externalpage does its own capability check -- local/fileshare:manageall,
// which is exactly what manage.php enforces -- so the tree entry is safe to
// declare for everyone and Moodle hides it from anyone who fails that check.
//
// Inside $hassiteconfig it was only ever declared for site administrators, so
// the three roles that actually hold manageall (manager, programhead,
// adminreadonly -- 39 accounts on jain) got no menu entry at all and could
// reach the page only by typing the URL. The settings page above stays gated,
// because editing plugin settings genuinely is a site-config action.
if (!isset($ADMIN)) {
    // Defensive: this file is included with $ADMIN in scope in normal operation.
    return;
}
$ADMIN->add('reports', new admin_externalpage('local_fileshare_manage',
    get_string('managelinks', 'local_fileshare'),
    new moodle_url('/local/fileshare/manage.php'),
    'local/fileshare:manageall'));
