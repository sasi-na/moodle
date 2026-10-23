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
 * Injects the plugin's primary-navigation link (and, for verifiers, hides the
 * default Dashboard / My Courses items). Replaces inline script for CSP safety.
 *
 * @module     local_studentid/navinject
 * @copyright  2026 JAIN Online
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    /**
     * Hide primary-nav items addressed by their data-key.
     *
     * @param {String[]} keys The data-key values to hide.
     */
    var hideNodes = function(keys) {
        keys.forEach(function(key) {
            var nodes = document.querySelectorAll('[data-key="' + key + '"]');
            nodes.forEach(function(el) {
                el.style.display = 'none';
            });
        });
    };

    /**
     * Build the nav list item containing the plugin link.
     *
     * @param {String} key The data-key to assign to the item.
     * @param {String} href The link target.
     * @param {String} label The link text.
     * @return {HTMLElement} The constructed list item.
     */
    var buildItem = function(key, href, label) {
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
     * Insert the link into the navbar for the given role.
     *
     * @param {String} role Either 'verifier' or 'student'.
     * @param {String} href The link target.
     * @param {String} label The link text.
     */
    var inject = function(role, href, label) {
        var nav = document.querySelector('.primary-navigation ul, .navbar-nav');
        if (!nav) {
            return;
        }
        if (role === 'verifier') {
            hideNodes(['myhome', 'mycourses', 'home']);
            nav.prepend(buildItem('sid-verifier', href, label));
            return;
        }
        var item = buildItem('sid-student', href, label);
        var mycourses = document.querySelector('[data-key="mycourses"]');
        if (mycourses) {
            mycourses.after(item);
        } else {
            nav.appendChild(item);
        }
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
                inject(role, href, label);
            } else {
                document.addEventListener('DOMContentLoaded', function() {
                    inject(role, href, label);
                });
            }
        }
    };
});
