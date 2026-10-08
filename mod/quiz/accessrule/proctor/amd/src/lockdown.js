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
 * Browser-lockdown checkpoints: block + log copy/paste/right-click/dev-tools/key
 * shortcuts, force fullscreen re-entry, detect multiple monitors, network drops
 * and inactivity. Logging only — never blocks the exam.
 *
 * @module     quizaccess_proctor/lockdown
 * @copyright  2026 JAIN Online
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    var report = null;
    var handlers = [];
    var inactivityTimer = null;
    var INACTIVITY_MS = 120000;

    var on = function(target, type, fn, opts) {
        target.addEventListener(type, fn, opts || false);
        handlers.push([target, type, fn, opts || false]);
    };

    var resetInactivity = function() {
        if (inactivityTimer) {
            window.clearTimeout(inactivityTimer);
        }
        inactivityTimer = window.setTimeout(function() {
            report.event('inactivity', 1, 'No input for 2 minutes');
        }, INACTIVITY_MS);
    };

    return {
        /**
         * @param {Object} reporter {event, snapshot} sink from the monitor.
         */
        start: function(reporter) {
            report = reporter;

            // Clipboard + context menu + selection — block and log.
            ['copy', 'cut', 'paste'].forEach(function(t) {
                on(document, t, function(e) {
                    e.preventDefault();
                    report.event('clipboard', 2, t);
                });
            });
            on(document, 'contextmenu', function(e) {
                e.preventDefault();
                report.event('rightclick', 1);
            });
            on(document, 'selectstart', function(e) {
                e.preventDefault();
            });

            // Suspicious key combos.
            on(document, 'keydown', function(e) {
                var k = (e.key || '').toLowerCase();
                var combo = (e.ctrlKey || e.metaKey);
                if (e.key === 'PrintScreen') {
                    report.event('printscreen', 2);
                } else if (e.key === 'F12' || (combo && e.shiftKey && (k === 'i' || k === 'j' || k === 'c'))) {
                    e.preventDefault();
                    report.event('devtoolskey', 3);
                } else if (combo && (k === 'c' || k === 'v' || k === 'x' || k === 'p' || k === 's' ||
                        k === 't' || k === 'n' || k === 'u' || k === 'w')) {
                    e.preventDefault();
                    report.event('keyshortcut', 2, (e.metaKey ? 'Meta+' : 'Ctrl+') + k);
                }
                resetInactivity();
            });
            on(document, 'mousemove', resetInactivity);

            // Fullscreen exit — log and re-request.
            on(document, 'fullscreenchange', function() {
                if (!document.fullscreenElement) {
                    report.event('fullscreenexit', 2);
                    window.setTimeout(function() {
                        var el = document.getElementById('local-exams-runner') || document.documentElement;
                        if (el.requestFullscreen) {
                            el.requestFullscreen().catch(function() {
                                return null;
                            });
                        }
                    }, 800);
                }
            });

            // Multiple monitors.
            if (window.screen && window.screen.isExtended) {
                report.event('multimonitor', 2, 'Extended display detected');
            }

            // Window resize + network.
            on(window, 'resize', function() {
                report.event('resize', 1, window.innerWidth + 'x' + window.innerHeight);
            });
            on(window, 'offline', function() {
                report.event('offline', 1);
            });
            on(window, 'online', function() {
                report.event('online', 0);
            });

            // Dev-tools open heuristic (large outer/inner gap).
            var devtoolsOpen = false;
            window.setInterval(function() {
                var gap = Math.abs(window.outerWidth - window.innerWidth) > 200 ||
                    Math.abs(window.outerHeight - window.innerHeight) > 200;
                if (gap && !devtoolsOpen) {
                    devtoolsOpen = true;
                    report.event('devtoolsopen', 3);
                } else if (!gap) {
                    devtoolsOpen = false;
                }
            }, 3000);

            resetInactivity();
        },

        stop: function() {
            handlers.forEach(function(h) {
                h[0].removeEventListener(h[1], h[2], h[3]);
            });
            handlers = [];
            if (inactivityTimer) {
                window.clearTimeout(inactivityTimer);
            }
        }
    };
});
