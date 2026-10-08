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
 * Dashboard poll popup.
 *
 * Fetches the active poll for the current user; if unanswered, shows a blocking
 * overlay with the question. The student may close it without answering (it
 * returns on the next dashboard load), or submit, which swaps the card to the
 * animated results view.
 *
 * @module     local_dashboardpoll/popup
 * @copyright  2026 JAIN Online
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Ajax from 'core/ajax';
import Templates from 'core/templates';
import {getString} from 'core/str';

const GET_ACTIVE = 'local_dashboardpoll_get_active';
const SUBMIT = 'local_dashboardpoll_submit';

let overlay = null;

/**
 * Single web-service call returning the first response promise.
 *
 * @param {String} methodname The external function name.
 * @param {Object} args Arguments for the call.
 * @returns {Promise} The first (only) call's promise.
 */
const call = (methodname, args) => Ajax.call([{methodname, args}])[0];

/** Create and mount the blocking overlay element. */
const mountOverlay = () => {
    overlay = document.createElement('div');
    overlay.className = 'ldp-overlay';
    overlay.setAttribute('data-region', 'local-dashboardpoll');
    document.body.appendChild(overlay);
    document.body.classList.add('ldp-noscroll');
    document.addEventListener('keydown', onKeydown);
};

/** Remove the overlay (used by both "Maybe later" and "Close"). */
const dismiss = () => {
    if (!overlay) {
        return;
    }
    const el = overlay;
    overlay = null;
    el.classList.remove('ldp-in');
    el.classList.add('ldp-leaving');
    document.body.classList.remove('ldp-noscroll');
    document.removeEventListener('keydown', onKeydown);
    setTimeout(() => el.remove(), 240);
};

const onKeydown = (e) => {
    if (e.key === 'Escape') {
        dismiss();
    }
};

/**
 * Swap content into the card and play the entrance transition.
 *
 * @param {String} templateName The Mustache template to render.
 * @param {Object} context The template context.
 * @returns {Promise} Resolves with the overlay once painted.
 */
const paint = (templateName, context) =>
    Templates.render(templateName, context).then((html) => {
        overlay.innerHTML = html;
        // The corner X dismisses from any view.
        const closeBtn = overlay.querySelector('.ldp-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', dismiss);
        }
        // Next frame so the initial (hidden) state is committed before transitioning in.
        requestAnimationFrame(() => overlay.classList.add('ldp-in'));
        const card = overlay.querySelector('.ldp-card');
        if (card) {
            card.focus();
        }
        return overlay;
    });

/**
 * Render the voting state and wire up its controls.
 *
 * @param {Object} data The get_active payload.
 * @returns {Promise} Resolves once the poll is shown and wired.
 */
/**
 * Resolve the helper line shown under the question.
 *
 * @param {Object} data The poll payload.
 * @returns {Promise} Resolves with the hint string ('' for single-choice).
 */
const hintFor = (data) => {
    if (!data.multiselect) {
        return Promise.resolve('');
    }
    return data.maxselect > 0
        ? getString('selectexactly', 'local_dashboardpoll', data.maxselect)
        : getString('selectmultiple', 'local_dashboardpoll');
};

/**
 * Mount the overlay and render the voting state.
 *
 * @param {Object} data The poll payload.
 * @returns {Promise} Resolves once the poll is shown and wired.
 */
const showPoll = (data) => {
    mountOverlay();
    return renderPoll(data);
};

/**
 * Render the voting state into the (already mounted) overlay, pre-selecting any
 * picks the user has saved, and wire up its controls.
 *
 * @param {Object} data The poll payload.
 * @returns {Promise} Resolves once the poll is shown and wired.
 */
const renderPoll = (data) =>
    hintFor(data).then((hint) => {
        const context = {
            question: data.question,
            multiselect: data.multiselect,
            inputtype: data.multiselect ? 'checkbox' : 'radio',
            hint,
            options: data.options.map((o) => ({id: o.id, text: o.text, checked: o.selected})),
        };
        return paint('local_dashboardpoll/poll', context).then(() => wirePoll(data));
    });

