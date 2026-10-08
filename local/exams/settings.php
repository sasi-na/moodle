<?php
/**
 * Admin settings for local_exams.
 *
 * The whole feature ships dark: both `enabled` (master) and `shownav` (inject
 * portal links) default to OFF, so pages are reachable by direct URL only until
 * the administrator opts in.
 *
 * @package   local_exams
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_exams', get_string('pluginname', 'local_exams'));
    $ADMIN->add('localplugins', $settings);

    // Master switch. When OFF the portal pages redirect away; nothing is exposed.
    $settings->add(new admin_setting_configcheckbox(
        'local_exams/enabled',
        get_string('setting_enabled', 'local_exams'),
        get_string('setting_enabled_desc', 'local_exams'),
        0
    ));

    // Inject the portal links into the navigation. When OFF, access is URL-only.
    $settings->add(new admin_setting_configcheckbox(
        'local_exams/shownav',
        get_string('setting_shownav', 'local_exams'),
        get_string('setting_shownav_desc', 'local_exams'),
        0
    ));

    // Results visibility window — students only see results once released.
    $settings->add(new admin_setting_configcheckbox(
        'local_exams/resultsreleased',
        get_string('setting_resultsreleased', 'local_exams'),
        get_string('setting_resultsreleased_desc', 'local_exams'),
        0
    ));

    // Role shortname that designates "faculty" for the analytics view, in
    // addition to the manager archetype capability. Optional.
    $settings->add(new admin_setting_configtext(
        'local_exams/facultyrole',
        get_string('setting_facultyrole', 'local_exams'),
        get_string('setting_facultyrole_desc', 'local_exams'),
        '',
        PARAM_ALPHANUMEXT
    ));
}
