/**
 * @module block_zoomonline/live_join
 *
 * @copyright  2024 Ciaran Mac Donncha
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * This file is part of Moodle - http://moodle.org/
 *
 * Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Moodle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
 */

define(['jquery', 'core/ajax', 'core/notification'], function ($, Ajax, Notification) {
    return {
        init: function () {
            // Delay execution to allow for DOM readiness.
            setTimeout(function () {
                $('.live_join_class_eye').on('click', function () {
                    const meetingid = $(this).data('id');
                    const visibility = $(this).data('visible');

                    // Use Moodle's core AJAX framework to call the external service.
                    const request = Ajax.call([{
                        methodname: 'block_zoomonline_update_visibility',
                        args: {
                            meetingid: meetingid,
                            visible: visibility
                        }
                    }]);

                    request[0].done(function () {
                        // Reload the page after a successful response.
                        window.location.reload();
                    }).fail(function (error) {
                        Notification.alert(
                            M.str.block_zoomonline.error,
                            M.str.block_zoomonline.failed_update_visibility + error.message
                        );
                    });
                });
            }, 4000);
        }
    };
});
