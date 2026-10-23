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

$page = new admin_settingpage('theme_baz_block18', get_string('settingsblock18', 'theme_baz'));

$name = 'theme_baz/displayblock18';
$title = get_string('turnon', 'theme_baz');
$description = get_string('displayblock18_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/displayhrblock18';
$title = get_string('displayblockhr', 'theme_baz');
$description = get_string('displayblockhr_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/block18class';
$title = get_string('additionalclass', 'theme_baz');
$description = get_string('additionalclass_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/block18htmlcontent';
$title = get_string('blockhtmlcontent', 'theme_baz');
$description = get_string('blockhtmlcontent_desc', 'theme_baz');
$default = '';
$setting = new baz_setting_confightmleditor($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/block18footercontent';
$title = get_string('blockfootercontent', 'theme_baz');
$description = get_string('blockfootercontent_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$settings->add($page);
