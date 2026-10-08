/**
 * Live Session Stats — attendance detail modal.
 *
 * Markup carries classes only; everything visual lives in the block's
 * stylesheet so the modal follows the host theme's design tokens instead of
 * the inline colours this used to hardcode.
 *
 * @module     block_livesessionstats/viewmore
 */

define(['jquery', 'core/ajax', 'core/modal_factory', 'core/modal_events'],
function($, Ajax, ModalFactory, ModalEvents) {

    const PER_PAGE = 8;

    /**
     * Escape text coming from the server before it goes into markup.
     *
     * @param {string} text
     * @returns {string}
     */
    const esc = function(text) {
        return $('<div>').text(text === null || text === undefined ? '' : text).html();
    };

    /**
     * Tier for an attendance percentage, matching the block's own thresholds.
     *
     * @param {number} pct
     * @returns {string}
     */
    const tierFor = function(pct) {
        if (pct >= 75) {
            return 'good';
        }
        return pct >= 50 ? 'warn' : 'danger';
    };

    /**
     * Status pill. The server sends a human string; map it to a modifier so
     * the colour is not decided by matching prose in CSS.
     *
     * @param {string} status
     * @returns {string}
     */
    const pill = function(status) {
        let mod = 'missed';
        if (status.indexOf('Attended (Live') === 0) {
            mod = 'live';
        } else if (status.indexOf('Attended (') === 0) {
            mod = 'summary';
        } else if (status === 'Pending') {
            mod = 'pending';
        } else if (status === 'Rejected') {
            mod = 'rejected';
        }
        return '<span class="lsd-pill lsd-pill--' + mod + '">' + esc(status) + '</span>';
    };

    /**
     * The whole modal body for one page of sessions.
     *
     * @param {Array} sessions
     * @param {number} page
     * @returns {string}
     */
    const renderBody = function(sessions, page) {
        const total = sessions.length;
        const attended = sessions.filter(function(s) {
            return String(s.status).indexOf('Attended') === 0;
        }).length;
        const overall = total > 0 ? Math.round((attended / total) * 100) : 0;

        let html = '<div class="lsd-wrap">';

        html += '<div class="lsd-summary">' +
            '<span><b>' + total + '</b> sessions</span>' +
            '<span><b>' + attended + '</b> attended</span>' +
            '<span><b>' + overall + '%</b> attendance</span>' +
            '</div>';

        if (total === 0) {
            return html + '<div class="lsd-none">No live sessions have been held yet.</div></div>';
        }

        const rows = sessions.slice((page - 1) * PER_PAGE, page * PER_PAGE);

        html += '<div class="lsd-scroll"><table class="lsd-table"><thead><tr>' +
            '<th>Session</th>' +
            '<th>Attended / Total</th>' +
            '<th>Attendance</th>' +
            '<th>Status</th>' +
            '<th></th>' +
            '</tr></thead><tbody>';

        rows.forEach(function(s) {
            // No attendance record at all: show dashes rather than zeros, which
            // would read as "watched none of it" instead of "never joined".
            const absent = Number(s.hasrecord) === 0;
            const pct = Number(s.percentage) || 0;

            html += '<tr>';
            html += '<td><div class="lsd-name">' + esc(s.name) + '</div>' +
                '<div class="lsd-date">' + esc(s.date) + '</div></td>';

            html += '<td class="lsd-num">' + (absent
                ? '<span class="lsd-dash">&mdash;</span>'
                : esc(s.attendedduration) + ' / ' + esc(s.sessionduration) + ' min') + '</td>';

            html += '<td><div class="lsd-pctwrap">' + (absent
                ? '<span class="lsd-dash">&mdash;</span>'
                : '<div class="lsd-pctval">' + pct + '%</div>' +
                  '<div class="lsd-pctbar lsd-pctbar--' + tierFor(pct) + '">' +
                  '<i style="width:' + Math.min(100, pct) + '%"></i></div>') +
                '</div></td>';

            html += '<td>' + pill(String(s.status)) + '</td>';

            html += '<td>' + (s.recordingurl
                ? '<a class="lsd-watch" href="' + esc(s.recordingurl) + '">' +
                  '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>' +
                  'Watch recording</a>'
                : '') + '</td>';

            html += '</tr>';
        });

        html += '</tbody></table></div>';

        const pages = Math.ceil(total / PER_PAGE);
        if (pages > 1) {
            html += '<div class="lsd-pager">';
            for (let i = 1; i <= pages; i++) {
                html += '<button type="button" class="lsd-page" data-page="' + i + '"' +
                    (i === page ? ' aria-current="page"' : '') + '>' + i + '</button>';
            }
            html += '</div>';
        }

        return html + '</div>';
    };

    return {

        /**
         * Bind the "view more" link.
         */
        init: function() {

            $(document).on('click', '.livesession-viewmore', function(e) {
                e.preventDefault();

                const courseid = $(this).data('courseid');

                Ajax.call([{
                    methodname: 'block_livesessionstats_get_sessions',
                    args: {courseid: courseid}
                }])[0].done(function(response) {

                    const sessions = response.sessions || [];
                    let currentPage = 1;

                    ModalFactory.create({
                        title: 'Live session attendance',
                        body: renderBody(sessions, currentPage),
                        large: true
                    }).then(function(modal) {

                        modal.show();

                        modal.getRoot().on('click', '.lsd-page', function() {
                            currentPage = $(this).data('page');
                            modal.setBody(renderBody(sessions, currentPage));
                        });

                        modal.getRoot().on(ModalEvents.hidden, function() {
                            modal.destroy();
                        });

                        return modal;
                    }).catch(function() {
                        return;
                    });

                }).fail(function() {
                    // Deliberately not alert(): a browser dialog blocks the page
                    // and tells the student nothing useful.
                    ModalFactory.create({
                        title: 'Live session attendance',
                        body: '<div class="lsd-wrap"><div class="lsd-none">' +
                              'Could not load attendance just now. Please try again.' +
                              '</div></div>'
                    }).then(function(modal) {
                        modal.show();
                        return modal;
                    }).catch(function() {
                        return;
                    });
                });

            });
        }
    };
});
