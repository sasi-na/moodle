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
 * Drag-to-reorder for the dashboard banner slides on the theme settings page.
 *
 * Wraps the slide cards in core/sortable_list and, after each drop, writes the
 * new slot order (a comma-separated list of slot indices) into the hidden input
 * so it is saved with the rest of the settings form. No web service — the value
 * persists on the page's normal "Save changes" submit.
 *
 * @module     theme_baz/bannerorder
 * @copyright  2026 JAIN Online
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import $ from 'jquery';
import SortableList from 'core/sortable_list';

/**
 * Read the current card order from the DOM and write it to the hidden input.
 *
 * @param {HTMLElement} list The <ul> whose children are the slide cards.
 * @param {HTMLInputElement} input The hidden input receiving the order list.
 */
const sync = (list, input) => {
    const seen = new Set();
    const slots = Array.from(list.querySelectorAll(':scope > .baz-bannerorder__card'))
        // Skip the transient dragged clone core/sortable_list leaves in the list
        // during the drop (and any stray duplicate) so the order stays clean.
        .filter((el) => !el.classList.contains('sortable-list-is-dragged'))
        .map((el) => el.getAttribute('data-slot'))
        .filter((slot) => slot !== null && slot !== '' && !seen.has(slot) && seen.add(slot));
    input.value = slots.join(',');
};

/**
 * Entry point.
 *
 * @param {String} inputId Element id of the hidden order input.
 */
export const init = (inputId) => {
    const input = document.getElementById(inputId);
    if (!input) {
        return;
    }
    const wrapper = input.closest('.baz-bannerorder');
    const list = wrapper && wrapper.querySelector('.baz-bannerorder__list');
    if (!list) {
        return;
    }

    // Enable dragging (and the accessible keyboard "Move" dialogue via the handle).
    new SortableList(list);

    // The sortablelist-drop event bubbles from the dragged card up to the list.
    $(list).on('sortablelist-drop', () => sync(list, input));
};
