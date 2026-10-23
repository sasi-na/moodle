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
 * Styled confirmation dialog for any element carrying a data-sid-confirm
 * attribute, replacing native window.confirm() popups.
 *
 * @module     local_studentid/confirm
 * @copyright  2026 JAIN Online
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/notification', 'core/str'], function($, Notification, Str) {

    /**
     * Proceed with the original action once the user confirms.
     *
     * @param {HTMLElement} el The element that triggered the confirmation.
     */
    var proceed = function(el) {
        if (el.tagName === 'A') {
            window.location.href = el.getAttribute('href');
            return;
        }
        // Submit buttons: mark as confirmed and submit the owning form.
        el.setAttribute('data-sid-confirmed', '1');
        var form = el.closest('form');
        if (form) {
            form.submit();
        } else {
            el.click();
        }
    };

    /**
     * Show the confirmation modal for the triggering element.
     *
     * @param {HTMLElement} el The element that triggered the confirmation.
     */
    var ask = function(el) {
        var message = el.getAttribute('data-sid-confirm');
        Str.get_strings([
            {key: 'confirm', component: 'moodle'},
            {key: 'yes', component: 'moodle'},
            {key: 'no', component: 'moodle'}
        ]).then(function(s) {
            Notification.confirm(s[0], message, s[1], s[2], function() {
                proceed(el);
            });
            return;
        }).catch(Notification.exception);
    };

    return {
        /**
         * Bind the delegated click handler.
         */
        init: function() {
            $(document).on('click', '[data-sid-confirm]', function(e) {
                // Allow the second (confirmed) click through untouched.
                if (this.getAttribute('data-sid-confirmed') === '1') {
                    return;
                }
                e.preventDefault();
                ask(this);
            });
        }
    };
});
