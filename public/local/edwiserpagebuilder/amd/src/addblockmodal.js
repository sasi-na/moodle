/* eslint-disable no-unused-vars */
/* eslint-disable no-console*/
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
 * Show an add block modal instead of doing it on a separate page.
 *
 * @module     core/addblockmodal
 * @copyright  2016 Damyon Wiese <damyon@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'local_edwiserpagebuilder/jquery';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import Templates from 'core/templates';
import { get_string as getString } from 'core/str';
import Ajax from 'core/ajax';
import blockmanager from 'local_edwiserpagebuilder/blockmanager';

const SELECTORS = {
    ADD_BLOCK_REMUI: '[data-key="addblock"]',
    ADD_BLOCK: '.floating-add-block-button',
    MODAL_SUB_HEADER: '.modal-subheader',
    MODAL_HEADER_TITLE: '.modal-header .modal-title',
    UPDATE_PRO_BLOCKS_BTN: '.update-pro-blocks-btn'
};

// Ensure we only add our listeners once.
let listenerEventsRegistered = false;

/**
 * Register related event listeners.
 *
 * @method registerListenerEvents
 * @param {String} pageType The type of the page
 * @param {String} pageLayout The layout of the page
 * @param {String|null} addBlockUrl The add block URL
 * @param {String} subPage The subpage identifier
  * @param {String|null} issiteadmin check user is a site admin
 * @param {String} edwepbf check edwpbf plugin available or not
 * @param {Boolean} pbfnotenable check setting is enable or not
 */
const registerListenerEvents = async (pageType, pageLayout, addBlockUrl, subPage, issiteadmin, edwepbf, pbfnotenable) => {
    let ADD_BLOCK_SELECTOR = SELECTORS.ADD_BLOCK;

    if (M.cfg.theme === "remui") {
        ADD_BLOCK_SELECTOR = SELECTORS.ADD_BLOCK_REMUI;
    }
    $(document).on('click', ADD_BLOCK_SELECTOR, function(e) {
            e.preventDefault();

            let addBlockModal = null;
            let addBlockModalUrl = addBlockUrl ?? this.dataset.url;

            buildAddBlockModal()
                .then(async modal => {
                    modal.getRoot().addClass("epb_custom_modal fullwidth-modal");

                    const $root = modal.getRoot();
                    const rootEl = $root.get(0);

                    // === Step 1: define lock + unlock functions ===
                    function lockModal() {
                        $root.attr('data-backdrop', 'static');
                        $root.attr('data-keyboard', 'false');

                        // Prevent outside click
                        $root.on(ModalEvents.outsideClick, preventOutsideClick);
                        // Prevent ESC key
                        if (rootEl) {
                            rootEl.addEventListener('keydown', preventEscKey, true);
                        }
                    }

                    function unlockModal() {
                        $root.removeAttr('data-backdrop');
                        $root.removeAttr('data-keyboard');

                        // Allow outside click again
                        $root.off(ModalEvents.outsideClick, preventOutsideClick);
                        // Allow ESC again
                        if (rootEl) {
                            rootEl.removeEventListener('keydown', preventEscKey, true);
                        }
                    }

                    function preventOutsideClick(e) {
                        e.preventDefault();
                    }

                    function preventEscKey(e) {
                        if (e && (e.key === 'Escape' || e.keyCode === 27 || e.which === 27)) {
                            e.preventDefault();
                            e.stopImmediatePropagation();
                        }
                    }
                    modal.getRoot().on(ModalEvents.cancel, function (e) {
                        e.preventDefault();
                        modal.destroy();
                    });
                    addBlockModal = modal;

                    // Render overlay immediately while we fetch in background
                    Templates.render('local_edwiserpagebuilder/add_block_body_overlay', {})
                    .then(overlay => {
                        modal.setBody(overlay);
                        // Lock only while overlay is active
                        lockModal();
                        modal.show();

                        // Wait a bit for DOM to be ready, then start progress
                        setTimeout(() => {
                            // Initialize progress visualization using helper
                            const progress = initializeProgressVisualization();
                            if (progress) {
                                progress.start();

                                // Fetch blocks and complete progress
                                getFetchBlocks().then(fetchallblocks => {
                                    // Complete the progress bar
                                    progress.complete();

                                    // Continue with your existing logic
                                    renderBlocks(
                                        addBlockModalUrl, pageType, pageLayout, subPage,
                                        issiteadmin, edwepbf, pbfnotenable
                                    ).then(modalBody => {
                                        modal.setBody(modalBody);
                                        blockmanager.load(addBlockModalUrl, pageType);

                                        // Unlock modal so user can close normally
                                        unlockModal();

                                        // Register update pro blocks button handler
                                        registerUpdateProBlocksButton(modal, addBlockModalUrl, pageType, pageLayout, subPage, issiteadmin, edwepbf, pbfnotenable);

                                        // Register alert close handler to prevent modal from closing
                                        registerUpdateInfoAlertCloseHandler();

                                        // Get blocks context to check for locked blocks
                                        getAddableBlocks(pageType, pageLayout, subPage).then(blockscontextJson => {
                                            const blockscontext = JSON.parse(blockscontextJson);
                                            // Check if auto-update should be triggered (first time after upgrade)
                                            checkAndAutoUpdateProBlocks(modal, addBlockModalUrl, pageType, pageLayout, subPage, issiteadmin, edwepbf, pbfnotenable, blockscontext);
                                        }).catch(() => {
                                            // If fetching blocks context fails, skip auto-update check
                                            console.log('Could not fetch blocks context for auto-update check');
                                        });

                                        return modalBody;
                                    });
                                });
                            }
                        }, 150); // Small delay to ensure DOM is ready
                    });
                })
                .catch(() => {
                    addBlockModal.destroy();
                });

    });
};

