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
global $USER;
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
    $navdraweropen = (get_user_preferences('drawer-open-nav', 'true') == 'true');
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

// Bespoke student dashboard for student-only users. Decided BEFORE the block
// regions are rendered, so the default dashboard blocks' JavaScript is never
// queued for DOM this layout doesn't output (which would throw console errors).
// Guard on pagetype: the Messages page (message-index) also uses this layout,
// and must not be hijacked by the dashboard gate.
$studentdashboardon = ($PAGE->pagetype === 'my-index')
    && \theme_baz\local\experience::is_on('dashboard', $USER->id);

// Bespoke Messages page — the full messaging app wrapped in the navy/gold shell
// (all conversations/contacts/search JS preserved). Reached from the redesigned
// message drawer's "See all" and from message links.
if ($PAGE->pagetype === 'message-index'
        && \theme_baz\local\experience::is_on('messages', $USER->id)) {
    try {
        $themesettings = new \theme_baz\util\theme_settings();
        $studentwrap = new \theme_baz\output\student_wrap('messages');
        $templatecontext = array_merge([
            'sitename' => format_string($SITE->shortname, true,
                ['context' => context_course::instance(SITEID), 'escape' => false]),
            'output' => $OUTPUT,
            'bodyattributes' => $OUTPUT->body_attributes($extraclasses),
            'siteurl' => $CFG->wwwroot,
            'navdraweropen' => false,
            'darkmodeon' => !empty($darkmodeon),
            'darkmodetheme' => !empty($darkmodetheme),
        ], $themesettings->global_settings(), $studentwrap->export_for_template($OUTPUT));
        echo $OUTPUT->render_from_template('theme_baz/student-wrap', $templatecontext);
        return;
    } catch (\Throwable $e) {
        debugging('theme_baz student messages wrap: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
}

// "New theme" (two-level top bar) for the student + admin dashboards. Off by
// default; switched on in Baz settings > New theme, or previewed per session with
// /my/?newtheme=1. Falls back to the current dashboards on any error.
$darkmodeon = $darkmodeon ?? false;
$darkmodetheme = $darkmodetheme ?? false;
$ntrender = function(string $kind) use ($OUTPUT, $SITE, $CFG, $extraclasses, $darkmodeon, $darkmodetheme): bool {
    try {
        $themesettings = new \theme_baz\util\theme_settings();
        $dash = $kind === 'admin' ? new \theme_baz\output\nt_admin_dashboard() : new \theme_baz\output\nt_student_dashboard();
        $ctx = array_merge([
            'sitename' => format_string($SITE->shortname, true,
                ['context' => context_course::instance(SITEID), 'escape' => false]),
            'output' => $OUTPUT,
            'bodyattributes' => $OUTPUT->body_attributes(array_merge($extraclasses, ['nt-newtheme'])),
            'siteurl' => $CFG->wwwroot,
            'navdraweropen' => false,
            'darkmodeon' => !empty($darkmodeon),
            'darkmodetheme' => !empty($darkmodetheme),
            'ntcssurl' => \theme_baz\local\newtheme::cssurl(),
            'ntwidgetsurl' => \theme_baz\local\newtheme::asseturl('widgets.js'),
        ], $themesettings->global_settings(), $dash->export_for_template($OUTPUT));
        $ctx['weatherwidget'] = '' /* Weather removed from all dashboards (display only; block_weather untouched). */;
        // Options 2-5 all show the same widgets and colours as Option 1; only the menu design differs
        // (variants-nav.css). The old per-option dashboards (nt-student-rail/dock/campus) are not used.
        $tpl = 'theme_baz/nt-' . $kind . '-dashboard';
        $html = $OUTPUT->render_from_template($tpl, $ctx);
        echo $html;
        return true;
    } catch (\Throwable $e) {
        debugging('theme_baz new theme dashboard: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return false;
    }
};
$ntison = ($PAGE->pagetype === 'my-index') && function_exists('theme_baz_nt_feature') && theme_baz_nt_feature('dashboards');

if ($studentdashboardon) {
    if ($ntison && $ntrender('student')) {
        return;
    }
    $themesettings = new \theme_baz\util\theme_settings();
    $studentdashboard = new \theme_baz\output\student_dashboard();
    $templatecontext = array_merge([
        'sitename' => format_string($SITE->shortname, true,
            ['context' => context_course::instance(SITEID), 'escape' => false]),
        'output' => $OUTPUT,
        'bodyattributes' => $OUTPUT->body_attributes($extraclasses),
        'siteurl' => $CFG->wwwroot,
        'navdraweropen' => false,
        'darkmodeon' => !empty($darkmodeon),
        'darkmodetheme' => !empty($darkmodetheme),
    ], $themesettings->global_settings(), $studentdashboard->export_for_template($OUTPUT));
    $templatecontext['weatherwidget'] = '' /* Weather removed from all dashboards (display only; block_weather untouched). */; // Weather card (block_weather).
    echo $OUTPUT->render_from_template('theme_baz/student-dashboard', $templatecontext);
    return;
}

// Bespoke teacher dashboard for the staff audiences (needs the staff shell
// master on + staff_view_dashboard -- off / pilot roster / all, and unlike the
// other gates this one is ON when it has never been configured). Everything on it links into the
// existing grader / course / report screens; falls back to the classic
// dashboard on any error.
//
// PM is included so that every audience reaching the redesigned chrome also gets
// a redesigned dashboard. A PM with no teaching courses lands on the empty state
// rather than a section that silently disappears -- keep this list in step with
// the matching test in theme/baz/lib.php.
$teacheraud = \theme_baz\local\shell::audience($USER->id);
if ($PAGE->pagetype === 'my-index'
        && \theme_baz\local\gate::allows('staff_view_dashboard', true)
        && in_array($teacheraud,
            [\theme_baz\local\shell::AUD_FACULTY, \theme_baz\local\shell::AUD_TA,
             \theme_baz\local\shell::AUD_PM], true)) {
    try {
        $themesettings = new \theme_baz\util\theme_settings();
        $teacherdashboard = new \theme_baz\output\teacher_dashboard();
        $templatecontext = array_merge([
            'sitename' => format_string($SITE->shortname, true,
                ['context' => context_course::instance(SITEID), 'escape' => false]),
            'output' => $OUTPUT,
            'bodyattributes' => $OUTPUT->body_attributes($extraclasses),
            'siteurl' => $CFG->wwwroot,
            'navdraweropen' => false,
            'darkmodeon' => !empty($darkmodeon),
            'darkmodetheme' => !empty($darkmodetheme),
        ], $themesettings->global_settings(), $teacherdashboard->export_for_template($OUTPUT));
        $templatecontext['weatherwidget'] = '' /* Weather removed from all dashboards (display only; block_weather untouched). */; // Weather card (block_weather).
    echo $OUTPUT->render_from_template('theme_baz/teacher-dashboard', $templatecontext);
        return;
    } catch (\Throwable $e) {
        debugging('theme_baz teacher dashboard: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
}

// Bespoke admin dashboard for site administrators (site overview, engagement,
// system health, quick actions, recent config changes). Site-admins only, gated
// behind admin_view_dashboard -- off / pilot roster / all, off by default. Every tile links into the real
// admin screens; falls back to the classic dashboard on any error.
if ($PAGE->pagetype === 'my-index'
        && \theme_baz\local\gate::allows('admin_view_dashboard', false)
        && is_siteadmin($USER->id)) {
    if ($ntison && $ntrender('admin')) {
        return;
    }
    try {
        $themesettings = new \theme_baz\util\theme_settings();
        $admindashboard = new \theme_baz\output\admin_dashboard();
        $templatecontext = array_merge([
            'sitename' => format_string($SITE->shortname, true,
                ['context' => context_course::instance(SITEID), 'escape' => false]),
            'output' => $OUTPUT,
            'bodyattributes' => $OUTPUT->body_attributes($extraclasses),
            'siteurl' => $CFG->wwwroot,
            'navdraweropen' => false,
            'darkmodeon' => !empty($darkmodeon),
            'darkmodetheme' => !empty($darkmodetheme),
        ], $themesettings->global_settings(), $admindashboard->export_for_template($OUTPUT));
        $templatecontext['weatherwidget'] = '' /* Weather removed from all dashboards (display only; block_weather untouched). */; // Weather card (block_weather).
        echo $OUTPUT->render_from_template('theme_baz/admin-dashboard', $templatecontext);
        return;
    } catch (\Throwable $e) {
        debugging('theme_baz admin dashboard: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
}

$siteurl = $CFG->wwwroot;

// NO admin shell hook here, deliberately. /my/ keeps its content in five block
// regions (dtopblocks, dbottomblocks, dleftblocks, drightblocks, dmiddleblocks)
// and renders nothing into main_content, so the admin shell -- which emits
// main_content plus side-pre only -- produced a page of chrome around an empty
// region. The dashboard already has three purpose-built bespoke views above
// (student, staff, admin); anyone without one belongs on the classic dashboard.

$blockshtml = $OUTPUT->blocks('side-pre');
$blockshtml2 = $OUTPUT->blocks('dtopblocks');
$blockshtml3 = $OUTPUT->blocks('dbottomblocks');
$blockshtml4 = $OUTPUT->blocks('dleftblocks');
$blockshtml5 = $OUTPUT->blocks('drightblocks');
$blockshtml6 = $OUTPUT->blocks('dmiddleblocks');

$hasblocks = (strpos($blockshtml, 'data-block=') !== false || !empty($addblockbutton));
if (!$hasblocks) {
    $blockdraweropen = false;
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
$buildregionmainsettings = !$PAGE->include_region_main_settings_in_header_actions()
    && !$PAGE->has_secondary_navigation();
// If the settings menu will be included in the header then don't add it here.
$regionmainsettingsmenu = $buildregionmainsettings ? $OUTPUT->region_main_settings_menu() : false;

$header = $PAGE->activityheader;
$headercontent = $header->export_for_template($renderer);

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
    'sitename' => format_string($SITE->shortname,
        true,
        ['context' => context_course::instance(SITEID), "escape" => false]),
    'output' => $OUTPUT,
    'bodyattributes' => $bodyattributes,
    'darkmodeon' => !empty($darkmodeon),
    'darkmodetheme' => !empty($darkmodetheme),
    'siteurl' => $siteurl,
    'sidepreblocks' => $blockshtml,
    'dtopblocks' => $blockshtml2,
    'dbottomblocks' => $blockshtml3,
    'dleftblocks' => $blockshtml4,
    'drightblocks' => $blockshtml5,
    'dmiddleblocks' => $blockshtml6,
    'hasblocks' => $hasblocks,
    'hasdtopblocks' => !empty($blockshtml2),
    'hasdbottomblocks' => !empty($blockshtml3),
    'hasdleftblocks' => !empty($blockshtml4),
    'hasdrightblocks' => !empty($blockshtml5),
    'hasdmiddleblocks' => !empty($blockshtml6),
    'regionmainsettingsmenu' => $regionmainsettingsmenu,
    'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
    'navdraweropen' => $navdraweropen,
    'draweropenright' => $draweropenright,
    'isnotloggedin' => $isnotloggedin,
    // Moodle 4.
    'blockdraweropen' => $blockdraweropen,
    'primarymoremenu' => $primarymenu['moremenu'],
    'secondarymoremenu' => $secondarynavigation ?: false,
    'forceblockdraweropen' => $forceblockdraweropen,
    'regionmainsettingsmenu' => $regionmainsettingsmenu,
    'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
    'overflow' => $overflow,
    'headercontent' => $headercontent,
    'addblockbutton' => $addblockbutton
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

// Dashboard banner carousel (admin-configurable). Per-slide cohort targeting
// and display order are resolved by the shared helper so this path matches the
// bespoke student dashboard exactly.
$bannerslides = theme_baz_get_dashboard_banners();
$showbanner = !empty($bannerslides);

$bannerdelay = (int) theme_baz_get_setting('dashboardbannerdelay');
if ($bannerdelay <= 0) {
    $bannerdelay = 5000;
}
$bannereffect = theme_baz_get_setting('dashboardbannereffect');
if (empty($bannereffect)) {
    $bannereffect = 'slide';
}

$templatecontext['showbanner'] = $showbanner;
$templatecontext['bannerslides'] = $bannerslides;
$templatecontext['bannerdelay'] = $bannerdelay;
$templatecontext['bannereffect'] = $bannereffect;

echo $OUTPUT->render_from_template('theme_baz/tmpl-dashboard', $templatecontext);
