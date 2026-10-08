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
 * Support block settings.
 *
 * The helpdesk this block hands users to used to be hardcoded to Jain's
 * (support.onlinejain.com / portal.onlinejain.com). Every site running this
 * shared image therefore sent its own users -- and their email addresses, via
 * the sync-user call in support_api.php -- to Jain's helpdesk. These settings
 * make the destination per-site.
 *
 * The defaults are the previously hardcoded values, so sites that set nothing
 * behave exactly as before and Jain is unaffected. A site with its own
 * helpdesk sets these; a site with none should hide the block.
 *
 * @package   block_support
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    $settings->add(new admin_setting_heading(
        'block_support/endpoints',
        get_string('settings_endpoints', 'block_support'),
        get_string('settings_endpoints_desc', 'block_support')
    ));

    $settings->add(new admin_setting_configtext(
        'block_support/apibaseurl',
        get_string('setting_apibaseurl', 'block_support'),
        get_string('setting_apibaseurl_desc', 'block_support'),
        'https://support.onlinejain.com',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'block_support/apikey',
        get_string('setting_apikey', 'block_support'),
        get_string('setting_apikey_desc', 'block_support'),
        '426f90b088970132136cbc96dfa42d55dd5606d1'
    ));

    $settings->add(new admin_setting_configtext(
        'block_support/portalticketurl',
        get_string('setting_portalticketurl', 'block_support'),
        get_string('setting_portalticketurl_desc', 'block_support'),
        'https://portal.onlinejain.com/support/dashboard/tickets/created',
        PARAM_URL
    ));

    $settings->add(new admin_setting_heading(
        'block_support/display',
        get_string('settings_display', 'block_support'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_support/showescalationsop',
        get_string('setting_showescalationsop', 'block_support'),
        get_string('setting_showescalationsop_desc', 'block_support'),
        1
    ));
}
