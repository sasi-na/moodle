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
 * Settings for the Superset Dashboard block
 *
 * @package    block_supersetembed
 * @copyright  2025 JO LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {

    // API Gateway URL setting
    $settings->add(new admin_setting_configtext(
        'block_supersetembed/apiurl',
        get_string('apiurl', 'block_supersetembed'),
        get_string('apiurl_desc', 'block_supersetembed'),
        'https://qa.ams.dashboard.thekaios.com/api/v1/superset/guest-token',
        PARAM_URL
    ));

    // Superset URL setting
    $settings->add(new admin_setting_configtext(
        'block_supersetembed/superseturl',
        get_string('superseturl', 'block_supersetembed'),
        get_string('superseturl_desc', 'block_supersetembed'),
        'https://superset.thekaios.com',
        PARAM_URL
    ));

    // Dashboard ID setting
    $settings->add(new admin_setting_configtext(
        'block_supersetembed/dashboardid',
        get_string('dashboardid', 'block_supersetembed'),
        get_string('dashboardid_desc', 'block_supersetembed'),
        '4',
        PARAM_TEXT
    ));
}
