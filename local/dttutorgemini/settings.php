<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Settings.
 *
 * @package   local_dttutorgemini
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_dttutorgemini', get_string('pluginname', 'local_dttutorgemini'));
    $ADMIN->add('localplugins', $settings);
    $settings->add(new admin_setting_configpasswordunmask('local_dttutorgemini/apikey',
        get_string('apikey', 'local_dttutorgemini'), get_string('apikey_desc', 'local_dttutorgemini'), ''));
    $settings->add(new admin_setting_configtext('local_dttutorgemini/model',
        get_string('model', 'local_dttutorgemini'), get_string('model_desc', 'local_dttutorgemini'),
        'gemini-3.5-flash-lite', PARAM_TEXT));
}
