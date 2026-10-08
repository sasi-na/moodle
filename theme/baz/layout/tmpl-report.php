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

// Bespoke per-course grade report for student-only users. Matched by pagetype
// (the 'report' layout is shared by many reports). Also backs the profile route
// /course/user.php?mode=grade (pagetype 'course-user') — every other mode there
// redirects away, so only mode=grade renders; gated to the student's own grades.
// If the bespoke build fails, fall through to the standard report below.
$isusergradereport = ($PAGE->pagetype === 'grade-report-user-index')
    || ($PAGE->pagetype === 'course-user'
        && optional_param('mode', '', PARAM_ALPHA) === 'grade'
        && optional_param('user', $USER->id, PARAM_INT) === (int) $USER->id);
if ($isusergradereport
        && \theme_baz\local\experience::is_on('gradereport', $USER->id)) {
    try {
        $themesettings = new \theme_baz\util\theme_settings();
        $studentgradereport = new \theme_baz\output\student_gradereport();
        $grextraclasses = ['uses-drawers'];
        if (theme_baz_get_setting('darkmodefirst') == '1') {
            $grextraclasses[] = 'theme-dark';
        }
        $grtemplatecontext = array_merge([
            'sitename' => format_string($SITE->shortname, true,
                ['context' => context_course::instance(SITEID), 'escape' => false]),
            'output' => $OUTPUT,
            'bodyattributes' => $OUTPUT->body_attributes($grextraclasses),
            'siteurl' => $CFG->wwwroot,
            'navdraweropen' => false,
        ], $themesettings->global_settings(), $studentgradereport->export_for_template($OUTPUT));
        echo $OUTPUT->render_from_template('theme_baz/student-gradereport', $grtemplatecontext);
        return;
    } catch (\Throwable $e) {
        debugging('theme_baz student_gradereport failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        // Fall through to the standard report layout.
    }
}

// Bespoke Grades overview for student-only users. The overview report uses the
// 'report' layout when opened with a course id (the no-id variant is caught in
// tmpl-columns2). Matched by pagetype; falls through to standard on failure.
if ($PAGE->pagetype === 'grade-report-overview-index'
        && \theme_baz\local\experience::is_on('grades', $USER->id)) {
    try {
        $themesettings = new \theme_baz\util\theme_settings();
        $studentgrades = new \theme_baz\output\student_grades();
        $goextraclasses = ['uses-drawers'];
        if (theme_baz_get_setting('darkmodefirst') == '1') {
            $goextraclasses[] = 'theme-dark';
        }
        $gotemplatecontext = array_merge([
            'sitename' => format_string($SITE->shortname, true,
                ['context' => context_course::instance(SITEID), 'escape' => false]),
            'output' => $OUTPUT,
            'bodyattributes' => $OUTPUT->body_attributes($goextraclasses),
            'siteurl' => $CFG->wwwroot,
            'navdraweropen' => false,
        ], $themesettings->global_settings(), $studentgrades->export_for_template($OUTPUT));
        echo $OUTPUT->render_from_template('theme_baz/student-grades', $gotemplatecontext);
        return;
    } catch (\Throwable $e) {
        debugging('theme_baz student_grades (overview) failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        // Fall through to the standard report layout.
    }
}

$draweropenright = false;
$extraclasses = [];

// Moodle 4. - Add block button in editing mode.
$addblockbutton = $OUTPUT->addblockbutton();
if (isloggedin()) {
    $courseindexopen = (get_user_preferences('drawer-open-index', true) == true);
    $blockdraweropen = (get_user_preferences('drawer-open-block') == true);
} else {
    $courseindexopen = false;
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

// Bespoke admin shell, for the administrative pages that use this layout
// (report/*, cohort, grade reports for report; groups and other management screens
// for columns2). Allow-listed by path so an ordinary page an admin happens to open
// is not dressed as an admin screen. Same gate and fallback as tmpl-admin.php.
if (\theme_baz\output\admin_wrap::maybe_render($extraclasses, !empty($darkmodeon), !empty($darkmodetheme), true)) {
    return;
}

$blockshtml = $OUTPUT->blocks('side-pre');
$hasblocks = (strpos($blockshtml, 'data-block=') !== false || !empty($addblockbutton));
if (!$hasblocks) {
    $blockdraweropen = false;
}

if (theme_baz_get_setting('hidecourseindexnav') == true) {
    $hidecourseindexnav = true;
} else {
    $hidecourseindexnav = false;
}

if (theme_baz_get_setting('hidecourseindexnav') == false) {
    $courseindex = core_course_drawer();
    if (!$courseindex) {
        $courseindexopen = false;
    }
    if ($courseindexopen == false) {
        $extraclasses[] = 'drawer-open-index--closed';
    } else {
        $extraclasses[] = 'drawer-open-index--open';
    }
} else {
    $courseindex = false;
    $courseindexopen = false;
}

$sidecourseblocks = $OUTPUT->blocks('sidecourseblocks');
$hassidecourseblocks = strpos($sidecourseblocks, 'data-block=') !== false;


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
$iscoursepage = true;

if ($hassidecourseblocks) {
    $extraclasses[] = 'page-has-blocks';
}

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
    'navdraweropen' => $navdraweropen,
    'draweropenright' => $draweropenright,
    'isnotloggedin' => $isnotloggedin,
    // Moodle 4.
    'courseindexopen' => $courseindexopen,
    'blockdraweropen' => $blockdraweropen,
    'courseindex' => $courseindex,
    'primarymoremenu' => $primarymenu['moremenu'],
    'secondarymoremenu' => $secondarynavigation ?: false,
    'forceblockdraweropen' => $forceblockdraweropen,
    'regionmainsettingsmenu' => $regionmainsettingsmenu,
    'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
    'overflow' => $overflow,
    'headercontent' => $headercontent,
    'addblockbutton' => $addblockbutton,
    'hidecourseindexnav' => $hidecourseindexnav,
];

// Get and use the course page information banners HTML code, if any course page hints are configured.
$coursepageinformationbannershtml = theme_baz_get_course_information_banners();
if ($coursepageinformationbannershtml) {
    $templatecontext['coursepageinformationbanners'] = $coursepageinformationbannershtml;
}
// End.

// Load theme settings.
$themesettings = new \theme_baz\util\theme_settings();

$templatecontext = array_merge($templatecontext, $themesettings->global_settings());

$PAGE->requires->js_call_amd('theme_baz/rui', 'init');
if (theme_baz_get_setting('backtotop') == '1') {
    $PAGE->requires->js_call_amd('theme_baz/backtotop', 'init');
}

echo $OUTPUT->render_from_template('theme_baz/tmpl-report', $templatecontext);
