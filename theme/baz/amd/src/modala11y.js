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
 * Modal accessibility shim.
 *
 * Core's modal close sequence (core/modal) applies aria-hidden="true" to the
 * modal container (via core/aria) BEFORE it returns focus to the triggering
 * element on the 'hidden' event. During that brief window the still-focused
 * .modal-dialog has an aria-hidden ancestor, which browsers flag:
 *   "Blocked aria-hidden on an element because its descendant retained focus."
 *
 * This shim is purely additive: it does not override or patch core. For each
 * modal that opens, it watches that modal's own aria-hidden attribute and, the
 * instant core sets it to "true" while focus is still inside, it moves focus
 * out. The change runs in a MutationObserver microtask, i.e. before the browser
 * recomputes the accessibility tree, so the warning never fires. Core still
 * restores focus to the trigger on its 'hidden' event immediately afterwards,
 * so the end state is unchanged.
 *
 * @module     theme_baz/modala11y
 * @copyright  2026 JAIN Online
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import ModalEvents from 'core/modal_events';

/**
 * Move focus out of a modal container that has just become aria-hidden.
 *
 * @param {HTMLElement} container The modal container element.
 */
const releaseFocusIfHidden = (container) => {
    if (container.getAttribute('aria-hidden') !== 'true') {
        return;
    }
    const active = document.activeElement;
    if (active && active !== document.body && container.contains(active)) {
        // Blur the focused descendant so it is not trapped under aria-hidden.
        // Core's ModalEvents.hidden handler restores focus to the trigger next.
        active.blur();
    }
};

/**
 * Initialise the shim. Attaches a scoped observer to each modal as it is shown.
 */
export const init = () => {
    // ModalEvents are jQuery custom events triggered on the modal root; they
    // bubble to document, so a single delegated listener covers every modal.
    $(document).on(ModalEvents.shown, (e) => {
        const container = e.target;
        if (!(container instanceof HTMLElement) || typeof MutationObserver !== 'function') {
            return;
        }

        const observer = new MutationObserver(() => releaseFocusIfHidden(container));
        observer.observe(container, {attributes: true, attributeFilter: ['aria-hidden']});

        // The aria-hidden mutation happens during hide() before these events
        // fire, so the observer has already acted by the time we disconnect.
        $(container).one(`${ModalEvents.hidden} ${ModalEvents.destroyed}`, () => observer.disconnect());
    });
};
