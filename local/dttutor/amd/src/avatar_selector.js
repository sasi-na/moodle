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
 * Avatar selector for Tutor-IA admin settings
 *
 * @module     local_dttutor/avatar_selector
 * @copyright  2025 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {

    /**
     * Select an avatar option
     * @param {string} value - Avatar value to select
     */
    var selectAvatar = function(value) {
        document.querySelectorAll(".avatar-option").forEach(el => el.classList.remove("selected"));
        const selected = document.querySelector(`.avatar-option[data-value="${value}"]`);
        if (selected) {
            selected.classList.add("selected");
        }
        document.querySelectorAll("input[name='s_local_dttutor_avatar']").forEach(radio => {
            radio.checked = false;
        });
        const radio = document.querySelector(`input[name="s_local_dttutor_avatar"][value="${value}"]`);
        if (radio) {
            radio.checked = true;
        }
    };

    /**
     * Initialize avatar selector
     */
    var init = function() {
        // Make selectAvatar available globally for onclick attributes
        window.selectDttutorAvatar = selectAvatar;
    };

    return {
        init: init,
        selectAvatar: selectAvatar
    };
});
