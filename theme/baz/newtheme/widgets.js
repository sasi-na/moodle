/**
 * Student/admin widgets of BAZ Navigation Options 3-5 (loaded only on those dashboards).
 * Personal notes, to-dos and goals are kept in this browser only (localStorage), never in Moodle.
 */
(function() {
    'use strict';

    var root = document.querySelector('[data-nv-uid]');
    var uid = root ? root.getAttribute('data-nv-uid') : '0';
    var store = {
        get: function(k, d) {
            try {
                var v = window.localStorage.getItem(k + '-' + uid);
                return v === null ? d : JSON.parse(v);
            } catch (e) {
                return d;
            }
        },
        set: function(k, v) {
            try {
                window.localStorage.setItem(k + '-' + uid, JSON.stringify(v));
            } catch (e) {
                // Private window: not kept.
            }
        }
    };
    var $all = function(sel, ctx) {
        return Array.prototype.slice.call((ctx || document).querySelectorAll(sel));
    };

    /* ---------- Countdowns ---------- */
    function countdowns() {
        var els = $all('[data-nv-countdown][data-ts]');
        if (!els.length) {
            return;
        }
        var pad = function(n) {
            return (n < 10 ? '0' : '') + n;
        };
        var tick = function() {
            var now = Date.now() / 1000;
            els.forEach(function(el) {
                var d = parseInt(el.getAttribute('data-ts'), 10) - now;
                var big = el.getAttribute('data-nv-countdown') === 'big';
                if (d <= 0) {
                    el.textContent = big ? '00:00:00' : 'now';
                    el.classList.add('is-now');
                    return;
                }
                var days = Math.floor(d / 86400);
                var h = Math.floor((d % 86400) / 3600);
                var m = Math.floor((d % 3600) / 60);
                var s = Math.floor(d % 60);
                if (big) {
                    el.innerHTML = (days ? '<b>' + days + '</b><small>d</small> ' : '') + '<b>' + pad(h) + '</b><small>h</small> <b>' +
                        pad(m) + '</b><small>m</small> <b>' + pad(s) + '</b><small>s</small>';
                } else {
                    el.textContent = days ? 'in ' + days + 'd ' + h + 'h' : (h ? 'in ' + h + 'h ' + m + 'm' : 'in ' + m + 'm');
                }
                el.classList.toggle('is-soon', d < 86400 * 2);
            });
        };
        tick();
        window.setInterval(tick, 1000);
    }

    /* ---------- Grades: letters and average ---------- */
    function letter(p) {
        return p >= 90 ? 'A+' : p >= 80 ? 'A' : p >= 70 ? 'B' : p >= 60 ? 'C' : p >= 50 ? 'D' : 'E';
    }
    function grades() {
        $all('[data-nv-grade]').forEach(function(el) {
            var p = parseFloat(el.getAttribute('data-nv-grade')) || 0;
            el.textContent = letter(p);
            el.className += ' g-' + letter(p).charAt(0).toLowerCase();
        });
        $all('[data-nv-avg]').forEach(function(el) {
            var box = el.closest('[data-nv-grades]') || document;
            var ps = $all('[data-nv-pct]', box).map(function(x) {
                return parseFloat(x.getAttribute('data-nv-pct')) || 0;
            });
            if (!ps.length) {
                return;
            }
            var avg = Math.round(ps.reduce(function(a, b) {
                return a + b;
            }, 0) / ps.length);
            el.textContent = el.getAttribute('data-nv-avg') === 'letter' ? letter(avg) : avg + '%';
            var ring = el.closest('[data-nv-ring]');
            if (ring) {
                ring.style.setProperty('--p', avg);
            }
        });
    }

    /* ---------- Quick notes ---------- */
    function notes() {
        $all('[data-nv-notes]').forEach(function(box) {
            var ta = box.querySelector('textarea');
            var st = box.querySelector('[data-nv-saved]');
            if (!ta) {
                return;
            }
            var nkey = box.getAttribute('data-nv-key') || 'nv-notes';
            ta.value = store.get(nkey, '');
            var t = null;
            ta.addEventListener('input', function() {
                window.clearTimeout(t);
                t = window.setTimeout(function() {
                    store.set(nkey, ta.value);
                    if (st) {
                        st.hidden = false;
                        window.setTimeout(function() {
                            st.hidden = true;
                        }, 1200);
                    }
                }, 400);
            });
        });
    }

    /* ---------- To-do list ---------- */
    function todo() {
        $all('[data-nv-todo]').forEach(function(box) {
            var form = box.querySelector('form');
            var list = box.querySelector('ul');
            var count = box.querySelector('[data-nv-todo-count]');
            var tkey = box.getAttribute('data-nv-key') || 'nv-todo';
            var items = store.get(tkey, []);
            var draw = function() {
                list.textContent = '';
                items.forEach(function(it, i) {
                    var li = document.createElement('li');
                    li.className = it.d ? 'done' : '';
                    var cb = document.createElement('button');
                    cb.type = 'button';
                    cb.className = 'nv-tick';
                    cb.setAttribute('aria-label', it.d ? 'Mark as not done' : 'Mark as done');
                    cb.addEventListener('click', function() {
                        items[i].d = !items[i].d;
                        store.set(tkey, items);
                        draw();
                    });
                    var tx = document.createElement('span');
                    tx.textContent = it.t;
                    var del = document.createElement('button');
                    del.type = 'button';
                    del.className = 'nv-del';
                    del.setAttribute('aria-label', 'Remove');
                    del.textContent = '×';
                    del.addEventListener('click', function() {
                        items.splice(i, 1);
                        store.set(tkey, items);
                        draw();
                    });
                    li.appendChild(cb);
                    li.appendChild(tx);
                    li.appendChild(del);
                    list.appendChild(li);
                });
                var left = items.filter(function(x) {
                    return !x.d;
                }).length;
                if (count) {
                    count.textContent = items.length ? left + ' / ' + items.length : '';
                }
                box.classList.toggle('is-empty', !items.length);
            };
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var inp = form.querySelector('input');
                var v = inp.value.trim();
                if (v) {
                    items.push({t: v.slice(0, 140), d: false});
                    store.set(tkey, items);
                    inp.value = '';
                    draw();
                }
            });
            draw();
        });
    }

    /* ---------- Tip / thought of the day ---------- */
    function daily() {
        var day = Math.floor((Date.now() - new Date(new Date().getFullYear(), 0, 0)) / 86400000);
        $all('[data-nv-daily]').forEach(function(box) {
            var items = $all('[data-nv-item]', box);
            if (!items.length) {
                return;
            }
            var idx = day % items.length;
            items.forEach(function(it, i) {
                it.hidden = i !== idx;
            });
            var next = box.querySelector('[data-nv-next]');
            if (next) {
                next.addEventListener('click', function() {
                    items[idx].hidden = true;
                    idx = (idx + 1) % items.length;
                    items[idx].hidden = false;
                });
            }
        });
    }

    /* ---------- Rotating ticker ---------- */
    function ticker() {
        $all('[data-nv-ticker]').forEach(function(box) {
            var items = $all('[data-nv-item]', box);
            if (items.length < 2) {
                return;
            }
            var i = 0;
            items.forEach(function(it, k) {
                it.hidden = k !== 0;
            });
            var dots = box.querySelector('[data-nv-dots]');
            var paint = function() {
                if (dots) {
                    dots.textContent = '';
                    items.forEach(function(_, k) {
                        var d = document.createElement('i');
                        d.className = k === i ? 'on' : '';
                        dots.appendChild(d);
                    });
                }
            };
            paint();
            window.setInterval(function() {
                if (box.matches(':hover')) {
                    return;
                }
                items[i].hidden = true;
                i = (i + 1) % items.length;
                items[i].hidden = false;
                paint();
            }, 5000);
        });
    }

    /* ---------- Week strip / timetable from the month calendar ---------- */
    function week() {
        $all('[data-nv-week]').forEach(function(box) {
            var src = $all('.nv-src > *', box);
            var out = box.querySelector('[data-nv-week-out]');
            var ti = -1;
            src.forEach(function(c, i) {
                if (c.classList.contains('today')) {
                    ti = i;
                }
            });
            if (ti < 0 || !out) {
                return;
            }
            var start = ti - (ti % 7);
            src.slice(start, start + 7).forEach(function(c) {
                out.appendChild(c.cloneNode(true));
            });
        });
    }

    /* ---------- Weekly study goal ---------- */
    function goal() {
        $all('[data-nv-goal]').forEach(function(box) {
            var secs = parseInt(box.getAttribute('data-secs'), 10) || 0;
            var g = store.get('nv-goal', 10);
            var draw = function() {
                var pct = Math.min(100, Math.round((secs / 3600) / g * 100));
                box.style.setProperty('--p', pct);
                box.querySelector('[data-nv-goal-pct]').textContent = pct + '%';
                box.querySelector('[data-nv-goal-h]').textContent = g + 'h';
                box.classList.toggle('is-done', pct >= 100);
            };
            $all('[data-step]', box).forEach(function(b) {
                b.addEventListener('click', function() {
                    g = Math.max(1, Math.min(60, g + parseInt(b.getAttribute('data-step'), 10)));
                    store.set('nv-goal', g);
                    draw();
                });
            });
            draw();
        });
    }

    /* ---------- Achievements ---------- */
    function badges() {
        $all('[data-nv-badges]').forEach(function(box) {
            var best = 0;
            $all('[data-nv-pct]').forEach(function(x) {
                best = Math.max(best, parseFloat(x.getAttribute('data-nv-pct')) || 0);
            });
            var m = {
                streak: parseInt(box.getAttribute('data-streak'), 10) || 0,
                done: parseInt(box.getAttribute('data-done'), 10) || 0,
                level: parseInt(box.getAttribute('data-level'), 10) || 0,
                courses: parseInt(box.getAttribute('data-courses'), 10) || 0,
                progress: parseInt(box.getAttribute('data-progress'), 10) || 0,
                best: best,
                hours: Math.floor((parseInt(box.getAttribute('data-secs'), 10) || 0) / 3600)
            };
            var on = 0;
            var all = $all('[data-metric]', box);
            all.forEach(function(b) {
                var ok = (m[b.getAttribute('data-metric')] || 0) >= parseFloat(b.getAttribute('data-need'));
                b.classList.toggle('is-on', ok);
                on += ok ? 1 : 0;
            });
            var c = box.querySelector('[data-nv-badge-count]');
            if (c) {
                c.textContent = on + ' / ' + all.length;
            }
        });
    }

    /* ---------- Ask JO quick box ---------- */
    function ask() {
        $all('[data-nv-ask]').forEach(function(form) {
            var send = function(q) {
                var btn = document.querySelector('#nt-shellbar [id$="-askjo-btn"], #nt-shellbar .nt-askjo, [data-nt-jo-open]');
                if (btn) {
                    btn.click();
                }
                window.setTimeout(function() {
                    var panel = document.querySelector('#nt-jo-panel, .nt-jo, [id$="-askjo-panel"]');
                    var ta = panel && panel.querySelector('textarea, input[type="text"]');
                    if (ta) {
                        ta.value = q;
                        ta.dispatchEvent(new Event('input', {bubbles: true}));
                        ta.focus();
                    }
                }, 250);
            };
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var inp = form.querySelector('input');
                send(inp.value.trim());
                inp.value = '';
            });
            $all('[data-q]', form).forEach(function(chip) {
                chip.addEventListener('click', function() {
                    send(chip.getAttribute('data-q'));
                });
            });
        });
    }

    /* ---------- Admin: health score ---------- */
    function health() {
        $all('[data-nv-health]').forEach(function(box) {
            var rows = $all('[data-state]', box);
            if (!rows.length) {
                return;
            }
            var ok = rows.filter(function(r) {
                return r.getAttribute('data-state') === 'ok';
            }).length;
            var pct = Math.round(ok / rows.length * 100);
            $all('[data-nv-health-pct]').forEach(function(el) {
                el.textContent = pct + '%';
                var ring = el.closest('[data-nv-ring]');
                if (ring) {
                    ring.style.setProperty('--p', pct);
                }
            });
            $all('[data-nv-health-ok]').forEach(function(el) {
                el.textContent = ok + ' / ' + rows.length;
            });
        });
    }

    /* ---------- Admin: activity meters relative to the largest ---------- */
    function meters() {
        $all('.nv-meters').forEach(function(ul) {
            var items = $all('li', ul);
            var max = Math.max.apply(null, items.map(function(li) {
                return parseInt(li.querySelector('b').textContent.replace(/[^0-9]/g, ''), 10) || 0;
            }).concat([1]));
            items.forEach(function(li) {
                var n = parseInt(li.querySelector('b').textContent.replace(/[^0-9]/g, ''), 10) || 0;
                li.querySelector('i').style.setProperty('--n', Math.round(n / max * 100));
            });
        });
    }


    /* ---------- Shared date helpers ---------- */
    function dkey(d) {
        return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
    }
    function lastDays(n) {
        var out = [];
        for (var i = n - 1; i >= 0; i--) {
            var d = new Date();
            d.setHours(12, 0, 0, 0);
            d.setDate(d.getDate() - i);
            out.push(d);
        }
        return out;
    }
    var DOW = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];

    /* ---------- Option 3: habit tracker (last 7 days) ---------- */
    function habits() {
        $all('[data-nv-habits]').forEach(function(box) {
            var list = box.querySelector('ul');
            var form = box.querySelector('form');
            var dowrow = box.querySelector('[data-nv-habit-dow]');
            var score = box.querySelector('[data-nv-habit-score]');
            var days = lastDays(7);
            var items = store.get('nv-habits', []);
            var save = function() {
                store.set('nv-habits', items);
            };
            var draw = function() {
                dowrow.textContent = '';
                var sp = document.createElement('span');
                dowrow.appendChild(sp);
                days.forEach(function(d, i) {
                    var s = document.createElement('span');
                    s.textContent = DOW[d.getDay()];
                    if (i === days.length - 1) {
                        s.className = 'today';
                    }
                    dowrow.appendChild(s);
                });
                dowrow.appendChild(document.createElement('span'));
                list.textContent = '';
                var done = 0;
                items.forEach(function(h, idx) {
                    h.d = h.d || {};
                    var li = document.createElement('li');
                    var nm = document.createElement('span');
                    nm.className = 'nv-habit-name';
                    nm.textContent = h.t;
                    nm.title = h.t;
                    li.appendChild(nm);
                    days.forEach(function(d, i) {
                        var k = dkey(d);
                        var b = document.createElement('button');
                        b.type = 'button';
                        b.className = 'nv-hd' + (h.d[k] ? ' on' : '') + (i === days.length - 1 ? ' today' : '');
                        b.setAttribute('aria-pressed', h.d[k] ? 'true' : 'false');
                        b.setAttribute('aria-label', h.t + ' ' + k);
                        if (h.d[k]) {
                            done++;
                        }
                        b.addEventListener('click', function() {
                            if (h.d[k]) {
                                delete h.d[k];
                            } else {
                                h.d[k] = 1;
                            }
                            save();
                            draw();
                        });
                        li.appendChild(b);
                    });
                    var del = document.createElement('button');
                    del.type = 'button';
                    del.className = 'nv-del';
                    del.setAttribute('aria-label', 'Remove');
                    del.textContent = '×';
                    del.addEventListener('click', function() {
                        items.splice(idx, 1);
                        save();
                        draw();
                    });
                    li.appendChild(del);
                    list.appendChild(li);
                });
                box.classList.toggle('is-empty', !items.length);
                form.hidden = items.length >= 5;
                if (score) {
                    score.textContent = items.length ? Math.round(done / (items.length * 7) * 100) + '%' : '';
                }
            };
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var inp = form.querySelector('input');
                var v = inp.value.trim();
                if (v && items.length < 5) {
                    items.push({t: v.slice(0, 40), d: {}});
                    save();
                    inp.value = '';
                    draw();
                }
            });
            draw();
        });
    }

    /* ---------- Option 4: mood check-in ---------- */
    function mood() {
        var FACE = ['', '😫', '😕', '😐', '🙂', '🤩'];
        $all('[data-nv-mood]').forEach(function(box) {
            var log = store.get('nv-mood', {});
            var today = dkey(new Date());
            var week = box.querySelector('[data-nv-mood-week]');
            var draw = function() {
                var m = log[today] || 0;
                $all('.nv-mood-pick button', box).forEach(function(b) {
                    var on = String(m) === b.getAttribute('data-m');
                    b.classList.toggle('on', on);
                    b.setAttribute('aria-checked', on ? 'true' : 'false');
                    b.setAttribute('role', 'radio');
                });
                $all('[data-tip]', box).forEach(function(p) {
                    p.hidden = String(m) !== p.getAttribute('data-tip');
                });
                week.textContent = '';
                lastDays(7).forEach(function(d) {
                    var v = log[dkey(d)] || 0;
                    var c = document.createElement('span');
                    c.className = 'nv-mw' + (v ? ' m' + v : '');
                    c.title = dkey(d);
                    var f = document.createElement('i');
                    f.textContent = v ? FACE[v] : '·';
                    var l = document.createElement('small');
                    l.textContent = DOW[d.getDay()];
                    c.appendChild(f);
                    c.appendChild(l);
                    week.appendChild(c);
                });
            };
            $all('.nv-mood-pick button', box).forEach(function(b) {
                b.addEventListener('click', function() {
                    log[today] = parseInt(b.getAttribute('data-m'), 10);
                    var keep = {};
                    lastDays(30).forEach(function(d) {
                        if (log[dkey(d)]) {
                            keep[dkey(d)] = log[dkey(d)];
                        }
                    });
                    log = keep;
                    store.set('nv-mood', log);
                    draw();
                });
            });
            draw();
        });
    }

    /* ---------- Option 5: day of the year ---------- */
    function dayOfYear() {
        var now = new Date();
        var start = new Date(now.getFullYear(), 0, 0);
        var n = Math.floor((now - start) / 86400000);
        var total = ((now.getFullYear() % 4 === 0 && now.getFullYear() % 100 !== 0) || now.getFullYear() % 400 === 0) ? 366 : 365;
        $all('[data-nv-dayofyear]').forEach(function(el) {
            el.textContent = n + ' / ' + total;
        });
    }

    /* ---------- v4 study tools (private, kept in this browser only) ---------- */
    function el(tag, cls, txt) {
        var n = document.createElement(tag);
        if (cls) {
            n.className = cls;
        }
        if (txt !== undefined && txt !== null) {
            n.textContent = txt;
        }
        return n;
    }
    function fmt(t, o) {
        return (t || '').replace(/\{(\w+)\}/g, function(m, k) {
            return o[k] === undefined ? m : o[k];
        });
    }
    function num(v) {
        var n = parseFloat(v);
        return isNaN(n) ? null : n;
    }

    /* Attendance calculator (Option 3). */
    function attendance() {
        $all('[data-nv-attend]').forEach(function(box) {
            var f = {};
            ['att', 'tot', 'tgt'].forEach(function(k) {
                f[k] = box.querySelector('[data-f="' + k + '"]');
            });
            var out = box.querySelector('[data-out]');
            var pctEl = box.querySelector('[data-out-pct]');
            var bar = box.querySelector('[data-out-bar]');
            var d = store.get('nv-attend', {att: '', tot: '', tgt: 75});
            f.att.value = d.att;
            f.tot.value = d.tot;
            f.tgt.value = d.tgt;
            var calc = function() {
                var a = num(f.att.value), t = num(f.tot.value), g = num(f.tgt.value) || 75;
                store.set('nv-attend', {att: f.att.value, tot: f.tot.value, tgt: g});
                box.classList.remove('is-ok', 'is-low');
                if (a === null || t === null || t <= 0 || a < 0 || a > t) {
                    out.textContent = box.getAttribute('data-t-hint');
                    pctEl.textContent = '';
                    bar.style.width = '0';
                    return;
                }
                var p = a / t * 100;
                pctEl.textContent = p.toFixed(1) + '%';
                bar.style.width = Math.min(100, p) + '%';
                if (p >= g) {
                    var skip = Math.floor(a * 100 / g - t);
                    box.classList.add('is-ok');
                    out.textContent = fmt(box.getAttribute('data-t-ok'), {n: Math.max(0, skip)});
                } else {
                    box.classList.add('is-low');
                    var need = g >= 100 ? null : Math.ceil((g * t - 100 * a) / (100 - g));
                    out.textContent = need === null ? box.getAttribute('data-t-hint') : fmt(box.getAttribute('data-t-need'), {n: need});
                }
            };
            ['att', 'tot', 'tgt'].forEach(function(k) {
                f[k].addEventListener('input', calc);
            });
            calc();
        });
    }

    /* Exam planner (Option 3). */
    function exams() {
        $all('[data-nv-exams]').forEach(function(box) {
            var list = box.querySelector('ul');
            var form = box.querySelector('form');
            var items = store.get('nv-exams', []);
            var save = function() {
                store.set('nv-exams', items);
            };
            var draw = function() {
                list.textContent = '';
                items.sort(function(x, y) {
                    return x.d < y.d ? -1 : 1;
                });
                var today = new Date();
                today.setHours(0, 0, 0, 0);
                items.forEach(function(it, i) {
                    var dt = new Date(it.d + 'T00:00:00');
                    var days = Math.round((dt - today) / 86400000);
                    var li = el('li', days < 0 ? 'past' : (days <= 7 ? 'soon' : ''));
                    li.appendChild(el('span', 'nvw-ex-n', it.t));
                    li.appendChild(el('small', '', dt.toLocaleDateString(undefined, {day: 'numeric', month: 'short'})));
                    li.appendChild(el('b', 'nvw-ex-d', days < 0 ? '✓' : (days === 0 ? box.getAttribute('data-t-today') : days + box.getAttribute('data-t-d'))));
                    var del = el('button', 'nv-del', '×');
                    del.type = 'button';
                    del.setAttribute('aria-label', 'Remove');
                    del.addEventListener('click', function() {
                        items.splice(i, 1);
                        save();
                        draw();
                    });
                    li.appendChild(del);
                    list.appendChild(li);
                });
                box.classList.toggle('is-empty', !items.length);
                form.hidden = items.length >= 8;
            };
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var n = form.querySelector('[type=text]'), dt = form.querySelector('[type=date]');
                if (n.value.trim() && dt.value && items.length < 8) {
                    items.push({t: n.value.trim().slice(0, 40), d: dt.value});
                    save();
                    n.value = '';
                    dt.value = '';
                    draw();
                }
            });
            draw();
        });
    }

    /* Assignment board (Option 4): To do / Doing / Done. */
    function kanban() {
        $all('[data-nv-kanban]').forEach(function(box) {
            var cols = {};
            $all('[data-col]', box).forEach(function(c) {
                cols[c.getAttribute('data-col')] = c.querySelector('ul');
            });
            var order = ['todo', 'doing', 'done'];
            var items = store.get('nv-kanban', []);
            var form = box.querySelector('form');
            var save = function() {
                store.set('nv-kanban', items);
            };
            var draw = function() {
                order.forEach(function(k) {
                    cols[k].textContent = '';
                });
                var counts = {todo: 0, doing: 0, done: 0};
                items.forEach(function(it, i) {
                    counts[it.c]++;
                    var li = el('li', 'nvw-k-' + it.c);
                    li.appendChild(el('span', '', it.t));
                    var ctl = el('div', 'nvw-k-ctl');
                    var ci = order.indexOf(it.c);
                    [[-1, '‹'], [1, '›']].forEach(function(m) {
                        var j = ci + m[0];
                        if (j < 0 || j > 2) {
                            return;
                        }
                        var b = el('button', '', m[1]);
                        b.type = 'button';
                        b.setAttribute('aria-label', order[j]);
                        b.addEventListener('click', function() {
                            it.c = order[j];
                            save();
                            draw();
                        });
                        ctl.appendChild(b);
                    });
                    var del = el('button', 'nv-del', '×');
                    del.type = 'button';
                    del.setAttribute('aria-label', 'Remove');
                    del.addEventListener('click', function() {
                        items.splice(i, 1);
                        save();
                        draw();
                    });
                    ctl.appendChild(del);
                    li.appendChild(ctl);
                    cols[it.c].appendChild(li);
                });
                order.forEach(function(k) {
                    var h = box.querySelector('[data-col="' + k + '"] [data-n]');
                    if (h) {
                        h.textContent = counts[k];
                    }
                });
            };
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var inp = form.querySelector('input');
                if (inp.value.trim() && items.length < 40) {
                    items.push({t: inp.value.trim().slice(0, 60), c: 'todo'});
                    save();
                    inp.value = '';
                    draw();
                }
            });
            draw();
        });
    }

    /* Flashcards (Option 4). */
    function flashcards() {
        $all('[data-nv-cards]').forEach(function(box) {
            var cards = store.get('nv-cards', []);
            var idx = 0, flipped = false;
            var face = box.querySelector('[data-face]');
            var text = box.querySelector('[data-face-text]');
            var side = box.querySelector('[data-face-side]');
            var count = box.querySelector('[data-count]');
            var form = box.querySelector('form');
            var save = function() {
                store.set('nv-cards', cards);
            };
            var draw = function() {
                box.classList.toggle('is-empty', !cards.length);
                if (!cards.length) {
                    count.textContent = '';
                    return;
                }
                idx = (idx + cards.length) % cards.length;
                var c = cards[idx];
                text.textContent = flipped ? c.a : c.q;
                side.textContent = box.getAttribute(flipped ? 'data-t-a' : 'data-t-q');
                face.classList.toggle('is-back', flipped);
                count.textContent = (idx + 1) + ' / ' + cards.length;
            };
            face.addEventListener('click', function() {
                flipped = !flipped;
                draw();
            });
            box.querySelector('[data-prev]').addEventListener('click', function() {
                idx--;
                flipped = false;
                draw();
            });
            box.querySelector('[data-next]').addEventListener('click', function() {
                idx++;
                flipped = false;
                draw();
            });
            box.querySelector('[data-del]').addEventListener('click', function() {
                if (cards.length) {
                    cards.splice(idx, 1);
                    flipped = false;
                    save();
                    draw();
                }
            });
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var q = form.querySelector('[data-q]'), a = form.querySelector('[data-a]');
                if (q.value.trim() && a.value.trim() && cards.length < 60) {
                    cards.push({q: q.value.trim().slice(0, 140), a: a.value.trim().slice(0, 240)});
                    save();
                    q.value = '';
                    a.value = '';
                    idx = cards.length - 1;
                    flipped = false;
                    draw();
                }
            });
            draw();
        });
    }

    /* Grade calculator (Option 5): weighted marks and the score needed on what is left. */
    function gradecalc() {
        $all('[data-nv-gcalc]').forEach(function(box) {
            var list = box.querySelector('[data-rows]');
            var out = box.querySelector('[data-out]');
            var tgt = box.querySelector('[data-target]');
            var d = store.get('nv-gcalc', {rows: [], tgt: 60});
            tgt.value = d.tgt;
            var save = function() {
                store.set('nv-gcalc', d);
            };
            var calc = function() {
                var w = 0, pts = 0;
                d.rows.forEach(function(r) {
                    var wt = num(r.w), sc = num(r.s);
                    if (wt !== null && sc !== null && wt > 0) {
                        w += wt;
                        pts += wt * sc / 100;
                    }
                });
                var g = num(tgt.value);
                d.tgt = tgt.value;
                save();
                if (!w) {
                    out.textContent = box.getAttribute('data-t-hint');
                    return;
                }
                var line = fmt(box.getAttribute('data-t-sofar'), {p: pts.toFixed(1), w: w, a: (pts / w * 100).toFixed(1)});
                if (g !== null && w < 100) {
                    var need = (g - pts) / (100 - w) * 100;
                    line += ' ' + (need > 100 ? fmt(box.getAttribute('data-t-hard'), {g: g}) :
                        (need <= 0 ? fmt(box.getAttribute('data-t-safe'), {g: g}) : fmt(box.getAttribute('data-t-need'), {n: need.toFixed(1), r: (100 - w)})));
                }
                out.textContent = line;
            };
            var draw = function() {
                list.textContent = '';
                d.rows.forEach(function(r, i) {
                    var li = el('li');
                    var n = el('input'), w = el('input'), sc = el('input');
                    n.type = 'text';
                    n.maxLength = 24;
                    n.value = r.n;
                    n.placeholder = box.getAttribute('data-t-name');
                    [w, sc].forEach(function(x) {
                        x.type = 'number';
                        x.min = 0;
                        x.max = 100;
                    });
                    w.value = r.w;
                    sc.value = r.s;
                    w.placeholder = '%';
                    sc.placeholder = '%';
                    w.title = box.getAttribute('data-t-weight');
                    sc.title = box.getAttribute('data-t-score');
                    [[n, 'n'], [w, 'w'], [sc, 's']].forEach(function(p) {
                        p[0].addEventListener('input', function() {
                            r[p[1]] = p[0].value;
                            calc();
                        });
                        li.appendChild(p[0]);
                    });
                    var del = el('button', 'nv-del', '×');
                    del.type = 'button';
                    del.setAttribute('aria-label', 'Remove');
                    del.addEventListener('click', function() {
                        d.rows.splice(i, 1);
                        save();
                        draw();
                    });
                    li.appendChild(del);
                    list.appendChild(li);
                });
                calc();
            };
            box.querySelector('[data-add]').addEventListener('click', function() {
                if (d.rows.length < 8) {
                    d.rows.push({n: '', w: '', s: ''});
                    save();
                    draw();
                }
            });
            tgt.addEventListener('input', calc);
            if (!d.rows.length) {
                d.rows.push({n: '', w: '', s: ''});
            }
            draw();
        });
    }

    /* Citation helper (Option 5): APA / MLA / Harvard. */
    function citation() {
        $all('[data-nv-cite]').forEach(function(box) {
            var g = function(k) {
                return box.querySelector('[data-f="' + k + '"]');
            };
            var out = box.querySelector('[data-out]');
            var d = store.get('nv-cite', {});
            ['style', 'author', 'year', 'title', 'site', 'url'].forEach(function(k) {
                if (d[k] !== undefined) {
                    g(k).value = d[k];
                }
            });
            var build = function() {
                var v = {};
                ['style', 'author', 'year', 'title', 'site', 'url'].forEach(function(k) {
                    v[k] = g(k).value.trim();
                });
                store.set('nv-cite', v);
                var au = v.author || box.getAttribute('data-t-noauthor');
                var yr = v.year || 'n.d.';
                var t = v.title, text = '';
                if (!t) {
                    out.textContent = box.getAttribute('data-t-hint');
                    return;
                }
                if (v.style === 'mla') {
                    text = au.replace(/\.$/, '') + '. "' + t + '." ' + (v.site ? v.site + ', ' : '') + yr + (v.url ? ', ' + v.url : '') + '.';
                } else if (v.style === 'harvard') {
                    text = au.replace(/\.$/, '') + ' (' + yr + ') ' + t + '.' + (v.site ? ' ' + v.site + '.' : '') + (v.url ? ' Available at: ' + v.url + '.' : '');
                } else {
                    text = au.replace(/\.$/, '') + '. (' + yr + '). ' + t + '.' + (v.site ? ' ' + v.site + '.' : '') + (v.url ? ' ' + v.url : '');
                }
                out.textContent = text;
            };
            $all('input, select', box).forEach(function(i) {
                i.addEventListener('input', build);
                i.addEventListener('change', build);
            });
            var cp = box.querySelector('[data-copy]');
            cp.addEventListener('click', function() {
                var txt = out.textContent;
                var done = function() {
                    var o = cp.textContent;
                    cp.textContent = cp.getAttribute('data-done');
                    window.setTimeout(function() {
                        cp.textContent = o;
                    }, 1500);
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(txt).then(done, function() {});
                } else {
                    var r = document.createRange();
                    r.selectNodeContents(out);
                    var sel = window.getSelection();
                    sel.removeAllRanges();
                    sel.addRange(r);
                    try {
                        document.execCommand('copy');
                        done();
                    } catch (e) {
                        // Not copied.
                    }
                }
            });
            build();
        });
    }

    /* Today's plan (Option 3): time slots for today, cleared each new day. */
    function planner() {
        $all('[data-nv-plan]').forEach(function(box) {
            var list = box.querySelector('ul');
            var form = box.querySelector('form');
            var cnt = box.querySelector('[data-nv-plan-count]');
            var today = dkey(new Date());
            var d = store.get('nv-plan', {d: today, i: []});
            if (d.d !== today) {
                d = {d: today, i: []};
            }
            var save = function() {
                store.set('nv-plan', d);
            };
            var draw = function() {
                list.textContent = '';
                d.i.sort(function(a, b) {
                    return a.t < b.t ? -1 : (a.t > b.t ? 1 : 0);
                });
                var done = 0;
                d.i.forEach(function(it, idx) {
                    var li = el('li', it.x ? 'done' : '');
                    var tick = el('button', 'nv-tick');
                    tick.type = 'button';
                    tick.setAttribute('aria-pressed', it.x ? 'true' : 'false');
                    tick.addEventListener('click', function() {
                        it.x = it.x ? 0 : 1;
                        save();
                        draw();
                    });
                    li.appendChild(tick);
                    li.appendChild(el('b', '', it.t));
                    li.appendChild(el('span', '', it.n));
                    var del = el('button', 'nv-del', '×');
                    del.type = 'button';
                    del.setAttribute('aria-label', 'Remove');
                    del.addEventListener('click', function() {
                        d.i.splice(idx, 1);
                        save();
                        draw();
                    });
                    li.appendChild(del);
                    if (it.x) {
                        done++;
                    }
                    list.appendChild(li);
                });
                box.classList.toggle('is-empty', !d.i.length);
                cnt.textContent = d.i.length ? done + '/' + d.i.length : '';
            };
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var tm = form.querySelector('[type=time]'), tx = form.querySelector('[type=text]');
                if (tm.value && tx.value.trim() && d.i.length < 20) {
                    d.i.push({t: tm.value, n: tx.value.trim().slice(0, 50), x: 0});
                    save();
                    tx.value = '';
                    draw();
                }
            });
            draw();
        });
    }

    function init() {
        meters();
        countdowns();
        grades();
        notes();
        todo();
        daily();
        ticker();
        week();
        goal();
        badges();
        ask();
        health();
        habits();
        mood();
        dayOfYear();
        attendance();
        exams();
        kanban();
        flashcards();
        gradecalc();
        citation();
        planner();
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
