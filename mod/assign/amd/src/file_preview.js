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
 * Preview submitted files before downloading them on the faculty grading screen (Jain customisation).
 *
 * @module     mod_assign/file_preview
 * @copyright  2026 Jain Online
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal';
import ModalEvents from 'core/modal_events';
import Templates from 'core/templates';
import Notification from 'core/notification';
import {getString} from 'core/str';
import {notifyFilterContentUpdated} from 'core_filters/events';

const SELECTORS = {
    trigger: '[data-action="assign-preview-file"]',
    videojsPlayer: '.mediaplugin_videojs video, .mediaplugin_videojs audio',
};

/** @var {Number} Counter used to keep each opened player's element id unique. */
let previewSequence = 0;

/**
 * Give the server-built player markup a fresh element id.
 *
 * The same markup is attached to both the file name link and the Preview icon, and is re-injected
 * every time the modal is opened. Video.js registers players by element id, so reusing an id could
 * hand back the previous (now detached) player if it has not finished being disposed of yet.
 *
 * @param {String} html The player markup.
 * @returns {String} The markup with a unique element id.
 */
const uniquifyPlayerId = (html) => {
    previewSequence++;
    return html.replace(/\bid="(id_videojs_[^"]*)"/g, (match, id) => `id="${id}_p${previewSequence}"`);
};

/**
 * Build the template context for a preview trigger.
 *
 * @param {HTMLElement} link The clicked preview trigger.
 * @returns {Object} The mustache context for mod_assign/file_preview_modal.
 */
const buildContext = (link) => {
    const mimetype = link.dataset.mimetype || '';
    const previewurl = link.dataset.previewurl || '';
    const officeurl = link.dataset.officeurl || '';
    const downloadurl = link.dataset.downloadurl || '';
    const filename = link.dataset.filename || '';
    const embedhtml = link.dataset.embed || '';
    const nativemedia = link.dataset.nativemedia || '';

    const context = {
        filename,
        downloadurl,
        src: '',
        isimage: false,
        isembed: false,
        ismedia: false,
        isvideo: false,
        isaudio: false,
        embedhtml: '',
        notavailable: false,
    };

    if (embedhtml) {
        // Audio/video rendered with the site's configured media player, built server-side.
        context.ismedia = true;
        context.embedhtml = uniquifyPlayerId(embedhtml);
    } else if (nativemedia) {
        // No media player could handle the file - fall back to the browser's own element.
        context.isvideo = nativemedia === 'video';
        context.isaudio = nativemedia === 'audio';
        context.src = previewurl;
    } else if (mimetype.indexOf('image/') === 0) {
        context.isimage = true;
        context.src = previewurl;
    } else if (mimetype === 'application/pdf' || mimetype === 'text/plain') {
        context.isembed = true;
        context.src = previewurl;
    } else if (officeurl) {
        // Office documents are rendered through the configured external viewer.
        context.isembed = true;
        context.src = officeurl;
    } else {
        context.notavailable = true;
    }

    return context;
};

/**
 * Stop and tear down any media in the modal body before it is removed from the page.
 *
 * Faculty open one submission after another, so leaving disposed-of players behind would leak
 * a Video.js instance per file viewed.
 *
 * @param {HTMLElement} body The modal body.
 * @returns {void}
 */
const teardownMedia = (body) => {
    body.querySelectorAll('video, audio').forEach((media) => {
        try {
            media.pause();
        } catch (e) {
            // Nothing useful to do - the element is about to be removed anyway.
        }
    });

    if (!body.querySelector(SELECTORS.videojsPlayer)) {
        return;
    }
    // Only pull in Video.js if it was actually used to render this preview. Note that video-lazy
    // resolves to the videojs function itself (it is a UMD module), not an ES namespace - the same
    // way media_videojs/loader consumes it.
    import('media_videojs/video-lazy').then((module) => {
        const videojs = module.default || module;
        body.querySelectorAll(SELECTORS.videojsPlayer).forEach((element) => {
            const player = videojs.getPlayer(element.id);
            if (player) {
                player.dispose();
            }
        });
        return null;
    }).catch(() => {
        // Disposal is best-effort; removing the modal still stops playback.
    });
};

/**
 * Open the preview modal for the given trigger.
 *
 * @param {HTMLElement} link The clicked preview trigger.
 * @returns {Promise<void>}
 */
const showPreview = async(link) => {
    try {
        const context = buildContext(link);
        const [title, {html, js}] = await Promise.all([
            getString('previewof', 'assignsubmission_file', context.filename),
            Templates.renderForPromise('mod_assign/file_preview_modal', context),
        ]);

        const modal = await Modal.create({
            title,
            body: html,
            large: true,
            removeOnClose: true,
            show: true,
        });

        Templates.runTemplateJS(js);

        const body = modal.getBody()[0];
        if (body && (context.ismedia || context.isvideo || context.isaudio)) {
            if (context.ismedia) {
                // The player markup was injected after page load, so the media_videojs loader has not
                // seen it. It listens for this event to bind Video.js to new nodes.
                notifyFilterContentUpdated(body);
            }
            modal.getRoot().on(ModalEvents.hidden, () => teardownMedia(body));
        }
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Initialise the file-preview behaviour. Uses event delegation so it works for files added
 * dynamically (e.g. quick grading) without re-binding.
 */
export const init = () => {
    if (document.body.dataset.assignFilePreviewInit) {
        return;
    }
    document.body.dataset.assignFilePreviewInit = '1';

    document.addEventListener('click', (e) => {
        const link = e.target.closest(SELECTORS.trigger);
        if (!link) {
            return;
        }
        e.preventDefault();
        showPreview(link);
    });
};
