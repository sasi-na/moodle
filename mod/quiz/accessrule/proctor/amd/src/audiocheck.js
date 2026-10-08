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
 * Microphone-level monitoring: flags sustained voice/noise. Analyses RMS level
 * only — no audio is recorded or uploaded.
 *
 * @module     quizaccess_proctor/audiocheck
 * @copyright  2026 JAIN Online
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    var ctx = null;
    var raf = null;
    var report = null;
    var loud = 0;
    var lastFlag = 0;

    // RMS above this (0..1) counts as "sound present".
    var THRESHOLD = 0.12;
    // Consecutive loud samples (~ frames) before flagging.
    var SUSTAIN = 25;
    // Min seconds between voice flags.
    var COOLDOWN = 20;

    return {
        /**
         * @param {MediaStream} micStream
         * @param {Object} reporter {event} sink.
         * @return {Boolean} started.
         */
        start: function(micStream, reporter) {
            if (!micStream || !(window.AudioContext || window.webkitAudioContext)) {
                return false;
            }
            report = reporter;
            var AC = window.AudioContext || window.webkitAudioContext;
            ctx = new AC();
            var src = ctx.createMediaStreamSource(micStream);
            var analyser = ctx.createAnalyser();
            analyser.fftSize = 512;
            src.connect(analyser);
            var buf = new Uint8Array(analyser.fftSize);

            var tick = function() {
                analyser.getByteTimeDomainData(buf);
                var sum = 0;
                for (var i = 0; i < buf.length; i++) {
                    var v = (buf[i] - 128) / 128;
                    sum += v * v;
                }
                var rms = Math.sqrt(sum / buf.length);
                if (rms > THRESHOLD) {
                    loud++;
                    var now = Math.floor(Date.now() / 1000);
                    if (loud >= SUSTAIN && (now - lastFlag) > COOLDOWN) {
                        lastFlag = now;
                        report.event('audiovoice', 1, 'level ' + rms.toFixed(2));
                    }
                } else {
                    loud = Math.max(0, loud - 2);
                }
                raf = window.requestAnimationFrame(tick);
            };
            tick();
            return true;
        },

        stop: function() {
            if (raf) {
                window.cancelAnimationFrame(raf);
            }
            if (ctx && ctx.close) {
                ctx.close();
            }
            ctx = null;
        }
    };
});
