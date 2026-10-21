<?php
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

namespace local_authpopup;

/**
 * Prints the login / logout pop-up on the next page, in any theme.
 *
 * @package   local_authpopup
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /**
     * @param \core\hook\output\before_footer_html_generation $hook
     */
    public static function before_footer(\core\hook\output\before_footer_html_generation $hook): void {
        global $SESSION, $USER, $PAGE;
        try {
            if (during_initial_install() || in_array($PAGE->pagelayout, ['embedded', 'popup', 'frametop', 'redirect', 'print'], true)) {
                return;
            }
            $kind = '';
            if (!empty($SESSION->local_authpopup) && isloggedin() && !isguestuser()) {
                // Every role (students, teachers, admins ...). Kept until the user reaches the
                // dashboard (some land on a course or home page first), for up to 10 minutes.
                if (time() - (int) ($SESSION->local_authpopup_time ?? 0) > 600) {
                    unset($SESSION->local_authpopup, $SESSION->local_authpopup_time);
                    return;
                }
                if ((string) $PAGE->pagetype !== 'my-index') {
                    return;
                }
                $kind = $SESSION->local_authpopup;
                unset($SESSION->local_authpopup, $SESSION->local_authpopup_time);
            } else if (!isloggedin() && ($_COOKIE[observer::COOKIE] ?? '') === 'out') {
                $kind = 'out';
                unset($_COOKIE[observer::COOKIE]);
            }
            if ($kind === '') {
                return;
            }
            $cookiepath = !empty($GLOBALS['CFG']->sessioncookiepath) ? $GLOBALS['CFG']->sessioncookiepath : '/';
            // Shown once, only where it belongs: the login pop-up on the dashboard, the
            // logout pop-up on the home page. Anywhere else the message is just dropped.
            $pagetype = (string) $PAGE->pagetype;
            $place = $kind === 'out' ? ($pagetype === 'site-index') : ($pagetype === 'my-index');
            if (!$place) {
                $hook->add_html('<script>document.cookie=' . json_encode(observer::COOKIE . '=; Max-Age=0; path=' . $cookiepath)
                    . ';document.cookie=' . json_encode(observer::COOKIE . '=; Max-Age=0; path=/') . ';</script>');
                return;
            }
            if ($kind === 'out') {
                $msg = ['tone' => 'out', 'title' => get_string('loggedout_title', 'local_authpopup'),
                    'text' => ''];
            } else {
                $name = fullname($USER);
                $hour = (int) userdate(time(), '%H');
                $greet = get_string($hour < 12 ? 'morning' : ($hour < 17 ? 'afternoon' : 'evening'), 'local_authpopup');
                if ($kind === 'as' && \core\session\manager::is_loggedinas()) {
                    $real = \core\session\manager::get_realuser();
                    $msg = ['tone' => 'as', 'title' => get_string('loggedinas_title', 'local_authpopup', $name),
                        'text' => get_string('loggedinas_text', 'local_authpopup', fullname($real))];
                } else {
                    $last = !empty($USER->lastlogin) ? userdate($USER->lastlogin, get_string('strftimedatetimeshort', 'langconfig')) : '';
                    $msg = ['tone' => 'in', 'title' => get_string('loggedin_title', 'local_authpopup',
                        (object) ['greet' => $greet, 'name' => $name]),
                        'text' => ''];
                }
            }
            $msg['close'] = get_string('closebuttontitle');
            $msg['cookie'] = observer::COOKIE;
            $msg['path'] = $cookiepath;
            $hook->add_html(self::markup($msg));
        } catch (\Throwable $e) {
            debugging('local_authpopup: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Self-contained pop-up (inline CSS + JS so it looks the same in every theme).
     *
     * @param array $m
     * @return string
     */
    protected static function markup(array $m): string {
        $css = <<<CSS
.apx-wrap{position:fixed;top:16px;right:20px;z-index:100000;width:min(300px,calc(100vw - 24px));pointer-events:none;font-family:inherit}
.apx{pointer-events:auto;display:flex;gap:10px;align-items:center;background:#fff;color:#14213d;border-radius:14px;padding:9px 8px 11px 10px;box-shadow:0 12px 32px rgba(15,31,61,.18),0 1px 4px rgba(15,31,61,.08);border:1px solid #e6ebf3;position:relative;overflow:hidden;opacity:0;transform:translateY(-16px) scale(.98);transition:opacity .35s ease,transform .35s cubic-bezier(.2,.9,.25,1.2)}
.apx.on{opacity:1;transform:none}
.apx-ic{width:30px;height:30px;border-radius:9px;display:grid;place-items:center;flex:none}
.apx-ic svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.apx-in .apx-ic{background:#e3f6ee;color:#16936b}.apx-as .apx-ic{background:#eaf0fc;color:#2f6fe4}.apx-out .apx-ic{background:#fdf0d5;color:#c27a0e}
.apx-b{flex:1;min-width:0}
.apx-t{font-weight:700;font-size:13px;line-height:1.3;margin:0 0 1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.apx-s{font-size:11.5px;line-height:1.35;color:#5f6c82;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.apx-x{border:0;background:transparent;color:#8a96a8;width:24px;height:24px;border-radius:7px;cursor:pointer;font-size:17px;line-height:1;flex:none}
.apx-x:hover{background:#f0f3f8;color:#14213d}
.apx-bar{position:absolute;left:0;bottom:0;height:3px;width:100%;transform-origin:left;animation:apxbar 5s linear forwards}
.apx-in .apx-bar{background:#1fa37a}.apx-as .apx-bar{background:#2f6fe4}.apx-out .apx-bar{background:#f4b23e}
.apx:hover .apx-bar{animation-play-state:paused}
@keyframes apxbar{from{transform:scaleX(1)}to{transform:scaleX(0)}}
body.sd-dark .apx,body.theme-dark .apx{background:#141d31;color:#e6ecf7;border-color:#26334f}
body.sd-dark .apx-s,body.theme-dark .apx-s{color:#9aa7bd}
@media (prefers-reduced-motion:reduce){.apx{transition:none}.apx-bar{animation:none}}
CSS;
        $data = json_encode($m, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $js = <<<JS
(function(m){
  function show(){
    if(document.getElementById('apx-wrap')){return;}
    var icons={in:'<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5M15 12H3"/>',
      as:'<circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3-6 7-6M16 11l2 2 4-4"/>',
      out:'<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>'};
    var w=document.createElement('div');w.id='apx-wrap';w.className='apx-wrap';
    var t=document.createElement('div');t.className='apx apx-'+m.tone;t.setAttribute('role','status');t.setAttribute('aria-live','polite');
    t.innerHTML='<span class="apx-ic"><svg viewBox="0 0 24 24">'+icons[m.tone]+'</svg></span><div class="apx-b"><p class="apx-t"></p><p class="apx-s"></p></div><button type="button" class="apx-x">&times;</button><span class="apx-bar"></span>';
    t.querySelector('.apx-t').textContent=m.title;t.querySelector('.apx-s').textContent=m.text;
    var x=t.querySelector('.apx-x');x.setAttribute('aria-label',m.close);
    w.appendChild(t);document.body.appendChild(w);
    var top=12;document.querySelectorAll('#nt-shellbar,#nt-shellsub,header,nav.navbar,.navbar.fixed-top,[class*="-topbar"]').forEach(function(h){var r=h.getBoundingClientRect();if(r.height>0&&r.height<200&&r.top<160&&r.bottom>top-12&&r.bottom<260){top=Math.max(top,r.bottom+12);}});
    w.style.top=Math.round(top)+'px';
    setTimeout(function(){t.classList.add('on');},40);
    var gone=false,close=function(){if(gone){return;}gone=true;t.classList.remove('on');setTimeout(function(){w.remove();},400);};
    x.addEventListener('click',close);
    var left=5000,start=Date.now(),timer=setTimeout(close,left);
    t.addEventListener('mouseenter',function(){clearTimeout(timer);left-=Date.now()-start;});
    t.addEventListener('mouseleave',function(){start=Date.now();timer=setTimeout(close,Math.max(left,800));});
  }
  if(m.tone==='out'){document.cookie=m.cookie+'=; Max-Age=0; path='+m.path;document.cookie=m.cookie+'=; Max-Age=0; path=/';}
  // Same iPhone-style banner as every other site notification (theme_baz toast.js); own pop-up only if that is missing.
  var done=false,tries=0,kind={in:'login',as:'login',out:'logout'}[m.tone]||'info';
  function go(){
    if(done){return;}
    if(window.ntToast){done=true;window.ntToast(kind,m.title,m.text);return;}
    if(++tries<30){setTimeout(go,100);}else{done=true;show();}
  }
  if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',go);}else{go();}
})($data);
JS;
        return '<style>' . $css . '</style><script>' . $js . '</script>';
    }
}
