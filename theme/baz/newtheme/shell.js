/**
 * BAZ "Top bar" navigation layout: two-level top bar shell for every page.
 *
 * Baz renders its pages (admin pages, course pages, student pages ...) inside an
 * app shell: <div id="X-root"> <aside id="X-side"> (navy sidebar) + a white
 * <header class="X-topbar"> (breadcrumb, search, icons). With the Ednxt Top Bar
 * theme this script turns that into the dashboard's two-level top bar:
 *   level 1 (navy)  brand + main links + search + icons/user menu (moved, not copied,
 *                   so all their existing JavaScript keeps working)
 *   level 2 (white) the page's section links as tabs + "More" menu
 *   then the breadcrumb row.
 * Pages without that shell are left alone.
 *
 * @package   theme_baz
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

    var CHEVRON = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>';

    function cls(el, re) {
        return el && typeof el.className === 'string' && re.test(el.className);
    }

    function cloneLink(a) {
        var n = document.createElement('a');
        n.href = a.getAttribute('href') || '#';
        if (a.getAttribute('target')) {
            n.target = a.getAttribute('target');
            n.rel = 'noopener';
        }
        var svg = a.querySelector('svg');
        if (svg) {
            n.appendChild(svg.cloneNode(true));
        }
        var span = document.createElement('span');
        span.textContent = (a.textContent || '').replace(/\s+/g, ' ').trim();
        n.appendChild(span);
        if (a.classList.contains('on') || a.getAttribute('aria-current') === 'page') {
            n.className = 'on';
            n.setAttribute('aria-current', 'page');
        }
        return n;
    }

    function build() {
        if (!document.body.classList.contains('nt-shell')) {
            return;
        }
        if (document.getElementById('nt-shellbar') || document.querySelector('.nt-top')) {
            return;
        }
        var side = document.querySelector('[id$="-root"] > aside[id$="-side"]');
        if (!side) {
            return;
        }
        var root = side.parentNode;
        var header = root.querySelector('header[class*="-topbar"]');
        var nav = side.querySelector('nav');

        // --- Split the sidebar nav: main links (navy), section links (tabs), "more" groups.
        var main = [];
        var tabs = [];
        var more = [];
        var coursename = '';
        var group = 0;
        if (nav) {
            Array.prototype.forEach.call(nav.children, function(el) {
                if (el.tagName === 'A') {
                    (group === 0 ? main : tabs).push(el);
                } else if (cls(el, /nav-sep/)) {
                    group++;
                } else if (cls(el, /coursename/)) {
                    coursename = el.textContent.trim();
                } else if (cls(el, /nav-more/) || el.tagName === 'DETAILS') {
                    more.push(el);
                }
            });
        }

        // --- Level 1: navy bar.
        var bar = document.createElement('header');
        bar.className = 'nt-top';
        bar.id = 'nt-shellbar';
        var inner = document.createElement('div');
        inner.className = 'nt-top-inner';
        bar.appendChild(inner);

        var brandsrc = side.querySelector('a[class$="-brand"]');
        var brand = document.createElement('a');
        brand.className = 'nt-brand';
        if (brandsrc) {
            brand.href = brandsrc.getAttribute('href');
            var img = brandsrc.querySelector('img');
            if (img) {
                brand.appendChild(img.cloneNode(true));
            } else {
                var t = document.createElement('span');
                t.textContent = brandsrc.textContent.trim();
                brand.appendChild(t);
            }
        }
        inner.appendChild(brand);

        var mainnav = document.createElement('nav');
        mainnav.className = 'nt-nav';
        mainnav.setAttribute('aria-label', 'Main');
        main.forEach(function(a) {
            mainnav.appendChild(cloneLink(a));
        });
        inner.appendChild(mainnav);

        var right = document.createElement('div');
        right.className = 'nt-top-right';
        inner.appendChild(right);
        if (header) {
            var search = header.querySelector('[class*="-search-wrap"]');
            if (search) {
                search.classList.add('nt-moved-search');
                right.appendChild(search);
            }
            var actions = header.querySelector('[class*="-tb-right"], [class*="-top-actions"]');
            if (actions) {
                actions.classList.add('nt-moved-actions');
                right.appendChild(actions);
            }
        }

        fillRight(right, root);

        // --- Support contact (was at the foot of the sidebar): a "?" button + panel.
        var support = side.querySelector('.sd-sidesupport');
        if (support && support.textContent.trim() !== '') {
            var hw = document.createElement('div');
            hw.className = 'nt-supportwrap';
            var hb = document.createElement('button');
            hb.type = 'button';
            hb.className = 'nt-supportbtn';
            hb.title = 'Support';
            hb.setAttribute('aria-label', 'Support');
            hb.setAttribute('aria-expanded', 'false');
            hb.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/>' +
                '<path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .8-1 1.5v.4"/><path d="M12 17h.01"/></svg>';
            var hp = document.createElement('div');
            hp.className = 'nt-supportpanel';
            hp.hidden = true;
            hp.appendChild(support.cloneNode(true));
            hb.addEventListener('click', function(e) {
                e.stopPropagation();
                hp.hidden = !hp.hidden;
                hb.setAttribute('aria-expanded', hp.hidden ? 'false' : 'true');
            });
            document.addEventListener('click', function(e) {
                if (!hw.contains(e.target)) {
                    hp.hidden = true;
                    hb.setAttribute('aria-expanded', 'false');
                }
            });
            hw.appendChild(hb);
            hw.appendChild(hp);
            right.insertBefore(hw, right.lastChild && right.lastChild.classList &&
                right.lastChild.classList.contains('nt-moved-actions') ? right.lastChild : null);
        }

        // --- Level 2: section tabs + "More".
        var sub = null;
        if (tabs.length || more.length || coursename) {
            sub = document.createElement('div');
            sub.className = 'nt-sub';
            sub.id = 'nt-shellsub';
            var subin = document.createElement('div');
            subin.className = 'nt-sub-inner';
            sub.appendChild(subin);
            if (coursename) {
                var cn = document.createElement('span');
                cn.className = 'nt-sub-title';
                cn.textContent = coursename;
                cn.title = coursename;
                subin.appendChild(cn);
            }
            var tabnav = document.createElement('nav');
            tabnav.className = 'nt-tabs';
            tabs.forEach(function(a) {
                tabnav.appendChild(cloneLink(a));
            });
            subin.appendChild(tabnav);
            if (more.length) {
                var dd = document.createElement('div');
                dd.className = 'nt-more';
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'nt-more-btn';
                btn.setAttribute('aria-expanded', 'false');
                var heading = more[0].querySelector('[class*="nav-heading"]');
                btn.innerHTML = '<span></span>' + CHEVRON;
                btn.firstChild.textContent = heading ? heading.textContent.trim() : 'More';
                var panel = document.createElement('div');
                panel.className = 'nt-more-panel';
                panel.hidden = true;
                more.forEach(function(m) {
                    var c = m.cloneNode(true);
                    var h = c.querySelector('[class*="nav-heading"]');
                    if (h) {
                        h.remove();
                    }
                    panel.appendChild(c);
                });
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    panel.hidden = !panel.hidden;
                    btn.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
                });
                document.addEventListener('click', function(e) {
                    if (!dd.contains(e.target)) {
                        panel.hidden = true;
                        btn.setAttribute('aria-expanded', 'false');
                    }
                });
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        panel.hidden = true;
                        btn.setAttribute('aria-expanded', 'false');
                    }
                });
                dd.appendChild(btn);
                dd.appendChild(panel);
                subin.appendChild(dd);
            }
        }

        root.insertBefore(bar, root.firstChild);
        if (sub) {
            root.insertBefore(sub, bar.nextSibling);
        }
        root.classList.add('nt-shell-root');

    }

    var SUN = '<circle cx="12" cy="12" r="4.5"/><path d="M12 2v2M12 20v2M4 12H2M22 12h-2M5 5l1.5 1.5M17.5 17.5 19 19M5 19l1.5-1.5M17.5 6.5 19 5"/>';
    var MOON = '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>';

    /**
     * Same right-hand tools on every page: search, Ask JO (students), dark mode.
     * Pages whose own header lacks one get it added here.
     *
     * @param {HTMLElement} right
     * @param {HTMLElement} root
     */
    function fillRight(right, root) {
        var www = (window.M && M.cfg && M.cfg.wwwroot) || '';
        var isadmin = root.classList.contains('sd-admin');
        var first = right.firstChild;
        if (!right.querySelector('[class*="-search-wrap"], form[role="search"]')) {
            var sw = document.createElement('div');
            sw.className = 'nt-moved-search nt-added-search';
            sw.innerHTML = '<form role="search" method="get" action="' + www + '/course/search.php">' +
                '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>' +
                '<input type="text" name="search" autocomplete="off" aria-label="Search courses" placeholder="Search courses\u2026">' +
                '<span class="nt-kbd">/</span></form>';
            right.insertBefore(sw, first);
            document.addEventListener('keydown', function(e) {
                var t = e.target;
                if (e.key === '/' && !(t && (t.isContentEditable || /INPUT|TEXTAREA|SELECT/.test(t.tagName)))) {
                    e.preventDefault();
                    sw.querySelector('input').focus();
                }
            });
        }
        var actions = right.querySelector('.nt-moved-actions') || right;
        var before = actions.firstChild;
        if (!right.querySelector('[id$="-askjo-btn"], .nt-askjo') && (!isadmin || document.querySelector('[id$="-askjo-panel"], #nt-jo-panel'))) {
            var aj = document.createElement('a');
            aj.className = 'nt-askjo';
            aj.href = www + '/my/#askjo';
            aj.setAttribute('role', 'button');
            aj.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/></svg>Ask JO';
            actions.insertBefore(aj, before);
        }
        if (!right.querySelector('[id$="-themebtn"]')) {
            var tb = document.createElement('button');
            tb.type = 'button';
            tb.className = 'nt-iconbtn';
            tb.id = 'nt-themebtn';
            tb.title = 'Toggle dark mode';
            var ico = function() {
                tb.innerHTML = '<svg viewBox="0 0 24 24">' + (document.body.classList.contains('sd-dark') ? SUN : MOON) + '</svg>';
            };
            var saved = false;
            try {
                saved = window.localStorage.getItem('sd-dark') === '1';
            } catch (e) {
                saved = false;
            }
            document.body.classList.toggle('sd-dark', saved);
            document.body.classList.toggle('theme-dark', saved);
            ico();
            tb.addEventListener('click', function() {
                var dark = !document.body.classList.contains('sd-dark');
                document.body.classList.toggle('sd-dark', dark);
                document.body.classList.toggle('theme-dark', dark);
                try {
                    window.localStorage.setItem('sd-dark', dark ? '1' : '0');
                } catch (e) {
                    // Private mode etc.
                }
                ico();
            });
            var ref = actions.querySelector('.nt-askjo, [id$="-askjo-btn"]');
            actions.insertBefore(tb, ref ? ref.nextSibling : actions.firstChild);
        }
    }

    /**
     * "Priority+" menu: links that do not fit in the navy bar move into a
     * "More" dropdown at its end (the active link always stays visible), and
     * come back when there is room again. Runs on load and on resize.
     *
     * @param {HTMLElement} nav
     */
    function priorityNav(nav) {
        if (!nav || nav.dataset.ntFit) {
            return;
        }
        nav.dataset.ntFit = '1';
        var links = Array.prototype.filter.call(nav.children, function(el) {
            return el.tagName === 'A';
        });
        if (!links.length) {
            return;
        }
        var wrap = document.createElement('div');
        wrap.className = 'nt-navmore';
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'nt-navmore-btn';
        btn.setAttribute('aria-expanded', 'false');
        btn.setAttribute('aria-haspopup', 'true');
        btn.innerHTML = '<span>More</span>' + CHEVRON;
        var panel = document.createElement('div');
        panel.className = 'nt-navmore-panel';
        panel.hidden = true;
        wrap.appendChild(btn);
        wrap.appendChild(panel);
        nav.appendChild(wrap);
        var close = function() {
            panel.hidden = true;
            btn.setAttribute('aria-expanded', 'false');
        };
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            panel.hidden = !panel.hidden;
            btn.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
        });
        document.addEventListener('click', function(e) {
            if (!wrap.contains(e.target)) {
                close();
            }
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                close();
            }
        });

        var fit = function() {
            close();
            // Put every link back, in order, then move the overflow out.
            links.forEach(function(a) {
                nav.insertBefore(a, wrap);
            });
            wrap.style.display = 'none';
            // An upright menu (Option 4, left edge) overflows downwards, not sideways.
            var upright = function() {
                return window.getComputedStyle(nav).flexDirection === 'column';
            };
            var over = function() {
                return upright() ? nav.scrollHeight > nav.clientHeight + 1 : nav.scrollWidth > nav.clientWidth + 1;
            };
            // Upright menu: every icon stays visible (it shrinks / scrolls instead of using "More").
            if (upright() || !over()) {
                return;
            }
            wrap.style.display = '';
            var moved = [];
            var candidates = links.slice().reverse();
            for (var i = 0; i < candidates.length && over(); i++) {
                var a = candidates[i];
                if (a.classList.contains('on') && candidates.length - moved.length > 1) {
                    continue;
                }
                moved.unshift(a);
                panel.insertBefore(a, panel.firstChild);
            }
            // Keep the original order inside the dropdown.
            moved.sort(function(x, y) {
                return links.indexOf(x) - links.indexOf(y);
            }).forEach(function(a) {
                panel.appendChild(a);
            });
            btn.classList.toggle('on', moved.some(function(a) {
                return a.classList.contains('on');
            }));
        };
        var timer = null;
        window.addEventListener('resize', function() {
            window.clearTimeout(timer);
            timer = window.setTimeout(fit, 120);
        });
        fit();
        // Fonts, the logo and the icons can change widths after first paint.
        window.addEventListener('load', fit);
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(fit);
        }
        var logo = document.querySelector('#nt-shellbar .nt-brand img');
        if (logo && !logo.complete) {
            logo.addEventListener('load', fit);
        }
    }

    /**
     * Calendar pages: colour holidays (red) and festivals (amber) and label them,
     * from window.EDNXT_HOLIDAYS = {dayTimestamp: {n: name, t: 'holiday'|'festival'}}.
     * Works on Baz's student calendar and Moodle's own month view (which reloads
     * by AJAX, hence the observer).
     */
    function holidays() {
        var map = window.EDNXT_HOLIDAYS;
        if (!map) {
            return false;
        }
        var labels = window.EDNXT_HOLLABELS || {holiday: 'Holiday', festival: 'Festival'};
        var mark = function(cell, ts) {
            var h = map[ts];
            if (!h || !cell || cell.dataset.ntHol) {
                return;
            }
            cell.dataset.ntHol = '1';
            cell.classList.add(h.t === 'festival' ? 'nt-cal-fest' : 'nt-cal-hol');
            var tag = document.createElement('span');
            tag.className = 'nt-cal-tag';
            tag.textContent = h.n;
            tag.title = labels[h.t] + ': ' + h.n;
            var num = cell.querySelector('.cal-dn, [data-region="day-number"], .day-number, a.dayblock, a');
            if (num && num.parentNode === cell) {
                num.insertAdjacentElement('afterend', tag);
            } else {
                cell.insertBefore(tag, cell.firstChild ? cell.firstChild.nextSibling : null);
            }
        };
        // Baz student calendar: day links carry ?time=<day timestamp>.
        document.querySelectorAll('.cal-cell a.cal-dn[href*="time="]').forEach(function(a) {
            var m = /[?&]time=(\d+)/.exec(a.getAttribute('href'));
            if (m) {
                mark(a.closest('.cal-cell'), m[1]);
            }
        });
        // Moodle month view.
        document.querySelectorAll('td[data-day-timestamp]').forEach(function(td) {
            mark(td, td.getAttribute('data-day-timestamp'));
        });
        // Legend chips once, next to the existing calendar legend.
        var legend = document.querySelector('.cal-legend, [class*="cal-"] .lchip');
        if (legend && !document.querySelector('.nt-cal-legend')) {
            var host = legend.classList.contains('lchip') ? legend.parentNode : legend;
            ['holiday', 'festival'].forEach(function(t) {
                var c = document.createElement('span');
                c.className = 'lchip nt-cal-legend nt-cal-legend-' + t;
                c.innerHTML = '<i></i>';
                c.appendChild(document.createTextNode(' ' + labels[t]));
                host.appendChild(c);
            });
        }
        return true;
    }

        function nvDone() {
        document.querySelectorAll('.nt-sem .nt-courses li').forEach(function (li) {
            var p = li.querySelector('.nt-c-pct');
            if (p && parseInt(p.textContent, 10) >= 100) { li.classList.add('nt-done'); }
        });
    }
    function initHolidays() {
        if (!/calendar/.test(document.body.id + ' ' + document.body.className)) {
            return;
        }
        var tries = 0;
        var run = function() {
            if (!holidays() && tries++ < 20) {
                window.setTimeout(run, 250);
            }
        };
        run();
        var timer = null;
        new MutationObserver(function() {
            window.clearTimeout(timer);
            timer = window.setTimeout(holidays, 150);
        }).observe(document.body, {childList: true, subtree: true});
    }

    /**
     * Open the page's support panel ("Need help?") by pressing its own button,
     * so Baz's panel script keeps doing the work.
     *
     * @param {Event} e
     */
    function openHelp(e) {
        var fab = document.querySelector('[id$="-help-fab"]');
        if (!fab) {
            return;
        }
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        fab.click();
    }

    /**
     * "Assistive touch" for the AI dock: one round button at the bottom right;
     * clicking it slides the AI assistants (and "Need help?") out beside it.
     */
    /**
     * "Need help?" as a vertical tab on the left edge of the screen (feature: helptab).
     */
    function helpEdge() {
        var helpfab = document.querySelector('[id$="-help-fab"]');
        if (!helpfab || document.querySelector('.nt-helpedge')) {
            return;
        }
        // "Need help?" as a vertical tab on the left edge of the screen.
        var edge = document.createElement('button');
        edge.type = 'button';
        edge.className = 'nt-helpedge';
        edge.setAttribute('aria-label', 'Need help?');
        edge.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3.5"/>' +
            '<path d="M5.6 5.6l3.9 3.9M14.5 14.5l3.9 3.9M18.4 5.6l-3.9 3.9M9.5 14.5l-3.9 3.9"/></svg><span>Need help?</span>';
        edge.setAttribute('aria-expanded', 'false');
        edge.addEventListener('click', function(e) {
            openHelp(e);
            window.setTimeout(function() {
                var panel = document.querySelector('[id$="-help-panel"]');
                edge.setAttribute('aria-expanded', panel && !panel.hidden ? 'true' : 'false');
            }, 0);
        });
        document.addEventListener('click', function() {
            window.setTimeout(function() {
                var panel = document.querySelector('[id$="-help-panel"]');
                edge.setAttribute('aria-expanded', panel && !panel.hidden ? 'true' : 'false');
            }, 0);
        });
        // The old floating pill is replaced by the edge tab (still clicked programmatically).
        helpfab.style.setProperty('display', 'none', 'important');
        document.body.appendChild(edge);
        document.body.classList.add('nt-has-assist-helpbtn');
    }

    function assistive() {
        var dock = document.querySelector('.aidock');
        if (!document.body.classList.contains('ednxt-dock-right')) {
            return;
        }
        if (!dock) {
            return;
        }
        if (dock && dock.dataset.ntAssist) {
            return;
        }
        if (!dock) {
            dock = document.createElement('nav');
            dock.className = 'aidock';
            document.body.appendChild(dock);
        }
        dock.dataset.ntAssist = '1';
        dock.classList.add('nt-assist', 'nt-collapsed');
        var items = document.createElement('div');
        items.className = 'nt-assist-items';
        Array.prototype.slice.call(dock.querySelectorAll('.aidock-btn')).forEach(function(b) {
            items.appendChild(b);
        });
        var toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'nt-assist-toggle';
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Assistants');
        toggle.innerHTML = '<span class="nt-assist-dots" aria-hidden="true"><i></i><i></i><i></i><i></i></span>' +
            '<svg class="nt-assist-x" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>';
        dock.innerHTML = '';
        dock.appendChild(items);
        dock.appendChild(toggle);
        var set = function(open) {
            dock.classList.toggle('nt-collapsed', !open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            document.body.classList.toggle('nt-assist-open', open);
        };
        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            set(dock.classList.contains('nt-collapsed'));
        });
        document.addEventListener('click', function(e) {
            if (!dock.contains(e.target)) {
                set(false);
            }
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                set(false);
            }
        });
    }

    var SPARK = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/>' +
        '<path d="M19 15l.7 2 2 .7-2 .7-.7 2-.7-2-2-.7 2-.7z"/></svg>';

    /**
     * Brand: the "JAIN Online" wordmark (or the uploaded brand logo) in place of the
     * Moodle logo in the top bar, and the brand name in place of the site name in the
     * tab title, footer and page text. Display only: nothing stored is changed.
     */
    function brand() {
        var b = window.EDNXT_BRAND;
        if (!b || !b.name) {
            return;
        }
        var mark;
        if (b.logo) {
            mark = '<img class="nt-wm-img" src="' + b.logo + '" alt="">';
            if (b.logodark && b.logodark !== b.logo) {
                // Light designs (Option 3) show the logo made for light backgrounds.
                mark += '<img class="nt-wm-img nt-wm-dark" src="' + b.logodark + '" alt="">';
            }
        } else {
            var parts = b.name.split(/\s+/);
            var first = parts.shift();
            mark = '<span class="nt-wm-badge" aria-hidden="true">' + first.charAt(0).toUpperCase() + '</span>' +
                '<span class="nt-wm-text"><b></b>' + (parts.length ? '<i></i>' : '') + '</span>';
        }
        // Option 1: the left sidebar's own logo gets the brand logo too.
        document.querySelectorAll('aside[id$="-side"] a[class$="-brand"]').forEach(function(a) {
            a.classList.add('nt-brand', 'nt-side-brand');
        });
        // Activity pages and the logged-in site home have their own top bar logo (every option).
        document.querySelectorAll('a.ba-tb-logo, a.rui-navbar-brand').forEach(function(a) {
            a.classList.add('nt-brand', 'nt-tb-brand');
        });
        document.querySelectorAll('.nt-brand').forEach(function(a) {
            if (a.dataset.ntWm) {
                return;
            }
            a.dataset.ntWm = '1';
            a.innerHTML = mark;
            a.classList.add('nt-wm');
            a.setAttribute('aria-label', b.name);
            var im = a.querySelector('img');
            if (im) {
                im.alt = b.name;
                im.addEventListener('load', function() {
                    window.dispatchEvent(new Event('resize'));
                });
            }
            a.title = b.name;
            if (!b.logo) {
                var words = b.name.split(/\s+/);
                a.querySelector('.nt-wm-text b').textContent = words.shift();
                var rest = a.querySelector('.nt-wm-text i');
                if (rest) {
                    rest.textContent = words.join(' ');
                }
            }
        });
        // Site name -> brand name in titles and visible text.
        var names = (b.sitenames || []).filter(function(n) {
            return n && n !== b.name;
        });
        if (!names.length) {
            return;
        }
        var esc = function(t) {
            return t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        };
        var re = new RegExp('(^|[^A-Za-z0-9])(' + names.map(esc).join('|') + ')(?![A-Za-z0-9])', 'g');
        var fix = function(t) {
            return t.replace(re, function(m, pre) {
                return pre + b.name;
            });
        };
        var retitle = function() {
            var t = fix(document.title);
            if (t !== document.title) {
                document.title = t;
            }
        };
        retitle();
        var skip = /^(SCRIPT|STYLE|TEXTAREA|INPUT|SELECT|OPTION|CODE|PRE|NOSCRIPT)$/;
        var walk = function(root) {
            if (!root || !document.body) {
                return;
            }
            var tw = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
                acceptNode: function(n) {
                    var p = n.parentNode;
                    if (!p || skip.test(p.nodeName) || (p.closest && p.closest('[contenteditable="true"], .editor_atto, .tox, #openai_chat_log'))) {
                        return NodeFilter.FILTER_REJECT;
                    }
                    re.lastIndex = 0;
                    return re.test(n.nodeValue) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_SKIP;
                }
            });
            var list = [];
            while (tw.nextNode()) {
                list.push(tw.currentNode);
            }
            list.forEach(function(n) {
                re.lastIndex = 0;
                n.nodeValue = fix(n.nodeValue);
            });
            root.querySelectorAll && root.querySelectorAll('img[alt]').forEach(function(i) {
                re.lastIndex = 0;
                if (re.test(i.alt)) {
                    i.alt = fix(i.alt);
                }
            });
        };
        // Site administration pages are left as they are (settings text and descriptions
        // must stay exact); only the tab title changes there.
        if (/(^|\s)path-admin(\s|$)/.test(document.body.className)) {
            return;
        }
        walk(document.body);
        if (window.MutationObserver) {
            var pending = false;
            new MutationObserver(function(muts) {
                if (pending) {
                    return;
                }
                pending = true;
                window.setTimeout(function() {
                    pending = false;
                    retitle();
                    muts.forEach(function(m) {
                        m.addedNodes.forEach(function(n) {
                            if (n.nodeType === 1) {
                                walk(n);
                            }
                        });
                    });
                }, 60);
            }).observe(document.body, {childList: true, subtree: true});
        }
    }

    /**
     * Ask JO: one chat panel per page (the page's own, or the shared one the theme
     * adds before the footer), opened by every "Ask JO" button the same way and
     * shown as the same right-hand drawer everywhere.
     */
    function askJo() {
        var panel = document.getElementById('nt-jo-panel') || document.querySelector('[id$="-askjo-panel"]');
        if (!panel) {
            // The Ask JO block itself is on this page: open it in place (its popout view).
            var blk = document.querySelector('.block_openai_chat');
            if (blk) {
                document.addEventListener('click', function(e) {
                    var t = e.target.closest && e.target.closest('#nt-shellbar .nt-askjo, [id$="-askjo-btn"], a[href$="#askjo"]');
                    if (!t) {
                        return;
                    }
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    var pop = blk.querySelector('#popout');
                    if (pop && !blk.classList.contains('expanded')) {
                        pop.click();
                    }
                    blk.scrollIntoView({block: 'center'});
                    var inp = blk.querySelector('#openai_input');
                    if (inp) {
                        inp.focus();
                    }
                }, true);
            }
            return;
        }
        // Some pages render their main region inside a hidden wrapper (e.g. Interest Zone):
        // move the chat to <body> so it can always be shown.
        if (panel.parentNode !== document.body) {
            document.body.appendChild(panel);
        }
        panel.classList.add('nt-jo');
        panel.setAttribute('role', 'dialog');
        // Make sure the bar has an Ask JO button (e.g. the admin dashboard has none of its own).
        var barright = document.querySelector('#nt-shellbar .nt-top-right');
        if (barright && !document.querySelector('#nt-shellbar .nt-askjo, #nt-shellbar [id$="-askjo-btn"]')) {
            var jb = document.createElement('button');
            jb.type = 'button';
            jb.className = 'nt-askjo';
            jb.innerHTML = SPARK + '<span>Ask JO</span>';
            var tb = barright.querySelector('.nt-iconbtn, [id$="-themebtn"]');
            if (tb && tb.parentNode) {
                tb.parentNode.insertBefore(jb, tb);
            } else {
                barright.insertBefore(jb, barright.firstChild ? barright.firstChild.nextSibling : null);
            }
        }
        var name = 'Ask JO';
        // Header: icon, title, subtitle, new chat + close.
        var head = panel.firstElementChild;
        if (head && !head.classList.contains('block_openai_chat')) {
            var t = head.querySelector('span');
            if (t && t.textContent.trim()) {
                name = t.textContent.trim();
            }
            head.className += ' nt-jo-head';
            head.innerHTML = '<span class="nt-jo-ico">' + SPARK + '</span>' +
                '<span class="nt-jo-title"><b></b><small>Your AI study assistant</small></span>' +
                '<button type="button" class="nt-jo-new" title="New chat" aria-label="New chat"><svg viewBox="0 0 24 24" aria-hidden="true">' +
                '<path d="M20 11a8 8 0 1 0-2.3 5.7M20 5v6h-6"/></svg></button>' +
                '<button type="button" class="nt-jo-close" title="Close" aria-label="Close"><svg viewBox="0 0 24 24" aria-hidden="true">' +
                '<path d="M6 6l12 12M18 6 6 18"/></svg></button>';
            head.querySelector('.nt-jo-title b').textContent = name;
        }
        var chat = panel.querySelector('.block_openai_chat');
        var log = panel.querySelector('#openai_chat_log');
        var input = panel.querySelector('#openai_input');
        var go = panel.querySelector('#go');
        if (chat) {
            chat.classList.add('nt-jo-chat');
        }
        // Welcome + suggestions while the conversation is empty.
        if (log && !panel.querySelector('.nt-jo-welcome')) {
            var w = document.createElement('div');
            w.className = 'nt-jo-welcome';
            w.innerHTML = '<span class="nt-jo-wico">' + SPARK + '</span><h3>Hi! How can I help you study today?</h3>' +
                '<p>Ask about your courses, assignments, concepts or how to use the portal.</p><div class="nt-jo-sugg"></div>';
            [
                'Explain a topic from my course in simple words',
                'How do I submit an assignment?',
                'Make me a study plan for this week',
                'Give me 5 practice questions on my current topic'
            ].forEach(function(q) {
                var c = document.createElement('button');
                c.type = 'button';
                c.textContent = q;
                c.addEventListener('click', function() {
                    if (input && go) {
                        input.value = q;
                        go.click();
                    }
                });
                w.querySelector('.nt-jo-sugg').appendChild(c);
            });
            log.parentNode.insertBefore(w, log);
            var sync = function() {
                var empty = !log.querySelector('.openai_message');
                panel.classList.toggle('nt-jo-empty', empty);
                var body = log.parentNode;
                body.scrollTop = body.scrollHeight;
            };
            sync();
            if (window.MutationObserver) {
                new MutationObserver(sync).observe(log, {childList: true, subtree: true, characterData: true});
            }
        }
        if (input && log && window.MutationObserver) {
            // When a question fails, say so in the chat (not only in the input placeholder).
            new MutationObserver(function() {
                if (input.classList.contains('error') && !input.dataset.ntErrShown) {
                    input.dataset.ntErrShown = '1';
                    var m = document.createElement('div');
                    m.className = 'nt-jo-err';
                    m.textContent = 'Sorry, JO could not answer right now. Please try again in a moment.';
                    log.appendChild(m);
                } else if (!input.classList.contains('error')) {
                    delete input.dataset.ntErrShown;
                }
            }).observe(input, {attributes: true, attributeFilter: ['class']});
        }
        if (input) {
            input.setAttribute('placeholder', 'Ask JO anything…');
            input.setAttribute('rows', '1');
            var grow = function() {
                input.style.height = 'auto';
                input.style.height = Math.min(input.scrollHeight, 140) + 'px';
            };
            input.addEventListener('input', grow);
            // Enter sends, Shift+Enter makes a new line.
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey && go) {
                    e.preventDefault();
                    if (input.value.trim()) {
                        go.click();
                    }
                    window.setTimeout(grow, 0);
                }
            });
        }
        var setOpen = function(open) {
            panel.hidden = !open;
            document.body.classList.toggle('nt-jo-open', open);
            document.querySelectorAll('#nt-shellbar .nt-askjo, [id$="-askjo-btn"]').forEach(function(b) {
                b.setAttribute('aria-expanded', open ? 'true' : 'false');
                b.classList.toggle('nt-on', open);
            });
            if (open && input) {
                window.setTimeout(function() {
                    input.focus();
                }, 50);
            }
        };
        // Every Ask JO trigger toggles this panel (capture phase, before the page's own handlers).
        document.addEventListener('click', function(e) {
            var t = e.target.closest && e.target.closest(
                '#nt-shellbar .nt-askjo, [id$="-askjo-btn"], [data-nt-jo-open], a[href$="#askjo"], a[href*="askjo=1"]');
            if (t) {
                e.preventDefault();
                e.stopImmediatePropagation();
                setOpen(panel.hidden);
                return;
            }
            var c = e.target.closest && e.target.closest('.nt-jo-close, [class$="-askjo-close"], .nt-jo-gclose');
            if (c && panel.contains(c)) {
                e.preventDefault();
                e.stopImmediatePropagation();
                setOpen(false);
                return;
            }
            var n = e.target.closest && e.target.closest('.nt-jo-new');
            if (n && panel.contains(n)) {
                e.preventDefault();
                var r = panel.querySelector('#refresh');
                if (r) {
                    r.click();
                } else if (log) {
                    log.innerHTML = '';
                }
            }
        }, true);
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !panel.hidden) {
                setOpen(false);
            }
        });
        if (location.hash === '#askjo' || /[?&]askjo=1\b/.test(location.search)) {
            window.setTimeout(function() {
                setOpen(true);
            }, 300);
        }
    }

    /**
     * Dashboard focus (Pomodoro) timer: 25/5/15 minutes, keeps running across page
     * loads (end time in localStorage), counts today's finished focus sessions.
     */
    function focusTimer() {
        var box = document.getElementById('nt-timer');
        if (!box) {
            return;
        }
        var key = 'nt-timer-' + (box.dataset.userid || '0');
        var timeEl = box.querySelector('.nt-timer-time');
        var ring = box.querySelector('.t-fg');
        var go = box.querySelector('.nt-timer-go');
        var countEl = box.querySelector('.nt-timer-count');
        var modes = box.querySelectorAll('.nt-timer-modes button');
        var startTxt = go.textContent;
        var C = 326.7;
        var st = {min: 25, end: 0, left: 25 * 60, day: '', count: 0};
        var today = new Date().toDateString();
        try {
            st = Object.assign(st, JSON.parse(localStorage.getItem(key) || '{}'));
        } catch (e) {
            // Ignore.
        }
        if (st.day !== today) {
            st.day = today;
            st.count = 0;
        }
        var save = function() {
            try {
                localStorage.setItem(key, JSON.stringify(st));
            } catch (e) {
                // Ignore.
            }
        };
        var tick = null;
        var remaining = function() {
            return st.end ? Math.max(0, Math.round((st.end - Date.now()) / 1000)) : st.left;
        };
        var paint = function() {
            var r = remaining();
            timeEl.textContent = String(Math.floor(r / 60)).padStart(2, '0') + ':' + String(r % 60).padStart(2, '0');
            ring.style.strokeDashoffset = String(C * (1 - r / (st.min * 60)));
            box.classList.toggle('is-running', !!st.end);
            box.classList.toggle('is-break', st.min !== 25);
            go.textContent = st.end ? 'Pause' : (r < st.min * 60 ? 'Resume' : startTxt);
            countEl.textContent = st.count;
            modes.forEach(function(b) {
                b.classList.toggle('on', +b.dataset.min === st.min);
            });
            if (st.end && r === 0) {
                st.end = 0;
                st.left = st.min * 60;
                if (st.min === 25) {
                    st.count++;
                }
                save();
                window.clearInterval(tick);
                tick = null;
                try {
                    if (window.Notification && Notification.permission === 'granted') {
                        new Notification(st.min === 25 ? 'Focus session done - take a break!' : 'Break over - back to it!');
                    }
                } catch (e) {
                    // Ignore.
                }
                paint();
            }
        };
        var run = function() {
            if (!tick) {
                tick = window.setInterval(paint, 1000);
            }
        };
        go.addEventListener('click', function() {
            if (st.end) {
                st.left = remaining();
                st.end = 0;
                window.clearInterval(tick);
                tick = null;
            } else {
                st.end = Date.now() + remaining() * 1000;
                run();
                try {
                    if (window.Notification && Notification.permission === 'default') {
                        Notification.requestPermission();
                    }
                } catch (e) {
                    // Ignore.
                }
            }
            save();
            paint();
        });
        box.querySelector('.nt-timer-reset').addEventListener('click', function() {
            st.end = 0;
            st.left = st.min * 60;
            window.clearInterval(tick);
            tick = null;
            save();
            paint();
        });
        modes.forEach(function(b) {
            b.addEventListener('click', function() {
                st.min = +b.dataset.min;
                st.end = 0;
                st.left = st.min * 60;
                window.clearInterval(tick);
                tick = null;
                save();
                paint();
            });
        });
        if (st.end) {
            run();
        }
        paint();
    }

    /**
     * BAZ settings, Top Bar tab: keep each feature's "In use now: ..." line in step
     * with the dropdowns while they are being changed (before saving).
     */
    function featureNow() {
        var lines = document.querySelectorAll('.nt-feat-now[data-feature]');
        var nav = document.querySelector('select[name="s_theme_baz_navlayout"]');
        if (!lines.length || !nav) {
            return;
        }
        var label = function(sel, v) {
            var o = sel.querySelector('option[value="' + v + '"]');
            return o ? o.textContent : v;
        };
        var update = function() {
            lines.forEach(function(line) {
                if (line.dataset.always === '1') {
                    return;
                }
                line.querySelector('b').textContent = label(nav, nav.value);
                line.classList.toggle('is-top', nav.value !== 'left');
            });
        };
        nav.addEventListener('change', update);
        document.addEventListener('nt-navapplied', update);
        update();
    }

    /**
     * BAZ settings, Top Bar tab: show the Navigation / feature choices as clickable
     * buttons instead of dropdown lists (no pop-up list to draw, so it is instant and
     * never shows as an empty black box while the busy settings page is loading).
     */
    /**
     * Navigation (BAZ settings, Top Bar tab): one click applies the option straight away
     * (only theme_baz/navlayout is saved, no theme rebuild), no "Save changes" needed.
     *
     * @param {HTMLSelectElement} sel
     * @param {HTMLOptionElement} o
     * @param {HTMLElement} b
     * @param {Function} paint
     */
    function applyNav(sel, o, b, paint) {
        if (sel.value === o.value && !b.classList.contains('is-busy')) {
            return;
        }
        var cfg = window.M && M.cfg;
        if (!cfg || !cfg.sesskey) {
            return;
        }
        var old = sel.value;
        sel.value = o.value;
        paint();
        b.classList.add('is-busy');
        var body = new URLSearchParams();
        body.set('layout', o.value);
        body.set('sesskey', cfg.sesskey);
        body.set('ajax', '1');
        var note = sel.parentNode.querySelector('.nt-seg-note');
        if (!note) {
            note = document.createElement('div');
            note.className = 'nt-seg-note';
            note.setAttribute('role', 'status');
            sel.parentNode.insertBefore(note, sel);
        }
        note.className = 'nt-seg-note';
        note.textContent = '…';
        fetch(cfg.wwwroot + '/theme/baz/navigation.php', {method: 'POST', body: body, credentials: 'same-origin'})
            .then(function(r) {
                return r.json();
            })
            .then(function(res) {
                if (!res || !res.ok) {
                    throw new Error('not saved');
                }
                // Saved: make it the form's own value too, so leaving the page asks nothing.
                Array.prototype.forEach.call(sel.options, function(x) {
                    x.defaultSelected = x.value === o.value;
                });
                note.className = 'nt-seg-note is-ok';
                note.textContent = '\u2713 ' + res.message;
                document.dispatchEvent(new CustomEvent('nt-navapplied', {detail: o.value}));
                // Show the new look at once: reload this page on the same tab.
                try {
                    var pane = sel.closest('.tab-pane');
                    window.sessionStorage.setItem('nt-navapplied', JSON.stringify({tab: pane ? pane.id : '', msg: res.message}));
                } catch (e) {
                    // Private window: the page still reloads.
                }
                window.setTimeout(function() {
                    window.location.reload();
                }, 450);
            })
            .catch(function() {
                sel.value = old;
                note.className = 'nt-seg-note is-err';
                note.textContent = 'Could not apply. Please reload the page and try again.';
            })
            .then(function() {
                b.classList.remove('is-busy');
                paint();
            });
    }

    var NAVCARDS = {
        left: {d: 'Classic BAZ left sidebar', c: ['#002D47', '#F7B742', '#F4F7FB']},
        top: {d: 'Navy two-level top bar', c: ['#0f1f3d', '#f4b23e', '#eef2f7']},
        rail: {d: 'Menu button, light tiles \u00b7 navy & gold', c: ['#0f1f3d', '#f4b23e', '#eef2f7']},
        dock: {d: 'Floating bottom menu \u00b7 navy & gold', c: ['#0f1f3d', '#f4b23e', '#eef2f7']},
        campus: {d: 'Serif, search in the middle \u00b7 navy & gold', c: ['#0f1f3d', '#f4b23e', '#eef2f7']}
    };

    function featureButtons() {
        // Back from "apply": open the same tab again and say what is in use.
        var back = null;
        try {
            back = JSON.parse(window.sessionStorage.getItem('nt-navapplied') || 'null');
            window.sessionStorage.removeItem('nt-navapplied');
        } catch (e) {
            back = null;
        }
        if (back && back.tab) {
            var tl = document.querySelector('a[data-toggle="tab"][href="#' + back.tab + '"]');
            if (tl) {
                tl.click();
            }
        }
        var sels = document.querySelectorAll('select[name="s_theme_baz_navlayout"], select[name^="s_theme_baz_nt_f_"]');
        sels.forEach(function(sel) {
            if (sel.dataset.ntSeg) {
                return;
            }
            sel.dataset.ntSeg = '1';
            var isnav = sel.name === 's_theme_baz_navlayout';
            var seg = document.createElement('div');
            seg.className = isnav ? 'nt-seg-choice nt-navcards' : 'nt-seg-choice';
            seg.setAttribute('role', 'radiogroup');
            Array.prototype.forEach.call(sel.options, function(o, i) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'nt-seg-btn';
                b.setAttribute('role', 'radio');
                b.dataset.value = o.value;
                var meta = isnav ? NAVCARDS[o.value] : null;
                if (meta) {
                    var label = o.textContent.replace(/^Option\s*\d+\s*[\u2013-]\s*/, '');
                    b.className = 'nt-seg-btn nt-navcard nt-navcard-' + o.value;
                    b.style.setProperty('--c1', meta.c[0]);
                    b.style.setProperty('--c2', meta.c[1]);
                    b.style.setProperty('--bg', meta.c[2]);
                    b.innerHTML = '<span class="nt-nc-prev" aria-hidden="true"><i class="a"></i><i class="b"></i><i class="c"></i><i class="d"></i></span>' +
                        '<span class="nt-nc-body"><span class="nt-nc-num"></span><b></b><small></small></span>' +
                        '<span class="nt-nc-state"></span>';
                    b.querySelector('.nt-nc-num').textContent = i + 1;
                    b.querySelector('b').textContent = label;
                    b.querySelector('small').textContent = meta.d;
                } else {
                    b.textContent = o.textContent;
                }
                b.addEventListener('click', function() {
                    if (sel.name === 's_theme_baz_navlayout') {
                        applyNav(sel, o, b, paint);
                        return;
                    }
                    if (sel.value !== o.value) {
                        sel.value = o.value;
                        sel.dispatchEvent(new Event('change', {bubbles: true}));
                    }
                    paint();
                });
                seg.appendChild(b);
            });
            var paint = function() {
                seg.querySelectorAll('.nt-seg-btn').forEach(function(b) {
                    var on = b.dataset.value === sel.value;
                    b.classList.toggle('on', on);
                    b.setAttribute('aria-checked', on ? 'true' : 'false');
                });
            };
            sel.addEventListener('change', paint);
            sel.classList.add('nt-seg-hidden');
            sel.setAttribute('tabindex', '-1');
            sel.setAttribute('aria-hidden', 'true');
            sel.parentNode.insertBefore(seg, sel);
            paint();
            if (isnav && back && back.msg) {
                var note = document.createElement('div');
                note.className = 'nt-seg-note is-ok';
                note.setAttribute('role', 'status');
                note.textContent = '\u2713 ' + back.msg;
                sel.parentNode.insertBefore(note, sel);
            }
        });
    }

    /**
     * Options 3-5 (body.nt-v-rail / nt-v-dock / nt-v-campus): the same bar, small extras
     * for each design. Menu texts get their own span so the designs can show or hide them.
     */
    function variantShell() {
        var cl = document.body.classList;
        var v = cl.contains('nt-v-rail') ? 'rail' : (cl.contains('nt-v-dock') ? 'dock' : (cl.contains('nt-v-campus') ? 'campus' : ''));
        var bar = document.getElementById('nt-shellbar');
        if (!v || !bar || bar.dataset.ntVariant) {
            return;
        }
        bar.dataset.ntVariant = v;
        bar.querySelectorAll('.nt-nav > a').forEach(function(a) {
            var label = a.textContent.trim();
            Array.prototype.slice.call(a.childNodes).forEach(function(n) {
                if (n.nodeType === 3 && n.textContent.trim() !== '') {
                    var sp = document.createElement('span');
                    sp.className = 'nt-lbl';
                    sp.textContent = n.textContent.trim();
                    a.replaceChild(sp, n);
                }
            });
            if (label && !a.getAttribute('title')) {
                a.setAttribute('title', label);
            }
        });
        if (v === 'campus' && !bar.querySelector('.nv-mast')) {
            var mast = document.createElement('div');
            mast.className = 'nv-mast';
            try {
                mast.textContent = new Date().toLocaleDateString(undefined, {weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'});
            } catch (e) {
                mast.textContent = new Date().toDateString();
            }
            var inner = bar.querySelector('.nt-top-inner');
            inner.insertBefore(mast, inner.firstChild);
            // Option 5: logo stays top left; search sits in the top middle, right after the logo.
            var sw = bar.querySelector('.nt-top-right .nt-search-wrap, .nt-top-right .nt-moved-search');
            var tr = bar.querySelector('.nt-top-right');
            if (sw && tr) {
                sw.classList.add('nv-mid-search');
                inner.insertBefore(sw, tr);
            }
        }
        if (v === 'rail') {
            launcher(bar);
        }
    }

    var NV_TILES = [['#1c3a6e', '#0f1f3d'], ['#f4b23e', '#e08a12'], ['#406cd2', '#2a4fa3'], ['#1fa37a', '#16825f'],
        ['#e5556e', '#c43454'], ['#0f1f3d', '#0b1a33'], ['#f8c870', '#f4b23e'], ['#5c7184', '#3f5163']];
    var NV_DEFICON = '<rect x="4" y="4" width="16" height="16" rx="4"/><path d="M9 12h6M12 9v6"/>';

    /**
     * Option 3 (App launcher): no menu bar. The menu links become app tiles in a full-screen
     * launcher opened by the "Menu" button or Ctrl+K; the tools move into a dark capsule.
     *
     * @param {HTMLElement} bar
     */
    function launcher(bar) {
        var right = bar.querySelector('.nt-top-right');
        if (!right || document.getElementById('nv-launcher')) {
            return;
        }
        var tools = document.createElement('div');
        tools.className = 'nv-tools';
        Array.prototype.slice.call(right.children).forEach(function(el) {
            if (!/search/.test(el.className)) {
                tools.appendChild(el);
            }
        });
        var mac = /Mac|iPhone|iPad/.test(navigator.platform || '');
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'nv-launch-btn';
        btn.setAttribute('aria-haspopup', 'dialog');
        btn.setAttribute('aria-expanded', 'false');
        btn.innerHTML = '<span class="nv-launch-dots" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span>' +
            '<span class="nv-launch-t">Menu</span><kbd>' + (mac ? '\u2318' : 'Ctrl') + ' K</kbd>';
        right.appendChild(btn);
        right.appendChild(tools);

        // Assistive-touch button in place of the Menu button: one floating icon; when clicked
        // the main menu opens as a row of app tiles in the free top space of the header.
        btn.hidden = true;
        var touch = document.createElement('button');
        touch.type = 'button';
        touch.className = 'nv-touch';
        touch.setAttribute('aria-controls', 'nv-strip');
        touch.setAttribute('aria-expanded', 'false');
        touch.setAttribute('aria-label', 'Menu');
        touch.title = 'Menu (' + (mac ? '\u2318' : 'Ctrl') + '+K for all pages)';
        touch.innerHTML = '<span class="nv-burger" aria-hidden="true"><i></i><i></i><i></i></span><span class="nv-touch-t">Menu</span>';
        right.insertBefore(touch, tools);
        // The menu opens in the gap between the search (left) and the icon.
        var strip = document.createElement('nav');
        strip.className = 'nv-strip';
        strip.id = 'nv-strip';
        strip.setAttribute('aria-label', 'Main');
        right.insertBefore(strip, touch);
        // Fit the menu into the gap: full chips, then smaller chips, then icons only.
        var fit = function() {
            if (!bar.classList.contains('nv-menu-open')) {
                return;
            }
            // Equal-width modules filling the gap; next size down when a name would be cut.
            var cut = function() {
                var w = 0;
                Array.prototype.forEach.call(strip.children, function(c) {
                    w += c.getBoundingClientRect().width;
                });
                w += Math.max(0, strip.children.length - 1) * (parseFloat(window.getComputedStyle(strip).columnGap) || 0);
                if (w > strip.clientWidth + 1) {
                    return true;
                }
                return Array.prototype.some.call(strip.querySelectorAll('.nv-chip > span:last-child'), function(l) {
                    return l.offsetParent !== null && (l.scrollWidth > l.clientWidth + 1 || l.scrollHeight > l.clientHeight + 3);
                });
            };
            strip.classList.remove('nv-fit-sm', 'nv-fit-st', 'nv-fit-st2', 'nv-fit-ic');
            if (window.innerWidth < 768) {
                // Phones: small icon-and-text tiles that swipe sideways.
                strip.classList.add('nv-fit-st');
                return;
            }
            // Every module is the same tile (icon over its name); icons only if even that cannot fit.
            strip.classList.add('nv-fit-st');
            if (cut()) {
                strip.classList.add('nv-fit-st2');
            }
            if (cut()) {
                strip.classList.remove('nv-fit-st', 'nv-fit-st2');
                strip.classList.add('nv-fit-ic');
            }
        };
        window.addEventListener('resize', fit);
        var setMenu = function(show) {
            bar.classList.toggle('nv-menu-open', show);
            touch.querySelector('.nv-touch-t').textContent = show ? 'Close' : 'Menu';
            fit();
            window.setTimeout(fit, 60);
            touch.setAttribute('aria-expanded', show ? 'true' : 'false');
            touch.setAttribute('aria-label', show ? 'Close menu' : 'Menu');
            // Same top bar on every page: the open menu stays open after choosing a module.
            try {
                window.localStorage.setItem('nv-menu-open', show ? '1' : '0');
            } catch (e) {
                // Private window: not remembered.
            }
        };
        touch.addEventListener('click', function(e) {
            e.stopPropagation();
            setMenu(!bar.classList.contains('nv-menu-open'));
        });
        // While the menu is open the search shrinks to its icon; clicking it brings the search back.
        var sw = right.querySelector('[class*="search-wrap"], .nt-moved-search');
        if (sw) {
            sw.addEventListener('click', function() {
                if (bar.classList.contains('nv-menu-open')) {
                    setMenu(false);
                    var inp = sw.querySelector('input');
                    if (inp) {
                        inp.focus();
                    }
                }
            }, true);
        }
        document.addEventListener('click', function(e) {
            if (bar.classList.contains('nv-menu-open') && !strip.contains(e.target) && !touch.contains(e.target)) {
                setMenu(false);
            }
        });
        var ov = document.createElement('div');
        ov.className = 'nv-launcher';
        ov.id = 'nv-launcher';
        ov.setAttribute('role', 'dialog');
        ov.setAttribute('aria-modal', 'true');
        ov.setAttribute('aria-label', 'Menu');
        ov.innerHTML = '<div class="nv-launcher-in"><div class="nv-launcher-top"><h2>Where to?</h2>' +
            '<input type="search" class="nv-launcher-filter" placeholder="Type to find a page\u2026" aria-label="Find a page">' +
            '<button type="button" class="nv-launcher-x" aria-label="Close">\u00d7</button></div><div class="nv-launcher-body"></div>' +
            '<p class="nv-launcher-empty" hidden>Nothing found</p></div>';
        var body = ov.querySelector('.nv-launcher-body');
        var n = 0;
        var section = function(title, links) {
            var seen = {};
            links = links.filter(function(a) {
                var h = a.getAttribute('href');
                if (!h || h === '#' || seen[h + a.textContent.trim()]) {
                    return false;
                }
                seen[h + a.textContent.trim()] = 1;
                return a.textContent.trim() !== '';
            });
            if (!links.length) {
                return;
            }
            var h = document.createElement('div');
            h.className = 'nv-launcher-sec';
            h.textContent = title;
            var grid = document.createElement('div');
            grid.className = 'nv-launcher-grid';
            links.forEach(function(a) {
                var c = NV_TILES[n % NV_TILES.length];
                var t = document.createElement('a');
                t.className = 'nv-app' + (a.classList.contains('on') ? ' on' : '');
                t.href = a.getAttribute('href');
                if (a.target) {
                    t.target = a.target;
                }
                t.style.setProperty('--g', 'linear-gradient(140deg,' + c[0] + ',' + c[1] + ')');
                t.style.setProperty('--s', c[0]);
                t.style.animationDelay = (n * 25) + 'ms';
                var svg = a.querySelector('svg');
                var ic = document.createElement('span');
                ic.className = 'nv-app-ic';
                ic.innerHTML = svg ? svg.outerHTML : '<svg viewBox="0 0 24 24">' + NV_DEFICON + '</svg>';
                var lb = document.createElement('span');
                lb.textContent = a.textContent.trim();
                t.appendChild(ic);
                t.appendChild(lb);
                grid.appendChild(t);
                n++;
            });
            body.appendChild(h);
            body.appendChild(grid);
        };
        var q = function(sel) {
            return Array.prototype.slice.call(document.querySelectorAll(sel));
        };
        section('Apps', q('#nt-shellbar .nt-nav > a, #nt-shellbar .nt-navmore-panel a'));
        var sub = document.querySelector('#nt-shellsub .nt-sub-title');
        section(sub ? sub.textContent.trim() : 'This page', q('#nt-shellsub .nt-tabs a, #nt-shellsub .nt-more-panel a'));
        document.body.appendChild(ov);
        var k = 0;
        ov.querySelectorAll('.nv-launcher-grid').forEach(function(g, gi) {
            if (gi === 0) {
                g.querySelectorAll('.nv-app').forEach(function(t) {
                    var c = t.cloneNode(true);
                    c.className = 'nv-chip' + (t.classList.contains('on') ? ' on' : '');
                    c.style.animationDelay = (k++ * 35) + 'ms';
                    strip.appendChild(c);
                });
            }
        });
        if (ov.querySelectorAll('.nv-launcher-grid').length > 1) {
            var all = document.createElement('button');
            all.type = 'button';
            all.className = 'nv-chip nv-chip-all';
            all.style.animationDelay = (k * 35) + 'ms';
            all.innerHTML = '<span class="nv-app-ic"><svg viewBox="0 0 24 24"><rect x="4" y="4" width="6" height="6" rx="1.5"/><rect x="14" y="4" width="6" height="6" rx="1.5"/><rect x="4" y="14" width="6" height="6" rx="1.5"/><rect x="14" y="14" width="6" height="6" rx="1.5"/></svg></span><span></span>';
            all.lastChild.textContent = ov.querySelectorAll('.nv-launcher-sec')[1].textContent;
            all.addEventListener('click', function() {
                setMenu(false);
                btn.click();
            });
            strip.appendChild(all);
        }
        var keep = false;
        try {
            keep = window.localStorage.getItem('nv-menu-open') === '1';
        } catch (e) {
            keep = false;
        }
        if (keep) {
            strip.classList.add('nv-noanim');
            setMenu(true);
            window.addEventListener('load', fit);
            if (document.fonts && document.fonts.ready) {
                document.fonts.ready.then(fit);
            }
        }

        var filter = ov.querySelector('.nv-launcher-filter');
        var open = function(show) {
            ov.classList.toggle('open', show);
            document.body.classList.toggle('nv-launcher-on', show);
            btn.setAttribute('aria-expanded', show ? 'true' : 'false');
            if (show) {
                filter.value = '';
                apply();
                window.setTimeout(function() {
                    filter.focus();
                }, 60);
            } else {
                btn.focus();
            }
        };
        var apply = function() {
            var term = filter.value.trim().toLowerCase();
            var any = false;
            ov.querySelectorAll('.nv-launcher-grid').forEach(function(g) {
                var vis = 0;
                g.querySelectorAll('.nv-app').forEach(function(t) {
                    var hit = !term || t.textContent.toLowerCase().indexOf(term) !== -1;
                    t.hidden = !hit;
                    vis += hit ? 1 : 0;
                });
                g.hidden = !vis;
                g.previousElementSibling.hidden = !vis;
                any = any || vis > 0;
            });
            ov.querySelector('.nv-launcher-empty').hidden = any;
        };
        filter.addEventListener('input', apply);
        filter.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                var first = ov.querySelector('.nv-app:not([hidden])');
                if (first) {
                    e.preventDefault();
                    first.click();
                }
            }
        });
        btn.addEventListener('click', function() {
            open(true);
        });
        ov.querySelector('.nv-launcher-x').addEventListener('click', function() {
            open(false);
        });
        ov.addEventListener('click', function(e) {
            if (e.target === ov) {
                open(false);
            }
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && bar.classList.contains('nv-menu-open')) {
                setMenu(false);
            }
            if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
                e.preventDefault();
                open(!ov.classList.contains('open'));
            } else if (e.key === 'Escape' && ov.classList.contains('open')) {
                open(false);
            }
        });
    }


    /* Options 3-5 student dashboard: small extras built from data that is already on the page
       (hero circles for Option 3). Nothing is fetched and nothing is changed for Options 1-2. */
    function nvDash() {
        var cl = document.body.classList;
        var stu = document.querySelector('main.nt-stu');
        if (!stu || stu.getAttribute('data-nv') || !(cl.contains('nt-v-rail') || cl.contains('nt-v-dock') || cl.contains('nt-v-campus'))) {
            return;
        }
        stu.setAttribute('data-nv', '1');
        var t = function(sel) { var e = stu.querySelector(sel); return e ? e.textContent.replace(/\s+/g, ' ').trim() : ''; };
        var n = function(s) { var m = String(s).match(/[\d][\d.,]*/); return m ? m[0] : '0'; };
        var hello = stu.querySelector('.nt-hello');
        if (cl.contains('nt-v-rail') && hello && !hello.querySelector('.nv-circles')) {
            var box = document.createElement('div');
            box.className = 'nv-circles';
            var items = [[n(t('.nt-chip-amber')), 'Day streak'], [t('.nt-foot3 b') || '0', 'Study time'], [t('.nt-xp-pts b') || '0', 'XP']];
            box.innerHTML = items.map(function(i) {
                return '<span class="nv-circ"><b>' + i[0].replace(/</g, '&lt;') + '</b><small>' + i[1] + '</small></span>';
            }).join('');
            hello.appendChild(box);
            var go = stu.querySelector('.nt-focus-actions a');
            if (go) {
                var a = document.createElement('a');
                a.className = 'nv-continue';
                a.href = go.getAttribute('href');
                a.textContent = 'Continue learning \u2192';
                hello.appendChild(a);
            }
        }
        // Option 5: a "Join" style button + label on the class card, built from the focus link.
        if (cl.contains('nt-v-campus')) {
            var f = stu.querySelector('.nt-focus h1 a');
            var acts = stu.querySelector('.nt-focus-actions');
            if (f && acts && !acts.querySelector('.nv-open')) {
                var b = document.createElement('a');
                b.className = 'nt-btn nv-open';
                b.href = f.getAttribute('href');
                b.textContent = 'Open course';
                acts.appendChild(b);
            }
        }
    }


    // Options 3-5: the dashboard banner (BAZ setting) shows above the one-screen dashboard, like Option 1.
    function nvBanner() {
        var cl = document.body.classList;
        var stu = document.querySelector('main.nt-stu');
        if (!stu || !(cl.contains('nt-v-rail') || cl.contains('nt-v-dock') || cl.contains('nt-v-campus'))) {
            return;
        }
        var ban = stu.querySelector('.nt-banner') || document.querySelector('.nt-banner[data-nvb]');
        if (!ban) {
            return;
        }
        var home = ban.__nvhome;
        if (!home) {
            home = ban.__nvhome = { parent: ban.parentNode, next: ban.nextSibling };
        }
        var place = function() {
            var wide = window.matchMedia('(min-width: 1200px) and (min-height: 620px)').matches;
            if (wide) {
                if (ban.nextSibling !== stu || ban.parentNode !== stu.parentNode) {
                    stu.parentNode.insertBefore(ban, stu);
                }
                ban.setAttribute('data-nvb', '1');
                var cs = window.getComputedStyle(stu);
                ban.style.margin = '12px ' + cs.paddingRight + ' 0 ' + cs.paddingLeft;
                ban.style.display = 'block';
            } else if (ban.parentNode !== home.parent) {
                home.parent.insertBefore(ban, home.next && home.next.parentNode === home.parent ? home.next : null);
                ban.style.margin = '';
                ban.removeAttribute('data-nvb');
            }
        };
        place();
        window.addEventListener('resize', place);
    }

    function init() {
        // Option 2 (top bar) only: turn the sidebar shell into the two-level bar.
        var cl = document.body.classList;
        if (cl.contains('nt-shell')) {
            build();
            variantShell();
            nvDash();
            nvBanner();
            nvDone();
            if (!cl.contains('nt-v-rail')) {
                priorityNav(document.querySelector('#nt-shellbar .nt-nav'));
            }
        }
        // Features switched on for this layout (BAZ settings, Top Bar tab, "Features").
        if (window.EDNXT_HOLIDAYS) {
            initHolidays();
        }
        if (cl.contains('nt-f-helptab')) {
            helpEdge();
        }
        assistive();
        if (window.EDNXT_BRAND) {
            brand();
        }
        if (cl.contains('nt-f-askjo') || document.querySelector('#nt-jo-panel, [id$="-askjo-panel"]')) {
            askJo();
        }
        focusTimer();
        featureButtons();
        featureNow();
        // Hover labels for the AI tutor buttons (they are lined up in shell.css).
        window.setTimeout(function() {
            document.querySelectorAll('.tutor-ia-toggle-button').forEach(function(b) {
                b.setAttribute('data-nt-label', b.getAttribute('title') || b.getAttribute('aria-label') || 'AI Tutor');
            });
        }, 800);
        document.querySelectorAll('[data-nt-help]').forEach(function(b) {
            b.addEventListener('click', openHelp);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
