/**
 * Dashboard "peek": click any dashboard card (Options 1-5, student and admin) to open a
 * details panel with the full card - all items shown, text not cut, extra info from
 * tooltips and a button to the full page. Links inside cards keep working as before.
 * Display only: nothing is saved and no Moodle data changes.
 */
(function() {
    'use strict';
    if (window.__ntPeek) {
        return;
    }
    window.__ntPeek = true;

    var CARDS = '.sd-card, .sd-stat, .ad-card, .ad-kpi, .nt-card, .nv-tile, .nv-panel, .nv-box, .nv-story,' +
        ' .nv-timetable, .nv-courses, .nv-shelfbox, .nv-hl, .nv-hk';
    var SKIP = '.nt-actions4, .nv-t-mission, .nv-t-ahero, .nv-cmd, .nv-mood, .nv-opsnotes, .nv-t-notes, .nv-t-todo,' +
        ' .nv-t-habit, .nv-t-find, .nt-timer, [data-nv-notes], [data-nv-todo], [data-nv-habits], [data-nv-mood], [data-nv-ask], [data-nv-attend], [data-nv-exams], [data-nv-kanban], [data-nv-cards], [data-nv-gcalc], [data-nv-cite]';
    var INTERACTIVE = 'a, button, input, textarea, select, label, summary, [role="button"], [role="tab"], [contenteditable], .nt-seg-tabs';
    var HEADS = ['.nt-eyebrow', '.nv-kicker', '.nv-tile-h h2', '.nv-panel-h h2', '.nt-card-h h2', '.nt-card-h h3', '.nt-card-h', '.ad-card-h h2', '.ad-card-h h3',
        '.ad-card-h', '.sd-sec-h h2', '.sd-sec-h h3', '.sd-sec-h', '.nv-section', 'h2', 'h3', '.nv-label', '.ad-hl-label', '.sd-l',
        '.sd-stat-top', '.sd-ct', '.nt-minical-h', '.ad-kpi .l', '.sd-stat .sd-l', '.l', 'b', 'span'];
    var LINKS = '.nv-tile-h a, .nv-panel-h a, .nt-card-h a, .ad-card-h a, .sd-sec-h a, .nv-section a, h2 a, h3 a';

    var WWW = (window.M && M.cfg && M.cfg.wwwroot) || '';
    // When a card has no link of its own, offer the page that holds the full information.
    var GUESS = [
        [/calendar|deadline|upcoming|agenda|timetable|this week|week|live class|october|november|december|january|february|march|april|may|june|july|august|september/i, '/calendar/view.php?view=month', 'Open calendar'],
        [/grade|score|report card|average/i, '/grade/report/overview/index.php', 'Open grades'],
        [/course|catalogue|track|progress|semester|completed|reading list|pick up|jump back/i, '/my/courses.php', 'Open my courses'],
        [/announcement|notice board|^feed$/i, '/mod/forum/index.php', 'Open forums'],
        [/level|xp|badge|achievement|streak/i, '/badges/mybadges.php', 'Open my badges'],
        [/online|presence|visitors/i, '/admin/user.php', 'Open users'],
        [/user|enrol|blocked|account/i, '/admin/user.php', 'Open users'],
        [/health|system|check|bulletin|ops/i, '/admin/index.php', 'Open notifications'],
        [/activity|event|log|minutes/i, '/report/log/index.php?id=1', 'Open logs'],
        [/engagement|circulation|active/i, '/report/log/index.php?id=1', 'Open logs']
    ];
    var ABOUT = [
        [/avg\.? progress|semester|progress/i, 'Average completion across your active courses. Open a course to see which activities are left.'],
        [/deadline|upcoming|agenda|due/i, 'Assignments, quizzes and events due soon, nearest first. Click an item to open it.'],
        [/calendar|^[A-Z][a-z]+ \d{4}$/i, 'This month at a glance: days with deadlines, events and holidays are marked. Open the calendar for full details.'],
        [/timetable|this week/i, 'This week day by day, with deadlines, classes and holidays from your calendar.'],
        [/focus next|now learning|continue learning/i, 'The course you are working on now, how far you are, and the next activity to open.'],
        [/my week|study time|study pulse|heatmap/i, 'Your study activity over recent days, based on your time on the site.'],
        [/jump back|pick up|reading list|up next/i, 'Activities you opened recently - click one to continue where you left off.'],
        [/achievement|badge/i, 'Milestones you unlock by studying regularly and completing work.'],
        [/quick links|launchpad|offices|commands/i, 'Shortcuts to the pages used most. Click one to open it.'],
        [/fee/i, 'Fee status and the payment link from the university.'],
        [/thought|quote|tip|word of the day|registrar/i, 'A new one every day.'],
        [/event stream|live feed|minutes/i, 'Latest configuration changes and site events, newest first.'],
        [/almanac/i, 'Key dates and figures for this term: progress, courses, day of the year and the next holiday.'],
        [/live class/i, 'Live sessions scheduled in your courses this week.'],
        [/active courses|my courses|catalogue|course tracks|courses/i, 'Courses you are enrolled in, with your completion for each.'],
        [/level|xp/i, 'Points and level from the Level Up plugin, earned by taking part in your courses.'],
        [/announcement|notice|feed/i, 'Latest posts from your site and course announcement forums.'],
        [/business news|press/i, 'Business headlines from trusted publications. Opens in a new tab.'],
        [/podcast/i, 'Recommended podcast episodes. Opens in a new tab.'],
        [/grade|score|report/i, 'Your latest graded work with the score and percentage.'],
        [/streak/i, 'Days in a row you have been active on the site.'],
        [/site activity|activity today/i, 'Everything people did on the site today, split by type, compared with the same day last week.'],
        [/total users|enrolments|active today|new courses/i, 'Site figure with the change compared with the previous period.'],
        [/health|system|bulletin/i, 'Server checks: cron, failing tasks, task queue and failed sign-ins. Green means OK.'],
        [/online|presence|visitors/i, 'People active on the site in the last few minutes.'],
        [/engagement|circulation/i, 'Daily active learners over the chosen period.']
    ];
    var ABOUT_ADMIN = [
        [/courses|ledger|catalogue/i, 'Courses on the site, by category. Manage them in Course management.'],
        [/blocked|suspended/i, 'User accounts that are suspended and cannot sign in.'],
        [/active today/i, 'Distinct users who did something on the site today.'],
        [/enrol/i, 'Active course enrolments across the site, with the change for the period.'],
        [/total users/i, 'All user accounts (not deleted), with the change for the period.'],
        [/new courses/i, 'Courses created in the period.']
    ];
    var panel = null;
    var hideTimer = null;
    var lastCard = null;
    var lastFocus = null;

    var onDash = function() {
        return document.body && document.body.id === 'page-my-index';
    };

    var txt = function(el) {
        if (!el) {
            return '';
        }
        var c = el.cloneNode(true);
        Array.prototype.forEach.call(c.querySelectorAll('a, button, svg, .nt-seg-tabs, [data-nv-saved]'), function(x) {
            x.remove();
        });
        return (c.textContent || '').replace(/\s+/g, ' ').replace(/[→↻]/g, '').trim();
    };

    var titleOf = function(card) {
        for (var i = 0; i < HEADS.length; i++) {
            var hs = card.querySelectorAll(HEADS[i]);
            for (var j = 0; j < hs.length; j++) {
                var t = txt(hs[j]);
                if (t && t.length > 2 && t.length < 90 && /[A-Za-z]/.test(t)) {
                    var my = t.match(/^([A-Z][a-z]+ \d{4})/);
                    if (my) {
                        return my[1];
                    }
                    return t.length > 30 ? t.replace(/\s[+\-]?\d[\d,.%]*\s.*$/, '') : t;
                }
            }
        }
        var f = txt(card);
        var fm = f.match(/^([A-Z][a-z]+ \d{4})/);
        return fm ? fm[1] : (f.slice(0, 60) || 'Details');
    };

    var isCard = function(el) {
        return el && el.matches && el.matches(CARDS) && !el.matches(SKIP) && !el.querySelector(SKIP) &&
            !el.closest('.ntp-panel') && !el.querySelector('textarea, input[type="text"], input[type="search"]');
    };

    var findCard = function(t) {
        var el = t.closest ? t.closest(CARDS) : null;
        // Use the innermost card that qualifies.
        while (el && !isCard(el)) {
            el = el.parentElement ? el.parentElement.closest(CARDS) : null;
        }
        return el;
    };

    var build = function() {
        if (panel) {
            return panel;
        }
        panel = document.createElement('div');
        panel.className = 'ntp-wrap';
        panel.hidden = true;
        panel.innerHTML = '<div class="ntp-backdrop" data-ntp-close></div>' +
            '<aside class="ntp-panel" role="dialog" aria-modal="true" aria-labelledby="ntp-title" tabindex="-1">' +
            '<header class="ntp-head"><div class="ntp-ht"><small class="ntp-kicker">Details</small><h2 id="ntp-title"></h2></div>' +
            '<button type="button" class="ntp-x" data-ntp-close aria-label="Close">&times;</button></header>' +
            '<div class="ntp-scroll"><p class="ntp-about" hidden></p><div class="ntp-body"></div>' +
            '<div class="ntp-info" hidden><h3>More info</h3><ul></ul></div></div>' +
            '<footer class="ntp-foot"><a class="ntp-go" hidden></a><button type="button" class="ntp-close" data-ntp-close>Close</button></footer>' +
            '</aside>';
        panel.addEventListener('click', function(e) {
            if (e.target.closest('[data-ntp-close]')) {
                close();
            }
        });
        document.addEventListener('keydown', function(e) {
            if (panel.hidden) {
                return;
            }
            if (e.key === 'Escape') {
                close();
            } else if (e.key === 'Tab') {
                var f = panel.querySelectorAll('a[href]:not([hidden]), button:not([disabled])');
                if (!f.length) {
                    return;
                }
                var first = f[0];
                var last = f[f.length - 1];
                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        });
        return panel;
    };

    var clean = function(clone) {
        clone.removeAttribute('id');
        clone.classList.add('ntp-clone');
        Array.prototype.forEach.call(clone.querySelectorAll('[id]'), function(x) {
            x.removeAttribute('id');
        });
        Array.prototype.forEach.call(clone.querySelectorAll('.ntp-exp'), function(x) {
            x.remove();
        });
        // Show every rotating / hidden item (tickers, tips, quotes, extra rows).
        Array.prototype.forEach.call(clone.querySelectorAll('[data-nv-item][hidden]'), function(x) {
            x.hidden = false;
        });
        Array.prototype.forEach.call(clone.querySelectorAll('.nv-tickitem, [data-nv-item]'), function(x) {
            x.classList.add('ntp-show');
        });
        // Tabs in the copy (chart ranges): make them work here too.
        var tabs = clone.querySelectorAll('[data-nt-tab]');
        Array.prototype.forEach.call(tabs, function(b) {
            b.addEventListener('click', function(e) {
                e.preventDefault();
                var k = b.getAttribute('data-nt-tab');
                Array.prototype.forEach.call(tabs, function(o) {
                    o.classList.toggle('on', o === b);
                    o.setAttribute('aria-selected', o === b ? 'true' : 'false');
                });
                Array.prototype.forEach.call(clone.querySelectorAll('[data-nt-pane]'), function(p) {
                    p.hidden = p.getAttribute('data-nt-pane') !== k;
                });
            });
        });
        // Other buttons in the copy have no handlers - make them inert.
        Array.prototype.forEach.call(clone.querySelectorAll('button:not([data-nt-tab]), [data-nv-next]'), function(b) {
            b.setAttribute('tabindex', '-1');
            b.setAttribute('aria-hidden', 'true');
            b.classList.add('ntp-inert');
        });
        return clone;
    };

    var infoOf = function(card) {
        var seen = {};
        var out = [];
        Array.prototype.forEach.call(card.querySelectorAll('[title]'), function(el) {
            if (el.closest('.nv-heat, .nv-heat-key, .ntp-exp')) {
                return;
            }
            var t = (el.getAttribute('title') || '').trim();
            // Plain day names from mini calendars add nothing - skip them.
            if (/^[A-Z][a-z]+day,? \d{1,2} [A-Z][a-z]+,? \d{4}$/.test(t) || /^show details$/i.test(t) ||
                    (el.textContent || '').replace(/\s+/g, ' ').trim().indexOf(t) === 0) {
                return;
            }
            if (t && !seen[t] && t.length > 2 && out.length < 12) {
                seen[t] = 1;
                out.push(t);
            }
        });
        return out;
    };

    var open = function(card) {
        build();
        var root = card.closest('#sd-root, .nt-root, .nv-root') || document.body;
        if (panel.parentNode !== root) {
            root.appendChild(panel);
        }
        lastFocus = document.activeElement;
        var title = titleOf(card);
        title = title.charAt(0).toUpperCase() + title.slice(1);
        panel.querySelector('#ntp-title').textContent = title;
        var body = panel.querySelector('.ntp-body');
        body.textContent = '';
        var helptxt = card.getAttribute('data-nv-help');
        if (helptxt) {
            var tl = document.createElement('ul');
            tl.className = 'ntp-tips';
            (card.getAttribute('data-nv-tips') || '').split('|').forEach(function(t) {
                if (t.trim()) {
                    var li = document.createElement('li');
                    li.textContent = t.trim();
                    tl.appendChild(li);
                }
            });
            body.appendChild(tl);
        } else {
            body.appendChild(clean(card.cloneNode(true)));
        }
        panel.querySelector('.ntp-scroll').scrollTop = 0;
        var info = helptxt ? [] : infoOf(card);
        var ib = panel.querySelector('.ntp-info');
        var ul = ib.querySelector('ul');
        ul.textContent = '';
        info.forEach(function(t) {
            var li = document.createElement('li');
            li.textContent = t;
            ul.appendChild(li);
        });
        ib.hidden = !info.length;
        var about = panel.querySelector('.ntp-about');
        about.hidden = true;
        if (helptxt) {
            about.textContent = helptxt;
            about.hidden = false;
        }
        var isadm = !!document.querySelector('.sd-admin, .nt-admin, .nv-adm, .nv-ops, .nv-gazette');
        var list = isadm ? ABOUT_ADMIN.concat(ABOUT) : ABOUT;
        for (var a = 0; a < list.length; a++) {
            if (list[a][0].test(title)) {
                about.textContent = list[a][1];
                about.hidden = false;
                break;
            }
        }
        var go = panel.querySelector('.ntp-go');
        var link = helptxt ? null : card.querySelector(LINKS);
        if (!link && !helptxt) {
            var all = card.querySelectorAll('a[href]');
            link = all.length === 1 ? all[0] : card.querySelector('a[href*="/course/view.php"]');
        }
        if (link && link.getAttribute('href') && link.getAttribute('href') !== '#') {
            go.href = link.href;
            if (link.target) {
                go.target = link.target;
                go.rel = 'noopener';
            } else {
                go.removeAttribute('target');
            }
            var lt = (link.textContent || '').replace(/[→↗]/g, '').replace(/\s+/g, ' ').trim();
            go.textContent = (lt && lt.length < 26 ? lt : (/course\/view/.test(link.href) ? 'Open course' : 'Open')) + ' →';
            go.hidden = false;
        } else if (helptxt) {
            go.hidden = true;
        } else {
            go.hidden = true;
            var adm = !!document.querySelector('.sd-admin, .nt-admin, .nv-adm, .nv-ops, .nv-gazette');
            for (var g = 0; g < GUESS.length && WWW; g++) {
                if (adm && /course|catalogue|ledger/i.test(title)) {
                    go.href = WWW + '/course/management.php';
                    go.removeAttribute('target');
                    go.textContent = 'Manage courses →';
                    go.hidden = false;
                    break;
                }
                if (!adm && /online|presence|visitors|user|enrol|health|system|bulletin|ops|log|minutes/i.test(GUESS[g][1] + GUESS[g][2]) &&
                        /admin|report/.test(GUESS[g][1])) {
                    continue;
                }
                if (GUESS[g][0].test(title)) {
                    go.href = WWW + GUESS[g][1];
                    go.removeAttribute('target');
                    go.textContent = GUESS[g][2] + ' →';
                    go.hidden = false;
                    break;
                }
            }
        }
        window.clearTimeout(hideTimer);
        if (lastCard && lastCard !== card) {
            lastCard.classList.remove('ntp-active');
        }
        lastCard = card;
        panel.hidden = false;
        document.documentElement.classList.add('ntp-lock');
        card.classList.add('ntp-active');
        void panel.offsetWidth; // Start the slide-in from the closed position.
        panel.classList.add('in');
        panel.querySelector('.ntp-panel').focus({preventScroll: true});
    };

    var close = function() {
        if (!panel || panel.hidden) {
            return;
        }
        panel.classList.remove('in');
        document.documentElement.classList.remove('ntp-lock');
        if (lastCard) {
            lastCard.classList.remove('ntp-active');
        }
        window.clearTimeout(hideTimer);
        hideTimer = window.setTimeout(function() {
            if (!panel.classList.contains('in')) {
                panel.hidden = true;
            }
        }, 220);
        if (lastFocus && lastFocus.focus) {
            lastFocus.focus();
        }
    };

    var decorate = function() {
        Array.prototype.forEach.call(document.querySelectorAll(CARDS), function(card) {
            if (!isCard(card) || card.dataset.ntpReady) {
                return;
            }
            // Only the outer-most qualifying card gets a button.
            var outer = card.parentElement ? card.parentElement.closest(CARDS) : null;
            if (outer && isCard(outer)) {
                return;
            }
            card.dataset.ntpReady = '1';
            card.classList.add('ntp-card');
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'ntp-exp';
            b.setAttribute('aria-label', 'Show details: ' + titleOf(card));
            b.title = 'Show details';
            b.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 4h6v6M20 4l-7 7M10 20H4v-6M4 20l7-7"/></svg>';
            b.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                open(card);
            });
            card.appendChild(b);
        });
    };

    var decorateHelp = function() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-nv-help]'), function(card) {
            if (card.dataset.ntpReady) {
                return;
            }
            card.dataset.ntpReady = '1';
            card.classList.add('ntp-card');
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'ntp-exp';
            b.setAttribute('aria-label', 'How it works: ' + titleOf(card));
            b.title = 'How it works';
            b.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>';
            b.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                open(card);
            });
            card.appendChild(b);
        });
    };

    var init = function() {
        if (!onDash()) {
            return;
        }
        decorate();
        decorateHelp();
        document.addEventListener('click', function(e) {
            if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey) {
                return;
            }
            var t = e.target;
            if (!t || !t.closest || t.closest(INTERACTIVE) || t.closest('.ntp-wrap')) {
                return;
            }
            var sel = window.getSelection ? String(window.getSelection()) : '';
            if (sel.length > 2) {
                return;
            }
            var card = t.closest('.ntp-card');
            if (card) {
                open(card);
            }
        });
        // Cards added later (blocks loading by AJAX) get the button too.
        window.setTimeout(decorate, 1500);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
