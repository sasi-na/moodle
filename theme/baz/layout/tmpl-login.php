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
 * A login page layout for the space theme.
 *
 * @package   theme_baz
 * @copyright 2022 - 2023 Marcin Czaja (https://rosea.io)
 * @license   Commercial https://themeforest.net/licenses
 */

defined('MOODLE_INTERNAL') || die();

$siteurl = $CFG->wwwroot;

$loginlayout = theme_baz_get_setting('setloginlayout');

$loginlayout1 = false;
$extraclasses[] = 'rui-login-page';

$loginlayout = theme_baz_get_setting('setloginlayout');
if ($loginlayout == 1) {
    $extraclasses[] = 'rui-login-layout-1';
    $loginlayout1 = true;
}
if ($loginlayout == 2) {
    $extraclasses[] = 'rui-login-layout-2';
}
if ($loginlayout == 3) {
    $extraclasses[] = 'rui-login-layout-3';
}
if ($loginlayout == 4) {
    $extraclasses[] = 'rui-login-layout-4';
}
if ($loginlayout == 5) {
    $extraclasses[] = 'rui-login-layout-5';
}

if (theme_baz_get_setting('darkmodefirst') == '1') {
    $extraclasses[] = 'theme-dark';
    $darkmodetheme = false;
    $darkmodeon = true;
}

$bodyattributes = $OUTPUT->body_attributes($extraclasses);

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'output' => $OUTPUT,
    'bodyattributes' => $bodyattributes,
    'siteurl' => $siteurl,
    'loginlayout1' => $loginlayout1
];

$themesettings = new \theme_baz\util\theme_settings();
$templatecontext = array_merge($templatecontext, $themesettings->global_settings());

// Bespoke split-screen pre-auth (login + forgot-password) for everyone. Admin
// toggle; falls back to the standard rui login on any error so the critical
// login path can never be left broken.
if (theme_baz_get_setting('bespoke_login') == '1') {
    try {
        $headings = [
            'login-index' => get_string('lp_login_head', 'theme_baz'),
            'login-forgot-password' => get_string('lp_forgot_head', 'theme_baz'),
        ];
        // theme->setting_file_url() returns a plain URL string (not moodle_url).
        $loginbgurl = $PAGE->theme->setting_file_url('loginbg', 'loginbg');
        if ($loginbgurl instanceof \moodle_url) {
            $loginbgurl = $loginbgurl->out(false);
        }
        // get_logo_url() returns a moodle_url or false.
        $logourl = $OUTPUT->get_logo_url(null, 200);
        // A site's own lockup for the sign-in card. The sidebar logo is not reused:
        // it is drawn for the sidebar's own background, which is not necessarily the
        // card's. Falls back to the core logo, then to the site name as text.
        $loginlogourl = $PAGE->theme->setting_file_url('loginlogo', 'loginlogo');
        if ($loginlogourl instanceof \moodle_url) {
            $loginlogourl = $loginlogourl->out(false);
        }
        // Layout: the shipped split screen, or a centred card over a full-bleed
        // photograph. Per site, so one template serves both.
        $logincentered = theme_baz_get_setting('login_layout') === 'centered';
        $bodyattributes = $OUTPUT->body_attributes(array_merge($extraclasses, ['lp-bespoke']));
        // "Block Access" overlay: login/index.php stashes the field description here
        // when a blocked account submits credentials. Read once, then clear.
        global $SESSION;
        $blockmessage = '';
        if (!empty($SESSION->loginblockmsg)) {
            $blockmessage = $SESSION->loginblockmsg;
            unset($SESSION->loginblockmsg);
        }
        $ctx = array_merge($templatecontext, [
            'bodyattributes' => $bodyattributes,
            'loginbg' => $loginbgurl ? (string) $loginbgurl : '',
            'logourl' => $logourl ? $logourl->out(false) : '',
            'phead' => $headings[$PAGE->pagetype] ?? '',
            'blockmessage' => $blockmessage,
            'loginurl' => (new moodle_url('/login/index.php'))->out(false),
            'loginlogo' => $loginlogourl ? (string) $loginlogourl : '',
            'logincentered' => $logincentered,
        ]);
        echo $OUTPUT->render_from_template('theme_baz/student-login', $ctx);
        return;
    } catch (\Throwable $e) {
        debugging('theme_baz bespoke login failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
}

echo $OUTPUT->render_from_template('theme_baz/tmpl-login', $templatecontext);
