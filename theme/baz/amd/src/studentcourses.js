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
 * Behaviour for the bespoke "My courses" page: filter / sort / view-mode /
 * search / pagination over course cards, group collapse, star (favourite) and
 * per-course actions, plus the shared dark-mode toggle and mobile nav drawer.
 * Preferences persist via the same keys block_myoverview uses.
 *
 * @module     theme_baz/studentcourses
 * @copyright  2026 onlinejain.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    "use strict";

    var THEME_KEY = 'sd-dark';
    var PAGE_SIZE = 8;
    var SUN = '<circle cx="12" cy="12" r="4.5"/><path d="M12 2v2M12 20v2M4 12H2M22 12h-2M5 5l1.5 1.5'
        + 'M17.5 17.5 19 19M19 5l-1.5 1.5M6.5 17.5 5 19"/>';
    var MOON = '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>';

    var state = {filter: 'all', sort: 'fullname', query: ''};

    /**
     * Persist a user preference (best effort) via core_user/repository.
     *
     * @param {String} name
     * @param {String} value
     */
    function savePref(name, value) {
        require(['core_user/repository'], function(repo) {
            try {
                repo.setUserPreference(name, value);
            } catch (e) {
                // Best effort — the UI already reflects the choice.
            }
        });
    }

    /**
     * Apply the saved theme and wire the toggle (shared with the dashboard).
     */
    function initTheme() {
        var btn = document.getElementById('sc-themebtn');
        var icon = document.getElementById('sc-themeicon');
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
        var burger = document.getElementById('sc-burger');
        var side = document.getElementById('sc-side');
        var backdrop = document.getElementById('sc-backdrop');
        if (!burger || !side || !backdrop) {
            return;
        }
        var open = function(show) {
            side.classList.toggle('sc-open', show);
            backdrop.classList.toggle('sc-show', show);
            burger.setAttribute('aria-expanded', show ? 'true' : 'false');
            document.body.style.overflow = show ? 'hidden' : '';
        };
        burger.addEventListener('click', function() {
            open(!side.classList.contains('sc-open'));
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
     * Collapse / expand course groups.
     */
    function initGroups() {
        document.querySelectorAll('.sc-grp-h').forEach(function(head) {
            head.addEventListener('click', function() {
                var closed = head.classList.toggle('closed');
                var body = head.parentElement.querySelector('.sc-grp-body');
                if (body) {
                    body.hidden = closed;
                }
            });
        });
    }

    /**
     * Does a card pass the active filter?
     *
     * @param {HTMLElement} card
     * @return {Boolean}
     */
    function passesFilter(card) {
        var hidden = card.getAttribute('data-hidden') === '1';
        var f = state.filter;
        // "Removed" shows only hidden courses; every other filter excludes them.
        if (f === 'removed') {
            return hidden;
        }
        if (hidden) {
            return false;
        }
        if (f === 'all') {
            return true;
        }
        if (f === 'starred') {
            return card.getAttribute('data-starred') === '1';
        }
        if (f === 'inprogress' || f === 'future' || f === 'past') {
            return card.getAttribute('data-class') === f;
        }
        return true;
    }

    /**
     * Does a card match the text query?
     *
     * @param {HTMLElement} card
     * @return {Boolean}
     */
    function passesQuery(card) {
        if (!state.query) {
            return true;
        }
        return (card.getAttribute('data-name') || '').toLowerCase().indexOf(state.query) !== -1;
    }

    /**
     * Re-evaluate every group: filter, search, sort, paginate, update counts.
     */
    function refresh() {
        var groups = document.querySelectorAll('.sc-grp[data-group]');
        var anyVisible = false;

        groups.forEach(function(group) {
            var grid = group.querySelector('.sc-grid');
            var pager = group.querySelector('.sc-pager');
            var cards = Array.prototype.slice.call(group.querySelectorAll('.sc-card'));

            var matched = cards.filter(function(c) {
                return passesFilter(c) && passesQuery(c);
            });

            // Sort the matched cards.
            matched.sort(function(a, b) {
                if (state.sort === 'lastaccessed') {
                    return (parseInt(b.getAttribute('data-last'), 10) || 0)
                        - (parseInt(a.getAttribute('data-last'), 10) || 0);
                }
                return (a.getAttribute('data-name') || '').localeCompare(b.getAttribute('data-name') || '');
            });

            // Reorder in the DOM.
            matched.forEach(function(c) {
                grid.appendChild(c);
            });

            // Hide non-matched.
            cards.forEach(function(c) {
                if (matched.indexOf(c) === -1) {
                    c.hidden = true;
                }
            });

            // Paginate matched.
            var page = parseInt(group.getAttribute('data-page'), 10) || 1;
            var pages = Math.ceil(matched.length / PAGE_SIZE) || 1;
            if (page > pages) {
                page = 1;
            }
            matched.forEach(function(c, i) {
                c.hidden = !(i >= (page - 1) * PAGE_SIZE && i < page * PAGE_SIZE);
            });
            buildPager(pager, group, pages, page);

            // Group count + visibility.
            var cnt = group.querySelector('.sc-gcount');
            if (cnt) {
                cnt.textContent = matched.length + (matched.length === 1 ? ' course' : ' courses');
            }
            group.hidden = matched.length === 0;
            if (matched.length > 0) {
                anyVisible = true;
            }
        });

        var empty = document.getElementById('sc-empty');
        if (empty) {
            empty.hidden = anyVisible;
        }
    }

    /**
     * Render a group's pager.
     *
     * @param {HTMLElement} pager
     * @param {HTMLElement} group
     * @param {Number} pages
     * @param {Number} current
     */
    function buildPager(pager, group, pages, current) {
        if (!pager) {
            return;
        }
        pager.textContent = '';
        if (pages <= 1) {
            pager.hidden = true;
            return;
        }
        pager.hidden = false;
        for (var p = 1; p <= pages; p++) {
            pager.appendChild(pagerButton(group, p, current));
        }
    }

    /**
     * Build a single pager button.
     *
     * @param {HTMLElement} group
     * @param {Number} pageNum
     * @param {Number} current
     * @return {HTMLElement}
     */
    function pagerButton(group, pageNum, current) {
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = pageNum;
        if (pageNum === current) {
            b.className = 'on';
        }
        b.addEventListener('click', function() {
            group.setAttribute('data-page', pageNum);
            refresh();
        });
        return b;
    }

    /**
     * Filter chips.
     */
    function initFilter() {
        var bar = document.querySelector('.sc-bar');
        var map = {favourites: 'starred', hidden: 'removed'};
        var allowed = ['all', 'inprogress', 'future', 'past', 'starred', 'removed'];
        var initial = bar ? (bar.getAttribute('data-filter') || 'all') : 'all';
        state.filter = map[initial] || (allowed.indexOf(initial) !== -1 ? initial : 'all');

        var chips = document.querySelectorAll('.sc-fchip');
        var setActive = function() {
            chips.forEach(function(c) {
                c.classList.toggle('on', c.getAttribute('data-f') === state.filter);
            });
        };
        chips.forEach(function(chip) {
            chip.addEventListener('click', function() {
                state.filter = chip.getAttribute('data-f');
                setActive();
                document.querySelectorAll('.sc-grp[data-group]').forEach(function(g) {
                    g.removeAttribute('data-page');
                });
                refresh();
                var pref = {starred: 'favourites', removed: 'hidden'}[state.filter] || state.filter;
                savePref('block_myoverview_user_grouping_preference', pref);
            });
        });
        setActive();
    }

    /**
     * In-toolbar text filter.
     */
    function initFilterSearch() {
        var input = document.getElementById('sc-filtersearch');
        if (!input) {
            return;
        }
        input.addEventListener('input', function() {
            state.query = input.value.trim().toLowerCase();
            document.querySelectorAll('.sc-grp[data-group]').forEach(function(g) {
                g.removeAttribute('data-page');
            });
            refresh();
        });
    }

    /**
     * Sort dropdown.
     */
    function initSort() {
        var drop = document.getElementById('sc-sort');
        var menu = document.getElementById('sc-sortmenu');
        var label = document.getElementById('sc-sortlabel');
        if (!drop || !menu) {
            return;
        }
        state.sort = drop.getAttribute('data-sort') === 'lastaccessed' ? 'lastaccessed' : 'fullname';
        // Sync the label to the active sort on load — the template renders a
        // static default ("Course name"), so without this it disagrees with the
        // real order when the saved preference is "Last accessed".
        if (label) {
            var active = menu.querySelector('button[data-s="' + state.sort + '"]');
            if (active) {
                label.textContent = active.textContent;
            }
        }
        drop.addEventListener('click', function(e) {
            if (e.target.closest('.sc-menu')) {
                return;
            }
            menu.hidden = !menu.hidden;
        });
        menu.querySelectorAll('button').forEach(function(b) {
            b.addEventListener('click', function() {
                state.sort = b.getAttribute('data-s');
                if (label) {
                    label.textContent = b.textContent;
                }
                menu.hidden = true;
                refresh();
                savePref('block_myoverview_user_sort_preference', state.sort);
            });
        });
        document.addEventListener('click', function(e) {
            if (!drop.contains(e.target)) {
                menu.hidden = true;
            }
        });
    }

    /**
     * Card / List / Summary view toggle.
     */
    function initView() {
        var root = document.getElementById('sc-root');
        var bar = document.querySelector('.sc-bar');
        var buttons = document.querySelectorAll('.sc-vtoggle button');
        var apply = function(view) {
            root.classList.remove('sc-view-list', 'sc-view-summary');
            if (view === 'list') {
                root.classList.add('sc-view-list');
            } else if (view === 'summary') {
                root.classList.add('sc-view-summary');
            }
            buttons.forEach(function(b) {
                b.classList.toggle('on', b.getAttribute('data-v') === view);
            });
        };
        apply(bar ? (bar.getAttribute('data-view') || 'card') : 'card');
        buttons.forEach(function(b) {
            b.addEventListener('click', function() {
                var view = b.getAttribute('data-v');
                apply(view);
                savePref('block_myoverview_user_view_preference', view);
            });
        });
    }

    /**
     * Topbar search autosuggest over the user's courses.
     */
    function initSuggest() {
        var wrap = document.querySelector('.sc-search-wrap');
        var input = document.getElementById('sc-topsearch');
        var box = document.getElementById('sc-suggest');
        if (!wrap || !input || !box) {
            return;
        }
        var items = [];
        try {
            items = JSON.parse(wrap.getAttribute('data-suggest') || '[]');
        } catch (e) {
            items = [];
        }
        var close = function() {
            box.hidden = true;
            box.textContent = '';
        };
        input.addEventListener('input', function() {
            var q = input.value.trim().toLowerCase();
            if (!q) {
                close();
                return;
            }
            var matches = items.filter(function(it) {
                return (it.label || '').toLowerCase().indexOf(q) !== -1
                    || (it.sublabel || '').toLowerCase().indexOf(q) !== -1;
            }).slice(0, 8);
            box.textContent = '';
            if (!matches.length) {
                var em = document.createElement('div');
                em.className = 'sc-sg-empty';
                em.textContent = 'No matches';
                box.appendChild(em);
            } else {
                matches.forEach(function(m) {
                    var a = document.createElement('a');
                    a.href = m.url;
                    var l = document.createElement('span');
                    l.className = 'sc-sg-label';
                    l.textContent = m.label;
                    var s = document.createElement('span');
                    s.className = 'sc-sg-sub';
                    s.textContent = m.sublabel || '';
                    a.appendChild(l);
                    a.appendChild(s);
                    box.appendChild(a);
                });
            }
            box.hidden = false;
        });
        document.addEventListener('click', function(e) {
            if (!wrap.contains(e.target)) {
                close();
            }
        });
    }

    /**
     * Toggle a course favourite (star) on the server.
     *
     * @param {Number} courseid
     * @param {Boolean} favourite
     */
    function setFavourite(courseid, favourite) {
        require(['core/ajax'], function(Ajax) {
            try {
                Ajax.call([{
                    methodname: 'core_course_set_favourite_courses',
                    args: {courses: [{id: courseid, favourite: favourite ? 1 : 0}]}
                }]);
            } catch (e) {
                // Best effort.
            }
        });
    }

    /**
     * Star buttons + per-course actions menu (star / remove from view).
     */
    function initStarAndActions() {
        document.querySelectorAll('.sc-card').forEach(function(card) {
            var id = parseInt(card.getAttribute('data-course'), 10);
            var menu = card.querySelector('.sc-cardmenu');
            var starBtn = card.querySelector('.sc-star');

            var setStarred = function(on) {
                card.setAttribute('data-starred', on ? '1' : '0');
                if (starBtn) {
                    starBtn.classList.toggle('on', on);
                }
                var lbl = card.querySelector('.sc-star-lbl');
                if (lbl) {
                    lbl.textContent = on ? 'Unstar this course' : 'Star this course';
                }
                setFavourite(id, on);
            };
            var toggleStar = function() {
                setStarred(card.getAttribute('data-starred') !== '1');
                if (state.filter === 'starred') {
                    refresh();
                }
            };
            var setHidden = function(on) {
                card.setAttribute('data-hidden', on ? '1' : '0');
                var lbl = card.querySelector('.sc-rm-lbl');
                if (lbl) {
                    lbl.textContent = on ? 'Restore to view' : 'Remove from view';
                }
                savePref('block_myoverview_hidden_course_' + id, on ? '1' : '0');
                refresh();
            };

            if (starBtn) {
                starBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleStar();
                });
            }
            var actsBtn = card.querySelector('.sc-acts');
            if (actsBtn && menu) {
                actsBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    // Close any other open menu.
                    document.querySelectorAll('.sc-cardmenu').forEach(function(m) {
                        if (m !== menu) {
                            m.hidden = true;
                        }
                    });
                    menu.hidden = !menu.hidden;
                });
                menu.querySelectorAll('button').forEach(function(b) {
                    b.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        var act = b.getAttribute('data-act');
                        if (act === 'star') {
                            toggleStar();
                        } else if (act === 'remove') {
                            setHidden(card.getAttribute('data-hidden') !== '1');
                        }
                        menu.hidden = true;
                    });
                });
            }
        });
        document.addEventListener('click', function() {
            document.querySelectorAll('.sc-cardmenu').forEach(function(m) {
                m.hidden = true;
            });
        });
    }

    /**
     * Ask JO chat panel — toggled from the topbar button (in-page, no redirect).
     */
    function initAskJo() {
        var btn = document.getElementById('sc-askjo-btn');
        var panel = document.getElementById('sc-askjo-panel');
        var close = document.getElementById('sc-askjo-close');
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
        var fab = document.getElementById('sc-help-fab');
        var nav = document.getElementById('sc-navhelp');
        var panel = document.getElementById('sc-help-panel');
        var close = document.getElementById('sc-help-close');
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
            initAskJo();
            initHelp();
            initGroups();
            initFilter();
            initFilterSearch();
            initSort();
            initView();
            initSuggest();
            initStarAndActions();
            refresh();
        }
    };
});
