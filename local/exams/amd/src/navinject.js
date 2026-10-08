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
 * Injects the exam-portal link into the navigation, theme-independently.
 *
 * Mirrors local_studentid/navinject: theme_baz (RemUI) ignores plugin nav
 * nodes in the top bar, so the link is placed client-side into
 * ul.rui-flatnavigation / ul.header-main-menu, with a Boost fallback. Only
 * loaded by the server when the `shownav` setting is on.
 *
 * @module     local_exams/navinject
 * @copyright  2026 JAIN Online
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    var icon = '<svg width="20" height="20" fill="none" viewBox="0 0 24 24">'
        + '<path d="M7 4.75h7l4 4v10.5a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V5.75a1 1 0 0 1 1-1Z" '
        + 'stroke="currentColor" stroke-width="1.5"></path>'
        + '<path d="M9 12.5l2 2 4-4.5" stroke="currentColor" stroke-width="1.5" '
        + 'stroke-linecap="round" stroke-linejoin="round"></path>'
        + '</svg>';

    var buildRuiItem = function(key, href, label) {
        var li = document.createElement('li');
        li.dataset.key = key;
        var a = document.createElement('a');
        a.className = 'rui-list-group-item list-group-item';
        a.href = href;
        a.title = label;
        a.id = key;
        var container = document.createElement('div');
        container.className = 'rui-nav-item-container';
        var ic = document.createElement('span');
        ic.className = 'rui-nav-icon';
        ic.innerHTML = icon;
        var text = document.createElement('span');
        text.className = 'text';
        text.textContent = label;
        container.appendChild(ic);
        container.appendChild(text);
        a.appendChild(container);
        li.appendChild(a);
        return li;
    };

    var buildBoostItem = function(key, href, label) {
        var li = document.createElement('li');
        li.className = 'nav-item';
        li.dataset.key = key;
        var a = document.createElement('a');
        a.className = 'nav-link';
        a.href = href;
        a.textContent = label;
        li.appendChild(a);
        return li;
    };

    var injectRui = function(key, href, label) {
        var lists = document.querySelectorAll('ul.rui-flatnavigation, ul.header-main-menu');
        if (!lists.length) {
            return false;
        }
        lists.forEach(function(ul) {
            if (!ul.querySelector('[data-key="' + key + '"]')) {
                ul.appendChild(buildRuiItem(key, href, label));
            }
        });
        return true;
    };

    var injectBoost = function(key, href, label) {
        var nav = document.querySelector('.primary-navigation ul, .navbar-nav');
        if (!nav) {
            return false;
        }
        if (nav.querySelector('[data-key="' + key + '"]')) {
            return true;
        }
        var item = buildBoostItem(key, href, label);
        var mycourses = document.querySelector('[data-key="mycourses"]');
        if (mycourses) {
            mycourses.after(item);
        } else {
            nav.appendChild(item);
        }
        return true;
    };

    var inject = function(role, href, label) {
        var key = (role === 'faculty') ? 'exams-faculty' : 'exams-student';
        if (!injectRui(key, href, label)) {
            injectBoost(key, href, label);
        }
    };

    var run = function(role, href, label) {
        inject(role, href, label);
        [300, 1000, 2500].forEach(function(delay) {
            window.setTimeout(function() {
                inject(role, href, label);
            }, delay);
        });
    };

    return {
        init: function(role, href, label) {
            if (document.readyState !== 'loading') {
                run(role, href, label);
            } else {
                document.addEventListener('DOMContentLoaded', function() {
                    run(role, href, label);
                });
            }
        }
    };
});
