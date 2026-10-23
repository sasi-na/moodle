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


$page = new admin_settingpage('theme_baz_settingscourses', get_string('settingscourses', 'theme_baz'));

// Show/hide course index navigation.
$name = 'theme_baz/hidecourseindexnav';
$title = get_string('hidecourseindexnav', 'theme_baz');
$description = get_string('hidecourseindexnav_desc', 'theme_baz');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/ipcoursesummary';
$title = get_string('ipcoursesummary', 'theme_baz');
$description = get_string('ipcoursesummary_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/ipcoursedetails';
$title = get_string('ipcoursedetails', 'theme_baz');
$description = get_string('ipcoursedetails_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/ipcoursedates';
$title = get_string('ipcoursedates', 'theme_baz');
$description = get_string('ipcoursedates_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/ipcourseimage';
$title = get_string('ipcourseimage', 'theme_baz');
$description = get_string('ipcourseimage_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);


$name = 'theme_baz/cccteachers';
$title = get_string('cccteachers', 'theme_baz');
$description = get_string('cccteachers_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

// Progress Bar.
$name = 'theme_baz/courseprogressbar';
$title = get_string('courseprogressbar', 'theme_baz');
$description = get_string('courseprogressbar_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

// Start - Course Card.
$name = 'theme_baz/hcoursecard';
$heading = get_string('hcoursecard', 'theme_baz');
$setting = new admin_setting_heading($name, $heading, format_text(get_string('hcoursecard_desc', 'theme_baz'), FORMAT_MARKDOWN));
$page->add($setting);

$name = 'theme_baz/cccsummary';
$title = get_string('cccsummary', 'theme_baz');
$description = get_string('cccsummary_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/showccoursedate';
$title = get_string('showccoursedate', 'theme_baz');
$description = get_string('showccoursedate_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/showcustomfields';
$title = get_string('showcustomfields', 'theme_baz');
$description = get_string('showcustomfields_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/courselangbadge';
$title = get_string('courselangbadge', 'theme_baz');
$description = get_string('courselangbadge_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

// Customize Course Card Desc Limit.
$name = 'theme_baz/coursecarddesclimit';
$title = get_string('coursecarddesclimit', 'theme_baz');
$description = get_string('coursecarddesclimit_desc', 'theme_baz');
$setting = new admin_setting_configtext($name, $title, $description, '100');
$page->add($setting);
// End - Course Card.

// Must add the page after definiting all the settings!.
$settings->add($page);
