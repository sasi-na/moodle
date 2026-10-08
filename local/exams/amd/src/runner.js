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
 * Client-side exam runner: pre-exam system check, 3-zone command-center UI with
 * live stats/pace, navigation, palette, countdown, checkpointing and a single
 * deferred submission. Coordinates proctoring (quizaccess_proctor/monitor) when
 * the exam is proctored. Answers are saved via cache checkpoints, never per-page.
 *
 * @module     local_exams/runner
 * @copyright  2026 JAIN Online
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax'], function(Ajax) {

    var ROOT, DATA = {}, FORM;
    var panels = [];
    var current = 0;
    var review = {};
    var drift = 0;
    var timerInterval = null;
    var checkpointInterval = null;
    var submitted = false;
    var startedAt = 0;

    // Per-question timing.
    var qElapsed = [];      // accumulated seconds per index.
    var qEnter = 0;         // epoch when current question shown.

    // Proctoring.
    var PROCTOR = null;     // config or null.
    var MONITOR = null;     // loaded module.
    var checks = {};        // name -> bool.

    var region = function(name) {
        return ROOT.querySelector('[data-region="' + name + '"]');
    };
    var fmt = function(secs) {
        secs = Math.max(0, Math.round(secs));
        var m = Math.floor(secs / 60), s = secs % 60;
        return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    };
    var now = function() {
        return Math.floor(Date.now() / 1000) + drift;
    };

    var readData = function() {
        var el = document.getElementById('local-exams-data');
        try {
            return JSON.parse(el.textContent) || {};
        } catch (e) {
            return {};
        }
    };

    var serialize = function() {
        var map = {};
        new FormData(FORM).forEach(function(value, key) {
            if (Object.prototype.hasOwnProperty.call(map, key)) {
                if (!Array.isArray(map[key])) {
                    map[key] = [map[key]];
                }
                map[key].push(value);
            } else {
                map[key] = value;
            }
        });
        return map;
    };

    var isAnswered = function(panel) {
        var inputs = panel.querySelectorAll('input, select, textarea');
        for (var i = 0; i < inputs.length; i++) {
            var el = inputs[i];
            if (el.type === 'hidden' || el.disabled) {
                continue;
            }
            if (el.type === 'radio' || el.type === 'checkbox') {
                if (el.checked) {
                    return true;
                }
            } else if (el.value && String(el.value).trim() !== '') {
                return true;
            }
        }
        return false;
    };
    var answeredCount = function() {
        var n = 0;
        panels.forEach(function(p) {
            if (isAnswered(p)) {
                n++;
            }
        });
        return n;
    };
    var markedCount = function() {
        return Object.keys(review).filter(function(k) {
            return review[k];
        }).length;
    };

    // ---- Palette + stats -------------------------------------------------

    var buildPalette = function() {
        var pal = region('palette');
        pal.innerHTML = '';
        panels.forEach(function(p, i) {
            var b = document.createElement('button');
            b.type = 'button';
            b.textContent = (i + 1);
            b.addEventListener('click', function() {
                show(i);
            });
            pal.appendChild(b);
        });
    };
    var refreshPalette = function() {
        var pal = region('palette');
        Array.prototype.forEach.call(pal.children, function(b, i) {
            var answered = isAnswered(panels[i]);
            var marked = !!review[i];
            b.className = '';
            if (marked && answered) {
                b.classList.add('exui-pal-reviewanswered');
            } else if (marked) {
                b.classList.add('exui-pal-review');
            } else if (answered) {
                b.classList.add('exui-pal-answered');
            }
            if (i === current) {
                b.classList.add('exui-pal-current');
            }
        });
    };

    var setText = function(name, text) {
        var el = region(name);
        if (el) {
            el.textContent = text;
        }
    };

    var updateStats = function() {
        var total = panels.length;
        var ans = answeredCount();
        var pct = total ? Math.round(ans / total * 100) : 0;
        setText('stat-answered', ans);
        setText('stat-remaining', total - ans);
        setText('stat-marked', markedCount());
        setText('qposition', 'Question ' + (current + 1) + ' of ' + total);
        var fillL = region('progressbar-fill'), fillH = region('hprogress-fill');
        if (fillL) {
            fillL.style.width = pct + '%';
        }
        if (fillH) {
            fillH.style.width = pct + '%';
        }

        // This-question live time.
        var thisq = qElapsed[current] + (now() - qEnter);
        setText('stat-thisq', fmt(thisq));

        // Average per answered question.
        var elapsed = now() - startedAt;
        var avg = ans > 0 ? elapsed / ans : 0;
        setText('stat-avg', ans > 0 ? fmt(avg) : '—');

        // Pace vs remaining time.
        if (DATA.timelimit) {
            var left = (DATA.timestart + DATA.timelimit) - now();
            var qleft = total - ans;
            var pace = region('stat-pace');
            if (pace) {
                if (qleft <= 0) {
                    pace.textContent = 'All answered';
                    pace.className = 'exui-pace ok';
                } else {
                    var need = left / qleft;
                    var ok = need >= avg || avg === 0;
                    pace.textContent = ok ? 'On pace' : 'Behind pace';
                    pace.className = 'exui-pace ' + (ok ? 'ok' : 'behind');
                }
            }
        }
    };

    var show = function(idx) {
        if (idx < 0 || idx >= panels.length || idx === current) {
            if (idx === current) {
                return;
            }
            return;
        }
        // Bank time on the question we are leaving.
        qElapsed[current] += (now() - qEnter);
        panels[current].style.display = 'none';
        current = idx;
        var panel = panels[current];
        panel.style.display = '';
        panel.classList.remove('exui-qfade');
        // Force reflow to restart the animation.
        void panel.offsetWidth;
        panel.classList.add('exui-qfade');
        qEnter = now();
        refreshPalette();
        updateStats();
    };

    // ---- Checkpoint / submit (preserved) ---------------------------------

    var setStatus = function(text) {
        setText('savestatus', text);
    };
    var checkpoint = function() {
        if (submitted) {
            return;
        }
        setStatus('Saving…');
        Ajax.call([{
            methodname: 'local_exams_checkpoint',
            args: {token: DATA.token, answersjson: JSON.stringify(serialize())}
        }])[0].then(function(res) {
            setStatus('Saved ✓');
            if (res.status === 'expired') {
                doSubmit();
            }
            return res;
        }).catch(function() {
            setStatus('Offline — will retry');
        });
    };
    var doSubmit = function() {
        if (submitted) {
            return;
        }
        submitted = true;
        if (timerInterval) {
            window.clearInterval(timerInterval);
        }
        if (checkpointInterval) {
            window.clearInterval(checkpointInterval);
        }
        Ajax.call([{
            methodname: 'local_exams_submit_attempt',
            args: {token: DATA.token, answersjson: JSON.stringify(serialize())}
        }])[0].then(function() {
            region('exam').style.display = 'none';
            region('summary').style.display = 'none';
            region('submitted').style.display = '';
            if (MONITOR) {
                MONITOR.stop();
            }
            if (document.fullscreenElement) {
                document.exitFullscreen();
            }
            return true;
        }).catch(function() {
            submitted = false;
            setStatus('Submit failed — please retry');
        });
    };

    // ---- Timer -----------------------------------------------------------

    var startTimer = function() {
        var ring = region('timer');
        if (!DATA.timelimit) {
            if (ring) {
                ring.style.display = 'none';
            }
            return;
        }
        var fg = region('timer-fg');
        var circ = 2 * Math.PI * 44;
        fg.style.strokeDasharray = circ;
        var end = DATA.timestart + DATA.timelimit;
        var tick = function() {
            var remaining = Math.max(0, end - now());
            setText('timer-text', fmt(remaining));
            fg.style.strokeDashoffset = circ * (1 - remaining / DATA.timelimit);
            ring.classList.remove('exui-timer-warn', 'exui-timer-danger');
            if (remaining <= 60) {
                ring.classList.add('exui-timer-danger');
            } else if (remaining <= 300) {
                ring.classList.add('exui-timer-warn');
            }
            updateStats();
            if (remaining <= 0) {
                doSubmit();
            }
        };
        tick();
        timerInterval = window.setInterval(tick, 1000);
    };

    // ---- Summary + a11y --------------------------------------------------

    var openSummary = function() {
        var total = panels.length, ans = answeredCount();
        setText('summary-text', ans + ' of ' + total + ' answered, ' + (total - ans)
            + ' unanswered, ' + markedCount() + ' marked for review. '
            + 'You cannot change your answers after submitting.');
        region('exam').style.display = 'none';
        region('summary').style.display = '';
    };

    var fontScale = 1;
    var adjustFont = function(delta) {
        fontScale = Math.max(0.85, Math.min(1.4, fontScale + delta));
        ROOT.style.fontSize = fontScale + 'em';
    };

    // ---- Start exam ------------------------------------------------------

    var startExam = function() {
        region('launch').style.display = 'none';
        region('exam').style.display = '';
        startedAt = now();
        qEnter = now();
        buildPalette();
        refreshPalette();
        updateStats();
        startTimer();
        checkpointInterval = window.setInterval(checkpoint, 30000);
        if (MONITOR) {
            MONITOR.attachPreview(region('webcam'));
            MONITOR.begin();
        }
        try {
            if (ROOT.requestFullscreen) {
                ROOT.requestFullscreen().catch(function() {
                    return null;
                });
            }
        } catch (e) {
            return;
        }
    };

    // ---- System check ----------------------------------------------------

    var setCheck = function(name, ok, text) {
        checks[name] = ok;
        var li = ROOT.querySelector('[data-check="' + name + '"]');
        var st = region('check-' + name);
        if (st) {
            st.textContent = text;
        }
        if (li) {
            var ic = li.querySelector('.ck-ic');
            li.classList.remove('ck-ok', 'ck-fail');
            li.classList.add(ok ? 'ck-ok' : 'ck-fail');
            if (ic) {
                ic.textContent = ok ? '✓' : '✗';
            }
        }
    };
    var requiredChecksPass = function() {
        if (!PROCTOR) {
            return true;
        }
        var need = ['browser', 'camera', 'identity', 'connection'];
        if (PROCTOR.audiomonitor) {
            need.push('mic');
        }
        if (PROCTOR.screenrecord) {
            need.push('screen');
        }
        return need.every(function(n) {
            return checks[n];
        });
    };
    var refreshStart = function() {
        var btn = region('startbtn');
        if (btn) {
            btn.disabled = !requiredChecksPass();
        }
    };

    var runChecks = function() {
        // Browser capabilities.
        var okmedia = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia) && window.isSecureContext;
        setCheck('browser', okmedia, okmedia ? 'Supported' : 'Unsupported browser/HTTPS');
        setCheck('connection', navigator.onLine, navigator.onLine ? 'Online' : 'Offline');

        MONITOR.acquireCamera().then(function() {
            setCheck('camera', true, 'Ready');
            MONITOR.attachPreview(region('id-preview'));
            var cap = region('capturebtn');
            if (cap) {
                cap.disabled = false;
            }
        }).catch(function() {
            setCheck('camera', false, 'Denied/unavailable');
        }).then(refreshStart);

        if (PROCTOR.audiomonitor) {
            MONITOR.acquireMic().then(function() {
                setCheck('mic', true, 'Ready');
            }).catch(function() {
                setCheck('mic', false, 'Denied');
            }).then(refreshStart);
        }
        if (PROCTOR.screenrecord) {
            MONITOR.acquireScreen().then(function() {
                setCheck('screen', true, 'Sharing');
            }).catch(function() {
                setCheck('screen', false, 'Not shared');
            }).then(refreshStart);
        }
        refreshStart();
    };

    var captureIdentity = function() {
        setText('check-identity', 'Capturing…');
        MONITOR.captureIdentity().then(function(faces) {
            if (faces === 0) {
                setCheck('identity', false, 'No face — retake');
            } else if (faces > 1) {
                setCheck('identity', false, 'Multiple faces — retake');
            } else {
                setCheck('identity', true, 'Captured');
            }
            refreshStart();
            return faces;
        }).catch(function() {
            setCheck('identity', false, 'Failed — retry');
        });
    };

    // ---- Wiring ----------------------------------------------------------

    var bind = function() {
        ROOT.addEventListener('click', function(e) {
            var action = e.target.closest('[data-action]');
            if (!action) {
                return;
            }
            switch (action.dataset.action) {
                case 'runchecks': runChecks(); break;
                case 'capture-id': captureIdentity(); break;
                case 'start': startExam(); break;
                case 'prev': show(current - 1); break;
                case 'next': show(current + 1); break;
                case 'toggle-review':
                    review[current] = !review[current];
                    refreshPalette();
                    updateStats();
                    break;
                case 'clear':
                    panels[current].querySelectorAll('input, textarea, select').forEach(function(el) {
                        if (el.type === 'radio' || el.type === 'checkbox') {
                            el.checked = false;
                        } else if (el.type !== 'hidden') {
                            el.value = '';
                        }
                    });
                    refreshPalette();
                    updateStats();
                    break;
                case 'open-summary': openSummary(); break;
                case 'close-summary':
                    region('summary').style.display = 'none';
                    region('exam').style.display = '';
                    break;
                case 'submit': doSubmit(); break;
                case 'font-inc': adjustFont(0.08); break;
                case 'font-dec': adjustFont(-0.08); break;
                case 'contrast': ROOT.classList.toggle('exui-contrast'); break;
                default: break;
            }
        });

        var debounce = null;
        FORM.addEventListener('input', function() {
            refreshPalette();
            updateStats();
            window.clearTimeout(debounce);
            debounce = window.setTimeout(checkpoint, 1500);
        });

        // Keyboard navigation (ignored while typing in a field).
        document.addEventListener('keydown', function(e) {
            if (region('exam').style.display === 'none') {
                return;
            }
            var t = e.target;
            if (t && /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName)) {
                return;
            }
            if (e.key === 'ArrowLeft') {
                show(current - 1);
            } else if (e.key === 'ArrowRight') {
                show(current + 1);
            } else if (e.key === 'r' || e.key === 'R') {
                review[current] = !review[current];
                refreshPalette();
            } else if (/^[1-9]$/.test(e.key)) {
                show(parseInt(e.key, 10) - 1);
            }
        });
    };

    return {
        /**
         * @param {Object|null} proctorcfg Proctoring config, or null when not proctored.
         */
        init: function(proctorcfg) {
            ROOT = document.getElementById('local-exams-runner');
            if (!ROOT) {
                return;
            }
            DATA = readData();
            FORM = document.getElementById('local-exams-runnerform');
            panels = Array.prototype.slice.call(ROOT.querySelectorAll('.local-exams-qpanel'));
            qElapsed = panels.map(function() {
                return 0;
            });
            if (typeof DATA.servernow === 'number') {
                drift = DATA.servernow - Math.floor(Date.now() / 1000);
            }
            PROCTOR = proctorcfg || null;
            bind();

            if (PROCTOR) {
                // Load the proctor monitor and run the system check before Start unlocks.
                require(['quizaccess_proctor/monitor'], function(M) {
                    MONITOR = M;
                    M.setConfig(PROCTOR);
                });
            }
        }
    };
});
