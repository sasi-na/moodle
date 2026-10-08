<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Settings.
 *
 * @package   local_coursetutor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_coursetutor', get_string('pluginname', 'local_coursetutor'));
    $ADMIN->add('localplugins', $settings);
    $settings->add(new admin_setting_configpasswordunmask('local_coursetutor/apikey',
        get_string('apikey', 'local_coursetutor'), get_string('apikey_desc', 'local_coursetutor'), ''));
    $settings->add(new admin_setting_configtext('local_coursetutor/model',
        get_string('model', 'local_coursetutor'), get_string('model_desc', 'local_coursetutor'),
        'gemini-3.5-flash-lite', PARAM_TEXT));
}
