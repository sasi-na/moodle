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
 * Admin settings and queue link for local_candidature.
 *
 * @package   local_candidature
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Always defined: admin_externalpage handles visibility, and testing the capability
// here would throw on first install before db/access.php is read.
$ADMIN->add('users', new admin_externalpage(
    'local_candidature_queue',
    get_string('queue', 'local_candidature'),
    new moodle_url('/local/candidature/index.php'),
    'local/candidature:reviewall'
));

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_candidature_settings',
        get_string('settings', 'local_candidature'));

    $settings->add(new admin_setting_configcheckbox(
        'local_candidature/enabled',
        get_string('enabled', 'local_candidature'),
        get_string('enabled_desc', 'local_candidature'),
        0
    ));

    // The safety catch. Empty admits nobody, which is why it ships empty.
    $settings->add(new admin_setting_configtext(
        'local_candidature/startfrom',
        get_string('startfrom', 'local_candidature'),
        get_string('startfrom_desc', 'local_candidature'),
        '', PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_candidature/slahours',
        get_string('slahours', 'local_candidature'),
        get_string('slahours_desc', 'local_candidature'),
        48, PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_candidature/rejectblockslogin',
        get_string('rejectblockslogin', 'local_candidature'),
        get_string('rejectblockslogin_desc', 'local_candidature'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'local_candidature/programfield',
        get_string('programfield', 'local_candidature'),
        get_string('programfield_desc', 'local_candidature'),
        'mainprogram', PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_candidature/batchfield',
        get_string('batchfield', 'local_candidature'),
        get_string('batchfield_desc', 'local_candidature'),
        'batch', PARAM_ALPHANUMEXT
    ));

    $ADMIN->add('localplugins', $settings);
}
