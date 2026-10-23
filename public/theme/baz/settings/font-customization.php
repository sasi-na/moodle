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
$page = new admin_settingpage('theme_baz_fontcustomization', get_string('settingsfontcustomization', 'theme_baz'));

$name = 'theme_baz/fontbody';
$title = get_string('fontbody', 'theme_baz');
$description = get_string('fontbody_desc', 'theme_baz');
$default = "'Inter', sans-serif";
$setting = new admin_setting_configtext($name, $title, $description, $default);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/fontweightregular';
$title = get_string('fontweightregular', 'theme_baz');
$description = get_string('fontweightregular_desc', 'theme_baz');
$default = '400';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/fontweightmedium';
$title = get_string('fontweightmedium', 'theme_baz');
$description = get_string('fontweightmedium_desc', 'theme_baz');
$default = '500';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/fontweightbold';
$title = get_string('fontweightbold', 'theme_baz');
$description = get_string('fontweightbold_desc', 'theme_baz');
$default = '700';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/fontweightheadings';
$title = get_string('fontweightheadings', 'theme_baz');
$description = get_string('fontweightheadings_desc', 'theme_baz');
$default = '700';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

// Google Font.
$name = 'theme_baz/hgooglefont';
$heading = get_string('hgooglefont', 'theme_baz');
$setting = new admin_setting_heading($name, $heading, format_text(get_string('hgooglefont_desc', 'theme_baz'), FORMAT_MARKDOWN));
$page->add($setting);

// Google Font.
$name = 'theme_baz/googlefonturl';
$title = get_string('googlefonturl', 'theme_baz');
$description = get_string('googlefonturl_desc', 'theme_baz');
$default = 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

// Font files upload.
// Register the webfonts file types for filtering the uploads in the subsequent admin settings.
// This function call may return false. In this case, the filetypes were not registered and we
// can't restrict the filetypes in the subsequent admin settings unfortunately.
$name = 'theme_baz/hcustomfont';
$heading = get_string('hcustomfont', 'theme_baz');
$setting = new admin_setting_heading($name, $heading, format_text(get_string('hcustomfont_desc', 'theme_baz'), FORMAT_MARKDOWN));
$page->add($setting);

$registerfontsresult = theme_baz_register_webfonts_filetypes();

// Setting: Custom fonts.
$name = 'theme_baz/fontfiles';
$title = get_string('fontfilessetting', 'theme_baz', null, true);
$description = get_string('fontfilessetting_desc', 'theme_baz', null, true);
if ($registerfontsresult == true) {
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'fontfiles', 0,
            ['maxfiles' => -1, 'accepted_types' => theme_baz_get_webfonts_extensions()]);
} else {
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'fontfiles', 0,
            ['maxfiles' => -1]);
}
$page->add($setting);

// Information: Custom fonts list.
// If there is at least one file uploaded.
if (!empty(get_config('theme_baz', 'fontfiles'))) {
    // Prepare the widget.
    $name = 'theme_baz/customfontslist';
    $title = get_string('customfontslistsetting', 'theme_baz', null, true);
    $description = get_string('customfontslistsetting_desc', 'theme_baz', null, true);

    // Append the file list to the description.
    $templatecontext = ['files' => theme_baz_get_customfonts_templatecontext()];
    $description .= $OUTPUT->render_from_template('theme_baz/settings-customfonts-filelist', $templatecontext);

    // Finish the widget.
    $setting = new admin_setting_description($name, $title, $description);
    $page->add($setting);
}

$settings->add($page);
