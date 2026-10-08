/* eslint-disable no-undef */

/**
 * Initialise video tracker.
 *
 * @param {Object} config
 */
export const init = (config) => {

    if (document.readyState === 'loading') {

        document.addEventListener(
            'DOMContentLoaded',
            function() {
                start(config);
            }
        );

        return;
    }

    start(config);
};

/**
 * Resolve the Vimeo iframe and start tracking.
 *
 * @param {Object} config
 */
function start(config) {

    const iframe = ensureIframe(config);

    if (!iframe) {
        return;
    }

    renderSummaryStatus(config, iframe);

    loadVimeoSdk(config);
}

/**
 * Find the Vimeo iframe, or rebuild it from the configured video id.
 *
 * The recording is embedded as a raw <iframe> in the page content. For
 * non-trusted roles (students) Moodle's HTML purifier strips that iframe
 * before the page is served, so we recreate it client-side from the video
 * id, which is not subject to server-side cleaning. This also normalises
 * the element id so tracking attaches reliably regardless of how the
 * stored markup cased it.
 *
 * @param {Object} config
 * @returns {HTMLElement|null}
 */
function ensureIframe(config) {

    /*
    |--------------------------------------------------------------------------
    | Existing embed (trusted roles)
    |
    | The stored id casing varies ("vimeoPlayer" vs "vimeoPLayer"), so match
    | defensively and fall back to any Vimeo iframe on the page.
    |--------------------------------------------------------------------------
    */

    let iframe = document.getElementById('vimeoPlayer')
        || document.getElementById('vimeoPLayer')
        || document.querySelector('iframe[src*="player.vimeo.com"]');

    if (iframe) {

        iframe.id = 'vimeoPlayer';

        return iframe;
    }

    /*
    |--------------------------------------------------------------------------
    | Stripped embed (students) — rebuild it client-side
    |--------------------------------------------------------------------------
    */

    if (!config.videoid) {
        return null;
    }

    iframe = document.createElement('iframe');

    iframe.id = 'vimeoPlayer';

    iframe.src = 'https://player.vimeo.com/video/'
        + encodeURIComponent(config.videoid)
        + '?title=0&byline=0&portrait=0&badge=0'
        + '&autopause=0&player_id=0&app_id=58479';

    iframe.setAttribute('title', 'Recorded session');

    iframe.setAttribute('frameborder', '0');

    iframe.setAttribute(
        'allow',
        'autoplay; fullscreen; picture-in-picture;'
        + ' clipboard-write; encrypted-media; web-share'
    );

    iframe.setAttribute('allowfullscreen', '');

    iframe.style.cssText =
        'position:absolute;top:0;left:0;width:100%;height:100%;';

    /*
    |--------------------------------------------------------------------------
    | Reuse the author's responsive wrapper when the purifier left it behind,
    | otherwise build our own 16:9 container.
    |--------------------------------------------------------------------------
    */

    const wrapper = findResponsiveWrapper();

    if (wrapper) {

        if (!wrapper.style.position) {
            wrapper.style.position = 'relative';
        }

        wrapper.appendChild(iframe);

    } else {

        const box = document.createElement('div');

        box.style.cssText = 'padding:56.25% 0 0 0;position:relative;';

        box.appendChild(iframe);

        const anchor =
            document.querySelector('[role="main"] .no-overflow')
            || document.querySelector('.no-overflow')
            || document.querySelector('[role="main"]')
            || document.body;

        anchor.appendChild(box);
    }

    return iframe;
}

/**
 * Find an empty responsive (padding-based) wrapper left by the purifier.
 *
 * @returns {HTMLElement|null}
 */
function findResponsiveWrapper() {

    const candidates = document.querySelectorAll(
        '.no-overflow div[style*="padding"]'
    );

    for (let i = 0; i < candidates.length; i++) {

        const style = candidates[i].getAttribute('style') || '';

        if (
            style.indexOf('%') !== -1
            && !candidates[i].querySelector('iframe')
        ) {
            return candidates[i];
        }
    }

    return null;
}