const moveSubheaderToMain = () => {
    // Remove Sub Header and append in main header.
    $(".modal-header " + SELECTORS.MODAL_SUB_HEADER).remove();
    $($(SELECTORS.MODAL_SUB_HEADER)).insertAfter(SELECTORS.MODAL_HEADER_TITLE);
    $(".modal-body " + SELECTORS.MODAL_SUB_HEADER).remove();
    $(SELECTORS.MODAL_SUB_HEADER).removeClass("d-none");
};

const hideunactivetabdata = () =>{
    $('.moodleblock').addClass('d-none');
    $('.advanceblockblocks').removeClass('d-none');
};

/**
 * Common helper functions for modal locking/unlocking.
 *
 * @param {Object} $root The modal root jQuery element
 * @return {Object} Object containing lockModal, unlockModal functions
 */
const createModalLockHelpers = ($root) => {
    const preventOutsideClick = (e) => {
        e.preventDefault();
    };

    const preventEscKey = (e) => {
        if (e && (e.key === 'Escape' || e.keyCode === 27 || e.which === 27)) {
            e.preventDefault();
            e.stopImmediatePropagation();
        }
    };

    return {
        lockModal: () => {
            $root.attr('data-backdrop', 'static');
            $root.attr('data-keyboard', 'false');
            $root.on(ModalEvents.outsideClick, preventOutsideClick);
            const rootEl = $root.get(0);
            if (rootEl) {
                rootEl.addEventListener('keydown', preventEscKey, true);
            }
        },
        unlockModal: () => {
            $root.removeAttr('data-backdrop');
            $root.removeAttr('data-keyboard');
            $root.off(ModalEvents.outsideClick, preventOutsideClick);
            const rootEl = $root.get(0);
            if (rootEl) {
                rootEl.removeEventListener('keydown', preventEscKey, true);
            }
        }
    };
};

/**
 * Common function to execute pro blocks update with overlay and progress.
 *
 * @method executeProBlocksUpdate
 * @param {Object} modal The modal instance
 * @param {String} addBlockUrl The add block URL
 * @param {String} pageType The type of the page
 * @param {String} pageLayout The layout of the page
 * @param {String} subPage The subpage identifier
 * @param {Boolean} issiteadmin check user is a site admin
 * @param {Boolean} edwepbf check edwpbf plugin available or not
 * @param {Boolean} pbfnotenable check setting is enable or not
 * @param {Function} onSuccess Optional success callback
 * @param {Function} onError Optional error callback
 */
