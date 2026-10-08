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
 * @copyright 2024 Marcin Czaja (https://rosea.io)
 * @license   Commercial https://themeforest.net/licenses
 *
 */


defined('MOODLE_INTERNAL') || die();

// Dashboard settings.
$page = new admin_settingpage('theme_baz_settingsdashboard', get_string('settingsdashboard', 'theme_baz'));

// ---------------------------------------------------------------------------
// New student experience — bespoke views for student-only users.
// One master switch governs all bespoke views (dashboard, my courses, …); the
// per-view toggles let you disable an individual page without taking down the
// rest. Admin-only — there is no student-facing toggle.
// ---------------------------------------------------------------------------
$name = 'theme_baz/hstudentexperience';
$setting = new admin_setting_heading($name, get_string('hstudentexperience', 'theme_baz'),
    get_string('hstudentexperience_desc', 'theme_baz'));
$page->add($setting);

$name = 'theme_baz/student_view_helpdesk';
$title = get_string('student_view_helpdesk', 'theme_baz');
$description = get_string('student_view_helpdesk_desc', 'theme_baz');
$setting = new admin_setting_configcheckbox($name, $title, $description, 1);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

// Blocks to show in a student's course rail, as cards, by block name --
// e.g. "livesessionstats". The bespoke views do not render the side-pre
// region, so a block placed for students is otherwise invisible to exactly the
// audience it was placed for. Empty by default, so no site gains blocks it did
// not ask for, and named explicitly so nothing staff-oriented leaks in.
// The rail's "Your progress" card. Sites that name a progress-shaped block in
// student_course_blocks -- an attendance summary, say -- end up showing the same
// figures twice, so this turns the built-in card off. On by default: no existing
// site loses anything.
$name = 'theme_baz/student_rail_progress';
$title = get_string('student_rail_progress', 'theme_baz');
$description = get_string('student_rail_progress_desc', 'theme_baz');
$setting = new admin_setting_configcheckbox($name, $title, $description, 1);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/student_course_blocks';
$title = get_string('student_course_blocks', 'theme_baz');
$description = get_string('student_course_blocks_desc', 'theme_baz');
$setting = new admin_setting_configtext($name, $title, $description, '', PARAM_TEXT);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

// Support contact details, shown in the floating "Need help?" panel beneath
// whatever ticketing route the site uses. Both optional and independent: the
// panel shows whichever are set, and shows them even when no ticketing route
// exists, because they are the fallback way to reach a person.
$name = 'theme_baz/supportcontactemail';
$title = get_string('supportcontactemail', 'theme_baz');
$description = get_string('supportcontactemail_desc', 'theme_baz');
$setting = new admin_setting_configtext($name, $title, $description, '', PARAM_RAW_TRIMMED);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/supportcontactphone';
$title = get_string('supportcontactphone', 'theme_baz');
$description = get_string('supportcontactphone_desc', 'theme_baz');
$setting = new admin_setting_configtext($name, $title, $description, '', PARAM_TEXT);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/staff_shell';
$title = get_string('staff_shell', 'theme_baz');
$description = get_string('staff_shell_desc', 'theme_baz');
$setting = new admin_setting_configcheckbox($name, $title, $description, 0);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/staff_shell_roles';
$title = get_string('staff_shell_roles', 'theme_baz');
$description = get_string('staff_shell_roles_desc', 'theme_baz');
$setting = new admin_setting_configtextarea($name, $title, $description, '', PARAM_TEXT);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

// Bespoke teacher dashboard on /my/ for faculty/TA staff audiences (needs the
// staff_shell master above on). Default on, so it appears as soon as staff_shell
// is enabled; switch off to keep the classic dashboard for staff.
$name = 'theme_baz/staff_view_dashboard';
$title = get_string('staff_view_dashboard', 'theme_baz');
$description = get_string('staff_view_dashboard_desc', 'theme_baz');
$setting = new admin_setting_configselect($name, $title, $description, '1',
    \theme_baz\local\gate::mode_choices());
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

// Bespoke admin dashboard on /my/ for SITE ADMINS only (independent of the staff
// shell). Default off — replaces the classic dashboard for admins when on; every
// admin tool stays reachable via its links.
$name = 'theme_baz/admin_view_dashboard';
$title = get_string('admin_view_dashboard', 'theme_baz');
$description = get_string('admin_view_dashboard_desc', 'theme_baz');
$setting = new admin_setting_configselect($name, $title, $description, '0',
    \theme_baz\local\gate::mode_choices());
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

// Bespoke shell for the administration PAGES (distinct from the dashboard above):
// Site administration, its settings and category pages, and the management
// screens. Wrap-and-restyle only — every control on the wrapped page is kept.
// Default off.
$name = 'theme_baz/admin_view_pages';
$title = get_string('admin_view_pages', 'theme_baz');
$description = get_string('admin_view_pages_desc', 'theme_baz');
$setting = new admin_setting_configselect($name, $title, $description, '0',
    \theme_baz\local\gate::mode_choices());
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

// The pilot roster, shared by every gate above that is set to pilot mode: the
// teacher dashboard, the admin dashboard and the admin/staff pages shell. It keeps
// its original setting name because it is already populated in production.
$name = 'theme_baz/admin_view_pages_pilot';
$title = get_string('gate_pilot_roster', 'theme_baz');
$description = get_string('gate_pilot_roster_desc', 'theme_baz');
$setting = new admin_setting_configtextarea($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/enable_student_experience';
$title = get_string('enable_student_experience', 'theme_baz');
$description = get_string('enable_student_experience_desc', 'theme_baz');
$setting = new admin_setting_configcheckbox($name, $title, $description, 0);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/student_view_dashboard';
$title = get_string('student_view_dashboard', 'theme_baz');
$description = get_string('student_view_dashboard_desc', 'theme_baz');
$setting = new admin_setting_configcheckbox($name, $title, $description, 1);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/student_view_courses';
$title = get_string('student_view_courses', 'theme_baz');
$description = get_string('student_view_courses_desc', 'theme_baz');
$setting = new admin_setting_configcheckbox($name, $title, $description, 1);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/student_view_calendar';
$title = get_string('student_view_calendar', 'theme_baz');
$description = get_string('student_view_calendar_desc', 'theme_baz');
$setting = new admin_setting_configcheckbox($name, $title, $description, 1);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/student_view_grades';
$title = get_string('student_view_grades', 'theme_baz');
$description = get_string('student_view_grades_desc', 'theme_baz');
$setting = new admin_setting_configcheckbox($name, $title, $description, 1);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/student_view_coursepage';
$title = get_string('student_view_coursepage', 'theme_baz');
$description = get_string('student_view_coursepage_desc', 'theme_baz');
$setting = new admin_setting_configcheckbox($name, $title, $description, 1);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/student_view_gradereport';
$title = get_string('student_view_gradereport', 'theme_baz');
$description = get_string('student_view_gradereport_desc', 'theme_baz');
$setting = new admin_setting_configcheckbox($name, $title, $description, 1);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/student_view_idcard';
$title = get_string('student_view_idcard', 'theme_baz');
$description = get_string('student_view_idcard_desc', 'theme_baz');
$setting = new admin_setting_configcheckbox($name, $title, $description, 1);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/student_view_files';
$title = get_string('student_view_files', 'theme_baz');
$description = get_string('student_view_files_desc', 'theme_baz');
$setting = new admin_setting_configcheckbox($name, $title, $description, 1);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/student_view_profile';
$title = get_string('student_view_profile', 'theme_baz');
$description = get_string('student_view_profile_desc', 'theme_baz');
$setting = new admin_setting_configcheckbox($name, $title, $description, 1);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/student_view_activity';
$title = get_string('student_view_activity', 'theme_baz');
$description = get_string('student_view_activity_desc', 'theme_baz');
$setting = new admin_setting_configcheckbox($name, $title, $description, 0);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/student_view_badges';
$title = get_string('student_view_badges', 'theme_baz');
$description = get_string('student_view_badges_desc', 'theme_baz');
$setting = new admin_setting_configcheckbox($name, $title, $description, 1);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/student_view_blog';
$title = get_string('student_view_blog', 'theme_baz');
$description = get_string('student_view_blog_desc', 'theme_baz');
$setting = new admin_setting_configcheckbox($name, $title, $description, 1);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/student_view_eventreg';
$title = get_string('student_view_eventreg', 'theme_baz');
$description = get_string('student_view_eventreg_desc', 'theme_baz');
$setting = new admin_setting_configcheckbox($name, $title, $description, 1);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/student_view_userprofile';
$title = get_string('student_view_userprofile', 'theme_baz');
$description = get_string('student_view_userprofile_desc', 'theme_baz');
$setting = new admin_setting_configcheckbox($name, $title, $description, 1);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/student_view_interestzone';
$title = get_string('student_view_interestzone', 'theme_baz');
$description = get_string('student_view_interestzone_desc', 'theme_baz');
$setting = new admin_setting_configcheckbox($name, $title, $description, 1);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/student_view_account';
$title = get_string('student_view_account', 'theme_baz');
$description = get_string('student_view_account_desc', 'theme_baz');
$setting = new admin_setting_configcheckbox($name, $title, $description, 1);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/student_view_messages';
$title = get_string('student_view_messages', 'theme_baz');
$description = get_string('student_view_messages_desc', 'theme_baz');
$setting = new admin_setting_configcheckbox($name, $title, $description, 1);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/student_view_notifications';
$title = get_string('student_view_notifications', 'theme_baz');
$description = get_string('student_view_notifications_desc', 'theme_baz');
$setting = new admin_setting_configcheckbox($name, $title, $description, 1);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_baz/student_view_enrol';
$title = get_string('student_view_enrol', 'theme_baz');
$description = get_string('student_view_enrol_desc', 'theme_baz');
$setting = new admin_setting_configcheckbox($name, $title, $description, 1);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

// Off between exam cycles; turn on to show the gated "End Sem Exam" sidebar entry.
$name = 'theme_baz/show_endsem_exam';
$title = get_string('show_endsem_exam', 'theme_baz');
$description = get_string('show_endsem_exam_desc', 'theme_baz');
$setting = new admin_setting_configcheckbox($name, $title, $description, 0);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

// Exam audience, one checkbox per batch found in the cohort_batch profile field.
$name = 'theme_baz/endsem_exam_batches';
$title = get_string('endsem_exam_batches', 'theme_baz');
$description = get_string('endsem_exam_batches_desc', 'theme_baz');
$choices = \theme_baz\local\exam_batch::choices();
if ($choices) {
    $default = array_fill_keys(
        explode(',', \theme_baz\local\exam_batch::DEFAULT_BATCHES),
        1
    );
    $setting = new admin_setting_configmulticheckbox($name, $title, $description, $default, $choices);
    $setting->set_updatedcallback('theme_reset_all_caches');
} else {
    // No cohort_batch profile field on this tenant, so there is no audience to pick.
    $setting = new admin_setting_description($name . '_none', $title,
        get_string('endsem_exam_batches_nofield', 'theme_baz'));
}
$page->add($setting);

$name = 'theme_baz/dashboardblock1';
$title = get_string('dashboardblock1', 'theme_baz');
$description = get_string('dashboardblock1_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/dashboardblock2';
$title = get_string('dashboardblock2', 'theme_baz');
$description = get_string('dashboardblock2_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

// ---------------------------------------------------------------------------
// Dashboard banner carousel.
// ---------------------------------------------------------------------------

$name = 'theme_baz/hdashboardbanner';
$setting = new admin_setting_heading($name, get_string('hdashboardbanner', 'theme_baz'),
    get_string('hdashboardbanner_desc', 'theme_baz'));
$page->add($setting);

// Master on/off switch.
$name = 'theme_baz/displaydashboardbanner';
$title = get_string('displaydashboardbanner', 'theme_baz');
$description = get_string('displaydashboardbanner_desc', 'theme_baz');
$setting = new admin_setting_configcheckbox($name, $title, $description, 1);
$page->add($setting);

// Number of slides (minimum 1, maximum 5).
$name = 'theme_baz/dashboardbannercount';
$title = get_string('dashboardbannercount', 'theme_baz');
$description = get_string('dashboardbannercount_desc', 'theme_baz');
$choices = array();
for ($i = 1; $i <= 5; $i++) {
    $choices[$i] = $i;
}
$setting = new admin_setting_configselect($name, $title, $description, 1, $choices);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

// Transition effect.
$name = 'theme_baz/dashboardbannereffect';
$title = get_string('dashboardbannereffect', 'theme_baz');
$description = get_string('dashboardbannereffect_desc', 'theme_baz');
$choices = array(
    'slide'     => 'Slide',
    'fade'      => 'Fade',
    'cube'      => 'Cube',
    'coverflow' => 'Coverflow',
    'flip'      => 'Flip',
);
$setting = new admin_setting_configselect($name, $title, $description, 'slide', $choices);
$page->add($setting);

// Rotation delay (milliseconds).
$name = 'theme_baz/dashboardbannerdelay';
$title = get_string('dashboardbannerdelay', 'theme_baz');
$description = get_string('dashboardbannerdelay_desc', 'theme_baz');
$setting = new admin_setting_configtext($name, $title, $description, 5000, PARAM_INT);
$page->add($setting);

// Slide order: drag the slide cards to arrange them (replaces per-slide weights).
// Stores one comma-separated list of slot indices in display order.
$name = 'theme_baz/dashboardbannerorder';
$title = get_string('dashboardbannerorder', 'theme_baz');
$description = get_string('dashboardbannerorder_desc', 'theme_baz');
$setting = new \theme_baz\admin_setting_bannerorder($name, $title, $description, '');
$page->add($setting);

// Per-slide settings.
$bannercount = get_config('theme_baz', 'dashboardbannercount');
if (!$bannercount) {
    $bannercount = 1;
}

for ($bannerindex = 1; $bannerindex <= $bannercount; $bannerindex++) {
    $name = 'theme_baz/hdashboardbannerslide' . $bannerindex;
    $setting = new admin_setting_heading($name, '<span class="rui-admin-no">' .
        $bannerindex .
        '</span>' .
        get_string('hdashboardbannerslide', 'theme_baz'),
        get_string('hdashboardbannerslide_desc', 'theme_baz'));
    $page->add($setting);

    $fileid = 'dashboardbannerimg' . $bannerindex;
    $name = 'theme_baz/dashboardbannerimg' . $bannerindex;
    $title = get_string('dashboardbannerimg', 'theme_baz');
    $description = get_string('dashboardbannerimg_desc', 'theme_baz');
    $opts = array('accepted_types' => array('.png', '.jpg', '.jpeg', '.gif', '.webp', '.svg'), 'maxfiles' => 1);
    $setting = new admin_setting_configstoredfile($name, '<span class="rui-admin-no">' .
        $bannerindex .
        '</span>' .
        $title, $description, $fileid, 0, $opts);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $name = 'theme_baz/dashboardbannerlink' . $bannerindex;
    $title = get_string('dashboardbannerlink', 'theme_baz');
    $description = get_string('dashboardbannerlink_desc', 'theme_baz');
    $setting = new admin_setting_configtext($name, '<span class="rui-admin-no">' .
        $bannerindex .
        '</span>' .
        $title, $description, '', PARAM_URL);
    $page->add($setting);

    $name = 'theme_baz/dashboardbanneralt' . $bannerindex;
    $title = get_string('dashboardbanneralt', 'theme_baz');
    $description = get_string('dashboardbanneralt_desc', 'theme_baz');
    $setting = new admin_setting_configtext($name, '<span class="rui-admin-no">' .
        $bannerindex .
        '</span>' .
        $title, $description, '', PARAM_TEXT);
    $page->add($setting);

    // Audience mode: who sees this slide, by cohort_batch.
    $name = 'theme_baz/dashboardbannermode' . $bannerindex;
    $title = get_string('dashboardbannermode', 'theme_baz');
    $description = get_string('dashboardbannermode_desc', 'theme_baz');
    $modechoices = [
        0 => get_string('dashboardbannermode_all', 'theme_baz'),
        1 => get_string('dashboardbannermode_only', 'theme_baz'),
        2 => get_string('dashboardbannermode_except', 'theme_baz'),
    ];
    $setting = new admin_setting_configselect($name, '<span class="rui-admin-no">' .
        $bannerindex .
        '</span>' .
        $title, $description, 0, $modechoices);
    $page->add($setting);

    // The batches this slide targets / excludes (used only when mode is not "all").
    $name = 'theme_baz/dashboardbannercohorts' . $bannerindex;
    $title = get_string('dashboardbannercohorts', 'theme_baz');
    $description = get_string('dashboardbannercohorts_desc', 'theme_baz');
    $cohortchoices = theme_baz_get_cohort_batch_choices();
    $setting = new admin_setting_configmultiselect($name, '<span class="rui-admin-no">' .
        $bannerindex .
        '</span>' .
        $title, $description, [], $cohortchoices);
    $page->add($setting);
}


$settings->add($page);