/**
 * Render the student's summary status (pending / approved / rejected).
 *
 * status: -1 = none, 0 = pending, 1 = approved, 2 = rejected.
 *
 * @param {Object} config
 * @param {HTMLElement} iframe
 */
function renderSummaryStatus(config, iframe) {

    if (
        !config.summarysubmitted ||
        document.getElementById('videotracker-summary-status')
    ) {
        return;
    }

    const rejectedText = config.canresubmit
        ? 'Summary rejected — re-watch the recording to edit and resubmit (one attempt left)'
        : 'Summary rejected — no resubmission attempts remaining';

    const map = {
        0: {text: 'Summary pending faculty review', cls: 'alert-warning'},
        1: {text: 'Summary approved — attendance marked present', cls: 'alert-success'},
        2: {text: rejectedText, cls: 'alert-danger'}
    };

    const info = map[config.summarystatus];

    if (!info) {
        return;
    }

    const banner = document.createElement('div');

    banner.id = 'videotracker-summary-status';
    banner.className = 'alert ' + info.cls;
    banner.style.margin = '10px 0';
    banner.textContent = info.text;

    // Show the rejection reason when present.
    if (config.summarystatus === 2 && config.summaryfeedback) {

        const reason = document.createElement('div');

        reason.style.marginTop = '6px';
        reason.style.fontSize = '0.9em';
        reason.textContent = 'Reason: ' + config.summaryfeedback;

        banner.appendChild(reason);
    }

    const anchor = iframe.parentNode || iframe;

    anchor.insertBefore(banner, anchor.firstChild);
}

/**
 * Load Vimeo SDK.
 *
 * @param {Object} config
 */
