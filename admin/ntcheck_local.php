<?php
// Local check: render real pages (header + footer through the theme layouts) for an admin and a
// student, in Option 1 (left) and Option 2 (top), and report what each page contains. Read only:
// the layout is switched with the per-session preview flag, never the site setting. CLI only.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
require_once($CFG->dirroot . '/theme/baz/lib.php');
$users = ['admin' => get_admin(), 'student' => $DB->get_record('user', ['email' => 'anupamaacharya21@gmail.com'])];
$courseid = (int) $DB->get_field_sql("SELECT e.courseid FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid WHERE ue.userid = ? ORDER BY ue.timecreated DESC", [$users['student']->id], IGNORE_MULTIPLE);
$pages = [
    'dashboard' => ['/my/', 'mydashboard', 'my-index', context_user::instance($users['admin']->id)],
    'calendar' => ['/calendar/view.php', 'standard', 'calendar-view', context_system::instance()],
    'course' => ['/course/view.php', 'course', 'course-view-topics', context_course::instance($courseid)],
    'admin' => ['/admin/search.php', 'admin', 'admin-search', context_system::instance()],
    'mycourses' => ['/my/courses.php', 'mycourses', 'my-index', context_system::instance()],
    'grades' => ['/grade/report/overview/index.php', 'report', 'grade-report-overview-index', context_course::instance($courseid)],
    'profile' => ['/user/profile.php', 'mypublic', 'user-profile', context_system::instance()],
    'messages' => ['/message/index.php', 'standard', 'message-index', context_system::instance()],
    'front' => ['/', 'frontpage', 'site-index', context_course::instance(SITEID)],
    'activity' => ['/mod/x/view.php', 'incourse', 'mod-x-view', null],
    'settings' => ['/admin/settings.php', 'admin', 'admin-setting-themesettingbaz', context_system::instance()],
];
$cm = null;
foreach (get_fast_modinfo($courseid)->get_cms() as $c) {
    if ($c->uservisible && $c->modname !== 'label') { $cm = $c; break; }
}
if ($cm) {
    $pages['activity'] = ['/mod/' . $cm->modname . '/view.php', 'incourse', 'mod-' . $cm->modname . '-view', context_module::instance($cm->id)];
} else {
    unset($pages['activity']);
}
$pagefilter = getenv('NTPAGES') ?: (isset($_GET['p']) ? $_GET['p'] : '');
$only = getenv('NTOPTS') ?: (isset($_GET['o']) ? $_GET['o'] : 'left,top,rail,dock,campus');
foreach (['left' => 0, 'top' => 1, 'rail' => 1, 'dock' => 1, 'campus' => 1] as $opt => $flag) {
    if (!in_array($opt, explode(',', $only), true)) {
        continue;
    }
    foreach ($users as $who => $u) {
        foreach ($pages as $name => [$url, $layout, $ptype, $ctx]) {
            if ($who === 'student' && in_array($name, ['admin', 'settings'], true)) {
                continue;
            }
            if ($pagefilter !== '' && !in_array($name, explode(',', $pagefilter), true)) {
                continue;
            }
            \core\session\manager::set_user($u);
            $SESSION->theme_baz_newtheme_preview = $flag;
            // Force exactly this option, whatever the site setting is.
            $SESSION->theme_baz_newtheme_vpreview = $opt;
            \theme_baz\local\askjo::$built = false;
            $PAGE = new moodle_page();
            $GLOBALS['PAGE'] = $PAGE;
            if ($name === 'dashboard') {
                $ctx = context_user::instance($u->id);
            }
            $PAGE->set_context($ctx);
            $params = [];
            if (in_array($name, ['course', 'grades'], true)) { $params = ['id' => $courseid]; }
            if ($name === 'activity') { $params = ['id' => $cm->id]; }
            if ($name === 'profile') { $params = ['id' => $u->id]; }
            if ($name === 'settings') { $params = ['section' => 'themesettingbaz']; }
            $PAGE->set_url($url, $params);
            $PAGE->set_pagelayout($layout);
            $PAGE->set_pagetype($ptype);
            if (in_array($name, ['course', 'grades'], true)) {
                $PAGE->set_course(get_course($courseid));
            }
            if ($name === 'activity') {
                $PAGE->set_cm($cm, get_course($courseid));
            }
            try {
                $out = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);
                $GLOBALS['OUTPUT'] = $out;
                ob_start();
                echo $out->header();
                echo $out->footer();
                $html = ob_get_clean();
                file_put_contents($CFG->dirroot . "/theme/baz/newtheme/backup/check-$opt-$who-$name.html", $html);
                preg_match('/<body[^>]*class="([^"]*)"/', $html, $m);
                $cls = implode(' ', array_filter(explode(' ', $m[1] ?? ''), function($c) {
                    return preg_match('/^(nt-|ednxt-)/', $c);
                }));
                $has = function($needle) use ($html) {
                    return strpos($html, $needle) !== false ? 'Y' : '-';
                };
                printf("%-6s %-8s %-10s %7db | body:[%s] shelljs:%s ntvars:%s brand:%s holidays:%s askjoPanel:%s ntDash:%s sidebar:%s err:%s\n",
                    $opt, $who, $name, strlen($html), $cls, $has('newtheme/shell.js'), $has('id="nt-vars"'), $has('EDNXT_BRAND'),
                    $has('EDNXT_HOLIDAYS'), (strpos($html, 'nt-jo-panel') !== false || preg_match('/id="[a-z]+-askjo-panel"/', $html)) ? 'Y' : '-',
                    $has('class="nt-root'), (preg_match('/<aside[^>]+id="[a-z]+-side"/', $html) ? 'Y' : '-'),
                    (preg_match('/(Exception|Fatal error|Warning:|Notice:|Coding error)/', $html, $em) ? $em[1] : '-'));
            } catch (\Throwable $e) {
                @ob_end_clean();
                printf("%-6s %-8s %-10s EXCEPTION %s @ %s:%d\n", $opt, $who, $name, $e->getMessage(), basename($e->getFile()), $e->getLine());
            }
        }
    }
}
unset($SESSION->theme_baz_newtheme_preview, $SESSION->theme_baz_newtheme_vpreview);
echo "features: ";
foreach (theme_baz_nt_features() as $f => $d) {
    echo "$f=" . get_config('theme_baz', 'nt_f_' . $f) . " ";
}
echo "\nnavlayout=" . get_config('theme_baz', 'navlayout') . "\n";
