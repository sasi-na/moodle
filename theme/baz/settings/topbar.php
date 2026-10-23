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
 * @copyright Copyright © 2021 onwards Marcin Czaja (https://rosea.io)
 * @license   Commercial https://themeforest.net/licenses
 *
 */

defined('MOODLE_INTERNAL') || die();

$page = new admin_settingpage('theme_baz_settingstopbar', get_string('settingstopbar', 'theme_baz'));

$name = 'theme_baz/editbtntop';
$title = get_string('editbtntop', 'theme_baz');
$description = get_string('editbtntop_desc', 'theme_baz', $a);
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/turnoffdashboardlink';
$title = get_string('turnoffdashboardlink', 'theme_baz');
$description = get_string('turnoffdashboardlink_desc', 'theme_baz', $a);
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/customlogo';
$title = get_string('customlogo', 'theme_baz');
$description = get_string('customlogo_desc', 'theme_baz');
$opts = array('accepted_types' => array('.png', '.jpg', '.svg', 'gif'));
$setting = new admin_setting_configstoredfile($name, $title, $description, 'customlogo', 0, $opts);
$page->add($setting);

$name = 'theme_baz/logocontainer';
$title = get_string('logocontainer', 'theme_baz');
$description = get_string('logocontainer_desc', 'theme_baz');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/topbarhamburgermenu';
$title = get_string('topbarhamburgermenu', 'theme_baz');
$description = get_string('topbarhamburgermenu_desc', 'theme_baz');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

// Additional Button next to the Log in button.
$name = 'theme_baz/htopbaradditionalbtn';
$heading = get_string('htopbaradditionalbtn', 'theme_baz');
$setting = new admin_setting_heading($name, $heading, format_text(get_string('htopbaradditionalbtn_desc', 'theme_baz'), FORMAT_MARKDOWN));
$page->add($setting);

$name = 'theme_baz/topbaradditionalbtn';
$title = get_string('topbaradditionalbtn', 'theme_baz');
$description = get_string('topbaradditionalbtn_desc', 'theme_baz', $a);
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/stopbaradditionalbtn';
$title = get_string('stopbaradditionalbtn', 'theme_baz');
$description = get_string('stopbaradditionalbtn_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/topbaradditionalbtnurl';
$title = get_string('topbaradditionalbtnurl', 'theme_baz');
$description = get_string('topbaradditionalbtnurl_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$page->add($setting);

//My Courses String
$name = 'theme_baz/hmycoursesbtn';
$heading = get_string('hmycoursesbtn', 'theme_baz');
$setting = new admin_setting_heading($name, $heading, format_text(get_string('hmycoursesbtn_desc', 'theme_baz'), FORMAT_MARKDOWN));
$page->add($setting);

$name = 'theme_baz/stringallcourses';
$title = get_string('stringallcourses', 'theme_baz');
$description = get_string('stringallcourses_desc', 'theme_baz');
$default = 'List of all available courses';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/stringnocourses';
$title = get_string('stringnocourses', 'theme_baz');
$description = get_string('stringnocourses_desc', 'theme_baz');
$default = 'You are not enrolled in any courses.';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/stringshowhidden';
$title = get_string('stringshowhidden', 'theme_baz');
$description = get_string('stringshowhidden_desc', 'theme_baz');
$default = 'Show hidden courses';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/mycoursesnavlimit';
$title = get_string('mycoursesnavlimit', 'theme_baz');
$description = get_string('mycoursesnavlimit_desc', 'theme_baz');
$default = 50;
$setting = new admin_setting_configtext($name, $title, $description, $default, PARAM_INT);
$page->add($setting);

$name = 'theme_baz/displayfilterhiddenchbx';
$title = get_string('displayfilterhiddenchbx', 'theme_baz');
$description = get_string('displayfilterhiddenchbx_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/stringshowonlyinprogress';
$title = get_string('stringshowonlyinprogress', 'theme_baz');
$description = get_string('stringshowonlyinprogress_desc', 'theme_baz');
$default = 'Only courses in progress';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/stringdetails';
$title = get_string('stringdetails', 'theme_baz');
$description = get_string('stringdetails_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);


// Colors
$name = 'theme_baz/htopbarcolors';
$heading = get_string('htopbarcolors', 'theme_baz');
$setting = new admin_setting_heading($name, $heading, format_text(get_string('htopbarcolors_desc', 'theme_baz'), FORMAT_MARKDOWN));
$page->add($setting);

$name = 'theme_baz/colortopbarbg';
$title = get_string('colortopbarbg', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colortopbartext';
$title = get_string('colortopbartext', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colortopbarbtn';
$title = get_string('colortopbarbtn', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colortopbarbtntext';
$title = get_string('colortopbarbtntext', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colortopbarbtnhover';
$title = get_string('colortopbarbtnhover', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colortopbarbtnhovertext';
$title = get_string('colortopbarbtnhovertext', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

// Must add the page after definiting all the settings!
$settings->add($page);