const executeProBlocksUpdate = (modal, addBlockUrl, pageType, pageLayout, subPage, issiteadmin, edwepbf, pbfnotenable, onSuccess, onError) => {
    const $root = modal.getRoot();
    const {lockModal, unlockModal} = createModalLockHelpers($root);

    lockModal();

    // Render overlay and append it above entire modal
    Templates.render('local_edwiserpagebuilder/update_pro_blocks_overlay', {})
        .then(overlay => {
            const $modalRoot = modal.getRoot();
            const $modalContent = $modalRoot.find('.modal-content').first();

            // Make modal content position relative if not already
            if ($modalContent.length > 0) {
                const currentPosition = $modalContent.css('position');
                if (currentPosition === 'static' || !currentPosition) {
                    $modalContent.css('position', 'relative');
                }
            }

            const $overlay = $(overlay);

            // Append overlay to modal content
            if ($modalContent.length > 0) {
                $modalContent.append($overlay);
            } else {
                $modalRoot.css('position', 'relative');
                $modalRoot.append($overlay);
            }
            modal.show();

            // Wait a bit for DOM to be ready, then start progress
            setTimeout(() => {
                // Initialize progress visualization using helper
                const progress = initializeProgressVisualization();
                if (progress) {
                    progress.start();

                    // Fetch and update pro blocks
                    getUpdateProBlocks().then(() => {
                        // Complete the progress bar
                        progress.complete();

                        // Remove overlay
                        $overlay.remove();

                        // Reload the modal body to show updated blocks
                        renderBlocks(
                            addBlockUrl, pageType, pageLayout, subPage,
                            issiteadmin, edwepbf, pbfnotenable
                        ).then(modalBody => {
                            modal.setBody(modalBody);
                            blockmanager.load(addBlockUrl, pageType);

                            // Re-register button handler with same parameters
                            registerUpdateProBlocksButton(modal, addBlockUrl, pageType, pageLayout, subPage, issiteadmin, edwepbf, pbfnotenable);

                            // Register alert close handler to prevent modal from closing
                            registerUpdateInfoAlertCloseHandler();

                            // Call success callback if provided
                            if (onSuccess) {
                                onSuccess();
                            }

                            // Unlock modal
                            unlockModal();

                            // Show success message
                            $('.updateinfoalert').removeClass('d-none');

                            setTimeout(function(){
                                $('.updateinfoalert').addClass('d-none');
                            },1000);
                        });
                    }).catch((error) => {
                        console.error('Error updating pro blocks:', error);

                        // Remove overlay on error
                        $overlay.remove();

                        unlockModal();

                        // Call error callback if provided
                        if (onError) {
                            onError(error);
                        }
                    });
                }
            }, 150);
        });
};

/**
 * Check if auto-update should be triggered and trigger it automatically.
 * This runs after modal opens to check if user just upgraded to pro.
 *
 * @method checkAndAutoUpdateProBlocks
 * @param {Object} modal The modal instance
 * @param {String} addBlockUrl The add block URL
 * @param {String} pageType The type of the page
 * @param {String} pageLayout The layout of the page
 * @param {String} subPage The subpage identifier
 * @param {Boolean} issiteadmin check user is a site admin
 * @param {Boolean} edwepbf check edwpbf plugin available or not
 * @param {Boolean} pbfnotenable check setting is enable or not
 * @param {Object} blockscontext Blocks context data
 */
const checkAndAutoUpdateProBlocks = (modal, addBlockUrl, pageType, pageLayout, subPage, issiteadmin, edwepbf, pbfnotenable, blockscontext) => {
    // Check if pro is allowed
    if (!blockscontext?.isepbproallowed) {
        return; // Not pro, no auto-update needed
    }

    // Check if auto-update has already been done (server-side config, similar to edwadvancedblockfatchstatus)
    if (blockscontext?.pro_blocks_auto_update_done === true) {
        return; // Already done, skip
    }

    // Check if there are any locked blocks that need updating (prefer server-side SQL flag, fallback to JS scan)
    const hasLockedBlocks = (typeof blockscontext?.haslockedblocks === 'boolean')
        ? blockscontext.haslockedblocks
        : checkForLockedBlocks(blockscontext);
    if (!hasLockedBlocks) {
        // No locked blocks - server will mark as done when update completes
        return;
    }

    // Use common update function
    executeProBlocksUpdate(modal, addBlockUrl, pageType, pageLayout, subPage, issiteadmin, edwepbf, pbfnotenable);
};

/**
 * Check if there are locked blocks in the blocks context.
 *
 * @param {Object} blockscontext Blocks context data
 * @return {Boolean} True if locked blocks exist
 */
const checkForLockedBlocks = (blockscontext) => {
    if (!blockscontext || !blockscontext.blockscontext) {
        return false;
    }

    // Check all blocks in all categories for locked blocks
    const blocks = blockscontext.blockscontext || [];
    for (let category of blocks) {
        if (category.blocks && Array.isArray(category.blocks)) {
            for (let block of category.blocks) {
                // Check if block has locked field set to true
                if (block.locked === 1 || block.locked === true || block.locked === '1') {
                    return true;
                }
            }
        }
    }

    return false;
};

/**
 * Register close button handler for update info alert to prevent modal from closing.
 *
 * @method registerUpdateInfoAlertCloseHandler
 */
const registerUpdateInfoAlertCloseHandler = () => {
    // Remove Bootstrap dismiss attributes to prevent Bootstrap from handling the click
    $('.updateinfoalert .close').removeAttr('data-bs-dismiss').removeAttr('data-dismiss');

    // Remove any existing handlers first to avoid duplicates
    $('.updateinfoalert .close').off('click.alertclose');
    $('.updateinfoalert .close').on('click.alertclose', function(e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        // Only hide the alert, don't close the modal
        $('.updateinfoalert').addClass('d-none');
    });
};

