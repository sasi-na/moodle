<?php
/**
 * Adds the AI Dock (four round buttons, bottom-left) to every page.
 *
 * @package    local_aidock
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_aidock;

defined('MOODLE_INTERNAL') || die();

class hook_callbacks {

    public static function before_footer(\core\hook\output\before_footer_html_generation $hook): void {
        global $PAGE, $COURSE;
        try {
            if (during_initial_install() || !empty($GLOBALS['CFG']->upgraderunning)) {
                return;
            }
            if (get_config('local_aidock', 'enabled') === '0' || !isloggedin() || isguestuser()) {
                return;
            }
            if (in_array($PAGE->pagelayout, ['embedded', 'popup', 'frametop', 'maintenance', 'print', 'secure', 'redirect', 'login'], true)) {
                return;
            }
            if (strpos((string)$PAGE->pagetype, 'mod-quiz-attempt') === 0) {
                return; // Never during a quiz attempt.
            }
            $isadmin = is_siteadmin();
            $courseid = (!empty($COURSE->id) && $COURSE->id > 1) ? (int)$COURSE->id : 0;
            $buttons = [];
            foreach (tools::all() as $key => $t) {
                $buttons[] = [
                    'key' => $key,
                    'name' => get_string($key, 'local_aidock'),
                    'icon' => $t['icon'], 'color' => $t['color'],
                    'ready' => $t['ready'],
                    'status' => get_string($t['ready'] ? 'statuson' : 'statusoff', 'local_aidock'),
                    'url' => isset($t['url'])
                        ? (new \moodle_url($t['url'], $courseid ? ['courseid' => $courseid] : []))->out(false)
                        : (new \moodle_url('/local/aidock/tool.php', ['t' => $key, 'courseid' => $courseid]))->out(false),
                    'setup' => $isadmin && !$t['ready'] ? $t['setup']->out(false) : '',
                ];
            }
            if (!$buttons) {
                return;
            }
            // External tool (LTI, e.g. EduGears) pages: no Summarise button (the tool's content is not readable by Moodle)
            // and let the tool window use the full screen height.
            if (strpos((string) $PAGE->pagetype, 'mod-lti-') === 0) {
                $hook->add_html('<style>.ai-course-summarise-controls,'
                    . '.path-mod-lti .activity-header{display:none !important}'
                    . '#contentframe{height:calc(100vh - 80px) !important;min-height:640px;width:100% !important}</style>'
                    . '<script>document.addEventListener("DOMContentLoaded",function(){var f=document.getElementById("contentframe");'
                    . 'if(!f){return;}var p=f.parentElement;while(p&&p.previousElementSibling===null&&p.id!=="region-main"){p=p.parentElement;}'
                    . 'for(var e=p&&p.previousElementSibling;e;e=e.previousElementSibling){if(!e.innerText.trim()&&!e.querySelector("img,iframe,button,a")){e.style.display="none";}}'
                    . 'if(f.getBoundingClientRect().top>200){f.scrollIntoView({block:"start"});window.scrollBy(0,-70);}});</script>');
            }
            $hook->add_html(self::html($buttons, $courseid));
        } catch (\Throwable $e) {
            debugging('local_aidock: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    protected static function html(array $buttons, int $courseid): string {
        $h = '<nav class="aidock" aria-label="AI assistants">';
        foreach ($buttons as $b) {
            $h .= '<a href="' . s($b['url']) . '" class="aidock-btn" data-aidock="' . s($b['key']) . '" data-ready="' . ($b['ready'] ? 1 : 0)
                . '" data-setup="' . s($b['setup']) . '" style="--aidock-c:' . s($b['color']) . '" title="' . s($b['name'] . ' – ' . $b['status']) . '">'
                . '<i class="fa ' . s($b['icon']) . '" aria-hidden="true"></i><span class="aidock-dot' . ($b['ready'] ? ' on' : '') . '"></span>'
                . '<span class="aidock-label">' . s($b['name']) . '<small>' . s($b['status']) . '</small></span></a>';
        }
        $h .= '</nav>';
        // Keep the dock clear of the theme's floating "Need help?" button (BAZ layouts).
        $h .= '<style>body:has(.cv-help-fab) nav.aidock{bottom:96px !important}'
            . '@media (max-width:576px){body:has(.cv-help-fab) nav.aidock{bottom:80px !important}}</style>';
        $h .= <<<JS
<script>
(function(){
  function hideNative(){ /* keep the dock as the single entry point on the left */ }
  // Some theme layouts (e.g. the BAZ student course view) hide Moodle's main region, where these widgets
  // are printed. Move the dock and the AI chat widgets to <body> so they stay visible and clickable.
  function inHidden(el){
    for (var e = el.parentElement; e && e !== document.body; e = e.parentElement) {
      if (getComputedStyle(e).display === 'none') { return true; }
    }
    return false;
  }
  function rescue(){
    ['#geniai-chat', '.tutor-ia-toggle-button', '.tutor-ia-drawer', '#ctutor', '#coursetutor-launcher', 'nav.aidock'].forEach(function(s){
      document.querySelectorAll(s).forEach(function(el){ if (inHidden(el)) { document.body.appendChild(el); } });
    });
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', rescue); } else { rescue(); }
  // Always sit above the theme's floating "Need help?" button, wherever it is drawn.
  function clearFab(){
    var dock = document.querySelector('nav.aidock'), fab = document.querySelector('.cv-help-fab');
    if (!dock) { return; }
    if (fab && fab.getClientRects().length) {
      var r = fab.getBoundingClientRect();
      if (r.left < 120) { dock.style.setProperty('bottom', Math.max(18, Math.round(window.innerHeight - r.top + 14)) + 'px', 'important'); }
    } else {
      dock.style.removeProperty('bottom');
    }
  }
  function place(){ rescue(); clearFab(); }
  window.addEventListener('load', function(){ place(); setTimeout(place, 800); setTimeout(place, 2500); });
  window.addEventListener('resize', clearFab);
  place();
  document.addEventListener('click', function(e){
    var a = e.target.closest && e.target.closest('.aidock-btn'); if (!a) { return; }
    var k = a.getAttribute('data-aidock'), btn = null;
    if (k === 'geniai') { btn = document.querySelector('#geniai-chat-btn'); }
    if (k === 'tutorai') { btn = document.querySelector('.tutor-ia-toggle-button'); }
    if (k === 'raison') { btn = document.querySelector('#corolair-launcher, .raison-launcher'); }
    if (k === 'coursetutor') { btn = document.querySelector('#coursetutor-launcher'); }
    if (btn) { e.preventDefault(); btn.click(); return; }
    if (a.getAttribute('data-ready') !== '1' && a.getAttribute('data-setup')) { e.preventDefault(); window.location.href = a.getAttribute('data-setup'); }
  });
})();
</script>
JS;
        return $h;
    }
}
