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

$page = new admin_settingpage('theme_baz_general', get_string('generalsettings', 'theme_baz'));

$name = 'theme_baz/hintro';
$heading = get_string('hintro', 'theme_baz');
$setting = new baz_setting_specialsettingheading($name, $heading, format_text(get_string('hintro_desc', 'theme_baz'), FORMAT_MARKDOWN));
$page->add($setting);

$name = 'theme_baz/darkmodetheme';
$title = get_string('darkmodetheme', 'theme_baz');
$description = get_string('darkmodetheme_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/darkmodefirst';
$title = get_string('darkmodefirst', 'theme_baz');
$description = get_string('darkmodefirst_desc', 'theme_baz');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/sdarkmode';
$title = get_string('sdarkmode', 'theme_baz');
$description = get_string('sdarkmode_desc', 'theme_baz');
$default = 'Dark Mode';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/slightmode';
$title = get_string('slightmode', 'theme_baz');
$description = get_string('slightmode_desc', 'theme_baz');
$default = 'Light Mode';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/themeauthor';
$title = get_string('themeauthor', 'theme_baz');
$description = get_string('themeauthor_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/backtotop';
$title = get_string('backtotop', 'theme_baz');
$description = get_string('backtotop_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

// Video Size
$name = 'theme_baz/forcefwvideo';
$title = get_string('forcefwvideo', 'theme_baz');
$description = get_string('forcefwvideo_desc', 'theme_baz', $a);
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

// Setting: Show hint for switched role.
$name = 'theme_baz/showswitchedroleincourse';
$title = get_string('showswitchedroleincoursesetting', 'theme_baz');
$description = get_string('showswitchedroleincoursesetting_desc', 'theme_baz');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

// Setting: Show hint in hidden courses.
$name = 'theme_baz/showhintcoursehidden';
$title = get_string('showhintcoursehiddensetting', 'theme_baz');
$description = get_string('showhintcoursehiddensetting_desc', 'theme_baz');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

// Setting: Show hint guest for access.
$name = 'theme_baz/showhintcourseguestaccess';
$title = get_string('showhintcoursguestaccesssetting', 'theme_baz');
$description = get_string('showhintcourseguestaccesssetting_desc', 'theme_baz');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

// Setting: Show hint for self enrolment without enrolment key.
$name = 'theme_baz/showhintcourseselfenrol';
$title = get_string('showhintcourseselfenrolsetting', 'theme_baz');
$description = get_string('showhintcourseselfenrolsetting_desc', 'theme_baz');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

// Unaddable blocks.
$default = 'navigation,settings,course_list,section_links';
$setting = new admin_setting_configtext('theme_baz/unaddableblocks',
    get_string('unaddableblocks', 'theme_baz'), get_string('unaddableblocks_desc', 'theme_baz'), $default, PARAM_TEXT);
$page->add($setting);

// Google analytics block.
$name = 'theme_baz/googleanalytics';
$title = get_string('googleanalytics', 'theme_baz');
$description = get_string('googleanalyticsdesc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$fileid = 'bodybgimg';
$name = 'theme_baz/bodybgimg';
$title = get_string('bodybgimg', 'theme_baz');
$description = get_string('bodybgimg_desc', 'theme_baz');
$opts = array('accepted_types' => array('.png', '.jpg', '.gif', '.webp', '.tiff', '.svg'), 'maxfiles' => 1);
$setting = new admin_setting_configstoredfile($name, $title, $description, $fileid, 0, $opts);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$settings->add($page);
