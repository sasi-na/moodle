<?php
defined('MOODLE_INTERNAL') || die();

// Ensure the token-purge callback (set_updatedcallback below) is defined when
// settings are saved — Moodle does not auto-load local/*/lib.php on that path.
require_once($CFG->dirroot . '/local/zoomattendance/lib.php');

if ($hassiteconfig) {

    $settings = new admin_settingpage('local_zoomattendance', get_string('pluginname', 'local_zoomattendance'));
    $ADMIN->add('localplugins', $settings);

    $accountid = new admin_setting_configtext(
        'local_zoomattendance/zoom_account_id',
        get_string('zoomaccountid', 'local_zoomattendance'),
        get_string('zoomaccountid_desc', 'local_zoomattendance'),
        '', PARAM_RAW_TRIMMED);
    $accountid->set_updatedcallback('local_zoomattendance_purge_token');
    $settings->add($accountid);

    $clientid = new admin_setting_configtext(
        'local_zoomattendance/zoom_client_id',
        get_string('zoomclientid', 'local_zoomattendance'),
        get_string('zoomclientid_desc', 'local_zoomattendance'),
        '', PARAM_RAW_TRIMMED);
    $clientid->set_updatedcallback('local_zoomattendance_purge_token');
    $settings->add($clientid);

    $clientsecret = new admin_setting_configpasswordunmask(
        'local_zoomattendance/zoom_client_secret',
        get_string('zoomclientsecret', 'local_zoomattendance'),
        get_string('zoomclientsecret_desc', 'local_zoomattendance'),
        '');
    $clientsecret->set_updatedcallback('local_zoomattendance_purge_token');
    $settings->add($clientsecret);

    $settings->add(new admin_setting_configpasswordunmask(
        'local_zoomattendance/webhook_secret',
        get_string('webhooksecret', 'local_zoomattendance'),
        get_string('webhooksecret_desc', 'local_zoomattendance'),
        ''));

    $settings->add(new admin_setting_configcheckbox(
        'local_zoomattendance/webhook_strict',
        get_string('webhookstrict', 'local_zoomattendance'),
        get_string('webhookstrict_desc', 'local_zoomattendance'),
        0));

    $settings->add(new admin_setting_configtext(
        'local_zoomattendance/zoom_host_emails',
        get_string('zoomhostemails', 'local_zoomattendance'),
        get_string('zoomhostemails_desc', 'local_zoomattendance'),
        '', PARAM_RAW_TRIMMED));
}
