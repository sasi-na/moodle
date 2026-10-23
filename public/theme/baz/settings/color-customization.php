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

$page = new admin_settingpage('theme_baz_colorcustomization', get_string('settingscolorcustomization', 'theme_baz'));

$name = 'theme_baz/hgeneral';
$heading = get_string('hgeneral', 'theme_baz');
$setting = new admin_setting_heading($name, $heading, format_text(get_string('hgeneral_desc', 'theme_baz'), FORMAT_MARKDOWN));
$page->add($setting);

$name = 'theme_baz/colorbodybg';
$title = get_string('colorbodybg', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colorcontainerbg';
$title = get_string('colorcontainerbg', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colorborder';
$title = get_string('colorborder', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/hcolorstxt';
$heading = get_string('hcolorstxt', 'theme_baz');
$setting = new admin_setting_heading($name, $heading, format_text(get_string('hcolorstxt_desc', 'theme_baz'), FORMAT_MARKDOWN));
$page->add($setting);

$name = 'theme_baz/colorbody';
$title = get_string('colorbody', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colorbodysecondary';
$title = get_string('colorbodysecondary', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colorbodylight';
$title = get_string('colorbodylight', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colorheadings';
$title = get_string('colorheadings', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colorlink';
$title = get_string('colorlink', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colorlinkhover';
$title = get_string('colorlinkhover', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/hcolorsgrays';
$heading = get_string('hcolorsgrays', 'theme_baz');
$setting = new admin_setting_heading($name, $heading, format_text(get_string('hcolorsgrays_desc', 'theme_baz'), FORMAT_MARKDOWN));
$page->add($setting);

$name = 'theme_baz/colorwhite';
$title = get_string('colorwhite', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colorgray100';
$title = get_string('colorgray100', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colorgray200';
$title = get_string('colorgray200', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colorgray300';
$title = get_string('colorgray300', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colorgray400';
$title = get_string('colorgray400', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colorgray500';
$title = get_string('colorgray500', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colorgray600';
$title = get_string('colorgray600', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colorgray700';
$title = get_string('colorgray700', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colorgray800';
$title = get_string('colorgray800', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colorgray900';
$title = get_string('colorgray900', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colorblack';
$title = get_string('colorblack', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);



$name = 'theme_baz/hcolorsprimary';
$heading = get_string('hcolorsprimary', 'theme_baz');
$setting = new admin_setting_heading($name, $heading, format_text(get_string('hcolorsprimary_desc', 'theme_baz'), FORMAT_MARKDOWN));
$page->add($setting);


$name = 'theme_baz/colorprimary600';
$title = get_string('colorprimary600', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colorprimary100';
$title = get_string('colorprimary100', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colorprimary200';
$title = get_string('colorprimary200', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colorprimary300';
$title = get_string('colorprimary300', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colorprimary400';
$title = get_string('colorprimary400', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colorprimary500';
$title = get_string('colorprimary500', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colorprimary700';
$title = get_string('colorprimary700', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colorprimary800';
$title = get_string('colorprimary800', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colorprimary900';
$title = get_string('colorprimary900', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);


$name = 'theme_baz/hcolorssecondary';
$heading = get_string('hcolorssecondary', 'theme_baz');
$setting = new admin_setting_heading($name, $heading, format_text(get_string('hcolorssecondary_desc', 'theme_baz'), FORMAT_MARKDOWN));
$page->add($setting);

$name = 'theme_baz/colorsecondary600';
$title = get_string('colorsecondary600', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colorsecondary100';
$title = get_string('colorsecondary100', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colorsecondary200';
$title = get_string('colorsecondary200', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colorsecondary300';
$title = get_string('colorsecondary300', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colorsecondary400';
$title = get_string('colorsecondary400', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colorsecondary500';
$title = get_string('colorsecondary500', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colorsecondary700';
$title = get_string('colorsecondary700', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colorsecondary800';
$title = get_string('colorsecondary800', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colorsecondary900';
$title = get_string('colorsecondary900', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);


$settings->add($page);
