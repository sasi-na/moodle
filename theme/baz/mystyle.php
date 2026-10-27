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
 * "My dashboard style": every user (admin, teacher, student) picks Option 1-5 for themselves.
 * The choice is one user preference (theme_baz_navlayout); the site-wide setting stays the default.
 *
 * @package   theme_baz
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login(null, false);
// Personal styles were removed: admins choose the style for everyone in Site administration.
redirect(has_capability('moodle/site:config', context_system::instance())
    ? new moodle_url('/theme/baz/navigation.php') : new moodle_url('/my/'));
if (isguestuser()) {
    throw new require_login_exception('Guests cannot choose a style.');
}

use theme_baz\local\newtheme;

$url = new moodle_url('/theme/baz/mystyle.php');
$layouts = newtheme::LAYOUTS;
$choice = optional_param('layout', '', PARAM_ALPHA);
$applyall = optional_param('applyall', 0, PARAM_BOOL);
$isadmin = has_capability('moodle/site:config', context_system::instance());

if ($applyall && $isadmin && confirm_sesskey()) {
    // Admin: make the style I am using the style of ALL users (their earlier own choices are overridden).
    $now = newtheme::layout();
    $old = newtheme::site_layout();
    if ($now !== $old) {
        set_config('navlayout', $now, 'theme_baz');
        add_to_config_log('navlayout', $old, $now, 'theme_baz');
    }
    set_config('navforced', time(), 'theme_baz');
    set_user_preference(newtheme::USERPREF, $now);
    set_user_preference(newtheme::USERPREF . '_t', time() + 1);
    unset($SESSION->{newtheme::PREVIEW}, $SESSION->{newtheme::VPREVIEW});
    redirect($url, get_string('ms_applyall_done', 'theme_baz', get_string('ntf_' . $now, 'theme_baz')),
        null, \core\output\notification::NOTIFY_SUCCESS);
}
if ($choice !== '' && confirm_sesskey()) {
    if ($choice === 'default') {
        unset_user_preference(newtheme::USERPREF);
        $msg = get_string('ms_reset', 'theme_baz');
    } else if (in_array($choice, $layouts, true)) {
        set_user_preference(newtheme::USERPREF, $choice);
        set_user_preference(newtheme::USERPREF . '_t', time());
        $msg = get_string('ms_saved', 'theme_baz');
    }
    unset($SESSION->{newtheme::PREVIEW}, $SESSION->{newtheme::VPREVIEW});
    if (isset($msg)) {
        redirect($url, $msg, null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

$PAGE->set_context(context_user::instance($USER->id));
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('ms_title', 'theme_baz'));
$PAGE->set_heading(get_string('ms_title', 'theme_baz'));

$own = newtheme::user_layout();
$site = newtheme::site_layout();
$current = $own !== '' ? $own : $site;
$looks = [
    'left' => ['#002D47', '#F7B742', '#F4F7FB'],
    'top' => ['#0f1f3d', '#f4b23e', '#eef2f7'],
    'rail' => ['#0f1f3d', '#f4b23e', '#eef2f7'],
    'dock' => ['#0f1f3d', '#f4b23e', '#eef2f7'],
    'campus' => ['#0f1f3d', '#f4b23e', '#eef2f7'],
];
$cards = [];
foreach ($layouts as $i => $key) {
    $cards[] = [
        'key' => $key,
        'num' => $i + 1,
        'name' => get_string('ntf_' . $key, 'theme_baz'),
        'desc' => get_string('nvq_' . $key, 'theme_baz'),
        'c1' => $looks[$key][0], 'c2' => $looks[$key][1], 'bg' => $looks[$key][2],
        'current' => $key === $current,
        'sesskey' => sesskey(),
        'action' => $url->out(false),
    ];
}
$sitename = get_string('ntf_' . $site, 'theme_baz');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_baz/nt-navswitch', [
    'cards' => $cards,
    'intro' => get_string('ms_intro', 'theme_baz'),
    'reset' => true,
    'canreset' => $own !== '',
    'note' => get_string('ms_default_is', 'theme_baz', $sitename),
    'isadmin' => $isadmin,
    'applyalllabel' => get_string('ms_applyall', 'theme_baz'),
    'applyallhelp' => get_string('ms_applyall_help', 'theme_baz'),
    'action' => $url->out(false),
    'sesskey' => sesskey(),
]);
echo $OUTPUT->footer();
