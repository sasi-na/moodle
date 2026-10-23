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
 *
 * @package   theme_baz
 * @copyright 2022 - 2023 Marcin Czaja (https://rosea.io)
 * @license   Commercial https://themeforest.net/licenses
 *
 */

defined('MOODLE_INTERNAL') || die();

$page = new admin_settingpage('theme_baz_settingscoursesnav', get_string('settingscoursesnav', 'theme_baz'));

$name = 'theme_baz/secnavgroupitem';
$title = get_string('secnavgroupitem', 'theme_baz');
$description = get_string('secnavgroupitem_desc', 'theme_baz', $a);
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/secnavitems';
$title = get_string('secnavitems', 'theme_baz');
$description = get_string('secnavitems_desc', 'theme_baz');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

if (get_config('theme_baz', 'secnavitems')) {
    $name = 'theme_baz/secnavitemscount';
    $title = get_string('secnavitemscount', 'theme_baz');
    $description = get_string('secnavitemscount_desc', 'theme_baz');
    $default = 0;
    $options = array();
    for ($i = 1; $i <= 10; $i++) {
        $options[$i] = $i;
    }
    $setting = new admin_setting_configselect($name, $title, $description, $default, $options);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    $navitems = get_config('theme_baz', 'secnavitemscount');
    if (!$navitems) {
        $navitems = 1;
    }
    for ($secnavindex = 1; $secnavindex <= $navitems; $secnavindex++) {
        $name = 'theme_baz/hsecnavitem' . $secnavindex;
        $heading = get_string('hsecnavitem', 'theme_baz');
        $setting = new admin_setting_heading($name, '<span class="rui-admin-no">' .
            $secnavindex .
            '</span>' .
            $heading, format_text(get_string('hsecnavitem_desc', 'theme_baz'), FORMAT_MARKDOWN));
        $page->add($setting);

        $name = 'theme_baz/secnavcustomnavlabel' . $secnavindex;
        $title = get_string('secnavcustomnavlabel', 'theme_baz');
        $description = get_string('secnavcustomnavlabel_desc', 'theme_baz');
        $setting = new admin_setting_configtext($name, '<span class="rui-admin-no">' .
            $secnavindex .
            '</span>' .
            $title, $description, '', PARAM_TEXT);
        $page->add($setting);

        $name = 'theme_baz/secnavcustomnavurl' . $secnavindex;
        $title = get_string('secnavcustomnavurl', 'theme_baz');
        $description = get_string('secnavcustomnavurl_desc', 'theme_baz');
        $setting = new admin_setting_configtext($name, '<span class="rui-admin-no">' .
            $secnavindex .
            '</span>' .
            $title, $description, '', PARAM_TEXT);
        $page->add($setting);

        $name = 'theme_baz/secnavuserroles' . $secnavindex;
        $title = get_string('secnavuserroles', 'theme_baz');
        $description = get_string('secnavuserroles_desc', 'theme_baz');
        $options = [];
        $options[0] = get_string('all', 'moodle');
        $options[1] = get_string('rolemanager', 'theme_baz');
        $options[2] = get_string('rolecoursecreator', 'theme_baz');
        $options[3] = get_string('roleeditingteacher', 'theme_baz');
        $options[4] = get_string('rolenoneditingteacher', 'theme_baz');
        $options[5] = get_string('rolestudent', 'theme_baz');
        $options[6] = get_string('roleguest', 'theme_baz');
        $options[7] = get_string('roleauthenticateduser', 'theme_baz');
        $setting = new admin_setting_configselect($name, '<span class="rui-admin-no">' .
        $secnavindex .
        '</span>' .
        $title, $description, '0', $options);
        $page->add($setting);

    }
}

// Must add the page after definiting all the settings!
$settings->add($page);
