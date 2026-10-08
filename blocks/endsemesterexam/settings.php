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
 * Admin settings for block_endsemesterexam.
 *
 * @package   block_endsemesterexam
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use block_endsemesterexam\local\config;

if ($ADMIN->fulltree) {

    $settings->add(new admin_setting_heading('block_endsemesterexam/audienceheading',
        get_string('audienceheading', 'block_endsemesterexam'),
        get_string('audienceheading_desc', 'block_endsemesterexam')));

    $settings->add(new admin_setting_configselect('block_endsemesterexam/gatemode',
        get_string('gatemode', 'block_endsemesterexam'),
        get_string('gatemode_desc', 'block_endsemesterexam'),
        config::GATE_CATEGORY,
        [
            config::GATE_CATEGORY     => get_string('gatemode_category', 'block_endsemesterexam'),
            config::GATE_PROFILEFIELD => get_string('gatemode_profilefield', 'block_endsemesterexam'),
        ]));

    $settings->add(new admin_setting_configtext('block_endsemesterexam/examcategory',
        get_string('examcategory', 'block_endsemesterexam'),
        get_string('examcategory_desc', 'block_endsemesterexam'),
        '', PARAM_INT));

    $settings->add(new admin_setting_configtext('block_endsemesterexam/registerfield',
        get_string('registerfield', 'block_endsemesterexam'),
        get_string('registerfield_desc', 'block_endsemesterexam'),
        '', PARAM_ALPHANUMEXT));

    $settings->add(new admin_setting_heading('block_endsemesterexam/providerheading',
        get_string('providerheading', 'block_endsemesterexam'),
        get_string('providerheading_desc', 'block_endsemesterexam')));

    // Built from the registry, so adding a portal is a class plus an entry in
    // config::PROVIDERS plus its provider_<key> string -- not an edit here.
    $provideroptions = [];
    foreach (array_keys(config::PROVIDERS) as $providerkey) {
        $provideroptions[$providerkey] =
            get_string('provider_' . $providerkey, 'block_endsemesterexam');
    }

    $settings->add(new admin_setting_configselect('block_endsemesterexam/provider',
        get_string('provider', 'block_endsemesterexam'),
        get_string('provider_desc', 'block_endsemesterexam'),
        array_key_first(config::PROVIDERS),
        $provideroptions));

    // Examstack.
    $settings->add(new admin_setting_configtext('block_endsemesterexam/ssoendpoint',
        get_string('ssoendpoint', 'block_endsemesterexam'),
        get_string('ssoendpoint_desc', 'block_endsemesterexam'),
        '', PARAM_URL));

    $settings->add(new admin_setting_configpasswordunmask('block_endsemesterexam/ssoapikey',
        get_string('ssoapikey', 'block_endsemesterexam'),
        get_string('ssoapikey_desc', 'block_endsemesterexam'), ''));

    $settings->add(new admin_setting_configtext('block_endsemesterexam/ssoorigin',
        get_string('ssoorigin', 'block_endsemesterexam'),
        get_string('ssoorigin_desc', 'block_endsemesterexam'),
        '', PARAM_URL));

    $settings->add(new admin_setting_configtext('block_endsemesterexam/examportalurl',
        get_string('examportalurl', 'block_endsemesterexam'),
        get_string('examportalurl_desc', 'block_endsemesterexam'),
        '', PARAM_RAW_TRIMMED));

    // Gianveshana.
    $settings->add(new admin_setting_configtext('block_endsemesterexam/tokenendpoint',
        get_string('tokenendpoint', 'block_endsemesterexam'),
        get_string('tokenendpoint_desc', 'block_endsemesterexam'),
        '', PARAM_URL));

    $settings->add(new admin_setting_configtext('block_endsemesterexam/examurlendpoint',
        get_string('examurlendpoint', 'block_endsemesterexam'),
        get_string('examurlendpoint_desc', 'block_endsemesterexam'),
        '', PARAM_URL));

    $settings->add(new admin_setting_configtext('block_endsemesterexam/apiuser',
        get_string('apiuser', 'block_endsemesterexam'),
        get_string('apiuser_desc', 'block_endsemesterexam'),
        '', PARAM_RAW_TRIMMED));

    $settings->add(new admin_setting_configpasswordunmask('block_endsemesterexam/apipassword',
        get_string('apipassword', 'block_endsemesterexam'),
        get_string('apipassword_desc', 'block_endsemesterexam'), ''));
}
