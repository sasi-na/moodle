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

// Bespoke Calendar for student-only users. The calendar uses the shared
// 'standard' layout, so it is matched by pagetype. Decided before block regions
// render (block JS already suppressed for this pagetype by theme_baz_page_init).
if ($PAGE->pagetype === 'calendar-view' && \theme_baz\local\experience::is_on('calendar', $USER->id)) {
    $themesettings = new \theme_baz\util\theme_settings();
    $studentcalendar = new \theme_baz\output\student_calendar();
    $cextraclasses = ['uses-drawers'];
    if (theme_baz_get_setting('darkmodefirst') == '1') {
        $cextraclasses[] = 'theme-dark';
    }
    $templatecontext = array_merge([
        'sitename' => format_string($SITE->shortname, true,
            ['context' => context_course::instance(SITEID), 'escape' => false]),
        'output' => $OUTPUT,
        'bodyattributes' => $OUTPUT->body_attributes($cextraclasses),
        'siteurl' => $CFG->wwwroot,
        'navdraweropen' => false,
    ], $themesettings->global_settings(), $studentcalendar->export_for_template($OUTPUT));
    echo $OUTPUT->render_from_template('theme_baz/student-calendar', $templatecontext);
    return;
}

// Bespoke Grades (overview report) for student-only users. Shares the 'standard'
// layout, so it is matched by pagetype (block JS already suppressed by page_init).
if ($PAGE->pagetype === 'grade-report-overview-index' && \theme_baz\local\experience::is_on('grades', $USER->id)) {
    $themesettings = new \theme_baz\util\theme_settings();
    $studentgrades = new \theme_baz\output\student_grades();
    $gextraclasses = ['uses-drawers'];
    if (theme_baz_get_setting('darkmodefirst') == '1') {
        $gextraclasses[] = 'theme-dark';
    }
    $templatecontext = array_merge([
        'sitename' => format_string($SITE->shortname, true,
            ['context' => context_course::instance(SITEID), 'escape' => false]),
        'output' => $OUTPUT,
        'bodyattributes' => $OUTPUT->body_attributes($gextraclasses),
        'siteurl' => $CFG->wwwroot,
        'navdraweropen' => false,
    ], $themesettings->global_settings(), $studentgrades->export_for_template($OUTPUT));
    echo $OUTPUT->render_from_template('theme_baz/student-grades', $templatecontext);
    return;
}

