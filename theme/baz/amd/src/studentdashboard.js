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
 * Behaviour for the bespoke student dashboard: live deadline countdown,
 * Ask JO panel, search autosuggest, and a light/dark theme toggle (persisted
 * locally). The semester-progress donut is rendered server-side (static).
 *
 * @module     theme_baz/studentdashboard
 * @copyright  2026 onlinejain.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    "use strict";

    var STORAGE_KEY = 'sd-dark';

    var SUN = '<circle cx="12" cy="12" r="4.5"/><path d="M12 2v2M12 20v2M4 12H2M22 12h-2M5 5l1.5 1.5'
        + 'M17.5 17.5 19 19M19 5l-1.5 1.5M6.5 17.5 5 19"/>';
    var MOON = '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>';

    /**
     * Pad a number to two digits.
     *
     * @param {Number} n
     * @return {String}
     */
    function pad(n) {
        return (n < 10 ? '0' : '') + n;
    }

    /**
     * Start the live countdown to the nearest deadline.
     */
    function initCountdown() {
        var box = document.querySelector('.sd-upnext[data-deadline]');
        if (!box) {
            return;
        }
        var target = parseInt(box.getAttribute('data-deadline'), 10) * 1000;
        var el = box.querySelector('[data-countdown]');
        if (!target || !el) {
            return;
        }

        var timer;
        var tick = function() {
            var diff = Math.max(0, Math.floor((target - Date.now()) / 1000));
            var h = Math.floor(diff / 3600);
            var m = Math.floor((diff % 3600) / 60);
            var s = diff % 60;
            el.textContent = pad(h) + ':' + pad(m) + ':' + pad(s);
            if (diff <= 0 && timer) {
                window.clearInterval(timer);
            }
        };
        tick();
        timer = window.setInterval(tick, 1000);
    }

    /**
     * Apply the saved theme and wire the toggle button.
     */
    function initTheme() {
        var btn = document.getElementById('sd-themebtn');
        var icon = document.getElementById('sd-themeicon');
        var saved = false;
        try {
            saved = window.localStorage.getItem(STORAGE_KEY) === '1';
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
                    window.localStorage.setItem(STORAGE_KEY, dark ? '1' : '0');
                } catch (e) {
                    // Ignore storage failures (private mode, etc.).
                }
            });
        }
    }

    /**
     * Toggle the floating "Need help?" support panel.
     */
    function initHelp() {
        var fab = document.getElementById('sd-help-fab');
        var nav = document.getElementById('sd-navhelp');
        var panel = document.getElementById('sd-help-panel');
        var close = document.getElementById('sd-help-close');
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

    /**
     * Toggle the Ask JO chat panel from the topbar button.
     */
    function initAskJo() {
        var btn = document.getElementById('sd-askjo-btn');
        var panel = document.getElementById('sd-askjo-panel');
        var close = document.getElementById('sd-askjo-close');
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
        // Auto-open when arriving from another bespoke page's "Ask JO" link.
        if (/[?&]askjo=1\b/.test(window.location.search)) {
            panel.hidden = false;
        }
    }

    /**
     * Client-side search autosuggest over the user's courses + recent items.
     */
    function initSearch() {
        var wrap = document.querySelector('.sd-search-wrap');
        var input = document.getElementById('sd-search-input');
        var box = document.getElementById('sd-suggest');
        if (!wrap || !input || !box) {
            return;
        }
        var items = [];
        try {
            items = JSON.parse(wrap.getAttribute('data-suggest') || '[]');
        } catch (e) {
            items = [];
        }
        var active = -1;

        var close = function() {
            box.hidden = true;
            box.textContent = '';
            active = -1;
        };

        var render = function(matches) {
            box.textContent = '';
            if (!matches.length) {
                var em = document.createElement('div');
                em.className = 'sd-sg-empty';
                em.textContent = 'No matches';
                box.appendChild(em);
            } else {
                matches.forEach(function(m) {
                    var a = document.createElement('a');
                    a.href = m.url;
                    var l = document.createElement('span');
                    l.className = 'sd-sg-label';
                    l.textContent = m.label;
                    var s = document.createElement('span');
                    s.className = 'sd-sg-sub';
                    s.textContent = m.sublabel || '';
                    a.appendChild(l);
                    a.appendChild(s);
                    box.appendChild(a);
                });
            }
            box.hidden = false;
            active = -1;
        };

        input.addEventListener('input', function() {
            var q = input.value.trim().toLowerCase();
            if (q.length < 1) {
                close();
                return;
            }
            var matches = items.filter(function(it) {
                return (it.label || '').toLowerCase().indexOf(q) !== -1
                    || (it.sublabel || '').toLowerCase().indexOf(q) !== -1;
            }).slice(0, 8);
            render(matches);
        });

        input.addEventListener('keydown', function(e) {
            if (box.hidden) {
                return;
            }
            var links = box.querySelectorAll('a');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                active = Math.min(active + 1, links.length - 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                active = Math.max(active - 1, 0);
            } else if (e.key === 'Enter') {
                if (active >= 0 && links[active]) {
                    e.preventDefault();
                    window.location = links[active].href;
                }
                return;
            } else if (e.key === 'Escape') {
                close();
                return;
            } else {
                return;
            }
            Array.prototype.forEach.call(links, function(a, i) {
                a.classList.toggle('sd-active', i === active);
            });
        });

        document.addEventListener('click', function(e) {
            if (!wrap.contains(e.target)) {
                close();
            }
        });
    }

    /**
     * Mobile nav: toggle the sidebar as a slide-in drawer with a backdrop.
     */
    function initMobileNav() {
        var burger = document.getElementById('sd-burger');
        var side = document.getElementById('sd-side');
        var backdrop = document.getElementById('sd-backdrop');
        if (!burger || !side || !backdrop) {
            return;
        }
        var open = function(show) {
            side.classList.toggle('sd-open', show);
            backdrop.classList.toggle('sd-show', show);
            burger.setAttribute('aria-expanded', show ? 'true' : 'false');
            document.body.style.overflow = show ? 'hidden' : '';
        };
        burger.addEventListener('click', function() {
            open(!side.classList.contains('sd-open'));
        });
        backdrop.addEventListener('click', function() {
            open(false);
        });
        // Close when a nav link is tapped or Escape is pressed.
        side.addEventListener('click', function(e) {
            if (e.target.closest('a')) {
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
     * Auto-rotate the dashboard banner when more than one is configured.
     */
    function initBanner() {
        var wrap = document.querySelector('.sd-banner-carousel');
        if (!wrap) {
            return;
        }
        var slides = wrap.querySelectorAll('.sd-banner');
        if (slides.length < 2) {
            return;
        }
        var delay = parseInt(wrap.getAttribute('data-delay'), 10) || 5000;
        var idx = 0;

        var dots = document.createElement('div');
        dots.className = 'sd-banner-dots';
        Array.prototype.forEach.call(slides, function(slide, i) {
            slide.style.display = i === 0 ? 'block' : 'none';
            var dot = document.createElement('button');
            dot.type = 'button';
            dot.setAttribute('aria-label', 'Banner ' + (i + 1));
            if (i === 0) {
                dot.className = 'sd-on';
            }
            dot.addEventListener('click', function() {
                show(i);
            });
            dots.appendChild(dot);
        });
        wrap.appendChild(dots);

        var show = function(next) {
            slides[idx].style.display = 'none';
            dots.children[idx].classList.remove('sd-on');
            idx = (next + slides.length) % slides.length;
            slides[idx].style.display = 'block';
            dots.children[idx].classList.add('sd-on');
        };

        window.setInterval(function() {
            show(idx + 1);
        }, delay);
    }

    /**
     * Stagger the reveal animation on the course cards.
     */
    function initReveal() {
        var cards = document.querySelectorAll('.sd-course.sd-reveal');
        Array.prototype.forEach.call(cards, function(card, idx) {
            card.style.animationDelay = (idx * 60) + 'ms';
        });
    }

    return {
        /**
         * Entry point.
         */
        init: function() {
            initTheme();
            initCountdown();
            initMobileNav();
            initBanner();
            initReveal();
            initAskJo();
            initHelp();
            initSearch();
        }
    };
});
