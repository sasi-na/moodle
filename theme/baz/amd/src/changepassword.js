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
 * Progressive enhancement for the bespoke student Change-password page.
 *
 * The core moodleform is left intact (real field ids, CSRF token and server
 * validation are all preserved). This only decorates the DOM: a security-card
 * hero, show/hide toggles on each password field, and a live requirement
 * checklist + strength meter driven by the site's actual password policy
 * (passed in from $CFG). It fails open — any error leaves the plain form fully
 * usable, and the submit button is only disabled while the new password is
 * non-empty and clearly invalid.
 *
 * @module     theme_baz/changepassword
 * @copyright  2026 onlinejain.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    "use strict";

    var EYE_OFF = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17.9 17.9A10.4 10.4 0 0 1 12 19c-7 0-11-7-11-7a18.4 18.4 0 0 1 5.1-5.9M9.9 4.2A10.6 10.6 0 0 1 12 4c7 0 11 7 11 7a18.5 18.5 0 0 1-2.2 3.2M9.9 9.9a3 3 0 0 0 4.2 4.2"/><path d="M1 1l22 22"/></svg>';
    var EYE_ON = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>';
    var TICK = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12l5 5L19 7"/></svg>';

    /**
     * Build a DOM node from an HTML string.
     *
     * @param {string} html Markup for a single root element.
     * @return {Element} The parsed element.
     */
    function el(html) {
        var d = document.createElement('div');
        d.innerHTML = html.trim();
        return d.firstChild;
    }

    /**
     * Count regex matches in a string.
     *
     * @param {string} v Subject string.
     * @param {RegExp} re Global regex.
     * @return {number} Number of matches.
     */
    function count(v, re) {
        var m = v.match(re);
        return m ? m.length : 0;
    }

    /**
     * Add a show/hide eye toggle inside a password field's wrapper.
     *
     * @param {string} id Input id.
     * @param {Object} str String map.
     * @return {void}
     */
    function addEye(id, str) {
        var input = document.getElementById(id);
        if (!input || !input.parentNode) {
            return;
        }
        var wrap = input.parentNode;
        wrap.classList.add('sw-cp-pwwrap');
        var btn = el('<button type="button" class="sw-cp-eye" tabindex="0"></button>');
        btn.innerHTML = EYE_OFF;
        btn.setAttribute('aria-label', str.show);
        btn.addEventListener('click', function() {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.innerHTML = show ? EYE_ON : EYE_OFF;
            btn.setAttribute('aria-label', show ? str.hide : str.show);
        });
        wrap.appendChild(btn);
    }

    /**
     * Initialise the enhancement.
     *
     * @param {Object} cfg {rules:[{key,min,label}], str:{...}, enabled:bool}
     * @return {void}
     */
    function init(cfg) {
        try {
            var np1 = document.getElementById('id_newpassword1');
            if (!np1) {
                return;
            }
            var form = np1.closest('form');
            if (!form) {
                return;
            }
            var str = cfg.str || {};
            var rules = cfg.rules || [];
            // The core form wraps the fields in a fieldset.clearfix — make THAT the
            // security card (the form-level element would double the card chrome).
            var card = np1.closest('fieldset') || form;
            card.classList.add('sw-cp');

            // Hero at the very top of the card (the legend is hidden via CSS).
            var hero = el(
                '<div class="sw-cp-hero">' +
                '<div class="sw-cp-shield"><svg viewBox="0 0 24 24" aria-hidden="true">' +
                '<path d="M12 3l7 3v5c0 4.5-3 7.7-7 9-4-1.3-7-4.5-7-9V6l7-3z"/><path d="M9 12l2 2 4-4"/></svg></div>' +
                '<div><div class="sw-cp-ht"></div><div class="sw-cp-hs"></div></div></div>'
            );
            hero.querySelector('.sw-cp-ht').textContent = str.hero_t || '';
            hero.querySelector('.sw-cp-hs').textContent = str.hero_s || '';
            card.insertBefore(hero, card.firstChild);

            // Eye toggles.
            addEye('id_password', str);
            addEye('id_newpassword1', str);
            addEye('id_newpassword2', str);

            // Strength meter + requirement checklist (after the New password row).
            var np1item = document.getElementById('fitem_id_newpassword1') || np1.closest('.fitem');
            var reqHtml = '';
            rules.forEach(function(r) {
                reqHtml += '<div class="sw-cp-req" data-key="' + r.key + '">' +
                    '<span class="sw-cp-dot">' + TICK + '</span><span></span></div>';
            });
            var panel = el(
                '<div class="sw-cp-panel">' +
                '<div class="sw-cp-meter"><i></i></div>' +
                '<div class="sw-cp-meterlbl"><span></span><b>&mdash;</b></div>' +
                '<div class="sw-cp-reqs">' + reqHtml + '</div></div>'
            );
            panel.querySelector('.sw-cp-meterlbl span').textContent = str.strength || '';
            // Fill rule labels (textContent — labels already localised server-side).
            var reqNodes = panel.querySelectorAll('.sw-cp-req');
            rules.forEach(function(r, i) {
                reqNodes[i].querySelector('span:last-child').textContent = r.label;
            });
            if (np1item && np1item.parentNode) {
                np1item.parentNode.insertBefore(panel, np1item.nextSibling);
            }

            // Match indicator (after the Confirm row).
            var np2 = document.getElementById('id_newpassword2');
            var np2item = document.getElementById('fitem_id_newpassword2') || (np2 && np2.closest('.fitem'));
            var match = el('<div class="sw-cp-match"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12l5 5L19 7"/></svg><span></span></div>');
            if (np2item && np2item.parentNode) {
                np2item.parentNode.insertBefore(match, np2item.nextSibling);
            }

            // Evaluation.
            var bar = panel.querySelector('.sw-cp-meter i');
            var mlbl = panel.querySelector('.sw-cp-meterlbl b');
            var cur = document.getElementById('id_password');
            var submit = document.getElementById('id_submitbutton');
            var levels = [
                {w: 0, c: 'var(--sw-bad, #C24)', t: '—'},
                {w: 22, c: '#E0663A', t: str.weak || 'Weak'},
                {w: 46, c: '#E8A93C', t: str.fair || 'Fair'},
                {w: 72, c: '#C9B23A', t: str.good || 'Good'},
                {w: 100, c: 'var(--sw-ok, #1E9E6A)', t: str.strong || 'Strong'}
            ];

            var evaluate = function() {
                var v = np1.value;
                var met = 0;
                reqNodes.forEach(function(node) {
                    var key = node.getAttribute('data-key');
                    var rule = rules.filter(function(x) { return x.key === key; })[0];
                    var ok = false;
                    if (rule) {
                        if (key === 'len') { ok = v.length >= rule.min; }
                        else if (key === 'digit') { ok = count(v, /\d/g) >= rule.min; }
                        else if (key === 'lower') { ok = count(v, /[a-z]/g) >= rule.min; }
                        else if (key === 'upper') { ok = count(v, /[A-Z]/g) >= rule.min; }
                        else if (key === 'special') { ok = count(v, /[^A-Za-z0-9]/g) >= rule.min; }
                    }
                    node.classList.toggle('met', ok);
                    if (ok) { met++; }
                });
                var total = rules.length || 1;
                var lv = v.length === 0 ? 0 : Math.min(4, Math.round((met / total) * 4));
                if (v.length > 0 && lv === 0) { lv = 1; }
                var s = levels[lv];
                bar.style.width = s.w + '%';
                bar.style.background = s.c;
                mlbl.textContent = s.t;
                mlbl.style.color = (met === total && v.length > 0) ? 'var(--sw-ok, #1E9E6A)' : 'var(--sw-ink)';

                // Match.
                var mtxt = match.querySelector('span');
                var mic = match.querySelector('svg');
                if (np2 && np2.value.length) {
                    var same = np1.value === np2.value;
                    match.classList.add('show');
                    match.classList.toggle('ok', same);
                    match.classList.toggle('no', !same);
                    mtxt.textContent = same ? (str.match_ok || '') : (str.match_no || '');
                    mic.innerHTML = same ? '<path d="M5 12l5 5L19 7"/>' : '<path d="M6 6l12 12M18 6L6 18"/>';
                } else {
                    match.classList.remove('show');
                }

                // Fail-open submit gating: only disable when the user has started a
                // new password that is clearly invalid or mismatched.
                if (submit) {
                    var started = np1.value.length > 0 || (np2 && np2.value.length > 0);
                    var valid = (met === total) && np1.value.length > 0 &&
                        np2 && np1.value === np2.value && cur && cur.value.length > 0;
                    submit.disabled = started ? !valid : false;
                }
            };

            [cur, np1, np2].forEach(function(i) {
                if (i) { i.addEventListener('input', evaluate); }
            });
            evaluate();
        } catch (e) {
            // Fail open — leave the plain wrapped form fully functional.
            if (window.console && window.console.warn) {
                window.console.warn('theme_baz/changepassword: ' + e);
            }
        }
    }

    return {init: init};
});
