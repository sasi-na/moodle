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

$page = new admin_settingpage('theme_baz_settingsfiles', get_string('settingsfiles', 'theme_baz'));

$name = 'theme_baz/additionalresheading';
$title = get_string('additionalresheading', 'theme_baz');
$description = get_string('additionalresheading_desc', 'theme_baz');
$setting = new baz_setting_specialsettingheading($name, $title, $description);
$page->add($setting);

// Additional files
$name = 'theme_baz/additionalresources';
$title = get_string('additionalresourcessetting', 'theme_baz', null, true);
$description = get_string('additionalresourcessetting_desc', 'theme_baz', null, true);
$setting = new admin_setting_configstoredfile($name, $title, $description, 'additionalresources', 0,
        ['maxfiles' => -1]);
$page->add($setting);

// Information: Additional resources list.
// If there is at least one file uploaded.
if (!empty(get_config('theme_baz', 'additionalresources'))) {
    // Prepare the widget.
    $name = 'theme_baz/additionalresourceslist';
    $title = get_string('additionalresourceslistsetting', 'theme_baz', null, true);
    $description = get_string('additionalresourceslistsetting_desc', 'theme_baz', null, true).'<br /><br />'.
            get_string('resourcescachecontrolnote', 'theme_baz', null, true);

    // Append the file list to the description.
    $templatecontext = ['files' => theme_baz_get_additionalresources_templatecontext()];
    $description .= $OUTPUT->render_from_template('theme_baz/settings-additionalresources-filelist',
            $templatecontext);

    // Finish the widget.
    $setting = new baz_setting_specialsettingheading($name, $title, $description);
    $page->add($setting);
}

// Must add the page after definiting all the settings!
$settings->add($page);
