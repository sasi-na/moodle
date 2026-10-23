<?php
defined('MOODLE_INTERNAL') || die();
 
if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext(
        'myactivity/partner_api_key',
        get_string('partner_api_key', 'mod_myactivity'),
        get_string('partner_api_key_desc', 'mod_myactivity'),
        '',
        PARAM_TEXT
    ));
 
    $settings->add(new admin_setting_configtext(
        'myactivity/partner_secret',
        get_string('partner_secret', 'mod_myactivity'),
        get_string('partner_secret_desc', 'mod_myactivity'),
        '',
        PARAM_TEXT
    ));
}
