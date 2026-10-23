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

$page = new admin_settingpage('theme_baz_block14', get_string('settingsblock14', 'theme_baz'));

$name = 'theme_baz/displayblock14';
$title = get_string('turnon', 'theme_baz');
$description = get_string('displayblock14_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/block14class';
$title = get_string('additionalclass', 'theme_baz');
$description = get_string('additionalclass_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/block14textalign';
$title = get_string('block14textalign', 'theme_baz');
$description = get_string('block14textalign_desc', 'theme_baz');
$options = [];
$options['left'] = get_string('left', 'theme_baz');
$options['center'] = get_string('center', 'theme_baz');
$options['right'] = get_string('right', 'theme_baz');
$setting = new admin_setting_configselect($name, $title, $description, 'center', $options);
$page->add($setting);

$name = 'theme_baz/displayhrblock14';
$title = get_string('displayblockhr', 'theme_baz');
$description = get_string('displayblockhr_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/block14introtitle';
$title = get_string('blockintrotitle', 'theme_baz');
$description = get_string('blockintrotitle_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/block14introcontent';
$title = get_string('blockintrocontent', 'theme_baz');
$description = get_string('blockintrocontent_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);


$name = 'theme_baz/block14htmlcontent';
$title = get_string('blockhtmlcontent', 'theme_baz');
$description = get_string('blockhtmlcontent_desc', 'theme_baz');
$default = '';
$setting = new baz_setting_confightmleditor($name, $title, $description, $default);
$page->add($setting);

$fileid = 'block14bg';
$name = 'theme_baz/block14bg';
$title = get_string('block14bg', 'theme_baz');
$description = get_string('block14bg_desc', 'theme_baz');
$opts = array('accepted_types' => array('.png', '.jpg', '.gif', '.webp', '.tiff', '.svg'), 'maxfiles' => 1);
$setting = new admin_setting_configstoredfile($name, $title, $description, $fileid, 0, $opts);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/block14customcss';
$title = get_string('block14customcss', 'theme_baz');
$description = get_string('block14customcss_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$settings->add($page);
