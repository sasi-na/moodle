<?php
/**
 * Registers the event registration management page in the admin tree.
 *
 * @package   local_eventreg
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_eventreg_manage',
        get_string('manageforms', 'local_eventreg'),
        new moodle_url('/local/eventreg/manage.php'),
        'local/eventreg:manage'
    ));

    $settings = new admin_settingpage('local_eventreg_settings',
        get_string('settings', 'local_eventreg'));

    // Default download format offered first on the report page.
    $settings->add(new admin_setting_configselect(
        'local_eventreg/defaultformat',
        get_string('defaultformat', 'local_eventreg'),
        get_string('defaultformat_desc', 'local_eventreg'),
        'csv',
        ['csv' => 'CSV', 'excel' => 'Excel (xlsx)']
    ));

    $ADMIN->add('localplugins', $settings);
}
