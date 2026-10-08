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

// Bespoke student activity shell: the navy global sidebar is the primary left
// nav, so the course-index drawer stays a toggle (closed by default).
$bazactivity = (strpos($PAGE->pagetype, 'mod-') === 0 && \theme_baz\local\experience::is_on('activity'));
if ($bazactivity) {
    $courseindexopen = false;
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

// Bespoke admin shell for course ADMINISTRATION screens that use this layout
// (enrol/index.php and friends). mod/* pages also use it and are deliberately not
// in the allow-list, so activity pages keep the ordinary course chrome.
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

if ($PAGE->course->enablecompletion == '1') {
    $extraclasses[] = 'rui-course--enablecompletion';
}

if ($PAGE->course->showactivitydates == '1') {
    $extraclasses[] = 'rui-course--showactivitydates';
}

if ($PAGE->course->visible == '1') {
    $extraclasses[] = 'rui-course--visible';
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

$enrollmentcustomlayout = false;
if ($PAGE->pagetype === 'enrol-index') {
    $enrollmentcustomlayout = true;
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
    'iscoursepage' => $iscoursepage,
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
    'enrollmentcustomlayout' => $enrollmentcustomlayout
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

// Bespoke activity shell for student-only users — adds the navy global sidebar +
// Need help / Ask JO around the (unchanged) activity content/drawers. Falls back
// to the standard incourse layout on any error.
if (!empty($bazactivity)) {
    try {
        $sw = new \theme_baz\output\student_wrap('files');
        $swctx = $sw->export_for_template($OUTPUT);
        $templatecontext = array_merge($templatecontext, [
            'baz_dashboardurl' => $swctx['dashboardurl'],
            'baz_coursesurl'   => $swctx['coursesurl'],
            'baz_calendarurl'  => $swctx['calendarurl'],
            'baz_mygradesurl'  => $swctx['mygradesurl'],
            'baz_filesurl'     => $swctx['filesurl'],
            'baz_eidcardurl'   => $swctx['eidcardurl'],
            'baz_showeidcard'  => $swctx['showeidcard'],
            'baz_support_html' => $swctx['support_html'],
            'baz_hassupport'   => $swctx['hassupport'],
            'baz_askjo'        => $swctx['askjo'],
            'baz_hasaskjo'     => $swctx['hasaskjo'],
            'baz_coursename'   => format_string($PAGE->course->fullname),
            'baz_courseurl'    => (new \moodle_url('/course/view.php', ['id' => $PAGE->course->id]))->out(false),
            'baz_activityname' => $PAGE->cm ? format_string($PAGE->cm->name) : format_string($PAGE->title),
            'baz_nav'          => theme_baz_build_activity_nav($PAGE, $OUTPUT),
            'baz_modnav'       => theme_baz_build_activity_modnav($PAGE),
            'baz_forumthread'  => theme_baz_build_forum_thread($PAGE),
            'baz_forumlist'    => theme_baz_build_forum_list($PAGE),
            'baz_forumpost'    => theme_baz_build_forum_post($PAGE),
        ]);
        echo $OUTPUT->render_from_template('theme_baz/student-activity', $templatecontext);
        return;
    } catch (\Throwable $e) {
        debugging('theme_baz student activity shell: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
}

// Bespoke self-enrolment page for student-only users — wrap the core enrol
// form in the navy/gold shell and restyle it into a course-enrolment card.
// Wrap-and-restyle only: the core enrol mform (sesskey / Enrol me / enrolment
// key) is preserved; the card look is CSS scoped to .sw-enrol. Falls back to
// the standard incourse layout on any error.
if ($PAGE->pagetype === 'enrol-index' && \theme_baz\local\experience::is_on('enrol')) {
    try {
        $sw = new \theme_baz\output\student_wrap('enrol');
        $swctx = array_merge([
            'sitename' => format_string($SITE->shortname, true,
                ['context' => context_course::instance(SITEID), 'escape' => false]),
            'output' => $OUTPUT,
            'bodyattributes' => $OUTPUT->body_attributes($extraclasses),
            'siteurl' => $CFG->wwwroot,
            'navdraweropen' => false,
            'darkmodeon' => !empty($darkmodeon),
            'darkmodetheme' => !empty($darkmodetheme),
        ], $themesettings->global_settings(), $sw->export_for_template($OUTPUT));
        echo $OUTPUT->render_from_template('theme_baz/student-wrap', $swctx);
        return;
    } catch (\Throwable $e) {
        debugging('theme_baz student enrol wrap: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
}

echo $OUTPUT->render_from_template('theme_baz/tmpl-incourse', $templatecontext);
