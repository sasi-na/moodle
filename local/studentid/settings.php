<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_studentid', get_string('pluginadministration', 'local_studentid'));
    $ADMIN->add('localplugins', $settings);

    // ── Eligibility ────────────────────────────────────────────────────────────
    $settings->add(new admin_setting_heading(
        'local_studentid/hdr_eligibility',
        get_string('setting_hdr_eligibility', 'local_studentid'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_studentid/eligiblebatches',
        get_string('setting_eligiblebatches', 'local_studentid'),
        get_string('setting_eligiblebatches_desc', 'local_studentid'),
        \local_studentid\manager::DEFAULT_ELIGIBLE_BATCHES,
        PARAM_TEXT
    ));

    // ── Card branding ──────────────────────────────────────────────────────────
    $settings->add(new admin_setting_heading(
        'local_studentid/hdr_branding',
        get_string('setting_hdr_branding', 'local_studentid'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_studentid/universityname',
        get_string('setting_universityname', 'local_studentid'),
        get_string('setting_universityname_desc', 'local_studentid'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_studentid/universityaddress',
        get_string('setting_universityaddress', 'local_studentid'),
        get_string('setting_universityaddress_desc', 'local_studentid'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_studentid/universitywebsite',
        get_string('setting_universitywebsite', 'local_studentid'),
        get_string('setting_universitywebsite_desc', 'local_studentid'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_studentid/universitytag',
        get_string('setting_universitytag', 'local_studentid'),
        get_string('setting_universitytag_desc', 'local_studentid'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_studentid/signatorytitle',
        get_string('setting_signatorytitle', 'local_studentid'),
        get_string('setting_signatorytitle_desc', 'local_studentid'),
        'Authorised Signatory',
        PARAM_TEXT
    ));

    // ── Card validity ──────────────────────────────────────────────────────────
    $settings->add(new admin_setting_heading(
        'local_studentid/hdr_validity',
        get_string('setting_hdr_validity', 'local_studentid'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_studentid/validityyears',
        get_string('setting_validityyears', 'local_studentid'),
        get_string('setting_validityyears_desc', 'local_studentid'),
        '1',
        PARAM_INT
    ));

    // ── Uploads ────────────────────────────────────────────────────────────────
    $settings->add(new admin_setting_heading(
        'local_studentid/hdr_uploads',
        get_string('setting_hdr_uploads', 'local_studentid'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_studentid/maxphotosize',
        get_string('setting_maxphotosize', 'local_studentid'),
        get_string('setting_maxphotosize_desc', 'local_studentid'),
        '2097152',
        PARAM_INT
    ));

    // ── Notifications ──────────────────────────────────────────────────────────
    $settings->add(new admin_setting_heading(
        'local_studentid/hdr_notifications',
        get_string('setting_hdr_notifications', 'local_studentid'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_studentid/emailnotifications',
        get_string('setting_emailnotifications', 'local_studentid'),
        get_string('setting_emailnotifications_desc', 'local_studentid'),
        1
    ));
}
