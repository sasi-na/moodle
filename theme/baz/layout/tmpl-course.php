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

// Bespoke course view for student-only users (not while editing). If anything in
// the bespoke build fails, fall through to the standard course layout below so
// the student is never left without a course page.
if (!$PAGE->user_is_editing() && \theme_baz\local\experience::is_on('coursepage', $USER->id)) {
    try {
        $themesettings = new \theme_baz\util\theme_settings();
        $studentcoursepage = new \theme_baz\output\student_coursepage();
        $cpextraclasses = ['uses-drawers'];
        if (theme_baz_get_setting('darkmodefirst') == '1') {
            $cpextraclasses[] = 'theme-dark';
        }
        // Blocks an admin placed for students are invisible on this layout,
        // because it never asks for the side-pre region -- and asking for it
        // here does not work either: during a real request every region comes
        // back empty, though an identical call in CLI returns the block. So the
        // blocks are instantiated directly instead, which is the same approach
        // theme_baz_support_panel() already uses for the Support block.
        //
        // Named explicitly rather than "whatever is in side-pre" so a site
        // decides which blocks belong in a student's rail, and so nothing
        // staff-oriented leaks into it.
        $cpblocks = theme_baz_student_course_blocks($COURSE->id);

        $cptemplatecontext = array_merge([
            'sitename' => format_string($SITE->shortname, true,
                ['context' => context_course::instance(SITEID), 'escape' => false]),
            'output' => $OUTPUT,
            'bodyattributes' => $OUTPUT->body_attributes($cpextraclasses),
            'siteurl' => $CFG->wwwroot,
            'navdraweropen' => false,
            // A site whose rail already carries a progress-shaped block (see
            // student_course_blocks) can drop this card rather than show the
            // same figures twice.
            //
            // Read with get_config, not theme_baz_get_setting: the latter runs
            // the value through empty(), so a stored '0' comes back as false
            // and an explicit "off" reads identically to "never set". Default
            // on, so a site that has set nothing keeps the card.
            'showprogresscard' => get_config('theme_baz', 'student_rail_progress') !== '0',
            'studentblocks' => $cpblocks,
            'hasstudentblocks' => !empty($cpblocks),
        ], $themesettings->global_settings(), $studentcoursepage->export_for_template($OUTPUT));
        echo $OUTPUT->render_from_template('theme_baz/student-coursepage', $cptemplatecontext);
        return;
    } catch (\Throwable $e) {
        debugging('theme_baz student_coursepage failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        // Fall through to the standard course layout.
    }
}

$draweropenright = false;
$extraclasses = [];

$courseindexopen = false;
$blockdraweropen = false;

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
        $darkmodetheme = true;
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

$ctopbl = $OUTPUT->blocks('ctopbl');
$cbottombl = $OUTPUT->blocks('cbottombl');
$cstopbl = $OUTPUT->blocks('cstopbl');
$csbottombl = $OUTPUT->blocks('csbottombl');
$coursetab1 = $OUTPUT->blocks('coursetab-a');
$coursetab2 = $OUTPUT->blocks('coursetab-b');
$coursetab3 = $OUTPUT->blocks('coursetab-c');
$coursetab4 = $OUTPUT->blocks('coursetab-d');
$coursetab5 = $OUTPUT->blocks('coursetab-e');

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

// Start - Course Image Position (theme settings).
$courseimage = theme_baz_get_setting('ipcourseimage');

if ($hassidecourseblocks) {
    $extraclasses[] = 'page-has-blocks';
}

if ($PAGE->course->enablecompletion == '1') {
    $extraclasses[] = 'rui-course--enablecompletion';
}

if ($PAGE->course->showactivitydates == '1') {
    $extraclasses[] = 'rui-course--showactivitydates';
}

if ($PAGE->course->visible == '1') {
    $extraclasses[] = 'rui-course--visible';
}

$iscoursepage = true;

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
    'ctopbl' => $ctopbl,
    'cbottombl' => $cbottombl,
    'cstopbl' => $cstopbl,
    'csbottombl' => $csbottombl,
    'coursetab-a' => $coursetab1,
    'coursetab-b' => $coursetab2,
    'coursetab-c' => $coursetab3,
    'coursetab-d' => $coursetab4,
    'coursetab-e' => $coursetab5,
    'hasctopbl' => !empty($ctopbl),
    'hascbottombl' => !empty($cbottombl),
    'hascstopbl' => !empty($cstopbl),
    'hascsbottombl' => !empty($csbottombl),
    'sidecourseblocks' => $sidecourseblocks,
    'hassidecourseblocks' => $hassidecourseblocks,
    'ipcourseimage' => $courseimage,
    'iscoursepage' => $iscoursepage,
    // Moodle 4.
    'courseindexopen' => $courseindexopen,
    'blockdraweropen' => $blockdraweropen,
    'courseindex' => $courseindex,
    'primarymoremenu' => $primarymenu['moremenu'],
    'secondarymoremenu' => $secondarynavigation ?: false,
    'headercontent' => $headercontent,
    'forceblockdraweropen' => $forceblockdraweropen,
    'regionmainsettingsmenu' => $regionmainsettingsmenu,
    'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
    'overflow' => $overflow,
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
$templatecontext = array_merge($templatecontext, $themesettings->course_settings());

$PAGE->requires->js_call_amd('theme_baz/rui', 'init');
if (theme_baz_get_setting('backtotop') == '1') {
    $PAGE->requires->js_call_amd('theme_baz/backtotop', 'init');
}

echo $OUTPUT->render_from_template('theme_baz/tmpl-course', $templatecontext);
