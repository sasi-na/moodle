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
 * An embedded layout for the baz theme.
 *
 * @package   theme_baz
 * @copyright Copyright © 2021 onwards Marcin Czaja (https://rosea.io)
 * @license   Commercial https://themeforest.net/licenses
 */

defined('MOODLE_INTERNAL') || die();
$siteurl = $CFG->wwwroot;

if (isloggedin()) {
    if (theme_baz_get_setting('darkmodetheme') == '1') {
        $darkmodeon = (get_user_preferences('darkmode-on', 'false') == 'true'); //return 1
        if ($darkmodeon) {
            $extraclasses[] = 'theme-dark';
        }
        $darkmodetheme = true;
    } else {
        $darkmodeon = false;
    }
}

if (theme_baz_get_setting('darkmodefirst') == '1') {
    $extraclasses[] = 'theme-dark';
    $darkmodetheme = false;
}

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'output' => $OUTPUT,
    'darkmodeon' => !empty($darkmodeon),
    'darkmodetheme' => !empty($darkmodetheme),
    'siteurl' => $siteurl
];

//Load theme settings
$themesettings = new \theme_baz\util\theme_settings();
$templatecontext = array_merge($templatecontext, $themesettings->global_settings());

echo $OUTPUT->render_from_template('theme_baz/tmpl-embedded', $templatecontext);
