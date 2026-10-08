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
 * Behaviour for the bespoke course-view page: dark-mode toggle, mobile drawer,
 * section accordion, table-of-contents scrollspy, in-page Ask JO chat and the
 * "Need help?" FAB.
 *
 * @module     theme_baz/studentcoursepage
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
        var btn = document.getElementById('cv-themebtn');
        var icon = document.getElementById('cv-themeicon');
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
     * Mobile nav drawer (toggles a class on the #cv-root shell).
     */
    function initMobileNav() {
        var burger = document.getElementById('cv-burger');
        var root = document.getElementById('cv-root');
        var backdrop = document.getElementById('cv-backdrop');
        if (!burger || !root) {
            return;
        }
        var open = function(show) {
            root.classList.toggle('cv-nav-open', show);
            document.body.style.overflow = show ? 'hidden' : '';
        };
        burger.addEventListener('click', function() {
            open(!root.classList.contains('cv-nav-open'));
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
     * Section accordion + "collapse all" toggle.
     */
    function initSections() {
        var wrap = document.getElementById('cv-secs');
        if (wrap) {
            wrap.addEventListener('click', function(e) {
                var head = e.target.closest('.cv-sec-h');
                if (!head) {
                    return;
                }
                var sec = head.closest('.cv-sec');
                if (sec) {
                    sec.classList.toggle('open');
                }
            });
        }
        var collapse = document.getElementById('cv-collapse');
        if (collapse && wrap) {
            collapse.addEventListener('click', function() {
                var secs = wrap.querySelectorAll('.cv-sec');
                var anyOpen = wrap.querySelector('.cv-sec.open');
                secs.forEach(function(s) {
                    s.classList.toggle('open', !anyOpen);
                });
                collapse.textContent = anyOpen
                    ? collapse.getAttribute('data-expand') || 'Expand all'
                    : collapse.getAttribute('data-collapse') || collapse.textContent;
            });
        }
    }

    /**
     * Table-of-contents: smooth scroll + active highlight on scroll.
     */
    function initToc() {
        var toc = document.getElementById('cv-toc');
        if (!toc) {
            return;
        }
        var links = Array.prototype.slice.call(toc.querySelectorAll('.cv-toc-i'));
        links.forEach(function(link) {
            link.addEventListener('click', function(e) {
                var id = link.getAttribute('data-target');
                var target = id && document.getElementById(id);
                if (!target) {
                    return;
                }
                e.preventDefault();
                var sec = target;
                if (sec.classList && !sec.classList.contains('open')) {
                    sec.classList.add('open');
                }
                var y = target.getBoundingClientRect().top + window.pageYOffset - 76;
                window.scrollTo({top: y, behavior: 'smooth'});
            });
        });

        var sections = links.map(function(l) {
            return document.getElementById(l.getAttribute('data-target'));
        });
        var onScroll = function() {
            var pos = window.pageYOffset + 120;
            var activeIdx = 0;
            for (var i = 0; i < sections.length; i++) {
                if (sections[i] && sections[i].offsetTop <= pos) {
                    activeIdx = i;
                }
            }
            links.forEach(function(l, idx) {
                l.classList.toggle('on', idx === activeIdx);
            });
        };
        var ticking = false;
        window.addEventListener('scroll', function() {
            if (!ticking) {
                window.requestAnimationFrame(function() {
                    onScroll();
                    ticking = false;
                });
                ticking = true;
            }
        });
        onScroll();
    }

    /**
     * Animate the hero progress ring to its data-pct.
     */
    function initRing() {
        var ring = document.querySelector('.cv-ring');
        if (!ring) {
            return;
        }
        var fg = ring.querySelector('.cv-ring-fg');
        if (!fg) {
            return;
        }
        var pct = parseInt(ring.getAttribute('data-pct'), 10) || 0;
        var r = 58;
        var c = 2 * Math.PI * r;
        var v = pct / 100 * c;
        fg.setAttribute('stroke-dasharray', v.toFixed(1) + ' ' + (c - v).toFixed(1));
    }

    /**
     * Ask JO chat panel — toggled from the topbar button (in-page, no redirect).
     */
    function initAskJo() {
        var btn = document.getElementById('cv-askjo-btn');
        var panel = document.getElementById('cv-askjo-panel');
        var close = document.getElementById('cv-askjo-close');
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
        var fab = document.getElementById('cv-help-fab');
        var panel = document.getElementById('cv-help-panel');
        var close = document.getElementById('cv-help-close');
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
            initSections();
            initToc();
            initRing();
            initAskJo();
            initHelp();
        }
    };
});