/**
 * Register click event handler for update pro blocks button.
 *
 * @method registerUpdateProBlocksButton
 * @param {Object} modal The modal instance
 * @param {String} addBlockUrl The add block URL
 * @param {String} pageType The type of the page
 * @param {String} pageLayout The layout of the page
 * @param {String} subPage The subpage identifier
 * @param {Boolean} issiteadmin check user is a site admin
 * @param {Boolean} edwepbf check edwpbf plugin available or not
 * @param {Boolean} pbfnotenable check setting is enable or not
 */
const registerUpdateProBlocksButton = (modal, addBlockUrl, pageType, pageLayout, subPage, issiteadmin, edwepbf, pbfnotenable) => {
    $(document).off('click', SELECTORS.UPDATE_PRO_BLOCKS_BTN);
    $(document).on('click', SELECTORS.UPDATE_PRO_BLOCKS_BTN, function(e) {
        e.preventDefault();
        e.stopPropagation();

        const $button = $(this);

        // Disable button during update
        $button.prop('disabled', true);

        // Use common update function with button-specific callbacks
        executeProBlocksUpdate(
            modal, addBlockUrl, pageType, pageLayout, subPage,
            issiteadmin, edwepbf, pbfnotenable,
            () => {
                // Success callback: Re-enable button
                $button.prop('disabled', false);
            },
            () => {
                // Error callback: Re-enable button
                $button.prop('disabled', false);
            }
        );
    });
};

/**
 * Method that fetches and updates all pro blocks using the update_pro_blocks web service with retry mechanism.
 *
 * @method getUpdateProBlocks
 * @param {Number} retryCount The current retry attempt number
 * @return {Promise}
 */
const getUpdateProBlocks = (retryCount = 1) => {
    setTimeout(() => {
        $("#add-block-installing-blocks-placeholder-wrapper").removeClass("d-none");
    }, 400);

    return new Promise((resolve, reject) => {
        // Use an immediately invoked async function to handle async operations
        (async () => {
            try {
                // Initialize progress visualization using helper
                const progress = initializeProgressVisualization();
                if (progress) {
                    progress.start();
                }

                // First request - check if batching is supported or if we should process all blocks
                const initialResponse = await makeProBlocksBatchRequest(20, 0);
                console.log("Initial pro blocks update response:", initialResponse);

                if (initialResponse && initialResponse.result) {
                    // Check if this is a batching response or a "process all" response
                    if (initialResponse.result.limit > 0 && !initialResponse.result.complete) {
                        // Batching mode - process in batches
                        console.log("Batching mode detected for pro blocks, processing in batches...");

                        // Set initial progress based on first batch
                        if (progress) {
                            const initialProgress = Math.round((initialResponse.result.processed / initialResponse.result.total) * 100);
                            progress.setTarget(initialProgress);
                        }

                        await processProBlocksBatches(initialResponse, progress);
                    } else {
                        // Backward compatibility mode - all blocks processed in one go
                        console.log("Backward compatibility mode - all pro blocks processed");
                        if (progress) {
                            progress.setTarget(100);
                        }
                    }
                } else {
                    console.error("Invalid initial pro blocks response:", initialResponse);
                }

                resolve(true);
            } catch (error) {
                console.error('Error in pro blocks update:', error);

                // Retry logic
                if (retryCount < 6) {
                    console.log("Failed attempt: " + error);
                    setTimeout(() => {
                        getUpdateProBlocks(retryCount + 1)
                            .then(resolve)
                            .catch(reject);
                    }, 240000 * retryCount); // Wait 240 seconds before retrying
                } else {
                    reject(error);
                }
            }
        })();
    });
};

/**
 * Get progress bar elements (helper to avoid duplication)
 * @returns {Object} Object with progressBar and progressText elements
 */
const getProgressElements = () => {
    return {
        progressBar: document.querySelector("#add-block-overlay-bar"),
        progressText: document.querySelector("#add-block-overlay-progress")
    };
};

/**
 * Initialize progress visualization (helper to avoid duplication)
 * @returns {Object|null} Progress visualization object or null if elements not found
 */
const initializeProgressVisualization = () => {
    const {progressBar, progressText} = getProgressElements();
    if (progressBar && progressText) {
        return createProgressVisualization({
            progressBar,
            progressText,
            onComplete: () => {
                console.log("Progress visualization completed!");
            }
        });
    }
    return null;
};

/**
 * Parse and validate batch response (helper to avoid duplication)
 * @param {*} response Response from AJAX call
 * @param {String} logPrefix Prefix for log messages (optional)
 * @returns {Object} Parsed and validated response
 * @throws {Error} If response is invalid
 */
