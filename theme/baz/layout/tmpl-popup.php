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
 * A one column layout for the baz theme.
 *
 * @package   theme_baz
 * @copyright 2022 - 2023 Marcin Czaja (https://rosea.io)
 * @license   Commercial https://themeforest.net/licenses
 */

defined('MOODLE_INTERNAL') || die();

$extraclasses = [];

// Dark mode.
if (theme_baz_get_setting('darkmodetheme') == '1') {
    $darkmodeon = (get_user_preferences('darkmode-on', 'false') == 'true');
    if ($darkmodeon) {
        $extraclasses[] = 'theme-dark';
    }
    $darkmodetheme = true;
} else {
    $darkmodeon = false;
}

if (theme_baz_get_setting('darkmodefirst') == '1') {
    $extraclasses[] = 'theme-dark';
    $darkmodetheme = false;
}
// Bespoke shell for the handful of `popup`-layout pages that are real pages rather
// than modals -- see admin_wrap::POPUP_PAGETYPES for which, and why it is a list.
// Everything else on this layout keeps the bare popup treatment, which is correct
// for a file picker or an LTI launch. Any failure falls through to that below.
if (\theme_baz\output\admin_wrap::popup_allowed()
        && \theme_baz\output\admin_wrap::maybe_render($extraclasses,
            !empty($darkmodeon), !empty($darkmodetheme))) {
    return;
}

$bodyattributes = $OUTPUT->body_attributes($extraclasses);
$siteurl = $CFG->wwwroot;
$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'output' => $OUTPUT,
    'bodyattributes' => $bodyattributes,
    'darkmodeon' => !empty($darkmodeon),
    'darkmodetheme' => !empty($darkmodetheme),
    'siteurl' => $siteurl
];

// Load theme settings.
$themesettings = new \theme_baz\util\theme_settings();
$templatecontext = array_merge($templatecontext, $themesettings->global_settings());

echo $OUTPUT->render_from_template('theme_baz/tmpl-popup', $templatecontext);
