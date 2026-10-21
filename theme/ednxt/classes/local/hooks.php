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

namespace theme_ednxt\local;

/**
 * Hook callbacks: one Ask JO chat panel on every page of the Ednxt Top Bar theme.
 *
 * Pages that already build their own Ask JO panel (student dashboard, courses,
 * calendar, grades ...) keep it; every other page gets the same panel here, so
 * the "Ask JO" button in the top bar opens the chat in place on all pages.
 *
 * @package   theme_ednxt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hooks {

    /** @var string Why the panel was not added (diagnostics). */
    public static $skip = '';

    /**
     * Add the shared Ask JO panel before the footer.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     */
    public static function before_footer(\core\hook\output\before_footer_html_generation $hook): void {
        global $PAGE;
        try {
            self::$skip = 'called';
            if (during_initial_install() || $PAGE->theme->name !== 'ednxt') {
                self::$skip = 'theme';
                return;
            }
            if (!isloggedin() || isguestuser()) {
                self::$skip = 'login';
                return;
            }
            if (in_array($PAGE->pagelayout, ['embedded', 'popup', 'frametop', 'maintenance', 'redirect', 'login', 'print', 'secure'], true)) {
                self::$skip = 'layout';
                return;
            }
            if (!class_exists('\\theme_baz\\local\\askjo') || \theme_baz\local\askjo::$built) {
                self::$skip = 'built';
                return;
            }
            if ($PAGE->blocks->is_block_present('openai_chat')) {
                self::$skip = 'block';
                return; // The chat block itself is on this page.
            }
            $jo = \theme_baz\local\askjo::build();
            if (!$jo) {
                self::$skip = 'nobuild';
                return;
            }
            $title = get_string('sd_askjo', 'theme_baz');
            $hook->add_html(
                '<div class="nt-jo-global" id="nt-jo-panel" hidden role="dialog" aria-label="' . s($title) . '">'
                . '<div class="nt-jo-ghead"><span>' . s($title) . '</span>'
                . '<button type="button" class="nt-jo-gclose" aria-label="' . s(get_string('closebuttontitle')) . '">&times;</button></div>'
                . '<div class="block_openai_chat" data-instance-id="' . (int) $jo['blockid'] . '">'
                . '<div class="nt-jo-gbody">' . $jo['log'] . '</div>'
                . '<div class="nt-jo-gcontrol">' . $jo['control'] . '</div>'
                . '</div></div>'
            );
        } catch (\Throwable $e) {
            self::$skip = 'error: ' . $e->getMessage();
            debugging('theme_ednxt Ask JO: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
