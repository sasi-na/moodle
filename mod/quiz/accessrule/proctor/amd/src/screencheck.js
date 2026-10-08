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
 * Periodic screen-frame capture from a getDisplayMedia stream, and detection of
 * the student stopping the share. Frames are uploaded as 'screen' snapshots.
 *
 * @module     quizaccess_proctor/screencheck
 * @copyright  2026 JAIN Online
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    var video = null;
    var canvas = null;
    var timer = null;
    var report = null;

    var grab = function() {
        if (!video || video.videoWidth === 0) {
            return;
        }
        var w = 800;
        var h = Math.round(video.videoHeight / video.videoWidth * w) || 600;
        canvas.width = w;
        canvas.height = h;
        canvas.getContext('2d').drawImage(video, 0, 0, w, h);
        var data;
        try {
            data = canvas.toDataURL('image/jpeg', 0.5);
        } catch (e) {
            return;
        }
        report.rawSnapshot('screen', data);
    };

    return {
        /**
         * @param {MediaStream} screenStream
         * @param {Object} reporter {rawSnapshot, event} sink.
         * @param {Number} intervalSec capture interval.
         * @return {Boolean} started.
         */
        start: function(screenStream, reporter, intervalSec) {
            if (!screenStream) {
                return false;
            }
            report = reporter;
            video = document.createElement('video');
            video.autoplay = true;
            video.muted = true;
            video.srcObject = screenStream;
            canvas = document.createElement('canvas');

            var track = screenStream.getVideoTracks()[0];
            if (track) {
                track.addEventListener('ended', function() {
                    report.event('screensharestopped', 2);
                });
            }

            video.addEventListener('loadeddata', function() {
                window.setTimeout(grab, 1000);
            });
            timer = window.setInterval(grab, Math.max(20, intervalSec || 45) * 1000);
            return true;
        },

        stop: function() {
            if (timer) {
                window.clearInterval(timer);
            }
            timer = null;
        }
    };
});
