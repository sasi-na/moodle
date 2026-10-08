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
 * Light/dark toggle for the .exui surfaces, independent of the Moodle theme.
 * Preference persisted in localStorage.
 *
 * @module     local_exams/theme_toggle
 * @copyright  2026 JAIN Online
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    var KEY = 'local_exams_theme';

    var apply = function(root, mode) {
        root.classList.remove('exui-dark', 'exui-light');
        if (mode === 'dark') {
            root.classList.add('exui-dark');
        } else if (mode === 'light') {
            root.classList.add('exui-light');
        }
    };

    return {
        /**
         * @param {String} rootId Id of the .exui root element.
         */
        init: function(rootId) {
            var root = document.getElementById(rootId);
            if (!root) {
                return;
            }
            var saved = null;
            try {
                saved = window.localStorage.getItem(KEY);
            } catch (e) {
                saved = null;
            }
            if (saved) {
                apply(root, saved);
            }
            root.querySelectorAll('[data-action="theme-toggle"]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var dark = root.classList.contains('exui-dark');
                    // If neither class is set, infer from current rendered colours via the matchMedia.
                    if (!dark && !root.classList.contains('exui-light')) {
                        dark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                    }
                    var next = dark ? 'light' : 'dark';
                    apply(root, next);
                    try {
                        window.localStorage.setItem(KEY, next);
                    } catch (e) {
                        return;
                    }
                });
            });
        }
    };
});
