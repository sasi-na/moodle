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
 *
 * @package   theme_baz
 * @copyright Copyright © 2021 onwards Marcin Czaja (https://rosea.io)
 * @license   Commercial https://themeforest.net/licenses
 *
 */

defined('MOODLE_INTERNAL') || die();

$page = new admin_settingpage('theme_baz_settingstopbar', get_string('settingstopbar', 'theme_baz'));

// Features: switch each one on for Option 1, Option 2, both or neither (same code for both).
require_once($CFG->dirroot . '/theme/baz/lib.php');
$page->add(new admin_setting_heading('theme_baz/nt_featuresheading',
    get_string('ntf_heading', 'theme_baz'), get_string('ntf_heading_desc', 'theme_baz')));
$ntfchoices = [
    'left' => get_string('ntf_left', 'theme_baz'),
    'top' => get_string('ntf_top', 'theme_baz'),
    'rail' => get_string('ntf_rail', 'theme_baz'),
    'dock' => get_string('ntf_dock', 'theme_baz'),
    'campus' => get_string('ntf_campus', 'theme_baz'),
];
// First feature: the navigation itself, for every page (left sidebar or top bar).
$page->add(new admin_setting_configselect('theme_baz/navlayout',
    get_string('navlayout', 'theme_baz'), get_string('navlayout_desc', 'theme_baz'), 'left', $ntfchoices));
// The features follow Navigation automatically: listed with what is in use now (no choices).
$ntnav = class_exists('\\theme_baz\\local\\newtheme') ? \theme_baz\local\newtheme::layout() : get_config('theme_baz', 'navlayout');
$ntnav = isset($ntfchoices[$ntnav]) ? $ntnav : 'left';
foreach (array_keys(theme_baz_nt_features()) as $ntf) {
    $ntalways = ($ntf === 'branding');
    $ntnow = html_writer::div(
        html_writer::span('&#10003;', 'nt-feat-tick') . ' ' . get_string('ntf_now', 'theme_baz') . ' '
        . html_writer::tag('b', $ntalways ? get_string('ntf_always', 'theme_baz') : $ntfchoices[$ntnav])
        . ($ntalways ? '' : ' ' . html_writer::span(get_string('ntf_follows', 'theme_baz'), 'nt-feat-follow')),
        'nt-feat-now' . ($ntalways ? ' is-always' : ($ntnav !== 'left' ? ' is-top' : '')),
        ['data-feature' => $ntf, 'data-always' => $ntalways ? 1 : 0]);
    $page->add(new admin_setting_description('theme_baz/nt_finfo_' . $ntf,
        get_string('ntf_' . $ntf, 'theme_baz'), $ntnow . get_string('ntf_' . $ntf . '_desc', 'theme_baz')));
}
$page->add(new admin_setting_heading('theme_baz/nt_designheading',
    get_string('ntset_designheading', 'theme_baz'), get_string('ntset_designheading_desc', 'theme_baz')));
$setting = new admin_setting_configcheckbox('theme_baz/nt_showtabs',
    get_string('ntset_showtabs', 'theme_baz'), get_string('ntset_showtabs_desc', 'theme_baz'), 1);
// Read on each page (no theme cache rebuild needed, so saving is instant).
$page->add($setting);

$setting = new admin_setting_configselect('theme_baz/nt_cardstyle',
    get_string('ntset_cardstyle', 'theme_baz'), get_string('ntset_cardstyle_desc', 'theme_baz'), 'outlined', [
        'outlined' => get_string('ntset_cardstyle_outlined', 'theme_baz'),
        'flat' => get_string('ntset_cardstyle_flat', 'theme_baz'),
    ]);
// Read on each page (no theme cache rebuild needed, so saving is instant).
$page->add($setting);

$page->add(new admin_setting_heading('theme_baz/nt_brandheading',
    get_string('ntset_brandheading', 'theme_baz'), get_string('ntset_brandheading_desc', 'theme_baz')));
$setting = new admin_setting_configtext('theme_baz/nt_brandname',
    get_string('ntset_brandname', 'theme_baz'), get_string('ntset_brandname_desc', 'theme_baz'), 'JAIN Online', PARAM_TEXT);
$page->add($setting);
$setting = new admin_setting_configstoredfile('theme_baz/nt_brandlogo',
    get_string('ntset_brandlogo', 'theme_baz'), get_string('ntset_brandlogo_desc', 'theme_baz'), 'nt_brandlogo', 0,
    ['maxfiles' => 1, 'accepted_types' => ['.png', '.svg', '.jpg', '.jpeg', '.webp']]);
// Read on each page (no theme cache rebuild needed, so saving is instant).
$page->add($setting);

$page->add(new admin_setting_heading('theme_baz/nt_colourheading',
    get_string('ntset_colourheading', 'theme_baz'), get_string('ntset_colourheading_desc', 'theme_baz')));

foreach (['barcolour' => '#0f1f3d', 'accentcolour' => '#f4b23e', 'pagecolour' => '#eef2f7'] as $name => $default) {
    $setting = new admin_setting_configcolourpicker('theme_baz/nt_' . $name,
        get_string('ntset_' . $name, 'theme_baz'), get_string('ntset_' . $name . '_desc', 'theme_baz'), $default);
    // Read on each page (no theme cache rebuild needed, so saving is instant).
    $page->add($setting);
}

$page->add(new admin_setting_heading('theme_baz/nt_calendarheading',
    get_string('ntset_calendarheading', 'theme_baz'), get_string('ntset_calendarheading_desc', 'theme_baz')));

$page->add(new admin_setting_configtextarea('theme_baz/nt_holidays',
    get_string('ntset_holidays', 'theme_baz'), get_string('ntset_holidays_desc', 'theme_baz'),
    get_string('ntset_holidays_default', 'theme_baz'), PARAM_RAW, 60, 14));

$setting = new admin_setting_configtextarea('theme_baz/nt_customcss',
    get_string('ntset_customcss', 'theme_baz'), get_string('ntset_customcss_desc', 'theme_baz'), '', PARAM_RAW);
// Read on each page (no theme cache rebuild needed, so saving is instant).
$page->add($setting);


$name = 'theme_baz/editbtntop';
$title = get_string('editbtntop', 'theme_baz');
$description = get_string('editbtntop_desc', 'theme_baz', $a);
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/turnoffdashboardlink';
$title = get_string('turnoffdashboardlink', 'theme_baz');
$description = get_string('turnoffdashboardlink_desc', 'theme_baz', $a);
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/customlogo';
$title = get_string('customlogo', 'theme_baz');
$description = get_string('customlogo_desc', 'theme_baz');
$opts = array('accepted_types' => array('.png', '.jpg', '.svg', 'gif'));
$setting = new admin_setting_configstoredfile($name, $title, $description, 'customlogo', 0, $opts);
$page->add($setting);

$name = 'theme_baz/logocontainer';
$title = get_string('logocontainer', 'theme_baz');
$description = get_string('logocontainer_desc', 'theme_baz');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/topbarhamburgermenu';
$title = get_string('topbarhamburgermenu', 'theme_baz');
$description = get_string('topbarhamburgermenu_desc', 'theme_baz');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

// Additional Button next to the Log in button.
$name = 'theme_baz/htopbaradditionalbtn';
$heading = get_string('htopbaradditionalbtn', 'theme_baz');
$setting = new admin_setting_heading($name, $heading, format_text(get_string('htopbaradditionalbtn_desc', 'theme_baz'), FORMAT_MARKDOWN));
$page->add($setting);

$name = 'theme_baz/topbaradditionalbtn';
$title = get_string('topbaradditionalbtn', 'theme_baz');
$description = get_string('topbaradditionalbtn_desc', 'theme_baz', $a);
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/stopbaradditionalbtn';
$title = get_string('stopbaradditionalbtn', 'theme_baz');
$description = get_string('stopbaradditionalbtn_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/topbaradditionalbtnurl';
$title = get_string('topbaradditionalbtnurl', 'theme_baz');
$description = get_string('topbaradditionalbtnurl_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$page->add($setting);

//My Courses String
$name = 'theme_baz/hmycoursesbtn';
$heading = get_string('hmycoursesbtn', 'theme_baz');
$setting = new admin_setting_heading($name, $heading, format_text(get_string('hmycoursesbtn_desc', 'theme_baz'), FORMAT_MARKDOWN));
$page->add($setting);

$name = 'theme_baz/stringallcourses';
$title = get_string('stringallcourses', 'theme_baz');
$description = get_string('stringallcourses_desc', 'theme_baz');
$default = 'List of all available courses';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/stringnocourses';
$title = get_string('stringnocourses', 'theme_baz');
$description = get_string('stringnocourses_desc', 'theme_baz');
$default = 'You are not enrolled in any courses.';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/stringshowhidden';
$title = get_string('stringshowhidden', 'theme_baz');
$description = get_string('stringshowhidden_desc', 'theme_baz');
$default = 'Show hidden courses';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/mycoursesnavlimit';
$title = get_string('mycoursesnavlimit', 'theme_baz');
$description = get_string('mycoursesnavlimit_desc', 'theme_baz');
$default = 50;
$setting = new admin_setting_configtext($name, $title, $description, $default, PARAM_INT);
$page->add($setting);

$name = 'theme_baz/displayfilterhiddenchbx';
$title = get_string('displayfilterhiddenchbx', 'theme_baz');
$description = get_string('displayfilterhiddenchbx_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/stringshowonlyinprogress';
$title = get_string('stringshowonlyinprogress', 'theme_baz');
$description = get_string('stringshowonlyinprogress_desc', 'theme_baz');
$default = 'Only courses in progress';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/stringdetails';
$title = get_string('stringdetails', 'theme_baz');
$description = get_string('stringdetails_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);


// Colors
$name = 'theme_baz/htopbarcolors';
$heading = get_string('htopbarcolors', 'theme_baz');
$setting = new admin_setting_heading($name, $heading, format_text(get_string('htopbarcolors_desc', 'theme_baz'), FORMAT_MARKDOWN));
$page->add($setting);

$name = 'theme_baz/colortopbarbg';
$title = get_string('colortopbarbg', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colortopbartext';
$title = get_string('colortopbartext', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colortopbarbtn';
$title = get_string('colortopbarbtn', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colortopbarbtntext';
$title = get_string('colortopbarbtntext', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colortopbarbtnhover';
$title = get_string('colortopbarbtnhover', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/colortopbarbtnhovertext';
$title = get_string('colortopbarbtnhovertext', 'theme_baz');
$description = get_string('colordesc_desc', 'theme_baz');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

// SMS provider for the mobile-number one-time code.
$page->add(new admin_setting_heading('theme_baz/ct_smsheading',
    get_string('ct_smsheading', 'theme_baz'), get_string('ct_smsheading_desc', 'theme_baz')));
$page->add(new admin_setting_configselect('theme_baz/sms_provider', get_string('ct_sms_provider', 'theme_baz'), '', 'none', [
    'none' => 'None (SMS off)', 'fast2sms' => 'Fast2SMS', 'msg91' => 'MSG91', 'twilio' => 'Twilio', 'textbee' => 'TextBee (free, your own Android phone)']));
$page->add(new admin_setting_configpasswordunmask('theme_baz/sms_key', get_string('ct_sms_key', 'theme_baz'), '', ''));
$page->add(new admin_setting_configpasswordunmask('theme_baz/sms_secret', get_string('ct_sms_secret', 'theme_baz'), '', ''));
$page->add(new admin_setting_configtext('theme_baz/sms_sender', get_string('ct_sms_sender', 'theme_baz'), '', '', PARAM_RAW_TRIMMED));
$page->add(new admin_setting_configcheckbox('theme_baz/sms_demo', get_string('ct_sms_demo', 'theme_baz'),
    get_string('ct_sms_demo_desc', 'theme_baz'), 0));
$page->add(new admin_setting_configtext('theme_baz/ct_max_changes', get_string('ct_max_changes', 'theme_baz'),
    get_string('ct_max_changes_desc', 'theme_baz'), 5, PARAM_INT));

// Must add the page after definiting all the settings!
$settings->add($page);
