<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_ams', get_string('pluginname', 'local_ams'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configtext(
        'local_ams/skipnotifyemails',
        get_string('skipnotifyemails', 'local_ams'),
        get_string('skipnotifyemails_desc', 'local_ams'),
        'bhushan@usdcglobal.com',
        PARAM_RAW_TRIMMED
    ));
}
