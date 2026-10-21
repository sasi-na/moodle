/**
 * iPhone-style pop-up notifications for the whole site (all Navigation options).
 * - Moodle's own messages (saved / warning / error / info toasts and page notices) show as a banner at the top centre
 *   and disappear after 5 seconds.
 * - A new bell notification or unread message shows the same way (polled every 30 s, read-only).
 * Display only: nothing is saved or changed in Moodle. Long messages and messages with buttons/links to act on
 * stay where they are.
 */
(function() {
    'use strict';
    if (window.__ntToast) {
        return;
    }
    window.__ntToast = true;

    var SHOW_MS = 5000;
    var MAX = 3;
    var cfg = window.M && window.M.cfg ? window.M.cfg : {};
    var stack = null;

    var ICONS = {
        success: '<path d="m6 12.5 4 4 8-9"/>',
        info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
        warning: '<path d="M12 4 3 20h18z"/><path d="M12 10v4M12 17h.01"/>',
        error: '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/>',
        bell: '<path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15z"/><path d="M10 21h4"/>',
        chat: '<path d="M4 5h16v11H9l-5 4z"/>',
        login: '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5M15 12H3"/>',
        logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>'
    };
    var LABEL = {success: 'Done', info: 'Notice', warning: 'Warning', error: 'Problem', bell: 'Notification', chat: 'Message', login: 'Signed in', logout: 'Signed out'};

    var brand = function() {
        var b = window.EDNXT_BRAND && window.EDNXT_BRAND.name;
        return b || 'JAIN Online';
    };

    var ensure = function() {
        if (stack && document.body.contains(stack)) {
            return stack;
        }
        stack = document.createElement('div');
        stack.className = 'ntt-stack';
        stack.setAttribute('role', 'region');
        stack.setAttribute('aria-label', 'Notifications');
        document.body.appendChild(stack);
        return stack;
    };

    var dismiss = function(el) {
        if (!el || el.__gone) {
            return;
        }
        el.__gone = true;
        window.clearTimeout(el.__t);
        el.classList.remove('in');
        el.classList.add('out');
        window.setTimeout(function() {
            if (el.parentNode) {
                el.parentNode.removeChild(el);
            }
        }, 380);
    };

    var arm = function(el) {
        window.clearTimeout(el.__t);
        el.__t = window.setTimeout(function() {
            dismiss(el);
        }, SHOW_MS);
    };

    /**
     * Show one banner.
     * @param {string} kind success|info|warning|error|bell|chat
     * @param {string} title
     * @param {string} text
     * @param {string} [url] Opens when the banner is clicked.
     */
    var show = function(kind, title, text, url) {
        text = (text || '').replace(/\s+/g, ' ').trim();
        if (!text && !title) {
            return;
        }
        var host = ensure();
        // Same text already on screen: do not stack a duplicate.
        var dup = host.querySelectorAll('.ntt');
        for (var i = 0; i < dup.length; i++) {
            if (dup[i].__key === kind + '|' + title + '|' + text && !dup[i].__gone) {
                arm(dup[i]);
                return;
            }
        }
        while (host.children.length >= MAX) {
            dismiss(host.firstElementChild);
            host.removeChild(host.firstElementChild);
        }
        var el = document.createElement('div');
        el.className = 'ntt ntt-' + kind;
        el.__key = kind + '|' + title + '|' + text;
        el.setAttribute('role', kind === 'error' || kind === 'warning' ? 'alert' : 'status');
        var ic = document.createElement('span');
        ic.className = 'ntt-ic';
        ic.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true">' + (ICONS[kind] || ICONS.info) + '</svg>';
        var body = document.createElement('span');
        body.className = 'ntt-body';
        var top = document.createElement('span');
        top.className = 'ntt-top';
        var app = document.createElement('b');
        app.textContent = brand();
        top.appendChild(app);
        var t = document.createElement('strong');
        t.textContent = title || LABEL[kind] || '';
        var p = document.createElement('span');
        p.className = 'ntt-msg';
        p.textContent = text.length > 160 ? text.slice(0, 157) + '…' : text;
        body.appendChild(top);
        body.appendChild(t);
        if (text) {
            body.appendChild(p);
        }
        el.appendChild(ic);
        el.appendChild(body);
        el.addEventListener('click', function() {
            if (url) {
                window.location.href = url;
            } else {
                dismiss(el);
            }
        });
        el.addEventListener('mouseenter', function() {
            window.clearTimeout(el.__t);
        });
        el.addEventListener('mouseleave', function() {
            arm(el);
        });
        host.appendChild(el);
        void el.offsetWidth; // Start the slide-down from the hidden position.
        el.classList.add('in');
        arm(el);
    };
    window.ntToast = show;

    /* ---------- Moodle's own messages ---------- */
    var kindOf = function(node) {
        var c = node.className || '';
        if (/alert-success|toast-success|notifysuccess|success/.test(c)) {
            return 'success';
        }
        if (/alert-danger|alert-error|toast-danger|notifyproblem|error/.test(c)) {
            return 'error';
        }
        if (/alert-warning|toast-warning|notifywarning|warning/.test(c)) {
            return 'warning';
        }
        return 'info';
    };

    var grab = function(node) {
        if (!node || node.nodeType !== 1 || node.__ntt || node.closest('.ntt-stack')) {
            return;
        }
        // Only page-level notices and Moodle toasts - never alerts that are part of a form or page content.
        var isToast = node.matches('.toast') || node.querySelector(':scope > .toast-body');
        var isNotice = node.matches('.alert') && node.closest('#user-notifications, [data-region="notifications"]');
        if (!isToast && !isNotice) {
            return;
        }
        var bodyEl = node.querySelector('.toast-body') || node;
        var clone = bodyEl.cloneNode(true);
        Array.prototype.forEach.call(clone.querySelectorAll('button.close, .btn-close, .sr-only, .visually-hidden'), function(x) {
            x.remove();
        });
        var text = (clone.textContent || '').replace(/\s+/g, ' ').trim();
        // Keep long messages and anything with something to click (Continue, Confirm, forms) where it is.
        if (!text || text.length > 220 || bodyEl.querySelector('form, input, select, textarea, .btn, button:not(.close)')) {
            return;
        }
        node.__ntt = true;
        node.classList.add('ntt-moved');
        show(kindOf(node), '', text);
    };

    var scan = function(root) {
        Array.prototype.forEach.call((root || document).querySelectorAll(
            '#user-notifications .alert, [data-region="notifications"] .alert, .toast-wrapper .toast'), grab);
    };

    /* ---------- New bell notifications ---------- */
    var uid = cfg.userId ? parseInt(cfg.userId, 10) : 0;
    var lastId = null;
    var lastMsgCount = null;

    var store = {
        get: function(k) {
            try {
                return window.sessionStorage.getItem(k);
            } catch (e) {
                return null;
            }
        },
        set: function(k, v) {
            try {
                window.sessionStorage.setItem(k, v);
            } catch (e) {
                // Not kept.
            }
        }
    };

    var strip = function(h) {
        var d = document.createElement('div');
        d.innerHTML = h || '';
        return (d.textContent || '').replace(/\s+/g, ' ').trim();
    };

    var poll = function() {
        if (!uid || !cfg.wwwroot || !cfg.sesskey || document.hidden) {
            return;
        }
        var body = JSON.stringify([{index: 0, methodname: 'message_popup_get_popup_notifications',
            args: {useridto: uid, newestfirst: 1, limit: 5, offset: 0}}]);
        window.fetch(cfg.wwwroot + '/lib/ajax/service.php?sesskey=' + encodeURIComponent(cfg.sesskey) + '&info=message_popup_get_popup_notifications', {
            method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'}, body: body
        }).then(function(r) {
            return r.json();
        }).then(function(j) {
            var list = j && j[0] && !j[0].error && j[0].data && j[0].data.notifications;
            if (!list) {
                return;
            }
            var maxId = lastId || 0;
            var fresh = [];
            list.forEach(function(n) {
                var id = parseInt(n.id, 10);
                if (id > maxId) {
                    maxId = id;
                }
                if (lastId !== null && id > lastId && !n.read) {
                    fresh.push(n);
                }
            });
            fresh.reverse().slice(0, MAX).forEach(function(n) {
                show('bell', strip(n.subject) || LABEL.bell, strip(n.fullmessagehtml || n.fullmessage || n.smallmessage), n.contexturl || '');
            });
            lastId = maxId;
            store.set('ntt-last-' + uid, String(lastId));
        }).catch(function() {
            // Offline or logged out: stay quiet.
        });
    };

    var watchMessages = function() {
        var badge = document.querySelector('[data-region="count-container"].count-container, .popover-region-messages .count-container');
        var read = function() {
            var el = document.querySelector('.popover-region-messages .count-container');
            var n = el ? parseInt((el.textContent || '').replace(/\D/g, ''), 10) : 0;
            return isNaN(n) ? 0 : n;
        };
        if (!badge && !document.querySelector('.popover-region-messages')) {
            return;
        }
        lastMsgCount = read();
        var target = document.querySelector('.popover-region-messages') || document.body;
        new MutationObserver(function() {
            var n = read();
            if (lastMsgCount !== null && n > lastMsgCount) {
                show('chat', 'New message', n === 1 ? 'You have 1 unread message.' : 'You have ' + n + ' unread messages.',
                    (cfg.wwwroot || '') + '/message/index.php');
            }
            lastMsgCount = n;
        }).observe(target, {childList: true, subtree: true, characterData: true});
    };

    var start = function() {
        scan(document);
        new MutationObserver(function(muts) {
            muts.forEach(function(m) {
                Array.prototype.forEach.call(m.addedNodes, function(n) {
                    if (n.nodeType === 1) {
                        if (n.matches('.alert, .toast')) {
                            grab(n);
                        }
                        scan(n);
                    }
                });
            });
        }).observe(document.body, {childList: true, subtree: true});
        if (uid) {
            var saved = store.get('ntt-last-' + uid);
            lastId = saved === null ? null : parseInt(saved, 10);
            poll();
            window.setInterval(poll, 30000);
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    poll();
                }
            });
            window.setTimeout(watchMessages, 1500);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
