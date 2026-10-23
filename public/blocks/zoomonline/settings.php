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
 * Zoom Online block
 *
 * @package    block_zoomonline
 * @copyright  2024 Ciaran Mac Donncha
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    // General Zoom settings.
    $settings->add(new admin_setting_heading(
            'zoomonline/settingsheader',
            get_string('headerconfig', 'block_zoomonline'),
            get_string('descconfig', 'block_zoomonline')
    ));

    $settings->add(new admin_setting_configtext(
            'block_zoomonline/client_id',
            get_string('clientid', 'block_zoomonline'),
            get_string('clientid_desc', 'block_zoomonline'),
            '', PARAM_TEXT
    ));

    $settings->add(new admin_setting_configcheckbox(
            'block_zoomonline/force_groups',
            get_string('force_groups', 'block_zoomonline'),
            get_string('force_groups_desc', 'block_zoomonline'),
            0 // Default value (unchecked = groups are not forced)
    ));

    $settings->add(new admin_setting_configtext(
            'block_zoomonline/client_secret',
            get_string('clientsecret', 'block_zoomonline'),
            get_string('clientsecret_desc', 'block_zoomonline'),
            '', PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
            'block_zoomonline/account_id',
            get_string('accountid', 'block_zoomonline'),
            get_string('accountid_desc', 'block_zoomonline'),
            '', PARAM_TEXT
    ));

    // Add timezone setting.
    $settings->add(new admin_setting_configtext(
            'block_zoomonline/timezone',
            get_string('timezone', 'block_zoomonline'),
            get_string('timezone_desc', 'block_zoomonline'),
            'Europe/Dublin', // Default value
            PARAM_TEXT
    ));
    // Embed type setting.
    $settings->add(new admin_setting_configselect(
            'block_zoomonline/embedtype',
            get_string('embedtype', 'block_zoomonline'),
            get_string('embedtypedesc', 'block_zoomonline'),
            'book',
            [
                    'book' => get_string('embedbook', 'block_zoomonline'),
                    'label' => get_string('embedlabel', 'block_zoomonline'),
            ]
    ));

    // Attendance tracking setting.
    $settings->add(new admin_setting_configcheckbox(
            'block_zoomonline/useattendance',
            get_string('useattendance', 'block_zoomonline'),
            get_string('useattendance_desc', 'block_zoomonline'),
            0
    ));

    // Define Late Threshold.
    $settings->add(new admin_setting_configtext(
            'block_zoomonline/latetime',
            get_string('latetime', 'block_zoomonline'),
            get_string('latetime_desc', 'block_zoomonline'),
            900, // Default to 15 minutes (900 seconds).
            PARAM_INT
    ));

    // Define Attendance Percentage Threshold.
    $settings->add(new admin_setting_configtext(
            'block_zoomonline/attendance_threshold',
            get_string('attendance_threshold', 'block_zoomonline'),
            get_string('attendance_threshold_desc', 'block_zoomonline'),
            70, // Default to 70%.
            PARAM_INT
    ));

    // Define Early Departure Threshold.
    $settings->add(new admin_setting_configtext(
            'block_zoomonline/early_departure_threshold',
            get_string('early_departure_threshold', 'block_zoomonline'),
            get_string('early_departure_threshold_desc', 'block_zoomonline'),
            900, // Default to 15 minutes (900 seconds).
            PARAM_INT
    ));

    // Storage type setting.
    $options = [
            'aws' => get_string('storagetype_aws', 'block_zoomonline'),
            'local' => get_string('storagetype_local', 'block_zoomonline'),
    ];
    $settings->add(new admin_setting_configselect(
            'block_zoomonline/storagetype',
            get_string('storagetype', 'block_zoomonline'),
            get_string('storagetype_desc', 'block_zoomonline'),
            'aws',
            $options
    ));

    // AWS Settings.
    $settings->add(new admin_setting_configtext(
            'block_zoomonline/aws_key',
            get_string('aws_key', 'block_zoomonline'),
            get_string('aws_key_desc', 'block_zoomonline'),
            '', PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
            'block_zoomonline/aws_secret',
            get_string('aws_secret', 'block_zoomonline'),
            get_string('aws_secret_desc', 'block_zoomonline'),
            '', PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
            'block_zoomonline/aws_region',
            get_string('aws_region', 'block_zoomonline'),
            get_string('aws_region_desc', 'block_zoomonline'),
            '', PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
            'block_zoomonline/aws_bucket',
            get_string('aws_bucket', 'block_zoomonline'),
            get_string('aws_bucket_desc', 'block_zoomonline'),
            '', PARAM_TEXT
    ));

    // Local Folder Storage Path.
    $settings->add(new admin_setting_configtext(
            'block_zoomonline/localfolder',
            get_string('localfolder', 'block_zoomonline'),
            get_string('localfolder_desc', 'block_zoomonline'),
            'videos', PARAM_TEXT
    ));

    $settings->add(new admin_setting_configselect(
            'block_zoomonline/username_field',
            get_string('username_field', 'block_zoomonline'),
            get_string('username_field_desc', 'block_zoomonline'),
            'email', // Default value
            [
                    'email' => get_string('username_field_email', 'block_zoomonline'),
                    'zoomname' => get_string('username_field_zoomname', 'block_zoomonline'),
            ]
    ));

}