/**
 * Enforce the selection cap on a multi-select poll: once `max` boxes are
 * ticked, the remaining unticked ones are disabled until one is freed.
 *
 * @param {Number} max The maximum number of selections (0 = unlimited).
 */
const enforceCap = (max) => {
    if (!max) {
        return;
    }
    const inputs = Array.from(overlay.querySelectorAll('.ldp-radio'));
    const atCap = inputs.filter((i) => i.checked).length >= max;
    inputs.forEach((input) => {
        const disable = atCap && !input.checked;
        input.disabled = disable;
        input.closest('.ldp-option').classList.toggle('is-disabled', disable);
    });
};

const wirePoll = (data) => {
    const submitBtn = overlay.querySelector('[data-action="submit"]');
    const laterBtn = overlay.querySelector('[data-action="later"]');
    const error = overlay.querySelector('[data-region="error"]');
    const cap = data.multiselect ? (data.maxselect || 0) : 0;

    // Reflect any pre-selected picks in the cap and submit button straight away.
    enforceCap(cap);
    submitBtn.disabled = overlay.querySelector('.ldp-radio:checked') === null;

    overlay.querySelectorAll('.ldp-radio').forEach((input) => {
        input.addEventListener('change', () => {
            enforceCap(cap);
            submitBtn.disabled = overlay.querySelector('.ldp-radio:checked') === null;
            error.hidden = true;
        });
    });

    laterBtn.addEventListener('click', dismiss);

    submitBtn.addEventListener('click', () => {
        const optionids = Array.from(overlay.querySelectorAll('.ldp-radio:checked'))
            .map((el) => parseInt(el.value, 10));
        if (!optionids.length) {
            error.hidden = false;
            return;
        }
        submitBtn.disabled = true;
        submitBtn.classList.add('is-loading');
        call(SUBMIT, {pollid: data.pollid, optionids})
            .then((resp) => {
                // Enough picks saved -> reveal results. Otherwise keep the popup
                // open (picks now pre-selected) so the student can finish.
                if (resp.answered) {
                    return showResults(resp, optionids);
                }
                return renderPoll(resp);
            })
            .catch(() => {
                error.hidden = false;
                error.textContent = error.dataset.failed || error.textContent;
                submitBtn.disabled = false;
                submitBtn.classList.remove('is-loading');
            });
    });
};

/**
 * Render the done state, marking the user's pick(s). When the poll hides results
 * (`showresults` false) a plain thank-you card is shown instead of the tallies;
 * otherwise the results card animates its bars.
 *
 * @param {Object} data The submit payload (aggregate results).
 * @param {Number[]} chosenIds The option ids the user selected.
 * @returns {Promise} Resolves once the done state is shown.
 */
const showResults = (data, chosenIds) => {
    if (!data.showresults) {
        return paint('local_dashboardpoll/thanks', {question: data.question}).then(() => {
            overlay.querySelector('[data-action="close"]').addEventListener('click', dismiss);
        });
    }

    const mine = new Set(chosenIds);
    const context = {
        question: data.question,
        totalvotes: data.totalvotes,
        showpercent: data.showpercent,
        showvotecounts: data.showvotecounts,
        options: data.options.map((o) => ({
            id: o.id,
            text: o.text,
            percent: o.percent,
            votes: o.votes,
            mine: mine.has(o.id),
        })),
    };
    return paint('local_dashboardpoll/results', context).then(() => {
        overlay.querySelector('[data-action="close"]').addEventListener('click', dismiss);
        // Animate each bar from 0 to its share on the next frame.
        requestAnimationFrame(() => {
            overlay.querySelectorAll('.ldp-bar-fill').forEach((fill) => {
                fill.style.width = (parseInt(fill.dataset.pct, 10) || 0) + '%';
            });
        });
    });
};

export const init = () => {
    call(GET_ACTIVE, {})
        .then((data) => {
            if (data && data.hasactive && !data.answered) {
                showPoll(data);
            }
            return data;
        })
        .catch(() => {
            // Silent: a poll failure must never disrupt the dashboard.
        });
};
