<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Admin settings for local_facultyfeedback.
 *
 * @package   local_facultyfeedback
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_facultyfeedback_settings',
        get_string('settings', 'local_facultyfeedback'));

    // Ships off. Switch on once the figures have been checked on a real course.
    $settings->add(new admin_setting_configcheckbox(
        'local_facultyfeedback/enabled',
        get_string('enabled', 'local_facultyfeedback'),
        get_string('enabled_desc', 'local_facultyfeedback'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'local_facultyfeedback/minresponses',
        get_string('minresponses', 'local_facultyfeedback'),
        get_string('minresponses_desc', 'local_facultyfeedback'),
        \local_facultyfeedback\manager::DEFAULT_MIN_RESPONSES,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_facultyfeedback/trendweeks',
        get_string('trendweeks', 'local_facultyfeedback'),
        get_string('trendweeks_desc', 'local_facultyfeedback'),
        \local_facultyfeedback\manager::DEFAULT_TREND_WEEKS,
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $settings);
}