const parseAndValidateResponse = (response, logPrefix = '') => {
    // Parse JSON response since PHP returns JSON string
    let parsedResponse;
    try {
        parsedResponse = typeof response === 'string' ? JSON.parse(response) : response;
    } catch (e) {
        console.error(`Failed to parse JSON response:`, e);
        throw new Error("Invalid JSON response");
    }

    // Validate response structure
    if (parsedResponse && parsedResponse.result && typeof parsedResponse.result === 'object') {
        const prefix = logPrefix ? logPrefix + ' ' : '';
        console.log(`${prefix}Response validation passed, resolving with:`, parsedResponse);
        return parsedResponse;
    } else {
        const prefix = logPrefix ? logPrefix + ' ' : '';
        console.error(`Invalid ${prefix}response structure:`, parsedResponse);
        throw new Error("Invalid response structure");
    }
};

/**
 * Generic batch processing function (helper to avoid duplication)
 * @param {Object} initialResponse Initial batch response
 * @param {Function} batchRequestFn Function to make batch requests
 * @param {Object} progress Progress visualization object
 * @param {String} logPrefix Prefix for log messages (optional)
 */
const processBatchesGeneric = async (initialResponse, batchRequestFn, progress, logPrefix = '') => {
    const limit = 20;
    let totalBlocks = initialResponse.result.total;
    let processedBlocks = initialResponse.result.processed;
    let isComplete = initialResponse.result.complete;
    let currentOffset = initialResponse.result.next_offset || 0;

    const prefix = logPrefix ? logPrefix + ' ' : '';
    console.log(`Initial ${prefix}batch: ${processedBlocks}/${totalBlocks} blocks, Complete: ${isComplete}, Next offset: ${currentOffset}`);

    // Continue with subsequent batches if not complete
    let batchCount = 1;
    while (!isComplete) {
        batchCount++;

        console.log(`Making ${prefix}batch ${batchCount} request with offset: ${currentOffset}`);

        // Make next batch request
        const batchResponse = await batchRequestFn(limit, currentOffset);
        console.log(`${prefix}Batch ${batchCount} response:`, batchResponse);

        if (batchResponse && batchResponse.result) {
            processedBlocks += batchResponse.result.processed;
            isComplete = batchResponse.result.complete;
            currentOffset = batchResponse.result.next_offset || 0;

            console.log(`${prefix}Batch ${batchCount} completed: ${batchResponse.result.processed} blocks, Total processed: ${processedBlocks}/${totalBlocks}, Complete: ${isComplete}, Next offset: ${currentOffset}`);

            // Update progress smoothly based on actual completion
            if (progress) {
                const currentProgress = Math.round((processedBlocks / totalBlocks) * 100);
                progress.setTarget(currentProgress);
                console.log(`Setting progress target to: ${currentProgress}% (${processedBlocks}/${totalBlocks})`);
            }

            if (isComplete) {
                console.log(`All ${prefix}batches completed successfully!`);
                // Set final progress to 100%
                if (progress) {
                    progress.setTarget(100);
                }
                break;
            }
        } else {
            // Handle batch failure
            console.error(`${prefix}Batch ${batchCount} failed:`, batchResponse);
            break;
        }
    }
};

/**
 * Process pro blocks in batches for the new batching system
 */
const processProBlocksBatches = async (initialResponse, progress) => {
    return processBatchesGeneric(initialResponse, makeProBlocksBatchRequest, progress, 'pro blocks');
};

/**
 * Make a single batch request to update pro blocks.
 *
 * @param {Number|false} limit Number of blocks per batch, false for all blocks (backward compatibility)
 * @param {Number} offset Starting position for the batch
 * @return {Promise} Promise that resolves with the batch response
 */
const makeProBlocksBatchRequest = (limit, offset) => {
    return new Promise((resolve, reject) => {
        const args = {};

        // Only add parameters if they are meaningful (for backward compatibility)
        if (limit !== false) {
            args.limit = limit;
        }
        if (offset > 0) {
            args.offset = offset;
        }

        Ajax.call([{
            methodname: 'local_edwiserpagebuilder_update_pro_blocks',
            args: args,
            done: function(response) {
                console.log("Pro blocks batch request successful:", response);

                // Parse and validate response using helper
                try {
                    const parsedResponse = parseAndValidateResponse(response, 'pro blocks');
                    resolve(parsedResponse);
                } catch (e) {
                    reject(e);
                }
            },
            fail: function(ex) {
                console.error("Pro blocks batch request failed:", ex);
                reject(ex);
            },
        }]);
    });
};
/**
 * Method that creates the 'add block' modal.
 *
 * @method buildAddBlockModal
 * @returns {Promise} The modal promise (modal's body will be rendered later).
 */
const buildAddBlockModal = () => {
    return ModalFactory.create({
        type: ModalFactory.types.CANCEL,
        title: getString('addblock')
    });
};

/**
 * Method that renders the list of available blocks.
 *
 * @method renderBlocks
 * @param {String} addBlockUrl The add block URL
 * @param {String} pageType The type of the page
 * @param {String} pageLayout The layout of the page
 * @param {String} subPage The subpage identifier
* @param {Boolean} issiteadmin The layout of the page
 * @param {Boolean} edwepbf check the edwpbf plugin exist
 * @param {Boolean} pbfnotenable check setting is enable or not
 * @return {Promise}
 */
