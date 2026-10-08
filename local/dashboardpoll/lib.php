<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Injects the dashboard poll popup, scoped to the dashboard for students only.
 *
 * Mirrors local_exams_render_navbar_output: this callback fires on every page,
 * so it returns immediately unless we are on the dashboard (`my-index`), the
 * user is a student, and an active poll actually targets them — keeping the AMD
 * module off every other page and off non-eligible users.
 *
 * @return string Always '' (nothing is added to the navbar markup itself).
 */
function local_dashboardpoll_render_navbar_output() {
    global $PAGE, $USER;

    if (!isloggedin() || isguestuser()) {
        return '';
    }
    if ($PAGE->pagetype !== 'my-index') {
        return '';
    }

    $userid = (int) $USER->id;
    if (!\local_dashboardpoll\manager::is_student($userid)
            || !\local_dashboardpoll\manager::eligible_poll($userid)) {
        return '';
    }

    $PAGE->requires->js_call_amd('local_dashboardpoll/popup', 'init');

    return '';
}

/**
 * Whether the current user may view poll reports and download responses.
 *
 * Polls live in the system context, but Teacher / Non-editing teacher roles are
 * assigned at course level, so a system-context has_capability() misses them.
 * We therefore also accept the capability held in ANY course.
 *
 * @return bool
 */
function local_dashboardpoll_can_view_reports() {
    if (has_capability('local/dashboardpoll:viewreports', context_system::instance())) {
        // Admins, managers and any explicit system-level role holder.
        return true;
    }
    // Teachers hold the role per course; allow if they have it in at least one.
    return (bool) get_user_capability_course(
        'local/dashboardpoll:viewreports', null, false, '', '', 1);
}

/**
 * Adds a "Poll results" node to the primary navigation for report viewers who
 * are not site managers (managers reach it via the admin tree "Manage polls").
 *
 * @param global_navigation $navigation
 */
function local_dashboardpoll_extend_navigation(global_navigation $navigation) {
    if (!isloggedin() || isguestuser()) {
        return;
    }
    // Managers already have the admin-tree entry; don't double it up for them.
    if (has_capability('local/dashboardpoll:manage', context_system::instance())) {
        return;
    }
    if (!local_dashboardpoll_can_view_reports()) {
        return;
    }

    $navigation->add(
        get_string('pollresults', 'local_dashboardpoll'),
        new moodle_url('/local/dashboardpoll/reports.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_dashboardpoll_reports',
        new pix_icon('i/report', '')
    );
}
