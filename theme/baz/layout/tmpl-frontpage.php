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
 * A front page layout for the baz theme.
 *
 * @package   theme_baz
 * @copyright 2022 - 2023 Marcin Czaja (https://rosea.io)
 * @license   Commercial https://themeforest.net/licenses
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/behat/lib.php');

// Bespoke logged-out landing (gateway): for not-logged-in / guest visitors, show
// a branded hero (the "Learn Beyond Borders" banner over the login-navy gradient)
// with a single "Log in to learn" button. Logged-in users fall through to the
// standard front page below. Any failure falls through too.
if ((!isloggedin() || isguestuser())
        && theme_baz_get_setting('bespoke_frontpage') == '1') {
    try {
        $flsettings = new \theme_baz\util\theme_settings();
        $fltheme = \theme_config::load('baz');
        $fllogo = $fltheme->setting_file_url('customlogo', 'customlogo');
        $flbanner = $fltheme->setting_file_url('frontbanner', 'frontbanner');
        $flcontext = array_merge([
            'sitename' => format_string($SITE->fullname, true,
                ['context' => context_course::instance(SITEID), 'escape' => false]),
            'output' => $OUTPUT,
            'bodyattributes' => $OUTPUT->body_attributes(['fl-page']),
            // Prefer this site's own uploaded hero, falling back to the banner
            // shipped with the theme. The shipped one carries baked-in wording, so
            // a site that has not uploaded its own would otherwise render another
            // tenant's marketing copy.
            'bannerurl' => $flbanner ?: $OUTPUT->image_url('fp/frontbanner', 'theme')->out(false),
            'loginurl' => (new \moodle_url('/login/index.php'))->out(false),
            'logourl' => $fllogo,
            'haslogo' => !empty($fllogo),
        ], $flsettings->global_settings());
        echo $OUTPUT->render_from_template('theme_baz/front-landing', $flcontext);
        return;
    } catch (\Throwable $e) {
        debugging('theme_baz front-landing failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
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

if ($draweropenright && $hasblocks) {
    $extraclasses[] = 'drawer-open-right';
}

// Start - Simple Content Builder.
$ruiscb = '';
$total = 23;
for ($i = 0; $i <= $total; $i++) {
    ${"block" . $i} = theme_baz_get_setting("block" . $i);
}

$array = array();

for ($i = 0; $i <= $total; $i++) {
    if (theme_baz_get_setting("displayblock" . $i) == '1') {
        $array[$i] = ${"block" . $i};
    }
    $array[0] = $block0;
}

asort($array);
foreach ($array as $key => $value) {
    $ruiscb .= $OUTPUT->theme_part('block' . $key);
}
// End - Simple Content Builder.

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

if ($PAGE->pagetype == 'site-index') {
    $isfrontpage = true;
} else {
    $isfrontpage = false;
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
    'ruiscb' => $ruiscb,
    'navdraweropen' => $navdraweropen,
    'draweropenright' => $draweropenright,
    'isnotloggedin' => $isnotloggedin,
    'isfrontpage' => $isfrontpage,
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

echo $OUTPUT->render_from_template('theme_baz/tmpl-frontpage', $templatecontext);
