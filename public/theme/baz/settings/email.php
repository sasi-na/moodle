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

$page = new admin_settingpage('theme_baz_customemail', get_string('customemailsettings', 'theme_baz'));

$name = 'theme_baz/email_preview';
$title = get_string('email_preview', 'theme_baz');
$description = get_string('email_preview_desc', 'theme_baz');
$setting = new baz_setting_specialsettingheading($name, $title, $description);
$page->add($setting);

$name = 'theme_baz/email_logo_heading';
$title = get_string('email_logo_heading', 'theme_baz');
$description = get_string('empty_desc', 'theme_baz');
$setting = new admin_setting_heading($name, $title, $description);
$page->add($setting);

$name = 'theme_baz/email_logo_file';
$title = get_string('email_logo_file', 'theme_baz');
$description = get_string('email_logo_file_desc', 'theme_baz');
$opts = array('accepted_types' => array('.png', '.jpg', '.gif'), 'maxfiles' => 1);
$setting = new admin_setting_configstoredfile($name, $title, $description, 'email_logo_file', 0, $opts);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

// Logo Height
$name = 'theme_baz/email_logo_height';
$title = get_string('email_logo_height', 'theme_baz');
$description = get_string('empty_desc', 'theme_baz');
$default = '40px';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

// Logo <img> alt value
$name = 'theme_baz/email_logo_alt';
$title = get_string('email_logo_alt', 'theme_baz');
$description = get_string('empty_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

// Logo <a> href value
$name = 'theme_baz/email_logo_href';
$title = get_string('email_logo_href', 'theme_baz');
$description = get_string('empty_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/email_typo_heading';
$title = get_string('email_typo_heading', 'theme_baz');
$description = get_string('empty_desc', 'theme_baz');
$setting = new admin_setting_heading($name, $title, $description);
$page->add($setting);

// Font family.
$name = 'theme_baz/email_typo_fontfamily';
$title = get_string('email_typo_fontfamily', 'theme_baz');
$description = get_string('empty_desc', 'theme_baz');
$default = 'Verdana,Arial,sans-serif';
$choices = array();
$choices['Arial,Verdana,sans-serif'] = 'Arial';
$choices['Verdana,Arial,sans-serif'] = 'Verdana';
$choices['Helvetica,Verdana,Arial,sans-serif'] = 'Helvetica';
$choices['Georgia,Times,Times New Roman,serif'] = 'Georgia';
$choices['Tahoma,Verdana,Arial,sans-serif'] = 'Tahoma';
$choices['Trebuchet MS,Lucida Grande,Lucida Sans Unicode,Lucida Sans,Tahoma,sans-serif'] = 'Trebuchet';
$choices['Times,Georgia,Times New Roman,serif'] = 'Times';
$setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

// Text font size.
$name = 'theme_baz/email_typo_text_fontsize';
$title = get_string('email_typo_text_fontsize', 'theme_baz');
$description = get_string('empty_desc', 'theme_baz');
$default = '15px';
$choices = array();
$choices['12px'] = '12px';
$choices['13px'] = '13px';
$choices['14px'] = '14px';
$choices['15px'] = '15px';
$choices['16px'] = '16px';
$choices['17px'] = '17px';
$choices['18px'] = '18px';
$setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

// Text font color.
$name = 'theme_baz/email_typo_text_color';
$title = get_string('email_typo_text_color', 'theme_baz');
$description = get_string('empty_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '#000000');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/email_typo_link_color';
$title = get_string('email_typo_link_color', 'theme_baz');
$description = get_string('empty_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '#0d80cb');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

// Footer font size.
$name = 'theme_baz/email_typo_footer_fontsize';
$title = get_string('email_typo_footer_fontsize', 'theme_baz');
$description = get_string('empty_desc', 'theme_baz');
$default = '15px';
$choices = array();
$choices['12px'] = '12px';
$choices['13px'] = '13px';
$choices['14px'] = '14px';
$choices['15px'] = '15px';
$choices['16px'] = '16px';
$choices['17px'] = '17px';
$choices['18px'] = '18px';
$setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

// Footer font color.
$name = 'theme_baz/email_typo_footer_color';
$title = get_string('email_typo_footer_color', 'theme_baz');
$description = get_string('empty_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '#6c757d');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);


// Title.
$name = 'theme_baz/email_footer_heading';
$title = get_string('email_footer_heading', 'theme_baz');
$description = get_string('empty_desc', 'theme_baz');
$setting = new admin_setting_heading($name, $title, $description);
$page->add($setting);

// Text.
$name = 'theme_baz/email_footer_text';
$title = get_string('email_footer_text', 'theme_baz');
$description = get_string('empty_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

// Copyright.
$name = 'theme_baz/email_footer_copyright';
$title = get_string('email_footer_copyright', 'theme_baz');
$description = get_string('empty_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

// Display logo
$name = 'theme_baz/email_footer_displaylogo';
$title = get_string('email_footer_displaylogo', 'theme_baz');
$description = get_string('empty_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$settings->add($page);
