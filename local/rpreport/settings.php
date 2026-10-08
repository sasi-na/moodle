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
 * Admin settings and pages for local_rpreport.
 *
 * @package   local_rpreport
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // On-demand "generate & send / download" page.
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_rpreport_run',
        get_string('runpage', 'local_rpreport'),
        new moodle_url('/local/rpreport/index.php'),
        'moodle/site:config'
    ));

    $settings = new admin_settingpage('local_rpreport_settings',
        get_string('settings', 'local_rpreport'));

    // Comma / newline separated recipient email addresses.
    $settings->add(new admin_setting_configtextarea(
        'local_rpreport/recipients',
        get_string('recipients', 'local_rpreport'),
        get_string('recipients_desc', 'local_rpreport'),
        '',
        PARAM_RAW
    ));

    $ADMIN->add('localplugins', $settings);
}
