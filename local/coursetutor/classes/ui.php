<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_coursetutor;

/**
 * Chat panel markup + script (used as a side drawer in courses and full page in chat.php).
 *
 * @package   local_coursetutor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ui {

    /**
     * Render the chat panel.
     *
     * @param array $tutors Tutor records.
     * @param int $courseid
     * @param bool $embedded True for a full-page chat, false for the side drawer.
     * @return string
     */
    public static function panel(array $tutors, int $courseid, bool $embedded): string {
        global $PAGE, $OUTPUT;
        $canmanage = $courseid && has_capability('local/coursetutor:manage', \context_course::instance($courseid));
        $opts = '';
        foreach ($tutors as $t) {
            $opts .= '<option value="' . (int) $t->id . '">' . s($t->name) . '</option>';
        }
        $manage = $canmanage ? '<a class="ctutor-manage" href="' . (new \moodle_url('/local/coursetutor/manage.php',
            ['courseid' => $courseid]))->out() . '">' . get_string('managetutors', 'local_coursetutor') . '</a>' : '';
        $cls = $embedded ? 'ctutor ctutor-embedded open' : 'ctutor';
        $h = '<div id="ctutor" class="' . $cls . '" role="dialog" aria-label="' . s(get_string('pluginname', 'local_coursetutor')) . '">'
            . '<div class="ctutor-head"><i class="fa fa-comments" aria-hidden="true"></i> '
            . '<select class="ctutor-select" aria-label="Tutor"' . (count($tutors) < 2 ? ' style="display:none"' : '') . '>' . $opts . '</select>'
            . '<strong class="ctutor-title">' . s(count($tutors) ? reset($tutors)->name : get_string('pluginname', 'local_coursetutor')) . '</strong>'
            . $manage . ($embedded ? '' : '<button type="button" class="ctutor-close" aria-label="Close">&times;</button>') . '</div>'
            . '<div class="ctutor-log" aria-live="polite"></div>';
        if ($tutors) {
            $h .= '<form class="ctutor-form"><textarea rows="2" placeholder="' . s(get_string('askplaceholder', 'local_coursetutor'))
                . '"></textarea><button type="submit" class="btn btn-primary">' . get_string('send', 'local_coursetutor') . '</button></form>';
        } else {
            $h .= '<div class="ctutor-empty">' . get_string($canmanage ? 'notutorsteacher' : 'notutors', 'local_coursetutor') . '</div>';
        }
        $h .= '</div>';
        if (!$embedded) {
            $h .= '<button type="button" id="coursetutor-launcher" hidden>open</button>';
        }

        $strings = json_encode([
            'thinking' => get_string('thinking', 'local_coursetutor'),
            'hello' => get_string('hello', 'local_coursetutor'),
        ]);
        $PAGE->requires->js_amd_inline(<<<JS
require(['core/ajax'], function(Ajax) {
  var box = document.getElementById('ctutor'); if (!box) { return; }
  var S = $strings;
  var log = box.querySelector('.ctutor-log'), sel = box.querySelector('.ctutor-select'),
      form = box.querySelector('.ctutor-form'), title = box.querySelector('.ctutor-title');
  function esc(t){ var d=document.createElement('div'); d.textContent=t; return d.innerHTML; }
  function md(t){ return esc(t).replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>').replace(/`([^`]+)`/g,'<code>$1</code>')
      .replace(/^\s*[-*] (.*)$/gm,'• $1').replace(/\\n/g,'<br>'); }
  function add(role, text){ var b=document.createElement('div'); b.className='ctutor-msg '+(role==='user'?'me':'bot');
      b.innerHTML = role==='user' ? esc(text).replace(/\\n/g,'<br>') : md(text); log.appendChild(b); log.scrollTop=log.scrollHeight; return b; }
  function load(){ if (!sel || !sel.value) { return; } log.innerHTML='';
    title.textContent = sel.options[sel.selectedIndex].text;
    Ajax.call([{methodname:'local_coursetutor_history', args:{tutorid: parseInt(sel.value,10)}}])[0].then(function(rows){
      if (!rows.length) { add('assistant', S.hello.replace('{name}', title.textContent)); }
      rows.forEach(function(r){ add(r.role, r.content); });
    }).catch(function(){ add('assistant', S.hello.replace('{name}', title.textContent)); });
  }
  var loaded=false;
  function open(){ box.classList.add('open'); if(!loaded){ loaded=true; load(); } var ta=box.querySelector('textarea'); if(ta){ ta.focus(); } }
  var l=document.getElementById('coursetutor-launcher'); if(l){ l.addEventListener('click', open); }
  var c=box.querySelector('.ctutor-close'); if(c){ c.addEventListener('click', function(){ box.classList.remove('open'); }); }
  if (sel) { sel.addEventListener('change', load); }
  if (box.classList.contains('open')) { loaded=true; load(); }
  if (form) {
    var ta=form.querySelector('textarea');
    ta.addEventListener('keydown', function(e){ if(e.key==='Enter' && !e.shiftKey){ e.preventDefault(); form.requestSubmit(); } });
    form.addEventListener('submit', function(e){ e.preventDefault(); var q=ta.value.trim(); if(!q){return;}
      ta.value=''; add('user', q); var wait=add('assistant', S.thinking); wait.classList.add('wait'); form.querySelector('button').disabled=true;
      Ajax.call([{methodname:'local_coursetutor_send', args:{tutorid: parseInt(sel.value,10), message:q}}])[0].then(function(r){
        wait.remove(); add('assistant', r.ok ? r.answer : '⚠ ' + r.error);
      }).catch(function(err){ wait.remove(); add('assistant', '⚠ ' + ((err && err.message) || 'Error')); })
      .then(function(){ form.querySelector('button').disabled=false; ta.focus(); });
    });
  }
});
JS
        );
        return $h;
    }
}
