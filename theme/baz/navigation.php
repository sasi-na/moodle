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
 * Quick switch for the BAZ Navigation option (Options 1-5). A light page (no admin tree,
 * no file pickers) so applying an option takes a moment. Only the theme_baz/navlayout
 * setting is changed; all the CSS for the options is static, so no theme rebuild is needed.
 *
 * @package   theme_baz
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$choice = optional_param('layout', '', PARAM_ALPHA);
$url = new moodle_url('/theme/baz/navigation.php');
$layouts = \theme_baz\local\newtheme::LAYOUTS;

if (optional_param('ajax', 0, PARAM_BOOL)) {
    // One-click apply from the BAZ settings page (Top Bar tab, Navigation buttons).
    header('Content-Type: application/json; charset=utf-8');
    if ($choice === '' || !in_array($choice, $layouts, true) || !confirm_sesskey()) {
        echo json_encode(['ok' => false]);
        die();
    }
    $old = \theme_baz\local\newtheme::site_layout();
    if ($choice !== $old) {
        set_config('navlayout', $choice, 'theme_baz');
        add_to_config_log('navlayout', $old, $choice, 'theme_baz');
    }
    unset_user_preference(\theme_baz\local\newtheme::USERPREF);
    unset($SESSION->{\theme_baz\local\newtheme::PREVIEW}, $SESSION->{\theme_baz\local\newtheme::VPREVIEW});
    echo json_encode(['ok' => true, 'layout' => $choice,
        'message' => get_string('nvq_applied', 'theme_baz', get_string('ntf_' . $choice, 'theme_baz')),
        'view' => get_string('nvq_view', 'theme_baz')]);
    die();
}

if ($choice !== '' && in_array($choice, $layouts, true) && confirm_sesskey()) {
    $old = \theme_baz\local\newtheme::site_layout();
    if ($choice !== $old) {
        set_config('navlayout', $choice, 'theme_baz');
        add_to_config_log('navlayout', $old, $choice, 'theme_baz');
    }
    // The admin's own style would hide the site default on this account: clear it so the choice shows.
    unset_user_preference(\theme_baz\local\newtheme::USERPREF);
    // Drop any per-session preview so the chosen option shows straight away.
    unset($SESSION->{\theme_baz\local\newtheme::PREVIEW}, $SESSION->{\theme_baz\local\newtheme::VPREVIEW});
    redirect(new moodle_url('/my/'), get_string('nvq_applied', 'theme_baz', get_string('ntf_' . $choice, 'theme_baz')), 1,
        \core\output\notification::NOTIFY_SUCCESS);
}

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('nvq_title', 'theme_baz'));
$PAGE->set_heading(get_string('nvq_title', 'theme_baz'));

$current = \theme_baz\local\newtheme::layout();
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

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_baz/nt-navswitch', [
    'cards' => $cards,
    'intro' => get_string('nvq_intro', 'theme_baz'),
    'settingsurl' => (new moodle_url('/admin/settings.php', ['section' => 'themesettingbaz']))->out(false),
]);
echo $OUTPUT->footer();