const renderBlocks = async (addBlockUrl, pageType, pageLayout, subPage, issiteadmin, edwepbf, pbfnotenable) => {

    // Fetch all addable blocks in the given page.
    let blockscontext = await getAddableBlocks(pageType, pageLayout, subPage);
    blockscontext = JSON.parse(blockscontext);

    var filterplugindata = false;

    var showfilterreleaseinfo = false;

    if (edwepbf && !pbfnotenable) {

        var filterplugindata = await getfilterpluginstatus();

        filterplugindata = JSON.parse(filterplugindata);

        const compareVersion = (v1, v2) => {
            const parts1 = v1.split('.').map(Number);
            const parts2 = v2.split('.').map(Number);
            for (let i = 0; i < Math.max(parts1.length, parts2.length); i++) {
                const a = parts1[i] || 0;
                const b = parts2[i] || 0;
                if (a < b) return -1;
                if (a > b) return 1;
            }
            return 0;
        };

        if (compareVersion(filterplugindata.release, '4.2.2') <= 0) {
            pbfnotenable = false;
            showfilterreleaseinfo = true;
        }
    }

    var showmodalsecondnav = false;
    // The variable edwremuitheninfo is comming from theme using data for js
    if ( typeof edwremuithemeinfo !== 'undefined' && edwremuithemeinfo == 'available') {
        showmodalsecondnav = true;
    }

    var match = addBlockUrl.match(/[?&]bui_blockregion=([^&]+)/);
    var region = match ? match[1] : "";
    return Templates.render('local_edwiserpagebuilder/add_block_body', {
        blockscontext: blockscontext?.blockscontext,
        htmlblock: blockscontext?.htmlblock,
        importblock: blockscontext?.importblock,
        categories: blockscontext?.categories,
        moodleblock: blockscontext.moodleblock,
        url: addBlockUrl,
        isadmin: issiteadmin,
        pbfpluginexist: edwepbf,
        edwpbfnotenable: pbfnotenable,
        blockpagetype: pageType,
        blockregion: region,
        showsecondmenu: showmodalsecondnav,
        showfilterreleasenotice:showfilterreleaseinfo,
        wwwroot: M.cfg.wwwroot,
        isepbproallowed: blockscontext?.isepbproallowed,
        haslockedblocks: blockscontext?.haslockedblocks,
    });
};

/**
 * Method that fetches all addable blocks in a given page.
 *
 * @method getAddableBlocks
 * @param {String} pageType The type of the page
 * @param {String} pageLayout The layout of the page
 * @param {String} subPage The subpage identifier
 * @return {Promise}
 */
const getAddableBlocks = async (pageType, pageLayout, subPage) => {
    const request = {
        methodname: 'local_edwiserpagebuilder_fetch_addable_blocks',
        args: {
            pagecontextid: M.cfg.contextid,
            pagetype: pageType,
            pagelayout: pageLayout,
            subpage: subPage,
        },
    };

    return Ajax.call([request])[0];
};

/**
 * Method that fetches all blocks using the fetchblocks web service with retry mechanism.
 *
 * @method getFetchBlocks
 * @param {Number} retryCount The current retry attempt number
 * @return {Promise}
 */
const getFetchBlocks = (retryCount = 1) => {
    setTimeout(() => {
        $("#add-block-installing-blocks-placeholder-wrapper").removeClass("d-none");
    }, 400);

    return new Promise((resolve, reject) => {
        // Use an immediately invoked async function to handle async operations
        (async () => {
            try {
                // Initialize progress visualization using helper
                const progress = initializeProgressVisualization();
                if (progress) {
                    progress.start();
                }

                // First request - check if batching is supported or if we should process all blocks
                const initialResponse = await makeBatchRequest(20, 0);
                console.log("Initial response:", initialResponse);

                if (initialResponse && initialResponse.result) {
                    // Check if this is a batching response or a "process all" response
                    if (initialResponse.result.limit > 0 && !initialResponse.result.complete) {
                        // Batching mode - process in batches
                        console.log("Batching mode detected, processing in batches...");

                        // Set initial progress based on first batch
                        if (progress) {
                            const initialProgress = Math.round((initialResponse.result.processed / initialResponse.result.total) * 100);
                            progress.setTarget(initialProgress);
                        }

                        await processBatches(initialResponse, progress);
                    } else {
                        // Backward compatibility mode - all blocks processed in one go
                        console.log("Backward compatibility mode - all blocks processed");
                        if (progress) {
                            progress.setTarget(100);
                        }
                    }
                } else {
                    console.error("Invalid initial response:", initialResponse);
                }

                resolve(true);
            } catch (error) {
                console.error('Error in block fetching:', error);

                // Retry logic
                if (retryCount < 6) {
                    console.log("Failed attempt: " + error);
                    setTimeout(() => {
                        getFetchBlocks(retryCount + 1)
                            .then(resolve)
                            .catch(reject);
                    }, 240000 * retryCount); // Wait 240 seconds before retrying
                } else {
                    resolve(false);
                }
            }
        })();
    });
};

