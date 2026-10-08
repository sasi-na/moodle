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


$page = new admin_settingpage('theme_baz_settingsenrollmentpage', get_string('settingsenrollmentpage', 'theme_baz'));

$name = 'theme_baz/henrollment';
$title = get_string('henrollment', 'theme_baz');
$description = get_string('henrollment_desc', 'theme_baz');
$setting = new baz_setting_specialsettingheading($name, $title, $description);
$page->add($setting);

$name = 'theme_baz/ecoursesummary';
$title = get_string('ecoursesummary', 'theme_baz');
$description = get_string('ecoursesummary_desc', 'theme_baz');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/ecoursedetails';
$title = get_string('ecoursedetails', 'theme_baz');
$description = get_string('ecoursedetails_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/ecoursedates';
$title = get_string('ecoursedates', 'theme_baz');
$description = get_string('ecoursedates_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

// Must add the page after definiting all the settings!.
$settings->add($page);
