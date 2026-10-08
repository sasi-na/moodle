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
 * Navigation hooks for local_fileshare.
 *
 * @package   local_fileshare
 * @copyright 2026 onlinejain.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Put "Share links" in a quiz's activity administration menu.
 *
 * The bulk page is a per-quiz action, so it belongs beside Results and Overrides rather than
 * anywhere under Site administration. Called for every local plugin by
 * settings_navigation::load_local_plugin_settings().
 *
 * The conditions mirror what local/fileshare/quiz.php itself enforces, so the link never
 * appears to somebody the page would then refuse.
 *
 * @param settings_navigation $settingsnav
 * @param context $context
 * @return void
 */
function local_fileshare_extend_settings_navigation(settings_navigation $settingsnav, context $context) {
    global $PAGE;

    if (!($context instanceof context_module) || !get_config('local_fileshare', 'enabled')) {
        return;
    }

    $cm = $PAGE->cm;
    if (empty($cm->id) || $cm->modname !== 'quiz') {
        return;
    }

    // Both, because quiz.php requires both.
    if (!has_capability('local/fileshare:share', $context)
            || !has_capability('mod/quiz:viewreports', $context)) {
        return;
    }

    $modulenode = $settingsnav->find('modulesettings', navigation_node::TYPE_SETTING);
    if (!$modulenode) {
        return;
    }

    $modulenode->add(
        get_string('bulktitle', 'local_fileshare'),
        new moodle_url('/local/fileshare/quiz.php', ['cmid' => $cm->id]),
        navigation_node::TYPE_SETTING,
        null,
        'local_fileshare_quiz',
        new pix_icon('i/report', '')
    );
}