/**
 * Backward compatibility function - fetches all blocks without batching (old behavior)
 * This maintains compatibility with existing code that doesn't pass parameters
 */
const getFetchBlocksLegacy = (retryCount = 1) => {
    setTimeout(() => {
        $("#add-block-installing-blocks-placeholder-wrapper").removeClass("d-none");
    }, 400);

    return new Promise((resolve, reject) => {
        // Use an immediately invoked async function to handle async operations
        (async () => {
            try {
                // Initialize progress visualization using helper
                const progress = initializeProgressVisualization();
                if (progress) {
                    progress.start();
                }

                // Call without parameters for backward compatibility
                const response = await makeBatchRequest(false, 0);
                console.log("Legacy response:", response);

                if (response && response.result) {
                    // Show completion immediately for legacy mode
                    if (progress) {
                        progress.setTarget(100);
                    }
                    console.log("Legacy mode - all blocks processed");
                } else {
                    console.error("Invalid legacy response:", response);
                }

                resolve(true);
            } catch (error) {
                console.error('Error in legacy block fetching:', error);

                // Retry logic
                if (retryCount < 6) {
                    console.log("Failed attempt: " + error);
                    setTimeout(() => {
                        getFetchBlocksLegacy(retryCount + 1)
                            .then(resolve)
                            .catch(reject);
                    }, 240000 * retryCount); // Wait 240 seconds before retrying
                } else {
                    resolve(false);
                }
            }
        })();
    });
};

/**
 * Process blocks in batches for the new batching system
 */
const processBatches = async (initialResponse, progress) => {
    return processBatchesGeneric(initialResponse, makeBatchRequest, progress);
};

/**
 * This method checks the version of filter plugin is more the 4.2.2 or not .
 *
 * @method getAddableBlocks
 * @param {String} pageType The type of the page
 * @param {String} pageLayout The layout of the page
 * @param {String} subPage The subpage identifier
 * @return {Promise}
 */
const getfilterpluginstatus = async (pageType, pageLayout, subPage) => {
    const request = {
        methodname: 'local_edwiserpagebuilder_get_filter_plugin_status',
        args: {
            config: ""
        }
    };

    return Ajax.call([request])[0];
};

/**
 * Handles the progress bar visualization with stable, smooth progress
 * Prevents flickering and ensures progress bar and text stay in sync
 * @param {Object} options - Configuration options for the progress
 * @param {HTMLElement} options.progressBar - The progress bar element
 * @param {HTMLElement} options.progressText - The progress text element
 * @param {Function} options.onComplete - Callback when progress reaches 100%
 * @returns {Object} - Object with methods to control the progress
 */
