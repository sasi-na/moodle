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
 * Injects the plugin's navigation link for students / verifiers.
 *
 * Works across themes:
 *   - theme_baz (RemUI): items live in <ul class="rui-flatnavigation"> rendered
 *     in both the left nav drawer and the topbar hamburger menu. RemUI builds
 *     this list from its own settings and ignores plugin navigation nodes, so
 *     the link must be injected client-side.
 *   - Boost / standard themes: primary nav <ul> (.primary-navigation / .navbar-nav).
 *
 * Server-side code only loads this module for users who should see the link
 * (eligible students / verifiers), so this module just places it.
 *
 * @module     local_studentid/navinject
 * @copyright  2026 JAIN Online
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    /**
     * Hide primary-nav items addressed by their data-key (Boost themes).
     *
     * @param {String[]} keys The data-key values to hide.
     */
    var hideNodes = function(keys) {
        keys.forEach(function(key) {
            document.querySelectorAll('[data-key="' + key + '"]').forEach(function(el) {
                el.style.display = 'none';
            });
        });
    };

    /**
     * Hide RemUI (theme_baz) flat-navigation items by their anchor id, hiding
     * the wrapping list item. The flat nav is rendered in both the drawer and
     * the hamburger menu, so each id may appear more than once.
     *
     * @param {String[]} ids The anchor ids to hide (e.g. 'itemDashboard').
     */
    var hideRuiItems = function(ids) {
        ids.forEach(function(id) {
            document.querySelectorAll('#' + id).forEach(function(el) {
                var li = el.closest ? el.closest('li') : null;
                (li || el).style.display = 'none';
            });
        });
    };

    /**
     * Build a Boost-style nav list item.
     *
     * @param {String} key The data-key to assign to the item.
     * @param {String} href The link target.
     * @param {String} label The link text.
     * @return {HTMLElement} The constructed list item.
     */
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

    /**
     * Build a RemUI (theme_baz) flat-navigation list item, matching the markup
     * the theme renders for its own menu entries so the link looks native.
     *
     * @param {String} key The data-key to assign to the item.
     * @param {String} href The link target.
     * @param {String} label The link text.
     * @return {HTMLElement} The constructed list item.
     */
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

        var icon = document.createElement('span');
        icon.className = 'rui-nav-icon';
        icon.innerHTML = '<svg width="20" height="20" fill="none" viewBox="0 0 24 24">'
            + '<rect x="4.75" y="6.75" width="14.5" height="10.5" rx="2" stroke="currentColor" stroke-width="1.5"></rect>'
            + '<circle cx="9" cy="11" r="1.4" stroke="currentColor" stroke-width="1.5"></circle>'
            + '<path d="M12.5 10.5H16.5M12.5 13H16.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>'
            + '</svg>';

        var text = document.createElement('span');
        text.className = 'text';
        text.textContent = label;

        container.appendChild(icon);
        container.appendChild(text);
        a.appendChild(container);
        li.appendChild(a);
        return li;
    };

    /**
     * Inject into RemUI navigation lists. Depending on the theme's
     * "topbarhamburgermenu" setting the main menu renders either into
     * ul.rui-flatnavigation (hamburger on: nav drawer + hamburger menu) or into
     * ul.header-main-menu (hamburger off: the secondary navbar). Cover both.
     *
     * @param {String} key Unique data-key for the injected item.
     * @param {String} href The link target.
     * @param {String} label The link text.
     * @return {Boolean} True if at least one RemUI list was found.
     */
    var injectRui = function(key, href, label) {
        var lists = document.querySelectorAll('ul.rui-flatnavigation, ul.header-main-menu');
        if (!lists.length) {
            return false;
        }
        lists.forEach(function(ul) {
            if (ul.querySelector('[data-key="' + key + '"]')) {
                return;
            }
            ul.appendChild(buildRuiItem(key, href, label));
        });
        return true;
    };

    /**
     * Inject into a Boost-style primary navigation bar.
     *
     * @param {String} role Either 'verifier' or 'student'.
     * @param {String} key Unique data-key for the injected item.
     * @param {String} href The link target.
     * @param {String} label The link text.
     * @return {Boolean} True if a navigation bar was found.
     */
    var injectBoost = function(role, key, href, label) {
        var nav = document.querySelector('.primary-navigation ul, .navbar-nav');
        if (!nav) {
            return false;
        }
        if (nav.querySelector('[data-key="' + key + '"]')) {
            return true;
        }
        var item = buildBoostItem(key, href, label);
        if (role === 'verifier') {
            nav.prepend(item);
            return true;
        }
        var mycourses = document.querySelector('[data-key="mycourses"]');
        if (mycourses) {
            mycourses.after(item);
        } else {
            nav.appendChild(item);
        }
        return true;
    };

    /**
     * Place the link into whichever navigation the active theme provides.
     * Idempotent — safe to call repeatedly (items are de-duplicated by data-key).
     *
     * @param {String} role Either 'verifier' or 'student'.
     * @param {String} href The link target.
     * @param {String} label The link text.
     */
    var inject = function(role, href, label) {
        var key = (role === 'verifier') ? 'sid-verifier' : 'sid-student';
        if (role === 'verifier') {
            // Boost themes address nav items by data-key; RemUI (theme_baz) by id.
            hideNodes(['myhome', 'mycourses', 'home']);
            hideRuiItems(['itemDashboard', 'itemMyCourses', 'itemHome']);
        }
        // RemUI (theme_baz) first; fall back to Boost-style primary nav.
        if (!injectRui(key, href, label)) {
            injectBoost(role, key, href, label);
        }
    };

    /**
     * Run the injection, retrying a few times in case the theme renders its
     * navigation lists slightly after DOMContentLoaded.
     *
     * @param {String} role Either 'verifier' or 'student'.
     * @param {String} href The link target.
     * @param {String} label The link text.
     */
    var run = function(role, href, label) {
        inject(role, href, label);
        [300, 1000, 2500].forEach(function(delay) {
            window.setTimeout(function() {
                inject(role, href, label);
            }, delay);
        });
    };

    return {
        /**
         * Entry point.
         *
         * @param {String} role Either 'verifier' or 'student'.
         * @param {String} href The link target.
         * @param {String} label The link text.
         */
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
