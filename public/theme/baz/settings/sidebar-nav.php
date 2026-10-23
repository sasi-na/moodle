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
 * @copyright 2022 - 2023 Marcin Czaja (https://rosea.io)
 * @license   Commercial https://themeforest.net/licenses
 *
 */

defined('MOODLE_INTERNAL') || die();
$page = new admin_settingpage('theme_baz_settingssidebar-nav', get_string('settingssidebar-nav', 'theme_baz'));

$name = 'theme_baz/isitemonsitehome';
$title = get_string('isitemonsitehome', 'theme_baz');
$description = get_string('isitemonsitehome_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/possitehome';
$title = get_string('possitehome', 'theme_baz');
$description = get_string('navposition_desc', 'theme_baz');
$default = 1;
$options = array();
for ($i = 1; $i <= 11; $i++) {
    $options[$i] = $i;
}
$setting = new admin_setting_configselect($name, $title, $description, $default, $options);
$page->add($setting);

$name = 'theme_baz/isitemondashboard';
$title = get_string('isitemondashboard', 'theme_baz');
$description = get_string('isitemondashboard_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/posdashboard';
$title = get_string('posdashboard', 'theme_baz');
$description = get_string('navposition_desc', 'theme_baz');
$default = 2;
$options = array();
for ($i = 1; $i <= 11; $i++) {
    $options[$i] = $i;
}
$setting = new admin_setting_configselect($name, $title, $description, $default, $options);
$page->add($setting);

$name = 'theme_baz/isitemoncalendar';
$title = get_string('isitemoncalendar', 'theme_baz');
$description = get_string('isitemoncalendar_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/poscalendar';
$title = get_string('poscalendar', 'theme_baz');
$description = get_string('navposition_desc', 'theme_baz');
$default = 3;
$options = array();
for ($i = 1; $i <= 11; $i++) {
    $options[$i] = $i;
}
$setting = new admin_setting_configselect($name, $title, $description, $default, $options);
$page->add($setting);

$name = 'theme_baz/isitemonprivatefiles';
$title = get_string('isitemonprivatefiles', 'theme_baz');
$description = get_string('isitemonprivatefiles_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/posprivatefiles';
$title = get_string('posprivatefiles', 'theme_baz');
$description = get_string('navposition_desc', 'theme_baz');
$default = 4;
$options = array();
for ($i = 1; $i <= 11; $i++) {
    $options[$i] = $i;
}
$setting = new admin_setting_configselect($name, $title, $description, $default, $options);
$page->add($setting);

$name = 'theme_baz/isitemoncontentbank';
$title = get_string('isitemoncontentbank', 'theme_baz');
$description = get_string('isitemoncontentbank_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/poscontentbank';
$title = get_string('poscontentbank', 'theme_baz');
$description = get_string('navposition_desc', 'theme_baz');
$default = 5;
$options = array();
for ($i = 1; $i <= 11; $i++) {
    $options[$i] = $i;
}
$setting = new admin_setting_configselect($name, $title, $description, $default, $options);
$page->add($setting);

$name = 'theme_baz/isitemonmycourses';
$title = get_string('isitemonmycourses', 'theme_baz');
$description = get_string('isitemonmycourses_desc', 'theme_baz');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/posmycourses';
$title = get_string('posmycourses', 'theme_baz');
$description = get_string('navposition_desc', 'theme_baz');
$default = 6;
$options = array();
for ($i = 1; $i <= 11; $i++) {
    $options[$i] = $i;
}
$setting = new admin_setting_configselect($name, $title, $description, $default, $options);
$page->add($setting);

// Custom Item #1.
$name = 'theme_baz/iscustomitem1on';
$title = get_string('iscustomitem1on', 'theme_baz');
$description = get_string('empty_desc', 'theme_baz');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/poscustomitem1';
$title = get_string('poscustomitem1', 'theme_baz');
$description = get_string('navposition_desc', 'theme_baz');
$default = 7;
$options = array();
for ($i = 1; $i <= 11; $i++) {
    $options[$i] = $i;
}
$setting = new admin_setting_configselect($name, $title, $description, $default, $options);
$page->add($setting);

$name = 'theme_baz/labelcustomitem1';
$title = get_string('labelcustomitem1', 'theme_baz');
$description = get_string('empty_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/iconcustomitem1';
$title = get_string('iconcustomitem1', 'theme_baz');
$description = get_string('iconcustomitem_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/urlcustomitem1';
$title = get_string('urlcustomitem1', 'theme_baz');
$description = get_string('urlcustomitem_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

// Custom Item #2.
$name = 'theme_baz/iscustomitem2on';
$title = get_string('iscustomitem2on', 'theme_baz');
$description = get_string('empty_desc', 'theme_baz');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/poscustomitem2';
$title = get_string('poscustomitem2', 'theme_baz');
$description = get_string('navposition_desc', 'theme_baz');
$default = 8;
$options = array();
for ($i = 1; $i <= 11; $i++) {
    $options[$i] = $i;
}
$setting = new admin_setting_configselect($name, $title, $description, $default, $options);
$page->add($setting);

$name = 'theme_baz/labelcustomitem2';
$title = get_string('labelcustomitem2', 'theme_baz');
$description = get_string('empty_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/iconcustomitem2';
$title = get_string('iconcustomitem2', 'theme_baz');
$description = get_string('iconcustomitem_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/urlcustomitem2';
$title = get_string('urlcustomitem2', 'theme_baz');
$description = get_string('urlcustomitem_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

// Custom Item #3.
$name = 'theme_baz/iscustomitem3on';
$title = get_string('iscustomitem3on', 'theme_baz');
$description = get_string('empty_desc', 'theme_baz');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/poscustomitem3';
$title = get_string('poscustomitem3', 'theme_baz');
$description = get_string('navposition_desc', 'theme_baz');
$default = 9;
$options = array();
for ($i = 1; $i <= 11; $i++) {
    $options[$i] = $i;
}
$setting = new admin_setting_configselect($name, $title, $description, $default, $options);
$page->add($setting);

$name = 'theme_baz/labelcustomitem3';
$title = get_string('labelcustomitem3', 'theme_baz');
$description = get_string('empty_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/iconcustomitem3';
$title = get_string('iconcustomitem3', 'theme_baz');
$description = get_string('iconcustomitem_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/urlcustomitem3';
$title = get_string('urlcustomitem3', 'theme_baz');
$description = get_string('iconcustomitem_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

// Custom Item #4.
$name = 'theme_baz/iscustomitem4on';
$title = get_string('iscustomitem4on', 'theme_baz');
$description = get_string('empty_desc', 'theme_baz');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/poscustomitem4';
$title = get_string('poscustomitem4', 'theme_baz');
$description = get_string('navposition_desc', 'theme_baz');
$default = 10;
$options = array();
for ($i = 1; $i <= 11; $i++) {
    $options[$i] = $i;
}
$setting = new admin_setting_configselect($name, $title, $description, $default, $options);
$page->add($setting);

$name = 'theme_baz/labelcustomitem4';
$title = get_string('labelcustomitem4', 'theme_baz');
$description = get_string('empty_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/iconcustomitem4';
$title = get_string('iconcustomitem4', 'theme_baz');
$description = get_string('iconcustomitem_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/urlcustomitem4';
$title = get_string('urlcustomitem4', 'theme_baz');
$description = get_string('urlcustomitem_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

// Custom Item #5.
$name = 'theme_baz/iscustomitem5on';
$title = get_string('iscustomitem5on', 'theme_baz');
$description = get_string('empty_desc', 'theme_baz');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/poscustomitem5';
$title = get_string('poscustomitem5', 'theme_baz');
$description = get_string('navposition_desc', 'theme_baz');
$default = 11;
$options = array();
for ($i = 1; $i <= 11; $i++) {
    $options[$i] = $i;
}
$setting = new admin_setting_configselect($name, $title, $description, $default, $options);
$page->add($setting);

$name = 'theme_baz/labelcustomitem5';
$title = get_string('labelcustomitem5', 'theme_baz');
$description = get_string('empty_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/iconcustomitem5';
$title = get_string('iconcustomitem5', 'theme_baz');
$description = get_string('iconcustomitem_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/urlcustomitem5';
$title = get_string('urlcustomitem5', 'theme_baz');
$description = get_string('urlcustomitem_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/customnavitems';
$title = get_string('customnavitems', 'theme_baz');
$description = get_string('customnavitems_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

// Must add the page after definiting all the settings!
$settings->add($page);
