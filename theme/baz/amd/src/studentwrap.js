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
 * Behaviour for generic wrapped student pages (My Files, Profile): dark-mode
 * toggle, mobile drawer, in-page Ask JO chat and the "Need help?" FAB. The
 * wrapped page keeps its own JS untouched.
 *
 * @module     theme_baz/studentwrap
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
        var btn = document.getElementById('sw-themebtn');
        var icon = document.getElementById('sw-themeicon');
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
        var burger = document.getElementById('sw-burger');
        var root = document.getElementById('sw-root');
        var backdrop = document.getElementById('sw-backdrop');
        if (!burger || !root) {
            return;
        }
        var open = function(show) {
            root.classList.toggle('sw-nav-open', show);
            document.body.style.overflow = show ? 'hidden' : '';
        };
        burger.addEventListener('click', function() {
            open(!root.classList.contains('sw-nav-open'));
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
     * Ask JO chat panel — toggled from the topbar button (in-page, no redirect).
     */
    function initAskJo() {
        var btn = document.getElementById('sw-askjo-btn');
        var panel = document.getElementById('sw-askjo-panel');
        var close = document.getElementById('sw-askjo-close');
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
        var fab = document.getElementById('sw-help-fab');
        var panel = document.getElementById('sw-help-panel');
        var close = document.getElementById('sw-help-close');
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

    /**
     * Activity focus mode: the ☰ topbar button slides in the course
     * module-navigation overlay; backdrop / close / Escape dismiss it.
     */
    function initModNav() {
        var btn = document.getElementById('ba-tb-menu');
        var panel = document.getElementById('ba-mnav');
        var backdrop = document.getElementById('ba-mnav-backdrop');
        var close = document.getElementById('ba-mnav-close');
        if (!btn || !panel) {
            return;
        }
        var open = function(show) {
            panel.classList.toggle('show', show);
            panel.setAttribute('aria-hidden', show ? 'false' : 'true');
            btn.setAttribute('aria-expanded', show ? 'true' : 'false');
            if (backdrop) {
                backdrop.hidden = !show;
            }
            document.body.style.overflow = show ? 'hidden' : '';
            if (show) {
                // Bring the current activity into view (it may be far down a long course).
                var cur = panel.querySelector('.ba-mnav-act.current');
                if (cur && cur.scrollIntoView) {
                    cur.scrollIntoView({block: 'center'});
                }
            }
        };
        btn.addEventListener('click', function() {
            open(!panel.classList.contains('show'));
        });
        if (close) {
            close.addEventListener('click', function() {
                open(false);
            });
        }
        if (backdrop) {
            backdrop.addEventListener('click', function() {
                open(false);
            });
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && panel.classList.contains('show')) {
                open(false);
            }
        });
    }

    /**
     * Focus mode: move the AI "Summarise" control up into the action row so it
     * sits on the same line as Course modules / Back to course, pinned right.
     */
    function initFocusLayout() {
        var bar = document.querySelector('.ba-actionbar');
        var summ = document.querySelector('#region-main .ai-course-summarise-controls');
        if (bar && summ && !bar.contains(summ)) {
            bar.appendChild(summ);
        }
    }

    return {
        /**
         * Entry point.
         */
        init: function() {
            initTheme();
            initMobileNav();
            initAskJo();
            initHelp();
            initModNav();
            initFocusLayout();
        }
    };
});
