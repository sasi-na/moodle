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
 * Adds a "See all messages" footer link to the core message drawer on the
 * bespoke student-experience pages, mirroring the notification popover's
 * "See all" → full page. The drawer is rendered asynchronously by core, so we
 * watch it and inject the link once its overview view appears. Cosmetic add-on;
 * the drawer's own behaviour is untouched.
 *
 * @module     theme_baz/studentmsg
 * @copyright  2026 onlinejain.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    "use strict";

    /**
     * Inject the link into the drawer's overview view if not already present.
     *
     * @param {string} url Full messages page URL.
     * @param {string} label Link text.
     * @return {void}
     */
    function inject(url, label) {
        var drawer = document.querySelector('[data-region="message-drawer"]');
        if (!drawer) {
            return;
        }
        var overview = drawer.querySelector('[data-region="view-overview"]');
        if (!overview || overview.querySelector('.sw-msg-seeall')) {
            return;
        }
        var body = overview.querySelector('[data-region="view-overview-body"]') || overview;
        var link = document.createElement('a');
        link.className = 'sw-msg-seeall';
        link.href = url;
        link.textContent = label;
        body.appendChild(link);
    }

    return {
        /**
         * Entry point.
         *
         * @param {string} url Full messages page URL.
         * @param {string} label Link text ("See all messages").
         * @return {void}
         */
        init: function(url, label) {
            var drawer = document.querySelector('[data-region="message-drawer"]');
            inject(url, label);
            if (!drawer || typeof MutationObserver === 'undefined') {
                return;
            }
            var obs = new MutationObserver(function() {
                inject(url, label);
            });
            obs.observe(drawer, {childList: true, subtree: true});
        }
    };
});
