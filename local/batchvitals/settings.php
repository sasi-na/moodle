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
 * Admin settings and report link for local_batchvitals.
 *
 * @package   local_batchvitals
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_batchvitals\vitals;

// Always defined: admin_externalpage handles visibility, and testing the
// capability here would throw on first install before db/access.php is read.
$ADMIN->add('reports', new admin_externalpage(
    'local_batchvitals_report',
    get_string('reportpage', 'local_batchvitals'),
    new moodle_url('/local/batchvitals/index.php'),
    'local/batchvitals:view'
));

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_batchvitals_settings',
        get_string('settings', 'local_batchvitals'));

    // Empty by default on purpose: the batch category ids differ per tenant, and
    // a hardcoded default would be wrong everywhere except the site it came from.
    $settings->add(new admin_setting_configtext(
        'local_batchvitals/batchcategories',
        get_string('batchcategories', 'local_batchvitals'),
        get_string('batchcategories_desc', 'local_batchvitals'),
        '',
        PARAM_SEQUENCE
    ));

    $settings->add(new admin_setting_configtext(
        'local_batchvitals/activedays',
        get_string('activedays', 'local_batchvitals'),
        get_string('activedays_desc', 'local_batchvitals'),
        vitals::DEFAULT_ACTIVE_DAYS,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_batchvitals/rolefilter',
        get_string('rolefilter', 'local_batchvitals'),
        get_string('rolefilter_desc', 'local_batchvitals'),
        1
    ));

    // Read-only: who can currently see a programme, and a link to change it.
    $settings->add(new \local_batchvitals\admin\setting_coordinators(
        'local_batchvitals/coordinators'));

    $ADMIN->add('localplugins', $settings);
}
