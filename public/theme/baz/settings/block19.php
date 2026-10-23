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

$page = new admin_settingpage('theme_baz_block19', get_string('settingsblock19', 'theme_baz'));

$name = 'theme_baz/displayblock19';
$title = get_string('turnon', 'theme_baz');
$description = get_string('displayblock19_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/block19class';
$title = get_string('additionalclass', 'theme_baz');
$description = get_string('additionalclass_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/block19textalign';
$title = get_string('block19textalign', 'theme_baz');
$description = get_string('block19textalign_desc', 'theme_baz');
$options = [];
$options['left'] = get_string('left', 'theme_baz');
$options['center'] = get_string('center', 'theme_baz');
$options['right'] = get_string('right', 'theme_baz');
$setting = new admin_setting_configselect($name, $title, $description, 'center', $options);
$page->add($setting);

$name = 'theme_baz/displayhrblock19';
$title = get_string('displayblockhr', 'theme_baz');
$description = get_string('displayblockhr_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/block19introtitle';
$title = get_string('blockintrotitle', 'theme_baz');
$description = get_string('blockintrotitle_desc', 'theme_baz');
$default = 'One-time charge<br />Get this theme today!';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/block19introcontent';
$title = get_string('blockintrocontent', 'theme_baz');
$description = get_string('blockintrocontent_desc', 'theme_baz');
$default = 'Our exclusive lifetime update theme & user-centered design<br />will keep your moodle site running strong for many years to come!';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);


$name = 'theme_baz/block19htmlcontent';
$title = get_string('blockhtmlcontent', 'theme_baz');
$description = get_string('blockhtmlcontent_desc', 'theme_baz');
$default = '<div class="mt-4"><a href="https://rosea.io/baz" class="btn btn-lg btn-dark">Get this theme for $99*</a></div><div class="mt-2"><a href="https://baz.rosea.io/doc" class="btn btn-sm btn-secondary">Documentation</a></div>';
$setting = new baz_setting_confightmleditor($name, $title, $description, $default);
$page->add($setting);


$name = 'theme_baz/block19footercontent';
$title = get_string('blockfootercontent', 'theme_baz');
$description = get_string('blockfootercontent_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$fileid = 'block19bg';
$name = 'theme_baz/block19bg';
$title = get_string('block19bg', 'theme_baz');
$description = get_string('block19bg_desc', 'theme_baz');
$opts = array('accepted_types' => array('.png', '.jpg', '.gif', '.webp', '.tiff', '.svg'), 'maxfiles' => 1);
$setting = new admin_setting_configstoredfile($name, $title, $description, $fileid, 0, $opts);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/block19customcss';
$title = get_string('block19customcss', 'theme_baz');
$description = get_string('block19customcss_desc', 'theme_baz');
$default = 'background-position: top; background-size: cover;';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$settings->add($page);