// Bespoke Student e-ID Card portal for eligible-batch student-only users. Shares
// the 'standard' layout, matched by pagetype. Falls through on any failure.
if ($PAGE->pagetype === 'local-studentid-index' && \theme_baz\local\experience::is_on('idcard', $USER->id)) {
    try {
        $themesettings = new \theme_baz\util\theme_settings();
        $studentidcard = new \theme_baz\output\student_idcard();
        $idextraclasses = ['uses-drawers'];
        if (theme_baz_get_setting('darkmodefirst') == '1') {
            $idextraclasses[] = 'theme-dark';
        }
        $templatecontext = array_merge([
            'sitename' => format_string($SITE->shortname, true,
                ['context' => context_course::instance(SITEID), 'escape' => false]),
            'output' => $OUTPUT,
            'bodyattributes' => $OUTPUT->body_attributes($idextraclasses),
            'siteurl' => $CFG->wwwroot,
            'navdraweropen' => false,
        ], $themesettings->global_settings(), $studentidcard->export_for_template($OUTPUT));
        echo $OUTPUT->render_from_template('theme_baz/student-idcard', $templatecontext);
        return;
    } catch (\Throwable $e) {
        debugging('theme_baz student_idcard failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        // Fall through to the standard layout (plugin renders its own content).
    }
}

// Bespoke wrapper for the e-ID request form. The plugin renders the form into
// region-main; we wrap it in the app shell and restyle it (uploads untouched).
if ($PAGE->pagetype === 'local-studentid-request' && \theme_baz\local\experience::is_on('idcard', $USER->id)) {
    try {
        $themesettings = new \theme_baz\util\theme_settings();
        $studentidform = new \theme_baz\output\student_idform();
        $ifextraclasses = ['uses-drawers'];
        if (theme_baz_get_setting('darkmodefirst') == '1') {
            $ifextraclasses[] = 'theme-dark';
        }
        $templatecontext = array_merge([
            'sitename' => format_string($SITE->shortname, true,
                ['context' => context_course::instance(SITEID), 'escape' => false]),
            'output' => $OUTPUT,
            'bodyattributes' => $OUTPUT->body_attributes($ifextraclasses),
            'siteurl' => $CFG->wwwroot,
            'navdraweropen' => false,
        ], $themesettings->global_settings(), $studentidform->export_for_template($OUTPUT));
        echo $OUTPUT->render_from_template('theme_baz/student-idform', $templatecontext);
        return;
    } catch (\Throwable $e) {
        debugging('theme_baz student_idform failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        // Fall through to the standard layout.
    }
}

// Bespoke shell wrapper for My Files + Profile. The core page renders its own
// content + JS into region-main; we wrap it in the app shell and restyle it so
// all existing features/filters are preserved. Falls through on any failure.
$wrapviews = ['user-files' => 'files', 'user-profile' => 'profile',
    'blog-external_blogs' => 'settings',
    'badges-mybadges' => 'badges', 'badges-badge' => 'badges',
    'blog-index' => 'blog', 'blog-edit' => 'blog',
    // Event registration: the learner form + open-forms list (default 'standard'
    // layout → columns2, wrapped in the shell so the form gets the redesigned card
    // + gold-button format).
    'local-eventreg-view' => 'eventreg', 'local-eventreg-index' => 'eventreg',
    // Support pages: raise, the learner's own list, and a ticket thread.
    'local-helpdesk-raise' => 'helpdesk', 'local-helpdesk-mytickets' => 'helpdesk',
    'local-helpdesk-ticket' => 'helpdesk'];
// The 'settings' wrap is gated by the shared 'account' view toggle.
$wrapgate = ($wrapviews[$PAGE->pagetype] ?? '') === 'settings'
    ? 'account' : ($wrapviews[$PAGE->pagetype] ?? '');
if (isset($wrapviews[$PAGE->pagetype])
        && \theme_baz\local\experience::is_on($wrapgate, $USER->id)) {
    try {
        $themesettings = new \theme_baz\util\theme_settings();
        $studentwrap = new \theme_baz\output\student_wrap($wrapviews[$PAGE->pagetype]);
        $wrextraclasses = ['uses-drawers'];
        if (theme_baz_get_setting('darkmodefirst') == '1') {
            $wrextraclasses[] = 'theme-dark';
        }
        $templatecontext = array_merge([
            'sitename' => format_string($SITE->shortname, true,
                ['context' => context_course::instance(SITEID), 'escape' => false]),
            'output' => $OUTPUT,
            'bodyattributes' => $OUTPUT->body_attributes($wrextraclasses),
            'siteurl' => $CFG->wwwroot,
            'navdraweropen' => false,
        ], $themesettings->global_settings(), $studentwrap->export_for_template($OUTPUT));
        echo $OUTPUT->render_from_template('theme_baz/student-wrap', $templatecontext);
        return;
    } catch (\Throwable $e) {
        debugging('theme_baz student_wrap failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        // Fall through to the standard layout.
    }
}

// The same shell for support staff, with their own sidebar. Gated on the staff
// audience rather than the student experience, which is student-only by design.
$staffwrapviews = ['local-helpdesk-queue' => 'queue',
    'local-helpdesk-forwarded' => 'forwarded',
    'local-helpdesk-ticket' => 'staffticket'];
if (isset($staffwrapviews[$PAGE->pagetype]) && \theme_baz\local\shell::is_staff()) {
    try {
        $themesettings = new \theme_baz\util\theme_settings();
        $staffwrap = new \theme_baz\output\student_wrap($staffwrapviews[$PAGE->pagetype]);
        $stextraclasses = ['uses-drawers'];
        if (theme_baz_get_setting('darkmodefirst') == '1') {
            $stextraclasses[] = 'theme-dark';
        }
        $templatecontext = array_merge([
            'sitename' => format_string($SITE->shortname, true,
                ['context' => context_course::instance(SITEID), 'escape' => false]),
            'output' => $OUTPUT,
            'bodyattributes' => $OUTPUT->body_attributes($stextraclasses),
            'siteurl' => $CFG->wwwroot,
            'navdraweropen' => false,
        ], $themesettings->global_settings(), $staffwrap->export_for_template($OUTPUT));
        echo $OUTPUT->render_from_template('theme_baz/student-wrap', $templatecontext);
        return;
    } catch (\Throwable $e) {
        debugging('theme_baz staff_wrap failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        // Fall through to the standard layout.
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

// Everything else a student lands on with this layout.
//
// admin_wrap above deliberately returns false for students, on the grounds that
// they have their own redesigned pages. That is only half true: the bespoke
// student views are specific pages (dashboard, courses, grades, profile, …) and
// the allow-lists earlier in this file name a further handful. Anything outside
// both -- core's notice and confirmation interstitials, which set no pagelayout
// of their own and so land on 'standard' -- had no wrap at all and rendered
// classic.
//
// The one that made this visible: /course/loginas.php ends in notice(), and by
// then session_manager::loginas() has already switched $USER to the student, so
// the page renders as a student and falls through every gate.
//
// Same shell, same failure behaviour as the wraps above: fall through to the
// classic layout on any error, so a page that does not suit the shell degrades
// rather than breaking.
if (\theme_baz\local\experience::is_on('generic', $USER->id)) {
    try {
        $themesettings = new \theme_baz\util\theme_settings();
        $genericwrap = new \theme_baz\output\student_wrap('generic');
        $gnextraclasses = ['uses-drawers'];
        if (theme_baz_get_setting('darkmodefirst') == '1') {
            $gnextraclasses[] = 'theme-dark';
        }
        $templatecontext = array_merge([
            'sitename' => format_string($SITE->shortname, true,
                ['context' => context_course::instance(SITEID), 'escape' => false]),
            'output' => $OUTPUT,
            'bodyattributes' => $OUTPUT->body_attributes($gnextraclasses),
            'siteurl' => $CFG->wwwroot,
            'navdraweropen' => false,
            'darkmodeon' => !empty($darkmodeon),
            'darkmodetheme' => !empty($darkmodetheme),
        ], $themesettings->global_settings(), $genericwrap->export_for_template($OUTPUT));
        echo $OUTPUT->render_from_template('theme_baz/student-wrap', $templatecontext);
        return;
    } catch (\Throwable $e) {
        debugging('theme_baz generic student wrap: ' . $e->getMessage(), DEBUG_DEVELOPER);
        // Fall through to the standard layout.
    }
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
$iscoursepage = false;

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
    'regionmainsettingsmenu' => $regionmainsettingsmenu,
    'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
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

echo $OUTPUT->render_from_template('theme_baz/tmpl-columns2', $templatecontext);
