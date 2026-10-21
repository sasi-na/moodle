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
 * Ednxt Top Bar settings (the gear on the Themes page).
 *
 * Logos, menus, fonts and the rest still come from Baz; this page holds what is
 * specific to the top bar design.
 *
 * @package   theme_ednxt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// A visible page (core passes a hidden one), so the gear shows on the Themes page.
$settings = new admin_settingpage('themesettingednxt', get_string('configtitle', 'theme_ednxt'));

if ($ADMIN->fulltree) {
    // One theme, one place: all top bar options are on the BAZ settings page, "Top Bar" tab.
    $url = (new moodle_url('/admin/settings.php', ['section' => 'themesettingbaz']))->out(false) . '#theme_baz_settingstopbar';
    $settings->add(new admin_setting_heading('theme_ednxt/moved',
        get_string('navlayout', 'theme_baz'),
        get_string('ednxt_moved', 'theme_baz', $url)));
}
