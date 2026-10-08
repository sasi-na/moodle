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
 * A two column layout for the baz theme.
 *
 * @package   theme_baz
 * @copyright 2022 - 2023 Marcin Czaja (https://rosea.io)
 * @license   Commercial https://themeforest.net/licenses
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/behat/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

global $USER;

// Bespoke "My courses" for student-only users — decided BEFORE block regions are
// rendered, so the standard course-overview block JS is never queued for DOM this
// layout won't output (mirrors the dashboard gate). Falls through to the classic
// page when the master/per-view setting is off or the user isn't eligible.
if (\theme_baz\local\experience::is_on('courses', $USER->id)) {
    // Block JS is already suppressed for this layout by theme_baz_page_init().
    $themesettings = new \theme_baz\util\theme_settings();
    $studentcourses = new \theme_baz\output\student_courses();
    $extraclasses = ['uses-drawers'];
    if (theme_baz_get_setting('darkmodefirst') == '1') {
        $extraclasses[] = 'theme-dark';
    }
    $templatecontext = array_merge([
        'sitename' => format_string($SITE->shortname, true,
            ['context' => context_course::instance(SITEID), 'escape' => false]),
        'output' => $OUTPUT,
        'bodyattributes' => $OUTPUT->body_attributes($extraclasses),
        'siteurl' => $CFG->wwwroot,
        'navdraweropen' => false,
    ], $themesettings->global_settings(), $studentcourses->export_for_template($OUTPUT));
    echo $OUTPUT->render_from_template('theme_baz/student-courses', $templatecontext);
    return;
}

$draweropenright = false;
$extraclasses = [];

// Moodle 4. - Add block button in editing mode.
$addblockbutton = $OUTPUT->addblockbutton();
if (isloggedin()) {
    $blockdraweropen = (get_user_preferences('drawer-open-block') == true);
} else {
    $blockdraweropen = false;
}

if (defined('BEHAT_SITE_RUNNING')) {
    $blockdraweropen = true;
}

$extraclasses = ['uses-drawers'];
// End.

// Dark mode.
if (isloggedin()) {
    $navdraweropen = get_user_preferences('drawer-open-nav', true);
    $draweropenright = (get_user_preferences('sidepre-open', 'true') == 'true');

    if (theme_baz_get_setting('darkmodetheme') == '1') {
        $darkmodeon = get_user_preferences('darkmode-on');
        if ($darkmodeon) {
            $extraclasses[] = 'theme-dark';
        }
    } else {
        $darkmodeon = false;
    }
} else {
    $navdraweropen = false;
}

if (theme_baz_get_setting('darkmodefirst') == '1') {
    $extraclasses[] = 'theme-dark';
    $darkmodetheme = false;
    $darkmodeon = true;
}

$siteurl = $CFG->wwwroot;

// Bespoke shell, for every audience except students -- they already have their own
// redesigned pages and are filtered out inside maybe_render(). Placed after this
// layout's student and staff branches so those keep priority. Any failure falls
// through to the classic layout below.
if (\theme_baz\output\admin_wrap::maybe_render($extraclasses, !empty($darkmodeon), !empty($darkmodetheme), true)) {
    return;
}

$blockshtml = $OUTPUT->blocks('side-pre');
$hasblocks = (strpos($blockshtml, 'data-block=') !== false || !empty($addblockbutton));
if (!$hasblocks) {
    $blockdraweropen = false;
}

// No course index or side course blocks on /my/courses.php: the page has no
// course context and the mycourses layout declares no block regions.

if ($draweropenright && $hasblocks) {
    $extraclasses[] = 'drawer-open-right';
}

// Moodle 4.
$forceblockdraweropen = $OUTPUT->firstview_fakeblocks();

$secondarynavigation = false;
$overflow = '';
if ($PAGE->has_secondary_navigation()) {
    $tablistnav = $PAGE->has_tablist_secondary_navigation();
    $moremenu = new \core\navigation\output\more_menu($PAGE->secondarynav, 'nav-tabs', true, $tablistnav);
    $secondarynavigation = $moremenu->export_for_template($OUTPUT);
    $overflowdata = $PAGE->secondarynav->get_overflow_menu_data();
    if (!is_null($overflowdata)) {
        $overflow = $overflowdata->export_for_template($OUTPUT);
    }
}

$primary = new core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = $primary->export_for_template($renderer);
$buildregionmainsettings = !$PAGE->include_region_main_settings_in_header_actions() && !$PAGE->has_secondary_navigation();
// If the settings menu will be included in the header then don't add it here.
$regionmainsettingsmenu = $buildregionmainsettings ? $OUTPUT->region_main_settings_menu() : false;

$header = $PAGE->activityheader;
$headercontent = $header->export_for_template($renderer);

// RUI.
$iscoursepage = false;

if (!isloggedin() || isguestuser()) {
    $isnotloggedin = true;
} else {
    $isnotloggedin = false;
}

// Check if guest user.
if (isguestuser()) {
    $extraclasses[] = 'moodle-guest-user';
}

if (theme_baz_get_setting('backtotop') == '1') {
    $extraclasses[] = 'back-to-top-on';
}

$bodyattributes = $OUTPUT->body_attributes($extraclasses);

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'output' => $OUTPUT,
    'bodyattributes' => $bodyattributes,
    'darkmodeon' => !empty($darkmodeon),
    'darkmodetheme' => !empty($darkmodetheme),
    'siteurl' => $siteurl,
    'sidepreblocks' => $blockshtml,
    'hasblocks' => $hasblocks,
    'regionmainsettingsmenu' => $regionmainsettingsmenu,
    'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
    'navdraweropen' => $navdraweropen,
    'draweropenright' => $draweropenright,
    'isnotloggedin' => $isnotloggedin,
    'iscoursepage' => $iscoursepage,
    // Moodle 4.
    'blockdraweropen' => $blockdraweropen,
    'primarymoremenu' => $primarymenu['moremenu'],
    'secondarymoremenu' => $secondarynavigation ?: false,
    'forceblockdraweropen' => $forceblockdraweropen,
    'regionmainsettingsmenu' => $regionmainsettingsmenu,
    'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
    'overflow' => $overflow,
    'headercontent' => $headercontent,
    'addblockbutton' => $addblockbutton,
    'hasmycoursesheader' => true,
    'userfirstname' => format_string($USER->firstname),
];

// Load theme settings.
$themesettings = new \theme_baz\util\theme_settings();

$templatecontext = array_merge($templatecontext, $themesettings->global_settings());

$PAGE->requires->js_call_amd('theme_baz/rui', 'init');
$PAGE->requires->js_call_amd('theme_baz/mycourses', 'init');
if (theme_baz_get_setting('backtotop') == '1') {
    $PAGE->requires->js_call_amd('theme_baz/backtotop', 'init');
}

echo $OUTPUT->render_from_template('theme_baz/tmpl-mycourses', $templatecontext);
