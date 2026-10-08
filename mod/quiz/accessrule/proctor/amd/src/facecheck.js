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
 * In-browser face checks: no-face, multiple-faces and looking-away. Uses the
 * native FaceDetector API when available, else loads TF.js BlazeFace from a CDN,
 * else degrades gracefully (logs that face checks are unavailable, exam continues).
 *
 * @module     quizaccess_proctor/facecheck
 * @copyright  2026 JAIN Online
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    var TF = 'https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.20.0/dist/tf.min.js';
    var BF = 'https://cdn.jsdelivr.net/npm/@tensorflow-models/blazeface@0.1.0/dist/blazeface.min.js';

    var mode = 'none';   // 'native' | 'blazeface' | 'none'
    var detector = null; // FaceDetector or blazeface model.
    var video = null;
    var report = null;
    var timer = null;
    var noFaceStreak = 0;
    var cooldown = {};

    var loadScript = function(src) {
        return new Promise(function(resolve, reject) {
            var s = document.createElement('script');
            s.src = src;
            s.onload = resolve;
            s.onerror = reject;
            document.head.appendChild(s);
        });
    };

    var loadModel = function() {
        if ('FaceDetector' in window) {
            try {
                detector = new window.FaceDetector({fastMode: true, maxDetectedFaces: 5});
                mode = 'native';
                return Promise.resolve(true);
            } catch (e) {
                detector = null;
            }
        }
        return loadScript(TF).then(function() {
            return loadScript(BF);
        }).then(function() {
            return window.blazeface.load();
        }).then(function(model) {
            detector = model;
            mode = 'blazeface';
            return true;
        }).catch(function() {
            mode = 'none';
            if (report) {
                report.event('faceunavailable', 0, 'Face model could not load');
            }
            return false;
        });
    };

    var detect = function() {
        if (mode === 'native') {
            return detector.detect(video).catch(function() {
                return [];
            });
        }
        if (mode === 'blazeface') {
            return detector.estimateFaces(video, false).catch(function() {
                return [];
            });
        }
        return Promise.resolve([]);
    };

    var flag = function(type, severity, detail) {
        var now = Math.floor(Date.now() / 1000);
        if (cooldown[type] && (now - cooldown[type]) < 15) {
            return;
        }
        cooldown[type] = now;
        report.event(type, severity, detail || '');
        if (severity >= 2) {
            report.snapshot('flagged');
        }
    };

    var centerX = function(face) {
        // Native: face.boundingBox {x,width}; blazeface: topLeft/bottomRight arrays.
        if (face.boundingBox) {
            return (face.boundingBox.x + face.boundingBox.width / 2);
        }
        if (face.topLeft && face.bottomRight) {
            return (face.topLeft[0] + face.bottomRight[0]) / 2;
        }
        return null;
    };

    var analyse = function(faces) {
        var n = faces ? faces.length : 0;
        if (n === 0) {
            noFaceStreak++;
            if (noFaceStreak >= 2) {
                flag('noface', 2, 'No face visible');
            }
            return;
        }
        noFaceStreak = 0;
        if (n > 1) {
            flag('multiface', 3, n + ' faces');
            return;
        }
        var cx = centerX(faces[0]);
        var w = video.videoWidth || 640;
        if (cx !== null && (cx < w * 0.2 || cx > w * 0.8)) {
            flag('lookingaway', 1, 'Off-centre');
        }
    };

    return {
        /**
         * @param {HTMLVideoElement} videoEl Live camera element.
         * @param {Object} reporter {event, snapshot} sink.
         * @param {Number} intervalMs inference cadence.
         */
        start: function(videoEl, reporter, intervalMs) {
            video = videoEl;
            report = reporter;
            loadModel().then(function(ok) {
                if (!ok) {
                    return;
                }
                timer = window.setInterval(function() {
                    if (video && video.videoWidth > 0) {
                        detect().then(analyse);
                    }
                }, intervalMs || 4000);
            });
        },

        /**
         * One-shot face count for the identity capture gate.
         *
         * @param {HTMLVideoElement} videoEl
         * @return {Promise<Number>} face count (-1 if unavailable).
         */
        countOnce: function(videoEl) {
            video = videoEl;
            return loadModel().then(function(ok) {
                if (!ok) {
                    return -1;
                }
                return detect().then(function(faces) {
                    return faces ? faces.length : 0;
                });
            });
        },

        stop: function() {
            if (timer) {
                window.clearInterval(timer);
            }
            timer = null;
        }
    };
});