function loadVimeoSdk(config) {

    /*
    |--------------------------------------------------------------------------
    | Already available
    |--------------------------------------------------------------------------
    */

    if (
        typeof window.Vimeo !== 'undefined' &&
        typeof window.Vimeo.Player !== 'undefined'
    ) {

        initialisePlayer(config);

        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Already loading
    |--------------------------------------------------------------------------
    */

    const existing = document.getElementById(
        'local-vimeo-sdk'
    );

    if (existing) {

        waitForVimeo(config);

        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Inject SDK
    |--------------------------------------------------------------------------
    */

    const script = document.createElement(
        'script'
    );

    script.id = 'local-vimeo-sdk';

    script.src =
        'https://player.vimeo.com/api/player.js';

    script.async = true;

    /*
    |--------------------------------------------------------------------------
    | Avoid the RequireJS / AMD clash
    |
    | Vimeo's player.js is a UMD module. With Moodle's RequireJS present its
    | anonymous define() throws "Mismatched anonymous define() module" and the
    | SDK never initialises. Temporarily hide AMD so it attaches window.Vimeo
    | as a plain global, then restore define.amd.
    |--------------------------------------------------------------------------
    */

    const amd = (typeof window.define === 'function')
        ? window.define.amd
        : undefined;

    const restoreAmd = function() {
        if (typeof window.define === 'function') {
            window.define.amd = amd;
        }
    };

    if (typeof window.define === 'function') {
        window.define.amd = undefined;
    }

    script.onload = function() {

        restoreAmd();

        waitForVimeo(config);
    };

    script.onerror = function() {

        restoreAmd();

        // eslint-disable-next-line no-console
        console.error(
            'Failed to load Vimeo SDK'
        );
    };

    document.head.appendChild(script);
}

/**
 * Wait for Vimeo SDK.
 *
 * @param {Object} config
 */
function waitForVimeo(config) {

    let attempts = 0;

    const interval = window.setInterval(function() {

        attempts++;

        if (
            typeof window.Vimeo !== 'undefined' &&
            typeof window.Vimeo.Player !== 'undefined'
        ) {

            window.clearInterval(interval);

            initialisePlayer(config);

            return;
        }

        if (attempts >= 300) {

            window.clearInterval(interval);

            // eslint-disable-next-line no-console
            console.error(
                'Vimeo SDK timeout'
            );
        }

    }, 100);
}

/**
 * Initialise player tracking.
 *
 * @param {Object} config
 */
function initialisePlayer(config) {

    const iframe = document.getElementById(
        'vimeoPlayer'
    );

    if (!iframe) {

        // eslint-disable-next-line no-console
        console.error('Iframe not found');

        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Prevent duplicate player init
    |--------------------------------------------------------------------------
    */

    if (
        iframe.dataset.videotrackerInitialised
    ) {

        return;
    }

    iframe.dataset.videotrackerInitialised =
        '1';

    /*
    |--------------------------------------------------------------------------
    | Create Vimeo player
    |--------------------------------------------------------------------------
    */

    const player =
        new window.Vimeo.Player(iframe);

    const sessionuuid =
        generateUuid();

    let videoDuration = 0;

    let lastTime = 0;

    let isPlaying = false;

    let watchedRanges = [];

    let currentRangeStart = null;

    let lastSave = Date.now();

    let sessionCreated = false;

    let completionHandled = false;

    let summaryPrompted = false;

    /*
    |--------------------------------------------------------------------------
    | Server-authoritative watch state
    |
    | The submit endpoint accepts a summary only when the SERVER-stored
    | percentage/completion meets the threshold. update_session.php derives
    | those values itself (with an anti-scrub realtime cap) and returns them,
    | so we mirror them here and gate the summary prompt on them — never on the
    | client-side live percentage, which can be inflated by seeking and would
    | otherwise unlock the button for a submission the backend will reject.
    |--------------------------------------------------------------------------
    */

    let serverPercentage = 0;

    let serverCompleted = false;

    /*
    |--------------------------------------------------------------------------
    | Debug
    |--------------------------------------------------------------------------
    */

    // eslint-disable-next-line no-console
    console.log(
        'Video tracker initialised'
    );

    /*
    |--------------------------------------------------------------------------
    | Get duration
    |--------------------------------------------------------------------------
    */

    player.getDuration()
        .then(function(duration) {

            videoDuration = Math.floor(
                duration
            );

            // eslint-disable-next-line no-console
            console.log(
                'Duration:',
                videoDuration
            );

            return createSession();
        })
        .then(function(response) {

            sessionCreated = true;

            // eslint-disable-next-line no-console
            console.log(
                'Session created',
                response
            );
        })
        .catch(function(error) {

            // eslint-disable-next-line no-console
            console.error(
                'Session creation failed',
                error
            );
        });

    /*
    |--------------------------------------------------------------------------
    | PLAY
    |--------------------------------------------------------------------------
    */

    player.on('play', function(data) {

        // eslint-disable-next-line no-console
        console.log('PLAY', data);

        isPlaying = true;

        if (currentRangeStart === null) {

            currentRangeStart = Math.floor(
                data.seconds
            );
        }

        lastTime = Math.floor(
            data.seconds
        );
    });

    /*
    |--------------------------------------------------------------------------
    | PAUSE
    |--------------------------------------------------------------------------
    */

    player.on('pause', function(data) {

        // eslint-disable-next-line no-console
        console.log('PAUSE', data);

        isPlaying = false;

        closeRange(
            Math.floor(data.seconds)
        );

        saveProgress(false);
    });

    /*
    |--------------------------------------------------------------------------
    | SEEKED
    |--------------------------------------------------------------------------
    */

    player.on('seeked', function(data) {

        // eslint-disable-next-line no-console
        console.log('SEEKED', data);

        const from = Math.floor(
            data.from
        );

        const to = Math.floor(
            data.seconds
        );

        if (
            currentRangeStart !== null &&
            from > currentRangeStart
        ) {

            closeRange(from);
        }

        currentRangeStart = to;

        lastTime = to;
    });

    /*
    |--------------------------------------------------------------------------
    | TIMEUPDATE
    |--------------------------------------------------------------------------
    */

    player.on('timeupdate', function(data) {

        if (
            !isPlaying ||
            !sessionCreated
        ) {
            return;
        }

        const current = Math.floor(
            data.seconds
        );

        if (
            Math.abs(current - lastTime) > 3
        ) {

            closeRange(lastTime);

            currentRangeStart = current;
        }

        lastTime = current;

        /*
        |--------------------------------------------------------------------------
        | Autosave every 10 seconds, then prompt for the summary as soon as the
        | SERVER confirms the required coverage — no waiting for the video to
        | end. Prompting after the save guarantees the gate uses the server's
        | latest authoritative percentage/completion, not the client estimate.
        |--------------------------------------------------------------------------
        */

        if (
            (Date.now() - lastSave) >
            10000
        ) {

            lastSave = Date.now();

            saveProgress(false).then(function() {
                maybePromptSummary();
            });
        }
    });

    /*
    |--------------------------------------------------------------------------
    | ENDED
    |--------------------------------------------------------------------------
    */

    player.on('ended', async function() {

    // eslint-disable-next-line no-console
    console.log('VIDEO ENDED');

    if (completionHandled) {
        return;
    }

    completionHandled = true;

    isPlaying = false;

    lastTime = Math.floor(videoDuration);

    closeRange(lastTime);

    const result =
        await saveProgress(true);

    // eslint-disable-next-line no-console
    console.log('SUMMARY CHECK', {
        requiresummary:
            config.requiresummary,

        summarysubmitted:
            config.summarysubmitted,

        percentage:
            result.percentage,

        minimum:
            config.minimumwatchpercentage
    });

    maybePromptSummary();
});

    /*
    |--------------------------------------------------------------------------
    | Browser close
    |--------------------------------------------------------------------------
    */

    window.addEventListener(
        'beforeunload',
        function() {

            if (
                currentRangeStart !== null
            ) {

                closeRange(lastTime);
            }

            saveProgress(false);
        }
    );

    /**
     * Close watched range.
     *
     * @param {Number} end
     */
    function closeRange(end) {

        if (
            currentRangeStart === null ||
            end <= currentRangeStart
        ) {
            return;
        }

        watchedRanges.push([
            currentRangeStart,
            end
        ]);

        currentRangeStart = null;
    }

    /**
     * Merge ranges.
     *
     * @param {Array} ranges
     * @returns {Array}
     */
    function mergeRanges(ranges) {

        if (!ranges.length) {
            return [];
        }

        ranges.sort(function(a, b) {

            return a[0] - b[0];
        });

        const merged = [
            [ranges[0][0], ranges[0][1]]
        ];

        for (
            let i = 1;
            i < ranges.length;
            i++
        ) {

            const prev =
                merged[
                    merged.length - 1
                ];

            const curr = ranges[i];

            if (curr[0] <= prev[1]) {

                prev[1] = Math.max(
                    prev[1],
                    curr[1]
                );

            } else {

                merged.push([
                    curr[0],
                    curr[1]
                ]);
            }
        }

        return merged;
    }

    /**
     * Watched seconds including the range currently in progress.
     *
     * Counts the open segment being
     * watched right now, so a continuous viewer's progress is reported to the
     * server (and thus credited) in real time rather than only when a range
     * closes on pause/seek/end.
     *
     * @returns {Number}
     */
    function liveWatchedSeconds() {

        if (videoDuration <= 0) {
            return 0;
        }

        const ranges = watchedRanges.slice();

        if (
            currentRangeStart !== null &&
            lastTime > currentRangeStart
        ) {

            ranges.push([currentRangeStart, lastTime]);
        }

        const merged = mergeRanges(ranges);

        let total = 0;

        merged.forEach(function(range) {

            total += (range[1] - range[0]);
        });

        return Math.min(total, videoDuration);
    }

    /**
     * Show the summary modal once the required coverage is reached.
     *
     * Gated on the SERVER-authoritative watch state (updated by saveProgress
     * from update_session.php's response), which is exactly what the submit
     * endpoint enforces. This guarantees the button never appears for a
     * submission the backend would reject. Safe to call repeatedly; it
     * triggers at most once per session.
     */
    function maybePromptSummary() {

        if (!config.requiresummary) {
            return;
        }

        if (config.summarysubmitted && !config.canresubmit) {
            return;
        }

        if (
            !serverCompleted ||
            serverPercentage < config.minimumwatchpercentage
        ) {
            return;
        }

        // Once the required coverage is reached, expose a persistent button so
        // the student can reopen the modal if they close it — no reload or
        // re-watch needed.
        ensureSummaryButton();

        if (summaryPrompted) {
            return;
        }

        summaryPrompted = true;

        // Pause so the recording is not playing behind the modal.
        if (player && typeof player.pause === 'function') {
            player.pause().catch(function() {});
        }

        showSummaryModal(config, sessionuuid);
    }

    /**
     * Ensure a persistent "Write summary" button exists.
     *
     * Lets the student reopen the summary modal after closing it, without
     * reloading the page or re-watching the recording. Safe to call
     * repeatedly; the button is created at most once.
     */
    function ensureSummaryButton() {

        if (document.getElementById('videotracker-summary-button')) {
            return;
        }

        const btn = document.createElement('button');

        btn.id          = 'videotracker-summary-button';
        btn.type        = 'button';
        btn.className   = 'vtk-reopen-btn';
        btn.textContent = config.canresubmit
            ? '✍ Edit & resubmit summary'
            : '✍ Write your summary';

        btn.addEventListener('click', function() {
            showSummaryModal(config, sessionuuid);
        });

        document.body.appendChild(btn);
    }

    /**
     * Create session.
     *
     * @returns {Promise}
     */
    async function createSession() {

        // eslint-disable-next-line no-console
        console.log(
            'Calling start_session.php'
        );

        const response = await fetch(

            config.wwwroot +
            '/local/videotracker/ajax/start_session.php',

            {

                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type':
                        'application/json'
                },

                body: JSON.stringify({

                    sesskey:
                        M.cfg.sesskey,

                    sessionuuid:
                        sessionuuid,

                    videoid:
                        config.videoid,

                    cmid:
                        config.cmid,

                    courseid:
                        config.courseid,

                    videoduration:
                        videoDuration
                })
            }
        );

        return await response.json();
    }

    /**
     * Save progress.
     *
     * @param {Boolean} completed
     * @returns {Promise<Object>}
     */
    async function saveProgress(completed) {

        if (!sessionCreated) {

            return {
                percentage: 0
            };
        }

        if (videoDuration <= 0) {

            return {
                percentage: serverPercentage,
                completed: serverCompleted
            };
        }

        // Include the segment currently being watched so a continuous viewer
        // is credited in real time. Inflating this by seeking does not help:
        // update_session.php caps the accepted watch time to elapsed real time.
        const watched =
            liveWatchedSeconds();

        const percentage = Math.min(

            100,

            parseFloat(
                (
                    (
                        watched /
                        videoDuration
                    ) * 100
                ).toFixed(2)
            )
        );

        // eslint-disable-next-line no-console
        console.log(
            'Saving progress',
            watched,
            percentage
        );

        try {

            const response = await fetch(

                config.wwwroot +
                '/local/videotracker/ajax/update_session.php',

                {

                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type':
                            'application/json'
                    },

                    body: JSON.stringify({

                        sesskey:
                            M.cfg.sesskey,

                        sessionuuid:
                            sessionuuid,

                        watchedseconds:
                            watched,

                        percentage:
                            percentage,

                        completed:
                            completed ? 1 : 0
                    })
                }
            );

            // Trust the server's derived values — they include the anti-scrub
            // cap and are exactly what the submit endpoint checks against.
            const result = await response.json();

            if (result && result.success) {

                serverPercentage =
                    parseFloat(result.percentage) || 0;

                serverCompleted =
                    Number(result.completed) === 1;
            }

        } catch (error) {

            // eslint-disable-next-line no-console
            console.error(
                'Save failed',
                error
            );
        }

        return {
            percentage: serverPercentage,
            completed: serverCompleted
        };
    }
}

/**
 * Show summary submission modal.
 *
 * @param {Object} config
 * @param {String} sessionuuid
 */
function showSummaryModal(config, sessionuuid) {

    if (document.getElementById('videotracker-summary-modal')) {
        return;
    }

    // ── Overlay ───────────────────────────────────────────────────
    const overlay = document.createElement('div');
    overlay.id        = 'videotracker-summary-modal';
    overlay.className = 'vtk-overlay';

    // ── Modal card ────────────────────────────────────────────────
    const modal = document.createElement('div');
    modal.className = 'vtk-modal';

    // Close button
    const closeBtn = document.createElement('button');
    closeBtn.className   = 'vtk-close';
    closeBtn.textContent = '×';
    closeBtn.setAttribute('aria-label', 'Close');
    closeBtn.addEventListener('click', () => overlay.remove());

    // Icon
    const icon = document.createElement('div');
    icon.className   = 'vtk-modal-icon';
    icon.textContent = '🎬';

    // A rejected summary is being edited and resubmitted.
    const isResubmit = config.canresubmit === true;

    // Heading
    const heading = document.createElement('h2');
    heading.className   = 'vtk-modal-title';
    heading.textContent = isResubmit ? 'Edit & Resubmit Your Summary' : 'Write Your Summary';

    // Description
    const description = document.createElement('p');
    description.className   = 'vtk-modal-desc';
    description.textContent = isResubmit
        ? 'Your previous summary was rejected. You may edit and resubmit it once. Minimum 150 words required.'
        : 'Summarise what you learned in this recording. Minimum 150 words required.';

    // Show the faculty's rejection reason when resubmitting.
    if (isResubmit && config.summaryfeedback) {

        const reason = document.createElement('p');
        reason.className = 'vtk-modal-desc';
        reason.style.color = '#b00020';
        reason.textContent = 'Reason for rejection: ' + config.summaryfeedback;
        description.insertAdjacentElement('afterend', reason);
    }

    // Textarea
    const textarea = document.createElement('textarea');
    textarea.className   = 'vtk-textarea';
    textarea.rows        = 10;
    textarea.placeholder = 'Start writing your summary here…';

    // Prefill with the previous text so the student can edit it.
    if (isResubmit && config.previoussummary) {
        textarea.value = config.previoussummary;
    }

    // Word-count progress bar
    const counterWrap = document.createElement('div');
    counterWrap.className = 'vtk-counter-wrap';

    const counterBar  = document.createElement('div');
    counterBar.className = 'vtk-counter-bar';
    const counterFill = document.createElement('div');
    counterFill.className = 'vtk-counter-fill';
    counterBar.appendChild(counterFill);

    const counter = document.createElement('div');
    counter.className   = 'vtk-counter-text';
    counter.textContent = '0 / 150 words';

    counterWrap.appendChild(counterBar);
    counterWrap.appendChild(counter);

    // Error / success messages
    const error   = document.createElement('div');
    error.className = 'vtk-error';

    const success = document.createElement('div');
    success.className = 'vtk-success';

    // Submit button
    const submit = document.createElement('button');
    submit.className   = 'vtk-submit';
    submit.textContent = 'Submit Summary';

    /*
    |--------------------------------------------------------------------------
    | Word count helper
    |--------------------------------------------------------------------------
    */
    /**
     * Get word count.
     *
     * @param {String} text
     * @returns {Number}
     */
    function getWordCount(text) {

        const trimmed = text.trim();

        if (!trimmed) {
            return 0;
        }

        return trimmed
            .split(/\s+/)
            .length;
    }

    /*
    |--------------------------------------------------------------------------
    | Live counter
    |--------------------------------------------------------------------------
    */

    textarea.addEventListener('input', function() {
        const count = getWordCount(textarea.value);
        const pct   = Math.min(100, Math.round((count / 150) * 100));

        counter.textContent        = count + ' / 150 words';
        counterFill.style.width    = pct + '%';
        counterFill.className      = 'vtk-counter-fill' +
            (count >= 150 ? ' good' : count >= 75 ? ' warn' : '');

        error.textContent   = '';
        success.textContent = '';
    });

    // Reflect any prefilled (resubmit) text in the counter immediately.
    if (textarea.value) {
        textarea.dispatchEvent(new Event('input'));
    }

    /*
    |--------------------------------------------------------------------------
    | Submit summary
    |--------------------------------------------------------------------------
    */

    submit.addEventListener(
        'click',
        async function() {

            const summary =
                textarea.value.trim();

            const wordcount =
                getWordCount(summary);

            error.textContent = '';
            success.textContent = '';

            /*
            |--------------------------------------------------------------------------
            | Validation
            |--------------------------------------------------------------------------
            */

            if (!summary) {

                error.textContent =
                    'Summary cannot be empty.';

                return;
            }

            if (wordcount < 150) {

                error.textContent =
                    'Minimum 150 words required.';

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Lock UI
            |--------------------------------------------------------------------------
            */

            submit.disabled = true;

            submit.textContent =
                'Submitting...';

            textarea.disabled = true;

            try {

                const response =
                    await fetch(

                        config.wwwroot +
                        '/local/videotracker/ajax/submit_summary.php',

                        {

                            method: 'POST',

                            credentials:
                                'same-origin',

                            headers: {
                                'Content-Type':
                                    'application/json'
                            },

                            body: JSON.stringify({

                                sesskey:
                                    M.cfg.sesskey,

                                sessionuuid:
                                    sessionuuid,

                                cmid:
                                    config.cmid,

                                courseid:
                                    config.courseid,

                                videoid:
                                    config.videoid,

                                zoommeetingid:
                                    config.zoommeetingid,

                                summary:
                                    summary
                            })
                        }
                    );

                /*
                |--------------------------------------------------------------------------
                | Parse response defensively
                |
                | A non-JSON body (e.g. a session-timeout login redirect) must
                | not surface as an opaque "Unexpected token" error.
                |--------------------------------------------------------------------------
                */

                const raw = await response.text();

                let result;

                try {
                    result = JSON.parse(raw);
                } catch (parseError) {
                    throw new Error(
                        'Could not reach the server. Please refresh the ' +
                        'page and try again.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Backend failure
                |
                | Surface the backend's specific reason. `message` is our own
                | validation text (e.g. "Minimum watch percentage not met.");
                | `error` is Moodle's exception shape when the script throws.
                |--------------------------------------------------------------------------
                */

                if (!result.success) {

                    throw new Error(
                        result.message ||
                        result.error ||
                        'Submission failed. Please try again.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Success
                |--------------------------------------------------------------------------
                */

                success.textContent = '✓ Summary submitted successfully!';

                // Submission is done — remove the reopen button and mark the
                // config so a later replay cannot re-create it.
                config.summarysubmitted = true;
                config.canresubmit = false;

                const reopenBtn = document.getElementById('videotracker-summary-button');
                if (reopenBtn) {
                    reopenBtn.remove();
                }

                setTimeout(() => overlay.remove(), 1400);

            } catch (err) {
                error.textContent = err.message || 'Submission failed. Please try again.';
                submit.disabled      = false;
                submit.textContent   = 'Submit Summary';
                textarea.disabled    = false;
            }
        }
    );

    // ── Assemble modal ────────────────────────────────────────────
    modal.appendChild(closeBtn);
    modal.appendChild(icon);
    modal.appendChild(heading);
    modal.appendChild(description);
    modal.appendChild(textarea);
    modal.appendChild(counterWrap);
    modal.appendChild(error);
    modal.appendChild(success);
    modal.appendChild(submit);

    overlay.appendChild(modal);
    document.body.appendChild(overlay);

    // Focus textarea immediately for accessibility.
    setTimeout(() => textarea.focus(), 50);
}

/**
 * Generate UUID.
 *
 * @returns {String}
 */
function generateUuid() {

    if (
        window.crypto &&
        window.crypto.randomUUID
    ) {

        return window.crypto.randomUUID();
    }

    return (
        'vt-' +
        Date.now() +
        '-' +
        Math.random()
            .toString(16)
            .substring(2)
    );
}