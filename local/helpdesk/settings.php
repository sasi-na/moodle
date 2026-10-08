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
 * Helpdesk settings.
 *
 * Shipped to every site on this codebase and off by default, so a site that does not
 * want a helpdesk is unaffected by its presence.
 *
 * @package    local_helpdesk
 * @copyright  2026 onwards
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_helpdesk', get_string('pluginname', 'local_helpdesk'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configcheckbox(
        'local_helpdesk/enabled',
        get_string('enabled', 'local_helpdesk'),
        get_string('enabled_desc', 'local_helpdesk'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'local_helpdesk/referenceprefix',
        get_string('referenceprefix', 'local_helpdesk'),
        get_string('referenceprefix_desc', 'local_helpdesk'),
        'TKT', PARAM_ALPHANUM, 8
    ));

    $settings->add(new admin_setting_heading(
        'local_helpdesk/slaheading',
        get_string('slaheading', 'local_helpdesk'),
        get_string('slaheading_desc', 'local_helpdesk')
    ));

    $settings->add(new admin_setting_configtext(
        'local_helpdesk/firstresponsehours',
        get_string('firstresponsehours', 'local_helpdesk'),
        get_string('firstresponsehours_desc', 'local_helpdesk'),
        6, PARAM_INT, 4
    ));

    $settings->add(new admin_setting_configtext(
        'local_helpdesk/resolutionhours',
        get_string('resolutionhours', 'local_helpdesk'),
        get_string('resolutionhours_desc', 'local_helpdesk'),
        24, PARAM_INT, 4
    ));

    $settings->add(new admin_setting_configtext(
        'local_helpdesk/specialisthours',
        get_string('specialisthours', 'local_helpdesk'),
        get_string('specialisthours_desc', 'local_helpdesk'),
        12, PARAM_INT, 4
    ));

    // Recorded for reporting and for what students are told, NOT used in deadline
    // arithmetic -- the clocks above are wall-clock and run through the night.
    $settings->add(new admin_setting_configtext(
        'local_helpdesk/coveragehours',
        get_string('coveragehours', 'local_helpdesk'),
        get_string('coveragehours_desc', 'local_helpdesk'),
        '09:00-22:00', PARAM_TEXT, 16
    ));

    $settings->add(new admin_setting_heading(
        'local_helpdesk/retentionheading',
        get_string('retentionheading', 'local_helpdesk'),
        get_string('retentionheading_desc', 'local_helpdesk')
    ));

    $settings->add(new admin_setting_configtext(
        'local_helpdesk/retentiondays',
        get_string('retentiondays', 'local_helpdesk'),
        get_string('retentiondays_desc', 'local_helpdesk'),
        365, PARAM_INT, 5
    ));

    $settings->add(new admin_setting_heading(
        'local_helpdesk/channelheading',
        get_string('channelheading', 'local_helpdesk'),
        get_string('channelheading_desc', 'local_helpdesk')
    ));

    $settings->add(new admin_setting_configtext(
        'local_helpdesk/supportemail',
        get_string('supportemail', 'local_helpdesk'),
        get_string('supportemail_desc', 'local_helpdesk'),
        '', PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_helpdesk/slackurl',
        get_string('slackurl', 'local_helpdesk'),
        get_string('slackurl_desc', 'local_helpdesk'),
        '', PARAM_URL
    ));

    // Told to the student in the confirmation, so they know who is coming back to
    // them and on what number.
    $settings->add(new admin_setting_configtext(
        'local_helpdesk/pmcontact',
        get_string('pmcontact', 'local_helpdesk'),
        get_string('pmcontact_desc', 'local_helpdesk'),
        '', PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_helpdesk/pmcontactfield',
        get_string('pmcontactfield', 'local_helpdesk'),
        get_string('pmcontactfield_desc', 'local_helpdesk'),
        '', PARAM_ALPHANUMEXT
    ));
}
