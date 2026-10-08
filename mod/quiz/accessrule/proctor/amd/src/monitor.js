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
 * Proctoring orchestrator. Owns the camera/mic/screen streams, the periodic
 * webcam capture, the start-of-exam fingerprint and base tab/blur/fullscreen
 * events, and composes the face / audio / screen / lockdown checkers. Exposes an
 * API the local_exams runner uses for its pre-exam system check.
 *
 * Never blocks the exam — every capture/permission failure is logged, not enforced.
 *
 * @module     quizaccess_proctor/monitor
 * @copyright  2026 JAIN Online
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'core/ajax',
    'quizaccess_proctor/facecheck',
    'quizaccess_proctor/audiocheck',
    'quizaccess_proctor/screencheck',
    'quizaccess_proctor/lockdown'
], function(Ajax, FaceCheck, AudioCheck, ScreenCheck, Lockdown) {

    var cfg = {};
    var camStream = null;
    var micStream = null;
    var screenStream = null;
    var video = null;
    var canvas = null;
    var captureTimer = null;
    var started = false;

    var grab = function() {
        if (!video || video.videoWidth === 0) {
            return null;
        }
        var w = 480;
        var h = Math.round(video.videoHeight / video.videoWidth * w) || 360;
        canvas.width = w;
        canvas.height = h;
        canvas.getContext('2d').drawImage(video, 0, 0, w, h);
        try {
            return canvas.toDataURL('image/jpeg', 0.6);
        } catch (e) {
            return null;
        }
    };

    var report = {
        event: function(type, severity, detail) {
            if (!cfg.attemptid) {
                return;
            }
            Ajax.call([{
                methodname: 'quizaccess_proctor_log_event',
                args: {
                    attemptid: cfg.attemptid,
                    eventtype: type,
                    occurredat: Math.floor(Date.now() / 1000),
                    detail: detail || '',
                    severity: (severity === undefined || severity === null) ? 1 : severity
                }
            }])[0].catch(function() {
                return null;
            });
        },
        rawSnapshot: function(type, dataurl) {
            if (!cfg.attemptid || !dataurl) {
                return;
            }
            Ajax.call([{
                methodname: 'quizaccess_proctor_upload_snapshot',
                args: {attemptid: cfg.attemptid, imagedata: dataurl, type: type,
                    capturedat: Math.floor(Date.now() / 1000)}
            }])[0].catch(function() {
                return null;
            });
        },
        snapshot: function(type) {
            report.rawSnapshot(type || 'periodic', grab());
        }
    };

    var buildCameraVideo = function(stream) {
        video = document.createElement('video');
        video.autoplay = true;
        video.muted = true;
        video.playsInline = true;
        video.srcObject = stream;
        video.style.position = 'fixed';
        video.style.width = '1px';
        video.style.height = '1px';
        video.style.opacity = '0';
        video.style.pointerEvents = 'none';
        document.body.appendChild(video);
        canvas = document.createElement('canvas');
    };

    var fingerprint = function() {
        var fp = {
            ua: navigator.userAgent,
            screen: window.screen.width + 'x' + window.screen.height,
            extended: !!window.screen.isExtended,
            tz: (Intl.DateTimeFormat().resolvedOptions().timeZone || ''),
            cores: navigator.hardwareConcurrency || 0,
            lang: navigator.language
        };
        report.event('fingerprint', 0, JSON.stringify(fp));
    };

    var api = {
        setConfig: function(c) {
            cfg = c || {};
        },

        acquireCamera: function() {
            if (camStream) {
                return Promise.resolve(camStream);
            }
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                return Promise.reject(new Error('nocamera'));
            }
            return navigator.mediaDevices.getUserMedia({video: true, audio: false}).then(function(s) {
                camStream = s;
                buildCameraVideo(s);
                s.getVideoTracks().forEach(function(t) {
                    t.addEventListener('ended', function() {
                        report.event('cameralost', 2);
                    });
                });
                return s;
            });
        },

        acquireMic: function() {
            if (micStream) {
                return Promise.resolve(micStream);
            }
            return navigator.mediaDevices.getUserMedia({audio: true, video: false}).then(function(s) {
                micStream = s;
                return s;
            });
        },

        acquireScreen: function() {
            if (screenStream) {
                return Promise.resolve(screenStream);
            }
            if (!navigator.mediaDevices || !navigator.mediaDevices.getDisplayMedia) {
                return Promise.reject(new Error('noscreen'));
            }
            return navigator.mediaDevices.getDisplayMedia({video: true, audio: false}).then(function(s) {
                screenStream = s;
                return s;
            });
        },

        /**
         * Mirror the live camera into a visible element (runner webcam thumbnail).
         * @param {HTMLElement} el
         */
        attachPreview: function(el) {
            if (el && camStream) {
                el.srcObject = camStream;
                el.muted = true;
                el.autoplay = true;
                el.playsInline = true;
                var p = el.play();
                if (p && p.catch) {
                    p.catch(function() {
                        return null;
                    });
                }
            }
        },

        /**
         * Capture + upload the identity photo. Resolves face count (-1 if unknown).
         * @return {Promise<Number>}
         */
        captureIdentity: function() {
            return api.acquireCamera().then(function() {
                return new Promise(function(resolve) {
                    var go = function() {
                        report.rawSnapshot('identity', grab());
                        if (cfg.facedetect) {
                            FaceCheck.countOnce(video).then(resolve);
                        } else {
                            resolve(-1);
                        }
                    };
                    if (video.videoWidth > 0) {
                        go();
                    } else {
                        video.addEventListener('loadeddata', function() {
                            window.setTimeout(go, 400);
                        });
                    }
                });
            });
        },

        /** Begin ongoing monitoring (after streams acquired / identity captured). */
        begin: function() {
            if (started || !cfg.attemptid) {
                return;
            }
            started = true;

            var interval = Math.max(15, cfg.interval || 60) * 1000;
            captureTimer = window.setInterval(function() {
                if (!document.hidden) {
                    report.snapshot('periodic');
                }
            }, interval);

            document.addEventListener('visibilitychange', function() {
                report.event(document.hidden ? 'hidden' : 'visible', document.hidden ? 2 : 0);
            });
            window.addEventListener('blur', function() {
                report.event('blur', 2);
            });
            window.addEventListener('focus', function() {
                report.event('focus', 0);
            });

            if (cfg.facedetect && video) {
                FaceCheck.start(video, report, 4000);
            }
            if (cfg.audiomonitor && micStream) {
                AudioCheck.start(micStream, report);
            }
            if (cfg.screenrecord && screenStream) {
                ScreenCheck.start(screenStream, report, 45);
            }
            if (cfg.lockdown) {
                Lockdown.start(report);
            }
            fingerprint();

            window.addEventListener('beforeunload', api.stop);
        },

        /**
         * Native attempt-page path: acquire everything + start, best-effort.
         * @param {Object} c Monitor config (attemptid, interval, toggles).
         */
        init: function(c) {
            api.setConfig(c);
            if (!cfg.attemptid) {
                return;
            }
            api.captureIdentity().catch(function() {
                report.event('cameradenied', 2);
            }).then(function() {
                var jobs = [];
                if (cfg.audiomonitor) {
                    jobs.push(api.acquireMic().catch(function() {
                        report.event('micdenied', 1);
                    }));
                }
                if (cfg.screenrecord) {
                    jobs.push(api.acquireScreen().catch(function() {
                        report.event('screendenied', 2);
                    }));
                }
                return Promise.all(jobs);
            }).then(function() {
                api.begin();
            });
        },

        getVideo: function() {
            return video;
        },

        stop: function() {
            if (captureTimer) {
                window.clearInterval(captureTimer);
            }
            FaceCheck.stop();
            AudioCheck.stop();
            ScreenCheck.stop();
            Lockdown.stop();
            [camStream, micStream, screenStream].forEach(function(s) {
                if (s) {
                    s.getTracks().forEach(function(t) {
                        t.stop();
                    });
                }
            });
        }
    };

    return api;
});
