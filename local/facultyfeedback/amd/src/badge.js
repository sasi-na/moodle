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
 * Weekly-feedback figure in the course header.
 *
 * Places the field in the course header's meta strip and, on click, opens a
 * pop-up with that course's overall average, week-by-week trend and latest
 * answer distribution.
 *
 * The server has already decided this user may see the figures — the module is
 * only queued when the capability check passed — but the web service re-checks
 * independently, so nothing here is a security boundary.
 *
 * @module     local_facultyfeedback/badge
 * @copyright  2026 JAIN Online
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Modal from 'core/modal';
import Notification from 'core/notification';
import {getString} from 'core/str';

const SELECTORS = {
    // Where the field goes, best first. `.rui-course-info-box` is the course
    // header's row of labelled facts (Start date, End date, course custom
    // fields) built by theme_baz's core_renderer::full_header(); the field is
    // styled to match its siblings, so that is where it belongs. The rest are
    // fallbacks for a future theme that lacks it.
    ANCHORS: [
        '.rui-course-info-box',
        '#page-header .header-actions-container',
        '#page-header',
    ],
    OPEN: '[data-action="local-facultyfeedback-open"]',
    REGION: '[data-region="local-facultyfeedback"]',
};

const WS_SUMMARY = 'local_facultyfeedback_get_course_summary';

/**
 * First anchor present on the page.
 *
 * @returns {Element|null}
 */
const findAnchor = () => {
    for (const selector of SELECTORS.ANCHORS) {
        const node = document.querySelector(selector);
        if (node) {
            return node;
        }
    }
    return null;
};

/**
 * Open the pop-up for a course.
 *
 * @param {Number} courseid
 * @param {Element} trigger The button that was clicked, for focus return.
 * @returns {Promise}
 */
const openModal = async(courseid, trigger) => {
    trigger.disabled = true;
    try {
        const [summary, closeLabel] = await Promise.all([
            Ajax.call([{methodname: WS_SUMMARY, args: {courseid: courseid}}])[0],
            getString('closebuttontitle', 'core'),
        ]);

        // The base modal with an explicit Close, rather than core/modal_cancel:
        // this dialog only reports numbers, so "Cancel" implies there is
        // something to abandon. data-action="hide" is wired by core/modal and
        // honours removeOnClose.
        const modal = await Modal.create({
            title: summary.title,
            body: summary.html,
            footer: `<button type="button" class="btn btn-primary" data-action="hide">${closeLabel}</button>`,
            removeOnClose: true,
            returnElement: trigger,
        });
        await modal.show();
    } catch (error) {
        Notification.exception(error);
    } finally {
        trigger.disabled = false;
    }
};

/**
 * Render the field and wire it up.
 *
 * @param {Object} context Template context built by manager::badge_context().
 */
export const init = async(context) => {
    const anchor = findAnchor();
    if (!anchor || document.querySelector(SELECTORS.REGION)) {
        // No anchor on this page, or the field is already there (a second
        // init would duplicate it).
        return;
    }

    try {
        const {html, js} = await Templates.renderForPromise('local_facultyfeedback/badge', context);
        // Append inside the anchor: in the meta strip that puts the field
        // alongside Start date, and in each fallback it lands at the end of the
        // course header, which is the same place visually.
        Templates.appendNodeContents(anchor, html, js);
    } catch (error) {
        // A missing field is not worth breaking a course page over.
        Notification.exception(error);
        return;
    }

    const region = document.querySelector(SELECTORS.REGION);
    if (!region) {
        return;
    }

    const button = region.querySelector(SELECTORS.OPEN);
    if (!button) {
        // Below the response threshold there is nothing to open.
        return;
    }

    // Spell the action out for screen readers; the visible label is terse.
    button.setAttribute('aria-label', await getString('viewfeedback', 'local_facultyfeedback'));

    button.addEventListener('click', (event) => {
        event.preventDefault();
        openModal(parseInt(button.dataset.courseid, 10), button);
    });
};
