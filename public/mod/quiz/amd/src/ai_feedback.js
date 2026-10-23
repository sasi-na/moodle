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
 * AI Feedback module for quiz review page.
 *
 * @module     mod_quiz/ai_feedback
 * @copyright  2024 Miles Education
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

/**
 * Initialize the AI feedback functionality.
 *
 * @param {Object} config Configuration object with contextid, attemptid, and prompt.
 */
export const init = (config) => {
    const container = document.getElementById(`quiz-ai-feedback-${config.attemptid}`);
    if (!container) {
        return;
    }

    const promptTextarea = container.querySelector('.quiz-ai-feedback-prompt-data');
    if (!config.prompt && promptTextarea) {
        config.prompt = promptTextarea.value;
    }

    const loading = container.querySelector('.quiz-ai-feedback-loading');
    const result = container.querySelector('.quiz-ai-feedback-result');
    const resultText = container.querySelector('.quiz-ai-feedback-text');
    const error = container.querySelector('.quiz-ai-feedback-error');
    const errorMessage = container.querySelector('.quiz-ai-feedback-error-message');
    const regenerateBtn = container.querySelector('.quiz-ai-feedback-regenerate');

    const showSection = (section) => {
        loading.classList.add('d-none');
        result.classList.add('d-none');
        error.classList.add('d-none');
        regenerateBtn.classList.add('d-none');

        switch (section) {
            case 'loading':
                loading.classList.remove('d-none');
                break;
            case 'result':
                result.classList.remove('d-none');
                regenerateBtn.classList.remove('d-none');
                break;
            case 'error':
                error.classList.remove('d-none');
                break;
        }
    };

    const generateFeedback = async() => {
        showSection('loading');

        try {
            const response = await Ajax.call([{
                methodname: 'aiplacement_courseassist_summarise_text',
                args: {
                    contextid: config.contextid,
                    prompttext: config.prompt,
                },
            }])[0];

            if (response.error || !response.success) {
                throw new Error(response.error || 'Failed to generate feedback');
            }

            const formattedContent = response.generatedcontent
                .replace(/\n\n/g, '</p><p>')
                .replace(/\n/g, '<br>');

            resultText.innerHTML = `<p>${formattedContent}</p>`;
            showSection('result');
        } catch (err) {
            errorMessage.textContent = err.message || 'An error occurred while generating feedback.';
            showSection('error');
            Notification.exception(err);
        }
    };

    container.addEventListener('click', (e) => {
        const button = e.target.closest('[data-action]');
        if (!button) {
            return;
        }

        const action = button.dataset.action;
        if (action === 'regenerate' || action === 'retry') {
            e.preventDefault();
            generateFeedback();
        }
    });

    if (container.dataset.autoload === 'true') {
        generateFeedback();
    }
};