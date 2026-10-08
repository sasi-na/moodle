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
 * Behaviour for the bespoke per-course grade report: dark-mode toggle, mobile
 * drawer, in-page Ask JO chat and the "Need help?" FAB.
 *
 * @module     theme_baz/studentgradereport
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
     * Apply the saved theme and wire the toggle (shared key with other pages).
     */
    function initTheme() {
        var btn = document.getElementById('sgr-themebtn');
        var icon = document.getElementById('sgr-themeicon');
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
     * Mobile nav drawer (toggles a class on the #sgr-root shell).
     */
    function initMobileNav() {
        var burger = document.getElementById('sgr-burger');
        var root = document.getElementById('sgr-root');
        var backdrop = document.getElementById('sgr-backdrop');
        if (!burger || !root) {
            return;
        }
        var open = function(show) {
            root.classList.toggle('sgr-nav-open', show);
            document.body.style.overflow = show ? 'hidden' : '';
        };
        burger.addEventListener('click', function() {
            open(!root.classList.contains('sgr-nav-open'));
        });
        if (backdrop) {
            backdrop.addEventListener('click', function() {
                open(false);
            });
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                open(false);
            }
        });
    }

    /**
     * Per-row 3-dots menus (grade analysis / open activity).
     */
    function initRowMenus() {
        var closeAll = function(except) {
            document.querySelectorAll('.sgr-menu-pop').forEach(function(pop) {
                if (pop !== except) {
                    pop.hidden = true;
                    var b = pop.parentNode && pop.parentNode.querySelector('.sgr-dots');
                    if (b) {
                        b.setAttribute('aria-expanded', 'false');
                    }
                }
            });
        };
        document.addEventListener('click', function(e) {
            var dots = e.target.closest('.sgr-dots');
            if (dots) {
                e.preventDefault();
                var pop = dots.parentNode.querySelector('.sgr-menu-pop');
                if (pop) {
                    var show = pop.hidden;
                    closeAll(show ? pop : null);
                    pop.hidden = !show;
                    dots.setAttribute('aria-expanded', show ? 'true' : 'false');
                }
                return;
            }
            if (!e.target.closest('.sgr-menu-pop')) {
                closeAll(null);
            }
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAll(null);
            }
        });
    }

    /**
     * Ask JO chat panel — toggled from the topbar button (in-page, no redirect).
     */
    function initAskJo() {
        var btn = document.getElementById('sgr-askjo-btn');
        var panel = document.getElementById('sgr-askjo-panel');
        var close = document.getElementById('sgr-askjo-close');
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
     * Floating "Need help?" support panel (the gold FAB).
     */
    function initHelp() {
        var fab = document.getElementById('sgr-help-fab');
        var panel = document.getElementById('sgr-help-panel');
        var close = document.getElementById('sgr-help-close');
        if (!fab || !panel) {
            return;
        }
        var open = function(show) {
            panel.hidden = !show;
            fab.setAttribute('aria-expanded', show ? 'true' : 'false');
        };
        fab.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            open(panel.hidden);
        });
        if (close) {
            close.addEventListener('click', function() {
                open(false);
            });
        }
        document.addEventListener('click', function(e) {
            if (!panel.hidden && !panel.contains(e.target) && !fab.contains(e.target)) {
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
            initRowMenus();
            initAskJo();
            initHelp();
        }
    };
});
