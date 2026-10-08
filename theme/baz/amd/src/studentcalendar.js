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
 * Behaviour for the bespoke Calendar: dark-mode toggle, mobile drawer, course
 * filter menu, event-type legend toggles, and the floating "Need help?" panel.
 *
 * @module     theme_baz/studentcalendar
 * @copyright  2026 onlinejain.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    "use strict";

    var THEME_KEY = 'sd-dark';
    var SUN = '<circle cx="12" cy="12" r="4.5"/><path d="M12 2v2M12 20v2M4 12H2M22 12h-2M5 5l1.5 1.5'
        + 'M17.5 17.5 19 19M19 5l-1.5 1.5M6.5 17.5 5 19"/>';
    var MOON = '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>';

    /**
     * Apply the saved theme and wire the toggle (shared with the dashboard).
     */
    function initTheme() {
        var btn = document.getElementById('cal-themebtn');
        var icon = document.getElementById('cal-themeicon');
        var saved = false;
        try {
            saved = window.localStorage.getItem(THEME_KEY) === '1';
        } catch (e) {
            saved = false;
        }
        var apply = function(dark) {
            document.body.classList.toggle('sd-dark', dark);
            document.body.classList.toggle('theme-dark', dark);
            if (icon) {
                icon.innerHTML = dark ? SUN : MOON;
            }
        };
        apply(saved);
        if (btn) {
            btn.addEventListener('click', function() {
                var dark = !document.body.classList.contains('sd-dark');
                apply(dark);
                try {
                    window.localStorage.setItem(THEME_KEY, dark ? '1' : '0');
                } catch (e) {
                    // Ignore storage failures.
                }
            });
        }
    }

    /**
     * Mobile nav drawer.
     */
    function initMobileNav() {
        var burger = document.getElementById('cal-burger');
        var side = document.getElementById('cal-side');
        var backdrop = document.getElementById('cal-backdrop');
        if (!burger || !side || !backdrop) {
            return;
        }
        var open = function(show) {
            side.classList.toggle('cal-open', show);
            backdrop.classList.toggle('cal-show', show);
            document.body.style.overflow = show ? 'hidden' : '';
        };
        burger.addEventListener('click', function() {
            open(!side.classList.contains('cal-open'));
        });
        backdrop.addEventListener('click', function() {
            open(false);
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                open(false);
            }
        });
    }

    /**
     * Course filter dropdown — navigates to the calendar scoped to a course.
     */
    function initFilter() {
        var drop = document.getElementById('cal-filter');
        var menu = document.getElementById('cal-filtermenu');
        var label = document.getElementById('cal-filterlabel');
        if (!drop || !menu) {
            return;
        }
        drop.addEventListener('click', function(e) {
            if (e.target.closest('.cal-menu')) {
                return;
            }
            menu.hidden = !menu.hidden;
        });
        menu.querySelectorAll('button').forEach(function(b) {
            b.addEventListener('click', function() {
                if (label) {
                    label.textContent = b.textContent;
                }
                menu.hidden = true;
                var cid = b.getAttribute('data-cid');
                var url = '/calendar/view.php?view=month';
                if (cid && cid !== '0') {
                    url += '&course=' + cid;
                }
                window.location = url;
            });
        });
        document.addEventListener('click', function(e) {
            if (!drop.contains(e.target)) {
                menu.hidden = true;
            }
        });
    }

    /**
     * Event-type legend toggles — show/hide events of a type across every view.
     */
    function initLegend() {
        document.querySelectorAll('.lchip[data-type]').forEach(function(chip) {
            chip.addEventListener('click', function() {
                var type = chip.getAttribute('data-type');
                var off = chip.classList.toggle('off');
                document.querySelectorAll('[data-evtype="' + type + '"]').forEach(function(el) {
                    el.classList.toggle('cal-hidden', off);
                });
            });
        });
    }

    /**
     * Ask JO chat panel — toggled from the topbar button (in-page, no redirect).
     */
    function initAskJo() {
        var btn = document.getElementById('cal-askjo-btn');
        var panel = document.getElementById('cal-askjo-panel');
        var close = document.getElementById('cal-askjo-close');
        if (!btn || !panel) {
            return;
        }
        btn.addEventListener('click', function() {
            panel.hidden = !panel.hidden;
        });
        if (close) {
            close.addEventListener('click', function() {
                panel.hidden = true;
            });
        }
    }

    /**
     * Floating "Need help?" panel (FAB + the left-nav "Need help" item).
     */
    function initHelp() {
        var fab = document.getElementById('cal-help-fab');
        var nav = document.getElementById('cal-navhelp');
        var panel = document.getElementById('cal-help-panel');
        var close = document.getElementById('cal-help-close');
        if (!panel || (!fab && !nav)) {
            return;
        }
        var open = function(show) {
            panel.hidden = !show;
            if (fab) {
                fab.setAttribute('aria-expanded', show ? 'true' : 'false');
            }
        };
        var toggle = function(e) {
            e.preventDefault();
            e.stopPropagation();
            open(panel.hidden);
        };
        if (fab) {
            fab.addEventListener('click', toggle);
        }
        if (nav) {
            nav.addEventListener('click', toggle);
        }
        if (close) {
            close.addEventListener('click', function() {
                open(false);
            });
        }
        document.addEventListener('click', function(e) {
            if (!panel.hidden && !panel.contains(e.target)
                && !(fab && fab.contains(e.target)) && !(nav && nav.contains(e.target))) {
                open(false);
            }
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                open(false);
            }
        });
    }

    return {
        /**
         * Entry point.
         */
        init: function() {
            initTheme();
            initMobileNav();
            initFilter();
            initLegend();
            initAskJo();
            initHelp();
        }
    };
});