const createProgressVisualization = ({ progressBar, progressText, onComplete }) => {
    let currentProgress = 0;
    let targetProgress = 0;
    let animationFrame = null;
    let isAnimating = false;
    let lastUpdateTime = 0;
    let debounceTimer = null;
    const ANIMATION_DURATION = 800; // 800ms for smooth transitions
    const DEBOUNCE_DELAY = 100; // 100ms debounce for target changes

    // Ensure progress bar and text are always in sync
    const updateProgressDisplay = (progressValue) => {
        const roundedProgress = Math.round(progressValue);

        // Update progress bar
        if (progressBar) {
            progressBar.style.width = `${progressValue}%`;
            progressBar.setAttribute("aria-valuenow", roundedProgress);
        }

        // Update progress text - only when there's a meaningful change
        if (progressText) {
            const currentText = progressText.textContent;
            const newText = `${roundedProgress}%`;

            // Only update text if it's different to prevent unnecessary DOM updates
            if (currentText !== newText) {
                progressText.textContent = newText;
            }
        }
    };

    // Smooth animation using requestAnimationFrame for better performance
    const animateProgress = (timestamp) => {
        if (!lastUpdateTime) lastUpdateTime = timestamp;

        const elapsed = timestamp - lastUpdateTime;
        const progress = Math.min(elapsed / ANIMATION_DURATION, 1);

        // Use easing function for smooth animation
        const easeOutQuart = 1 - Math.pow(1 - progress, 4);

        // Calculate current position between start and target
        const startProgress = currentProgress;
        const progressDiff = targetProgress - startProgress;
        const currentPosition = startProgress + (progressDiff * easeOutQuart);

        // Update display
        updateProgressDisplay(currentPosition);

        // Continue animation if not complete
        if (progress < 1) {
            animationFrame = requestAnimationFrame(animateProgress);
        } else {
            // Animation complete
            currentProgress = targetProgress;
            updateProgressDisplay(currentProgress);
            isAnimating = false;
            animationFrame = null;
        }
    };

    const startProgress = () => {
        // Reset state
        currentProgress = 2;
        targetProgress = 2;
        isAnimating = false;
        lastUpdateTime = 0;

        // Clear any existing animation and debounce
        if (animationFrame) {
            cancelAnimationFrame(animationFrame);
            animationFrame = null;
        }
        if (debounceTimer) {
            clearTimeout(debounceTimer);
            debounceTimer = null;
        }

        // Set initial display
        updateProgressDisplay(currentProgress);

        console.log("Progress visualization started at 2%");
    };

    const setTargetProgress = (newTarget) => {
        // Ensure target is within valid range
        newTarget = Math.max(0, Math.min(100, newTarget));

        // Don't animate if target is the same or very close
        if (Math.abs(newTarget - targetProgress) < 0.1) {
            return;
        }

        // Clear existing debounce timer
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }

        // Debounce target changes to prevent rapid updates
        debounceTimer = setTimeout(() => {
            console.log(`Setting target progress from ${targetProgress}% to ${newTarget}%`);

            // Update target
            targetProgress = newTarget;

            // Start animation if not already animating
            if (!isAnimating) {
                isAnimating = true;
                lastUpdateTime = 0;
                animationFrame = requestAnimationFrame(animateProgress);
            }
        }, DEBOUNCE_DELAY);
    };

    const completeProgress = () => {
        // Clear any existing animation and debounce
        if (animationFrame) {
            cancelAnimationFrame(animationFrame);
            animationFrame = null;
        }
        if (debounceTimer) {
            clearTimeout(debounceTimer);
            debounceTimer = null;
        }

        // Set final target and animate to 100%
        targetProgress = 100;
        isAnimating = true;
        lastUpdateTime = 0;

        console.log("Completing progress to 100%");

        // Animate to completion
        animationFrame = requestAnimationFrame(animateProgress);

        // Call onComplete after animation finishes
        setTimeout(() => {
            if (onComplete) onComplete();
        }, ANIMATION_DURATION + 100);
    };

    const stopProgress = () => {
        if (animationFrame) {
            cancelAnimationFrame(animationFrame);
            animationFrame = null;
        }
        if (debounceTimer) {
            clearTimeout(debounceTimer);
            debounceTimer = null;
        }
        isAnimating = false;
    };

    const getProgress = () => currentProgress;

    return {
        start: startProgress,
        setTarget: setTargetProgress,
        complete: completeProgress,
        stop: stopProgress,
        getProgress: getProgress
    };
};

/**
 * Make a single batch request to the server.
 *
 * @param {Number|false} limit Number of blocks per batch, false for all blocks (backward compatibility)
 * @param {Number} offset Starting position for the batch
 * @return {Promise} Promise that resolves with the batch response
 */
const makeBatchRequest = (limit, offset) => {
    return new Promise((resolve, reject) => {
        const args = {};

        // Only add parameters if they are meaningful (for backward compatibility)
        if (limit !== false) {
            args.limit = limit;
        }
        if (offset > 0) {
            args.offset = offset;
        }

        Ajax.call([{
            methodname: 'local_edwiserpagebuilder_fetchblocks',
            args: args,
            done: function(response) {
                console.log("Batch request successful:", response);

                // Parse and validate response using helper
                try {
                    const parsedResponse = parseAndValidateResponse(response);
                    resolve(parsedResponse);
                } catch (e) {
                    reject(e);
                }
            },
            fail: function(ex) {
                console.error("Batch request failed:", ex);
                reject(ex);
            },
        }]);
    });
};

/**
 * Set up the actions.
 *
 * @method init
 * @param {String} pageType The type of the page
 * @param {String} pageLayout The layout of the page
 * @param {String|null} addBlockUrl The add block URL
 * @param {String} subPage The subpage identifier
 * @param {String|null} issiteadmin issiteadmin
 * @param {Boolean} edwepbf plugin avaialable
 *@param {Boolean} pbfnotenable plugin avaialable
 */
export const init = (
    pageType, pageLayout, addBlockUrl = null, subPage = '', issiteadmin = false, edwepbf = false, pbfnotenable = false) => {
    edwepbf = edwepbf == 0 ? false : true;
    issiteadmin = issiteadmin == 0 ? false : true;
    pbfnotenable = pbfnotenable == 0 ? false : true;
    if (!listenerEventsRegistered) {
        registerListenerEvents(pageType, pageLayout, addBlockUrl, subPage, issiteadmin, edwepbf, pbfnotenable);
        listenerEventsRegistered = true;
    }
};
