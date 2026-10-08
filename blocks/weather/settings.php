<?php
/**
 * Weather block - site-wide settings.
 *
 * @package    block_weather
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext('block_weather/defaultcity',
        get_string('defaultcity', 'block_weather'), get_string('defaultcity_desc', 'block_weather'), 'Bengaluru', PARAM_TEXT));
    $settings->add(new admin_setting_configcheckbox('block_weather/showondashboard',
        get_string('showondashboard', 'block_weather'), get_string('showondashboard_desc', 'block_weather'), 1));
    $settings->add(new admin_setting_configselect('block_weather/units',
        get_string('units', 'block_weather'), get_string('units_desc', 'block_weather'), 'c',
        ['c' => get_string('celsius', 'block_weather'), 'f' => get_string('fahrenheit', 'block_weather')]));
}
