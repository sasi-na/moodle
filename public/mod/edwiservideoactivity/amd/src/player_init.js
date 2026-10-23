/* eslint-disable */
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
 * Edwiser Video Activity - Plyr Player Initialization
 *
 * @copyright  2025 Edwiser
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {
    return {
        init: function(selector) {
            require(['mod_edwiservideoactivity/plyr'], function(Plyr) {
                $(document).ready(function() {
                    const player = new Plyr(selector, {
                        addCSS: false,  // Disable CSS injection
                        tooltips: {
                            controls: true,  // Show tooltips on control buttons
                            seek: true       // Show tooltip on progress bar when seeking
                        },
                        disableContextMenu: true,  // Disable right-click context menu
                        clickToPlay: true,
                        controls: [
                            'play-large', 'play', 'progress', 'current-time', 'mute', 'volume', 'captions', 'settings', 'pip', 'airplay', 'fullscreen'
                        ],
                    });
                    console.log('Plyr initialized:', player);
                    console.log(selector);
                });
            });
        }
    };
});
