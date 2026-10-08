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
 * @copyright 2024 Marcin Czaja (https://rosea.io)
 * @license   Commercial https://themeforest.net/licenses
 *
 */


defined('MOODLE_INTERNAL') || die();

// Dashboard settings.
$page = new admin_settingpage('theme_baz_settingsmycourses', get_string('settingsmycourses', 'theme_baz'));

$name = 'theme_baz/mycoursesblock1';
$title = get_string('mycoursesblock1', 'theme_baz');
$description = get_string('mycoursesblock1_desc', 'theme_baz', $a);
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/mycoursesblock2';
$title = get_string('mycoursesblock2', 'theme_baz');
$description = get_string('mycoursesblock2_desc', 'theme_baz', $a);
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$settings->add($page);
