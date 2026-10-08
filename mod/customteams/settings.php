<?php
// Admin settings for mod_customteams.
//
// The Sharda build hardcoded the Azure tenant/client/secret in graph_helper.php.
// They live here instead: this codebase builds one image shared by every tenant
// (see deploy/overlays/*), so a hardcoded credential would ship to all of them,
// and a rotated secret would need a rebuild rather than a settings change.

defined('MOODLE_INTERNAL') || die();

// Always added: admin_externalpage handles its own visibility, and the page is
// needed outside fulltree so the settings page can link to it.
$ADMIN->add('modsettings', new admin_externalpage(
    'mod_customteams_checkaccounts',
    get_string('checkaccounts', 'customteams'),
    new moodle_url('/mod/customteams/checkaccounts.php'),
    'moodle/site:config'
));

if ($ADMIN->fulltree) {

    $settings->add(new admin_setting_heading(
        'customteams/azureheading',
        get_string('settings_azure', 'customteams'),
        get_string('settings_azure_desc', 'customteams')
    ));

    $settings->add(new admin_setting_configtext(
        'customteams/tenantid',
        get_string('settings_tenantid', 'customteams'),
        get_string('settings_tenantid_desc', 'customteams'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'customteams/clientid',
        get_string('settings_clientid', 'customteams'),
        get_string('settings_clientid_desc', 'customteams'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'customteams/clientsecret',
        get_string('settings_clientsecret', 'customteams'),
        get_string('settings_clientsecret_desc', 'customteams'),
        ''
    ));

    $settings->add(new admin_setting_heading(
        'customteams/meetingheading',
        get_string('settings_meeting', 'customteams'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'customteams/timezone',
        get_string('settings_timezone', 'customteams'),
        get_string('settings_timezone_desc', 'customteams'),
        'Asia/Kolkata',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'customteams/autorecord',
        get_string('settings_autorecord', 'customteams'),
        get_string('settings_autorecord_desc', 'customteams'),
        1
    ));

    $settings->add(new admin_setting_heading(
        'customteams/organizerheading',
        get_string('settings_organizer', 'customteams'),
        get_string('settings_organizer_desc', 'customteams')
    ));

    $settings->add(new \mod_customteams\admin\setting_accountmap());

    $settings->add(new admin_setting_description(
        'customteams/checkaccountslink',
        '',
        html_writer::link(
            new moodle_url('/mod/customteams/checkaccounts.php'),
            get_string('checkaccountslink', 'customteams')
        )
    ));

    $settings->add(new admin_setting_configtextarea(
        'customteams/fallbackaccounts',
        get_string('settings_fallbackaccounts', 'customteams'),
        get_string('settings_fallbackaccounts_desc', 'customteams'),
        '',
        PARAM_RAW
    ));

    $settings->add(new admin_setting_configcheckbox(
        'customteams/allowprofilefallback',
        get_string('settings_allowprofilefallback', 'customteams'),
        get_string('settings_allowprofilefallback_desc', 'customteams'),
        0
    ));
}
