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
 * Ednxt Top Bar: Moodle callbacks (handed to Baz where it owns them) plus the
 * top bar shell for every page.
 *
 * @package   theme_ednxt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../baz/lib.php');

/**
 * Per-page init: Baz's own, then the two-level top bar shell (CSS, JS, body classes).
 *
 * @param moodle_page $page
 */
function theme_ednxt_page_init(moodle_page $page) {
    // Same code as BAZ: theme_baz_page_init() turns the top bar layout on for this theme.
    if (function_exists('theme_baz_page_init')) {
        theme_baz_page_init($page);
    }
}

/**
 * Extra SCSS: the colour settings as CSS custom properties, plus custom CSS.
 * (Baz's own extra SCSS is added separately, from the parent theme.)
 *
 * @param theme_config $theme
 * @return string
 */
function theme_ednxt_get_extra_scss($theme) {
    // Colours and custom CSS now come from the shared BAZ settings (Top Bar tab), added in
    // the page head by theme_baz_nt_head_css(), so both themes use the same values.
    return '';
}

/**
 * Theme setting files (logos, backgrounds ...) are stored by Baz.
 */
function theme_ednxt_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel == CONTEXT_SYSTEM && $filearea === 'brandlogo') {
        $theme = theme_config::load('ednxt');
        return $theme->setting_file_serve('brandlogo', $args, $forcedownload, $options);
    }
    return theme_baz_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, $options);
}
