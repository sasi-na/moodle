<?php
defined('MOODLE_INTERNAL') || die();
if ($hassiteconfig) {
    $settings = new admin_settingpage('local_aidock', get_string('pluginname', 'local_aidock'));
    $settings->add(new admin_setting_configcheckbox('local_aidock/enabled',
        get_string('enabled', 'local_aidock'), get_string('enabled_desc', 'local_aidock'), 1));
    $ADMIN->add('localplugins', $settings);
}
