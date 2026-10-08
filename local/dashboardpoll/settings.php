<?php
/**
 * Registers the dashboard poll management page in the admin tree.
 *
 * @package   local_dashboardpoll
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_dashboardpoll_manage',
        get_string('managepolls', 'local_dashboardpoll'),
        new moodle_url('/local/dashboardpoll/manage.php'),
        'local/dashboardpoll:manage'
    ));

    $settings = new admin_settingpage('local_dashboardpoll_settings',
        get_string('settings', 'local_dashboardpoll'));

    // Whether the per-option vote count ("49 votes") is shown under each bar on
    // the student results card. Percentages and the "Your answer" chip are always
    // shown; only the raw count is gated. Off by default.
    $settings->add(new admin_setting_configcheckbox(
        'local_dashboardpoll/showvotecounts',
        get_string('showvotecounts', 'local_dashboardpoll'),
        get_string('showvotecounts_desc', 'local_dashboardpoll'),
        0
    ));

    $ADMIN->add('localplugins', $settings);
}
