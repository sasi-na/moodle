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

/**
 * Theme functions.
 *
 * @package   theme_baz
 * @copyright Copyright © 2021 onwards Marcin Czaja (https://rosea.io)
 * @license   Commercial https://themeforest.net/licenses
 */

/**
 * Get theme setting
 *
 * @param string $setting
 * @param bool $format
 * @return string
 */
function theme_baz_get_setting($setting, $format = false) {
    $theme = theme_config::load('baz');

    if (empty($theme->settings->$setting)) {
        return false;
    }

    if (!$format) {
        return $theme->settings->$setting;
    }

    if ($format === 'format_text') {
        return format_text($theme->settings->$setting, FORMAT_PLAIN);
    }

    if ($format === 'format_html') {
        return format_text($theme->settings->$setting, FORMAT_HTML, array('trusted' => true, 'noclean' => true));
    }

    return format_string($theme->settings->$setting);
}

/**
 * Per-page theme init. For the bespoke student dashboard, stop the block manager
 * from creating real block content (the dashboard's calendar/private_files/etc.
 * blocks otherwise queue their JavaScript against DOM this custom layout never
 * renders, throwing console errors). Runs before /my/index.php renders the
 * 'content' region, so the flag takes effect. The dashboard renders its chosen
 * blocks explicitly via block_instance() in the exporter, unaffected by the flag.
 *
 * @param moodle_page $page
 */
function theme_baz_page_init(moodle_page $page) {
    global $USER, $CFG;

    // Accessibility shim for core modals: moves focus out of a modal the instant
    // core marks its container aria-hidden on close, avoiding the browser warning
    // "Blocked aria-hidden on an element because its descendant retained focus".
    // Purely additive (no core override); safe to load on every page.
    $page->requires->js_call_amd('theme_baz/modala11y', 'init');

    // Bespoke teacher dashboard on /my/ for faculty / TA staff audiences. Like the
    // student dashboard, decide before block regions render so the default
    // dashboard blocks' JS is never queued for DOM this layout won't output.
    // Must test the PAGELAYOUT, not just the pagetype: core sets pagetype
    // 'my-index' on /my/courses.php as well as on the dashboard, so keying off the
    // pagetype alone suppressed the My courses page's own myoverview block and left
    // it empty. And the gate must go through theme_baz\local\gate so a pilot roster
    // is honoured here too -- the old `!== '0'` test treated 'pilot' as "on for
    // everyone", which switched this branch on for every faculty/TA account.
    if ($page->pagelayout === 'mydashboard'
            && $page->pagetype === 'my-index'
            && \theme_baz\local\gate::allows('staff_view_dashboard', true)
            && in_array(\theme_baz\local\shell::audience($USER->id),
                [\theme_baz\local\shell::AUD_FACULTY, \theme_baz\local\shell::AUD_TA,
                 \theme_baz\local\shell::AUD_PM], true)) {
        $page->add_body_classes(['baz-student-exp']);
        $page->requires->js_call_amd('theme_baz/studentmsg', 'init', [
            (new \moodle_url('/message/index.php'))->out(false),
            get_string('sw_seeallmsg', 'theme_baz'),
        ]);
        require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
        $page->blocks->show_only_fake_blocks(true);
        return;
    }

    // Bespoke admin dashboard on /my/ for site administrators. Same block-region
    // handling as the student/teacher paths so the classic dashboard blocks' JS
    // is never queued for a DOM this layout won't output. Gated + off by default.
    if ($page->pagelayout === 'mydashboard'
            && $page->pagetype === 'my-index'
            && \theme_baz\local\gate::allows('admin_view_dashboard', false)
            && is_siteadmin($USER->id)) {
        $page->add_body_classes(['baz-student-exp']);
        require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
        $page->blocks->show_only_fake_blocks(true);
        return;
    }

    // SCORM player (embedded layout): Moodle has no native full-screen control, so
    // add a toggle that fills the whole screen with the SCO. Applies to everyone
    // (teachers reviewing benefit too); purely additive, no core edit.
    if ($page->pagetype === 'mod-scorm-player') {
        $page->requires->js_init_code(theme_baz_scorm_fullscreen_js(), true);
    }

    // Activity pages (incourse layout, mod-* pagetypes) get the bespoke navy/gold
    // restyle for student-only users via body classes — the activity's own markup,
    // JS, drawers and navigation are all preserved (no template rebuild). Full-
    // screen / external / secure players are excluded so they keep working as-is.
    // The forum search page (results + advanced form) uses the 'standard'
    // pagelayout, so match it by pagetype alongside the incourse mod-* pages.
    $isincoursemod = ($page->pagelayout === 'incourse' && strpos($page->pagetype, 'mod-') === 0);
    $isforumsearch = ($page->pagetype === 'mod-forum-search');
    if ($isincoursemod || $isforumsearch) {
        // Full-screen / external / secure players stay standard. VPL, SCORM and
        // LTI are NOT excluded: their student-facing *view* pages are content-like
        // incourse pages that get the bespoke shell (VPL Description/Submission/
        // Submission view/Previous; SCORM entry + attempts; LTI launch/embed).
        // Their actual players sit outside this incourse branch by layout and stay
        // full-screen: VPL IDE (mod/vpl/forms/edit.php) is 'popup', the SCORM player
        // (mod/scorm/player.php) is 'embedded', and LTI "replace window" redirects.
        $excludedmods = ['imscp', 'zoom', 'msteams', 'h5pactivity'];
        $parts = explode('-', $page->pagetype); // mod-{type}-{action}
        $modtype = $parts[1] ?? '';
        if ($modtype !== '' && !in_array($modtype, $excludedmods)
                && \theme_baz\local\experience::is_on('activity', $USER->id)) {
            $classes = ['baz-student-exp', 'baz-student-activity', 'baz-mod-' . $modtype];
            if ($isforumsearch) {
                $classes[] = 'baz-forum-search';
                // Search.php uses the 'standard' layout, which has no bespoke
                // shell. Switch it to 'incourse' so the sidebar + topbar render
                // (tmpl-incourse shows the shell for any mod-* pagetype).
                $page->set_pagelayout('incourse');
            }
            $page->add_body_classes($classes);
            $page->requires->js_call_amd('theme_baz/studentmsg', 'init', [
                (new \moodle_url('/message/index.php'))->out(false),
                get_string('sw_seeallmsg', 'theme_baz'),
            ]);
            // mod_resource has no renderer — build a bespoke document header
            // (icon + filename + type + Download/Open) from the rendered file
            // object/link client-side. Lightweight, no core edits.
            if ($modtype === 'resource') {
                $page->requires->js_init_code(theme_baz_resource_header_js(), true);
            }
            // mod_page: add a course crumb above the activity title (card hero).
            if ($modtype === 'page') {
                $page->requires->js_init_code(theme_baz_page_hero_js(), true);
            }
            // mod_url: turn the "click to open" line into an external-link card.
            if ($modtype === 'url') {
                $page->requires->js_init_code(theme_baz_url_header_js(), true);
            }
        }
        return;
    }

    // The Interest Zone is a course-category listing ('coursecategory' layout,
    // pagetype 'course-index-category'). Only the specific category the custom-nav
    // item points at gets the bespoke treatment — every other category page stays
    // classic. Suppress its blocks + tag it like the other bespoke pages.
    if ($page->pagetype === 'course-index-category') {
        $izcat = theme_baz_interestzone_categoryid();
        $curcat = optional_param('categoryid', 0, PARAM_INT);
        if ($izcat !== null && $curcat === $izcat
                && \theme_baz\local\experience::is_on('interestzone', $USER->id)) {
            $page->add_body_classes(['baz-student-exp']);
            $page->requires->js_call_amd('theme_baz/studentmsg', 'init', [
                (new \moodle_url('/message/index.php'))->out(false),
                get_string('sw_seeallmsg', 'theme_baz'),
            ]);
            require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
            $page->blocks->show_only_fake_blocks(true);
        }
        return;
    }

    // Self-enrolment page (enrol-index, 'incourse' layout): tmpl-incourse wraps it
    // in the bespoke shell. Here just tag the body (so the popover/drawer restyle
    // applies) and suppress side blocks for a clean course-enrolment card.
    if ($page->pagetype === 'enrol-index'
            && \theme_baz\local\experience::is_on('enrol', $USER->id)) {
        $page->add_body_classes(['baz-student-exp']);
        $page->requires->js_call_amd('theme_baz/studentmsg', 'init', [
            (new \moodle_url('/message/index.php'))->out(false),
            get_string('sw_seeallmsg', 'theme_baz'),
        ]);
        require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
        $page->blocks->show_only_fake_blocks(true);
        return;
    }

    // Map the bespoke pages to their "student experience" view key. The calendar
    // and grades pages share the generic 'standard' layout, so they are matched by
    // pagetype instead.
    $views = ['mydashboard' => 'dashboard', 'mycourses' => 'courses', 'course' => 'coursepage',
        // /user/view.php uses the 'mypublic' layout under a 'course-view-*' pagetype
        // (not in the pagetype map) — suppress blocks there like other profile pages.
        'mypublic' => 'profile'];
    $pagetypeviews = ['calendar-view' => 'calendar', 'grade-report-overview-index' => 'grades',
        'grade-report-user-index' => 'gradereport',
        // Profile route to the per-course grade report (/course/user.php?mode=grade).
        'course-user' => 'gradereport',
        'local-studentid-index' => 'idcard',
        'local-studentid-request' => 'idcard',
        'user-files' => 'files', 'user-profile' => 'profile',
        'message-index' => 'messages', 'message-output-popup-notifications' => 'notifications',
        // Badges: My Badges + single-badge pages (both 'standard'/'base' → columns2,
        // wrapped in the shell). The available-badges list (badges-index) is a
        // course/admin layout, so it is NOT wrapped — its cards come from the renderer.
        'badges-mybadges' => 'badges', 'badges-badge' => 'badges',
        // Blog: listing + add/edit entry (default 'standard' layout → columns2, wrapped).
        'blog-index' => 'blog', 'blog-edit' => 'blog',
        // Event registration form + open-forms list (wrapped in the shell).
        'local-eventreg-view' => 'eventreg', 'local-eventreg-index' => 'eventreg',
        // Support pages sit in the shell like any other student view.
        'local-helpdesk-raise' => 'helpdesk', 'local-helpdesk-mytickets' => 'helpdesk',
        'local-helpdesk-ticket' => 'helpdesk',
        // Account pages (all 'admin' pagelayout) — wrapped in the shell by tmpl-admin.
        'user-preferences' => 'account', 'user-edit' => 'account',
        'login-change_password' => 'account',
        // Preference sub-pages reached from the Preferences hub.
        'user-language' => 'account', 'user-forum' => 'account', 'user-editor' => 'account',
        'user-calendar' => 'account', 'user-contentbank' => 'account',
        'message-edit' => 'account', 'message-notificationpreferences' => 'account',
        'blog-preferences' => 'account', 'blog-external_blogs' => 'account',
        'blog-external_blog_edit' => 'account'];
    // Pagetype takes precedence over pagelayout: the Messages page uses the
    // 'mydashboard' layout but must NOT be treated as the dashboard.
    $view = $pagetypeviews[$page->pagetype] ?? ($views[$page->pagelayout] ?? null);
    if ($view === null) {
        return;
    }
    // The course page falls back to the standard layout while editing, so don't
    // suppress its blocks in that case.
    if ($view === 'coursepage' && $page->user_is_editing()) {
        return;
    }
    if (!\theme_baz\local\experience::is_on($view, $USER->id)) {
        return;
    }

    // Marker class so the bespoke (navy/gold) restyle of the shared notification
    // popover + message drawer applies ONLY on student-experience pages, leaving
    // those components untouched everywhere else (teachers, classic pages).
    $page->add_body_classes(['baz-student-exp']);

    // Add a "See all messages" link to the core message drawer (mirrors the
    // notification popover's "See all"). Bespoke pages only.
    $page->requires->js_call_amd('theme_baz/studentmsg', 'init', [
        (new \moodle_url('/message/index.php'))->out(false),
        get_string('sw_seeallmsg', 'theme_baz'),
    ]);

    // show_only_fake_blocks() stops core loading real blocks, which means the
    // block base class would never be required — yet core still renders the
    // dashboard's add-block scaffolding and would fatal with "Class block_base
    // not found". Load it up front so that scaffolding works.
    require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
    $page->blocks->show_only_fake_blocks(true);

    // Change password: turn the wrapped core form into a bespoke security card
    // (show/hide toggles + a live requirement checklist driven by the site's
    // actual password policy). Progressive enhancement only — real field ids,
    // CSRF token and server-side validation are untouched, and it fails open.
    if ($page->pagetype === 'login-change_password' && $view === 'account') {
        $page->requires->js_call_amd('theme_baz/changepassword', 'init',
            [theme_baz_changepassword_config()]);
    }
}

/**
 * The user's name as we want it shown on the profile page: first name plus last
 * name, with placeholder surnames dropped.
 *
 * local_ams stores a bare '.' as the last name when the source system sends no
 * surname (see local/ams/classes/external.php), so those accounts must render as
 * just the first name rather than "Firstname .". Built from the raw name fields
 * on purpose: fullname() honours fullnamedisplay / alternativefullnameformat and
 * would put the stray dot back.
 *
 * @param stdClass $user user record (needs firstname / lastname)
 * @return string display name, '' when there is nothing to show
 */
function theme_baz_profile_display_name($user): string {
    $first = trim((string) ($user->firstname ?? ''));
    $last = trim((string) ($user->lastname ?? ''));

    // A surname made up only of dots/whitespace is a placeholder, not a name.
    if (trim($last, " .\t\n\r\0\x0B") === '') {
        $last = '';
    }

    return trim($first . ' ' . $last);
}

/**
 * Add a "Full name" row to the User details card on the profile page.
 *
 * Core does not list the name as a field, and the bespoke student profile shell
 * titles the page "My Profile" rather than the student's name, so the name the
 * LMS holds is nowhere on screen. The node is positioned directly above Email by
 * theme_baz\output\core_user\myprofile\renderer::render_category() — core runs its
 * own nodes first, so insertion order alone cannot put us above the email row.
 *
 * @param \core_user\output\myprofile\tree $tree
 * @param stdClass $user user whose profile is being viewed
 * @param bool $iscurrentuser
 * @param stdClass|null $course
 */
function theme_baz_myprofile_navigation(\core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    if (empty($user) || !empty($user->deleted) || isguestuser($user)) {
        return;
    }

    $name = theme_baz_profile_display_name($user);
    if ($name === '') {
        return;
    }

    $node = new \core_user\output\myprofile\node(
        'contact',
        'fullname',
        get_string('profile_fullname', 'theme_baz'),
        null,
        null,
        s($name)
    );
    $tree->add_node($node);
}

/**
 * Build the config for the Change-password enhancement: the live password-policy
 * rules (only those actually enforced) plus localised labels/strings. Mirroring
 * $CFG keeps the checklist honest if an admin changes the policy later.
 *
 * @return array
 */
function theme_baz_changepassword_config(): array {
    global $CFG;

    $rules = [];
    $enforced = !empty($CFG->passwordpolicy);
    if ($enforced) {
        $len = (int) ($CFG->minpasswordlength ?? 8);
        if ($len > 0) {
            $rules[] = ['key' => 'len', 'min' => $len,
                'label' => get_string('cp_req_len', 'theme_baz', $len)];
        }
        $map = [
            'digit'   => ['minpassworddigits', 'cp_req_digit'],
            'lower'   => ['minpasswordlower', 'cp_req_lower'],
            'upper'   => ['minpasswordupper', 'cp_req_upper'],
            'special' => ['minpasswordnonalphanum', 'cp_req_special'],
        ];
        foreach ($map as $key => [$cfgkey, $strkey]) {
            $min = (int) ($CFG->$cfgkey ?? 0);
            if ($min > 0) {
                $rules[] = ['key' => $key, 'min' => $min,
                    'label' => get_string($strkey, 'theme_baz', $min)];
            }
        }
    }

    return [
        'rules' => $rules,
        'str' => [
            'hero_t'   => get_string('cp_hero_title', 'theme_baz'),
            'hero_s'   => get_string('cp_hero_sub', 'theme_baz'),
            'strength' => get_string('cp_strength', 'theme_baz'),
            'match_ok' => get_string('cp_match_ok', 'theme_baz'),
            'match_no' => get_string('cp_match_no', 'theme_baz'),
            'show'     => get_string('cp_show', 'theme_baz'),
            'hide'     => get_string('cp_hide', 'theme_baz'),
            'weak'     => get_string('cp_weak', 'theme_baz'),
            'fair'     => get_string('cp_fair', 'theme_baz'),
            'good'     => get_string('cp_good', 'theme_baz'),
            'strong'   => get_string('cp_strong', 'theme_baz'),
        ],
    ];
}

/**
 * Tenant-neutral branding values for templates.
 *
 * This theme is shared by every tenant built from this repo, so no user-facing
 * string may name a specific institution. Six strings used to hardcode
 * "JAIN Online"; the result was that a second tenant rendered the first one's
 * brand -- nitk-prod displayed "Copyright (c) 2025 JAIN Online" on its own front
 * page, to its own users.
 *
 * brandname is the site's own fullname, so each tenant renders itself with no
 * per-tenant configuration and nothing to forget when adding the next one.
 * brandyear is the current year, replacing a hardcoded "2025" that was already
 * a year out of date.
 *
 * Merged into global_settings(), which every layout already includes.
 *
 * @return array [brandname, brandyear]
 */
function theme_baz_brand_ctx(): array {
    global $SITE;

    $sitename = format_string($SITE->fullname, true, [
        'context' => context_course::instance(SITEID),
        'escape'  => false,
    ]);

    // A site usually has three names, not one: what it calls itself day to day
    // ("NITK Online"), its legal name for a copyright line ("National Institute of
    // Technology Karnataka, Surathkal"), and a short form for running text ("NITK").
    // The site fullname only carries the first, which is why per-tenant copies of
    // the language file used to exist purely to spell out the other two.
    //
    // Both settings fall back to the site fullname, so a site that sets neither
    // behaves exactly as before.
    $short = trim((string) theme_baz_get_setting('brandnameshort'));
    $full = trim((string) theme_baz_get_setting('brandnamefull'));
    $name = $short !== '' ? $short : $sitename;
    $legal = $full !== '' ? $full : $sitename;

    $a = (object) ['year' => date('Y'), 'name' => $legal];

    return [
        'brandname'      => $name,
        'brandnamefull'  => $legal,
        'brandyear'      => $a->year,
        // Resolved here rather than via {{#str}} with a JSON $a.
        //
        // The mustache string helper parses a JSON $a out of the raw template
        // text, so a site whose fullname contained a double quote would produce
        // malformed JSON, json_decode would return null, and the placeholders
        // would render literally. Building it in PHP sidesteps that entirely --
        // no tenant can break its own copyright line by being named awkwardly.
        'brandcopyright' => get_string('fl_copyright', 'theme_baz', $a),
        'brandfootnote'  => get_string('sd_footnote', 'theme_baz', $a),
    ];
}

/**
 * Context for the bespoke sidebar "Interest Zone" nav item.
 *
 * Sourced from the theme's existing custom-nav item 1 (label / icon / url),
 * gated on iscustomitem1on so it mirrors the classic nav. Merged into every
 * bespoke student page context so the sidebar stays consistent.
 *
 * @return array [hasinterestzone, interestzoneurl, interestzonelabel, interestzoneicon]
 */
function theme_baz_interestzone_ctx(): array {
    $ctx = [
        'hasinterestzone'   => false,
        'interestzoneurl'   => '',
        'interestzonelabel' => '',
        'interestzoneicon'  => '',
    ];
    try {
        if (get_config('theme_baz', 'iscustomitem1on') != 1) {
            return $ctx;
        }
        $label = (string) get_config('theme_baz', 'labelcustomitem1');
        $url = (string) get_config('theme_baz', 'urlcustomitem1');
        $icon = (string) get_config('theme_baz', 'iconcustomitem1');
        if (trim($label) === '' || trim($url) === '') {
            return $ctx;
        }
        // Relative urls are stored as-is (e.g. /course/index.php?categoryid=200).
        if (strpos($url, 'http') !== 0) {
            global $CFG;
            $url = $CFG->wwwroot . '/' . ltrim($url, '/');
        }
        $ctx['hasinterestzone']   = true;
        $ctx['interestzoneurl']   = $url;
        $ctx['interestzonelabel'] = format_string($label);
        $ctx['interestzoneicon']  = $icon; // Raw SVG from the setting.
    } catch (\Throwable $e) {
        return [
            'hasinterestzone'   => false,
            'interestzoneurl'   => '',
            'interestzonelabel' => '',
            'interestzoneicon'  => '',
        ];
    }
    return $ctx;
}

/**
 * The category id the Interest Zone custom-nav item points at (or null).
 *
 * @return int|null
 */
function theme_baz_interestzone_categoryid(): ?int {
    if (get_config('theme_baz', 'iscustomitem1on') != 1) {
        return null;
    }
    $url = (string) get_config('theme_baz', 'urlcustomitem1');
    if (preg_match('#/course/index\.php\?categoryid=(\d+)#', $url, $m)) {
        return (int) $m[1];
    }
    return null;
}

/**
 * JS that builds the bespoke document header for a mod_resource page from the
 * rendered file object / workaround link (mod_resource has no renderer to
 * override). Inserts .baz-res-file before the content; no core markup edits.
 *
 * @return string
 */
function theme_baz_resource_header_js(): string {
    return <<<'JS'
(function(){
  var main = document.querySelector('#region-main') || document.body;
  var content = main.querySelector('.resourcecontent');
  var obj = main.querySelector('#resourceobject');
  var work = main.querySelector('.resourceworkaround');
  var url = '';
  if (obj) { url = obj.getAttribute('data') || (obj.querySelector('param[name=src]') && obj.querySelector('param[name=src]').value) || ''; }
  if (!url && work) { var a = work.querySelector('a[href]'); if (a) { url = a.getAttribute('href'); } }
  if (!url && content) { var a2 = content.querySelector('a[href]'); if (a2) { url = a2.getAttribute('href'); } }
  if (!url) { return; }
  var clean = url.split('?')[0].split('#')[0];
  var name = decodeURIComponent(clean.substring(clean.lastIndexOf('/') + 1)) || 'File';
  var ext = (name.indexOf('.') > -1 ? name.substring(name.lastIndexOf('.') + 1) : '').toUpperCase();
  var types = {PDF:'PDF',DOC:'Word',DOCX:'Word',PPT:'PowerPoint',PPTX:'PowerPoint',XLS:'Excel',XLSX:'Excel',ZIP:'Archive',TXT:'Text',CSV:'CSV'};
  var typelbl = types[ext] || (ext ? ext + ' file' : 'File');
  var dlurl = clean + (clean.indexOf('?') > -1 ? '&' : '?') + 'forcedownload=1';
  var doc = '<path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5"/>';
  var head = document.createElement('div');
  head.className = 'baz-res-file';
  head.innerHTML =
    '<span class="baz-res-ic"><svg viewBox="0 0 24 24">' + doc + '</svg>' + (ext ? '<span class="ext">' + ext.substring(0,4) + '</span>' : '') + '</span>' +
    '<div class="baz-res-meta"><div class="baz-res-name"></div><div class="baz-res-sub"></div></div>' +
    '<div class="baz-res-acts">' +
      '<a class="baz-res-open" target="_blank" rel="noopener"><svg viewBox="0 0 24 24"><path d="M15 3h6v6M10 14 21 3M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/></svg>Open</a>' +
      '<a class="baz-res-dl"><svg viewBox="0 0 24 24"><path d="M12 3v12M7 10l5 5 5-5M5 21h14"/></svg>Download</a>' +
    '</div>';
  head.querySelector('.baz-res-name').textContent = name;
  head.querySelector('.baz-res-sub').textContent = typelbl;
  head.querySelector('.baz-res-open').setAttribute('href', clean);
  head.querySelector('.baz-res-dl').setAttribute('href', dlurl);
  var anchor = content || work;
  if (anchor && anchor.parentNode) { anchor.parentNode.insertBefore(head, anchor); }
})();
JS;
}

/**
 * JS that prepends a small course crumb above the mod_page activity title so the
 * activity header reads as a clean card hero (crumb + title). CSS in
 * scss/moodle/student-activity.scss (&.baz-mod-page) styles the header card.
 *
 * @return string
 */
function theme_baz_page_hero_js(): string {
    return <<<'JS'
(function(){
  var main = document.querySelector('#region-main') || document.body;
  var title = main.querySelector('.rui-title-container .rui-main-content-title') ||
              main.querySelector('.rui-main-content-title');
  if (!title) { return; }
  var box = title.parentNode;
  if (box.querySelector('.baz-pg-eyebrow')) { return; }
  // Derive the course name from the breadcrumb (2nd crumb = course).
  var crumb = '';
  var links = document.querySelectorAll('.ba-crumb a');
  if (links.length > 1) { crumb = (links[1].textContent || '').trim(); }
  else if (links.length) { crumb = (links[links.length - 1].textContent || '').trim(); }
  var eye = document.createElement('div');
  eye.className = 'baz-pg-eyebrow';
  eye.textContent = (crumb ? crumb + ' · ' : '') + 'Content';
  box.insertBefore(eye, title);
})();
JS;
}

/**
 * JS that turns the mod_url "Click on … to open the resource" line (.urlworkaround)
 * into a tidy external-link card (icon + name + host + Open button). Embedded URLs
 * (image/video/object) have no .urlworkaround and are framed via CSS instead.
 *
 * @return string
 */
function theme_baz_url_header_js(): string {
    return <<<'JS'
(function(){
  var main = document.querySelector('#region-main') || document.body;
  var work = main.querySelector('.urlworkaround');
  if (!work) { return; }
  var a = work.querySelector('a[href]');
  if (!a) { return; }
  var href = a.getAttribute('href');
  var name = (a.textContent || '').trim() || 'Open link';
  var host = '';
  try { host = new URL(href, window.location.origin).hostname.replace(/^www\./, ''); } catch (e) { host = ''; }
  var card = document.createElement('div');
  card.className = 'baz-url-card';
  card.innerHTML =
    '<span class="baz-url-ic"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.5 2.5 15 0 18M12 3c-2.5 2.5-2.5 15 0 18"/></svg></span>' +
    '<div class="baz-url-meta"><div class="baz-url-crumb">External link</div><div class="baz-url-name"></div><div class="baz-url-host"></div></div>' +
    '<a class="baz-url-open" target="_blank" rel="noopener"><svg viewBox="0 0 24 24"><path d="M15 3h6v6M10 14 21 3M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/></svg>Open link</a>';
  card.querySelector('.baz-url-name').textContent = name;
  card.querySelector('.baz-url-host').textContent = host || href;
  var open = card.querySelector('.baz-url-open');
  open.setAttribute('href', href);
  // Preserve the original popup/new-window click behaviour if any.
  if (a.getAttribute('onclick')) { open.setAttribute('onclick', a.getAttribute('onclick')); }
  work.textContent = '';
  work.appendChild(card);
})();
JS;
}

/**
 * JS that adds a "Full screen" toggle to the SCORM player. Moodle's SCORM player
 * (mod/scorm/player.php) builds #scorm_content > #scorm_object via YUI async, and
 * has no native full-screen control; this injects one that uses the Fullscreen
 * API on the content region so the SCO fills the whole screen. No core edit.
 *
 * @return string
 */
function theme_baz_scorm_fullscreen_js(): string {
    return <<<'JS'
(function(){
  var EXP = '<svg viewBox="0 0 24 24"><path d="M8 3H5a2 2 0 0 0-2 2v3M16 3h3a2 2 0 0 1 2 2v3M8 21H5a2 2 0 0 1-2-2v-3M16 21h3a2 2 0 0 0 2-2v-3"/></svg>';
  var SHR = '<svg viewBox="0 0 24 24"><path d="M4 8V6a2 2 0 0 1 2-2h2M16 4h2a2 2 0 0 1 2 2v2M20 16v2a2 2 0 0 1-2 2h-2M8 20H6a2 2 0 0 1-2-2v-2"/></svg>';
  var tries = 0;
  var timer = setInterval(function(){
    tries++;
    var content = document.getElementById('scorm_content');
    var obj = document.getElementById('scorm_object');
    if ((content && obj) || tries > 40) {
      clearInterval(timer);
      if (!content || !obj || document.getElementById('baz-scorm-fs')) { return; }

      // Core's exit bar: the row immediately above #scormpage carrying "Exit
      // activity". mod/scorm/player.php renders it only when the display mode is
      // not 'popup', so it can legitimately be absent. Matched structurally, not
      // by its label, so it is found in any language.
      var page = document.getElementById('scormpage');
      var prev = page ? page.previousElementSibling : null;
      var bar = (prev && prev.classList.contains('flex-row-reverse')
                 && prev.querySelector('a')) ? prev : null;

      // Lift the exit bar onto the AI Summarise row.
      //
      // scss/moodle/scorm.scss pins .rui-scorm-exit-btn with position:fixed at
      // top:$navbar-height+10, which was right for the classic theme's bare
      // player page. Under the shell that lands on the page header -- above the
      // eyebrow and the activity title -- so the bar is put back in flow (see
      // .baz-scorm-actionrow in student-activity.scss) and moved beside
      // Summarise, which is where these controls belong.
      //
      // Summarise is server-rendered by aiplacement_courseassist, so it is in
      // the document by the time this runs. When it is absent -- AI off, or a
      // role without aiplacement/courseassist:summarise_text -- the bar stays
      // where core put it and only loses the fixed positioning, which still
      // beats floating over the header.
      if (bar) {
        var summ = document.querySelector('.ai-course-summarise-controls');
        if (summ && !summ.contains(bar)) {
          summ.classList.add('baz-scorm-actionrow');
          summ.appendChild(bar);
        }
        bar.classList.add('baz-scorm-exitbar-inflow');
      }

      var btn = document.createElement('button');
      btn.type = 'button';
      btn.id = 'baz-scorm-fs';
      btn.className = 'baz-scorm-fs';
      var label = function(t){ btn.innerHTML = (t ? SHR : EXP) + '<span>' + (t ? 'Exit full screen' : 'Full screen') + '</span>'; };
      label(false);

      // Windowed, the button belongs in core's action row. Floating it over the
      // SCO put it ~20px below "Exit activity" on the same right edge, and
      // covered the top-right corner some packages use for their own controls.
      //
      // Fullscreen, it MUST sit inside #scorm_content: that is the element being
      // fullscreened, and anything outside it is not rendered, so leaving it in
      // the bar would make "Exit full screen" vanish exactly when it is needed.
      // One button re-parented, rather than two to keep in sync.
      //
      // appendChild, not insertBefore(firstChild): the row is flex-row-reverse,
      // so the FIRST DOM child renders rightmost. Core's Exit activity is that
      // first child, and it should keep the outer edge it has always had --
      // appending makes Full screen the second child, which renders immediately
      // to its left. (Prepending was tried first and displaced Exit activity
      // inboard, which read wrong.)
      var place = function(fs) {
        if (fs || !bar) {
          if (getComputedStyle(content).position === 'static') { content.style.position = 'relative'; }
          content.appendChild(btn);
          btn.classList.add('baz-scorm-fs--overlay');
        } else {
          bar.appendChild(btn);
          btn.classList.remove('baz-scorm-fs--overlay');
        }
      };
      place(false);
      var isFs = function(){ return document.fullscreenElement || document.webkitFullscreenElement; };
      btn.addEventListener('click', function(){
        if (isFs()) {
          (document.exitFullscreen || document.webkitExitFullscreen || function(){}).call(document);
        } else {
          (content.requestFullscreen || content.webkitRequestFullscreen || function(){}).call(content);
        }
      });
      // SCORM expands the table of contents by itself when we go fullscreen:
      // scorm_toggle_toc(true) in mod/scorm/module.js runs on every resize, and
      // entering fullscreen reports a screen-sized winWidth, so it clears the
      // TOC's collapsed state. It is invisible while fullscreen (the TOC is a
      // sibling outside #scorm_content) but is left expanded on the way out,
      // which is not where the learner had it.
      //
      // So remember the state on the way in and put it back on the way out.
      // Restoring by clicking SCORM's own toggle rather than by editing classes,
      // so its internal bookkeeping (disabled-by, button label, content grid
      // class) stays consistent with what is on screen.
      var toc = document.getElementById('scorm_toc');
      var wascollapsed = null;
      var restoretoc = function() {
        var tgl = document.getElementById('scorm_toc_toggle_btn');
        if (!toc || !tgl || wascollapsed === null) { return; }
        var now = toc.classList.contains('disabled');
        if (now !== wascollapsed) { tgl.click(); }
        wascollapsed = null;
      };

      var sync = function(){
        var fs = !!isFs();
        if (fs && toc && wascollapsed === null) {
          wascollapsed = toc.classList.contains('disabled');
        }
        label(fs);
        place(fs);
        if (!fs) { setTimeout(restoretoc, 0); }
      };
      document.addEventListener('fullscreenchange', sync);
      document.addEventListener('webkitfullscreenchange', sync);
    }
  }, 250);
})();
JS;
}

/**
 * Post process the CSS tree.
 *
 * @param string $tree The CSS tree.
 * @param theme_config $theme The theme config object.
 */
function theme_baz_css_tree_post_processor($tree, $theme) {
    error_log('theme_baz_css_tree_post_processor() is deprecated. Required' .
        'prefixes for Bootstrap are now in theme/baz/scss/moodle/prefixes.scss');
    $prefixer = new theme_baz\autoprefixer($tree);
    $prefixer->prefix();
}

/**
 * Get the current user preferences that are available
 *
 * @return array[]
 */
function theme_baz_user_preferences(): array {
    return [
        'drawer-open-block' => [
            'type' => PARAM_BOOL,
            'null' => NULL_NOT_ALLOWED,
            'default' => false,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
        'drawer-open-index' => [
            'type' => PARAM_BOOL,
            'null' => NULL_NOT_ALLOWED,
            'default' => true,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
        'mycourses-on' => [
            'type' => PARAM_BOOL,
            'null' => NULL_NOT_ALLOWED,
            'default' => false,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
        'mycourses-hidden-on' => [
            'type' => PARAM_BOOL,
            'null' => NULL_NOT_ALLOWED,
            'default' => true,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
        'mycourses-inprogress-on' => [
            'type' => PARAM_BOOL,
            'null' => NULL_NOT_ALLOWED,
            'default' => true,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
        'darkmode-on' => [
            'type' => PARAM_BOOL,
            'null' => NULL_NOT_ALLOWED,
            'default' => false,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
        'sidepre-open' => [
            'type' => PARAM_BOOL,
            'null' => NULL_NOT_ALLOWED,
            'default' => true,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
        'drawer-open-nav' => [
            'type' => PARAM_BOOL,
            'null' => NULL_NOT_ALLOWED,
            'default' => true,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
    ];
}

/**
 * Inject additional SCSS.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_baz_get_extra_scss($theme) {
    $content = '';

    // Sets the login background image.
    // Check login layout, only layout #1 has background image.
    $loginlayout = theme_baz_get_setting('setloginlayout');
    $loginlayoutimg = false;

    if ($loginlayout == 1 || $loginlayout == 4 || $loginlayout == 5) {
        $loginlayoutimg = true;
    }
    if ($loginlayout == 2 || $loginlayout == 3) {
        $loginlayoutimg = false;
    } else {
        $loginlayoutimg = false;
    }

    // Sets the background image, and its settings.
    $imageurl = $theme->setting_file_url('bodybgimg', 'bodybgimg');
    if (!empty($imageurl)) {
        $content .= '@media (min-width: 768px) {';
        $content .= 'body { ';
        $content .= "background-image: url('$imageurl'); background-size: cover; background-position: center; background-attachment: fixed; background-repeat: no-repeat;";
        $content .= ' } }';
    }

    $loginbackgroundimageurl = $theme->setting_file_url('loginbg', 'loginbg');
    if ($loginlayout == 1 || $loginlayout == 4 || $loginlayout == 5) {
        if (!empty($loginbackgroundimageurl)) {
            $content .= 'body.path-login { ';
            $content .= "background-image: url('$loginbackgroundimageurl'); background-size: cover; background-attachment: fixed;";
            $content .= ' }';
        }
    }

    $forcefwvideo = theme_baz_get_setting('forcefwvideo');
    if ($forcefwvideo == 1) {
        $content .= '.mediaplugin.mediaplugin_videojs div[style*="max-width"] {margin-left:auto;margin-right:auto;
            width: 100%; max-width: 100% !important;}';
    }

    // Always return the background image with the scss when we have it.
    return !empty($theme->settings->scss) ? $theme->settings->scss . ' ' . $content : $content;
}

/**
 * Serves any files associated with the theme settings.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function theme_baz_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    if ($context->contextlevel == CONTEXT_SYSTEM) {
        $theme = theme_config::load('baz');
        // By default, theme files must be cache-able by both browsers and proxies.
        if (!array_key_exists('cacheability', $options)) {
            $options['cacheability'] = 'public';
        }
        if ($filearea === 'hvp') {
            return theme_baz_serve_hvp_css($args[1], $theme);
        }
        if ($filearea === 'favicon') {
            return $theme->setting_file_serve('favicon', $args, $forcedownload, $options);
        } else if ($filearea === 'logo') {
            return $theme->setting_file_serve('logo', $args, $forcedownload, $options);
        } else if ($filearea === 'loginbg') {
            return $theme->setting_file_serve('loginbg', $args, $forcedownload, $options);
        } else if ($filearea === 'bodybgimg') {
            return $theme->setting_file_serve('bodybgimg', $args, $forcedownload, $options);
        } else if ($filearea === 'customloginlogo') {
            return $theme->setting_file_serve('customloginlogo', $args, $forcedownload, $options);
        } else if ($filearea === 'customlogindmlogo') {
            return $theme->setting_file_serve('customlogindmlogo', $args, $forcedownload, $options);
        } else if ($filearea === 'customlogo') {
            return $theme->setting_file_serve('customlogo', $args, $forcedownload, $options);
        } else if ($filearea === 'fontfiles') {
            return $theme->setting_file_serve('fontfiles', $args, $forcedownload, $options);
        } else if ($filearea === 'bazsettingsimgs') {
            return $theme->setting_file_serve('bazsettingsimgs', $args, $forcedownload, $options);
        } else if (preg_match("/^dashboardbannerimg[1-5]$/", $filearea) === 1) {
            return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
        } else if (preg_match("/^block1slideimg[1-9][0-9]?$/", $filearea) !== false) {
            return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
        } else if (preg_match("/^block5itemimg[1-9][0-9]?$/", $filearea) !== false) {
            return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
        } else if ($filearea === 'block2videoposter') {
            return $theme->setting_file_serve('block2videoposter', $args, $forcedownload, $options);
        } else if ($filearea === 'block2img') {
            return $theme->setting_file_serve('block2img', $args, $forcedownload, $options);
        } else if ($filearea === 'block2videomp4') {
            return $theme->setting_file_serve('block2videomp4', $args, $forcedownload, $options);
        } else if ($filearea === 'block2videowebm') {
            return $theme->setting_file_serve('block2videowebm', $args, $forcedownload, $options);
        } else if ($filearea === 'footerbgimg') {
            return $theme->setting_file_serve('footerbgimg', $args, $forcedownload, $options);
        } else {
            send_file_not_found();
        }
    }
}

/**
 * Get the URL of files from theme settings.
 *
 * @param $setting
 * @param $filearea
 * @param $theme
 * @return array|false|string|string[]|null
 * @throws dml_exception
 */
function theme_baz_setting_file_url($setting, $filearea, $theme) {
    global $CFG;

    $component = 'theme_baz';
    $itemid = 0;
    $filepath = $theme->settings->$filearea;

    if (empty($filepath)) {
        return false;
    }
    $syscontext = context_system::instance();

    $url = moodle_url::make_file_url("$CFG->wwwroot/pluginfile.php", "/$syscontext->id/$component/$filearea/$itemid" . $filepath);

    // Now this is tricky because the we can not hardcode http or https here, lets use the relative link.
    // Note: unfortunately moodle_url does not support //urls yet.

    $url = preg_replace('|^https?://|i', '//', $url->out(false));

    return $url;
}

/**
 * Returns the main SCSS content.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_baz_get_main_scss_content($theme) {
    global $CFG;

    $scss = '';
    $filename = !empty($theme->settings->preset) ? $theme->settings->preset : null;
    $fs = get_file_storage();

    $context = context_system::instance();
    if ($filename == 'default.scss') {
        $scss .= file_get_contents($CFG->dirroot . '/theme/baz/scss/preset/default.scss');
    } else if ($filename == 'plain.scss') {
        $scss .= file_get_contents($CFG->dirroot . '/theme/baz/scss/preset/plain.scss');
    } else if ($filename && ($presetfile = $fs->get_file($context->id, 'theme_baz', 'preset', 0, '/', $filename))) {
        $scss .= $presetfile->get_content();
    } else {
        // Safety fallback - maybe new installs etc.
        $scss .= file_get_contents($CFG->dirroot . '/theme/baz/scss/preset/default.scss');
    }

    return $scss;
}

/**
 * Get compiled css.
 *
 * @return string compiled css
 */
function theme_baz_get_precompiled_css() {
    global $CFG;
    return file_get_contents($CFG->dirroot . '/theme/baz/style/moodle.css');
}

/**
 * Derive the full brand palette from two colours.
 *
 * theme_baz ships forty brand tones (scss/_brand.scss) -- gradient stops, dark-mode
 * navies, the accent's text/background/border tints. Asking a site to supply forty
 * values would be absurd, and hand-maintaining a second copy of them per tenant is
 * how the old per-tenant brand.scss came about.
 *
 * Instead a site supplies two: a navy and an accent. Every other tone is derived by
 * taking the shipped tone's offset from ITS base in lightness and saturation, and
 * applying that offset to the new hue. The tonal relationships of the design are
 * preserved rather than reinvented, so a site that changes its colours still looks
 * like the same product.
 *
 * @param string $navy   Hex, with or without the leading hash.
 * @param string $accent Hex, with or without the leading hash.
 * @return array Map of SCSS variable name (no $) => hex string.
 */
function theme_baz_brand_palette(string $navy, string $accent): array {
    // The shipped palette. Keys match scss/_brand.scss; the two bases are first in
    // each group and everything after them is derived relative to that base.
    $shipped = [
        'navy' => [
            'bz-navy' => '#002D47', 'bz-navy-deep' => '#012137', 'bz-navy-hi' => '#01486E',
            'bz-navy-lo' => '#002235', 'bz-navy-dm' => '#06283C', 'bz-navy-dm-hi' => '#0A3854',
            'bz-navy-dm-lo' => '#061B2A', 'bz-navy-a' => '#013256', 'bz-navy-b' => '#01243B',
            'bz-navy-c' => '#0A3A63', 'bz-navy-d' => '#01314E', 'bz-navy-e' => '#073E5F',
            'bz-navy-f' => '#013556', 'bz-navy-g' => '#002236', 'bz-navy-h' => '#001A2B',
            'bz-navy-i' => '#01121F',
        ],
        'accent' => [
            'bz-accent' => '#F7B742', 'bz-accent-hi' => '#F9C45A', 'bz-accent-lo' => '#E89A2C',
            'bz-accent-lo2' => '#E0892F', 'bz-accent-text' => '#B07A12',
            'bz-accent-text2' => '#9A6B11', 'bz-accent-text3' => '#9B7414',
            'bz-accent-ink' => '#241A04', 'bz-accent-ink2' => '#1D1809',
            'bz-accent-ink3' => '#3D2A00', 'bz-accent-ink4' => '#3A2602',
            'bz-accent-ink5' => '#4A3D18', 'bz-accent-bg' => '#FFF6E5',
            'bz-accent-bg2' => '#FFF6E0', 'bz-accent-bg3' => '#FFEFD0',
            'bz-accent-bg4' => '#FFFDF7', 'bz-accent-bg5' => '#FBEECB',
            'bz-accent-bg6' => '#FCE9BE', 'bz-accent-bd' => '#F3DCA6',
            'bz-accent-bd2' => '#F1D48C', 'bz-accent-bd3' => '#F0DCA0',
            'bz-accent-bd4' => '#F0D9A6', 'bz-accent-bd5' => '#E7C277',
            'bz-accent-bd6' => '#F7D08A',
        ],
    ];
    $targets = ['navy' => $navy, 'accent' => $accent];

    $out = [];
    foreach ($shipped as $group => $tones) {
        $basehex = reset($tones);
        $basekey = key($tones);
        $target = trim((string) $targets[$group]);
        if ($target === '') {
            continue;
        }
        [$bh, $bl, $bs] = theme_baz_hex_to_hsl($basehex);
        [$th, $tl, $ts] = theme_baz_hex_to_hsl($target);
        foreach ($tones as $name => $hex) {
            if ($name === $basekey) {
                $out[$name] = theme_baz_normalise_hex($target);
                continue;
            }
            [$sh, $sl, $ss] = theme_baz_hex_to_hsl($hex);
            $out[$name] = theme_baz_hsl_to_hex($th, $tl + ($sl - $bl), $ts + ($ss - $bs));
        }
    }
    // Label on a solid accent button. Previously shipped with the navy group, so
    // it tracked the tenant's navy rather than the surface it sits on: IIITD's
    // accent is a mid blue, which left the navy label at about 3.3:1 and made
    // the "Grade" button hard to read. Pick whichever of white or the tenant's
    // own navy has the higher measured contrast against the accent.
    //
    // Measured for the three shipped accents:
    //   #0075ED (IIITD blue)  white 4.40  navy 3.33  -> white
    //   #F7B742 (jain amber)  white 1.78  navy 8.05  -> navy
    //   #3DB166 (NITK green)  white 2.73  navy 5.36  -> navy
    // so only a site with a dark accent changes.
    if (isset($out['bz-accent'])) {
        $ink = $out['bz-navy'] ?? '#002D47';
        $out['bz-accent-on'] = theme_baz_contrast($out['bz-accent'], '#FFFFFF')
            > theme_baz_contrast($out['bz-accent'], $ink) ? '#FFFFFF' : $ink;
    }

    return $out;
}

/**
 * Relative luminance of a hex colour, per WCAG 2.x.
 *
 * @param string $hex
 * @return float 0..1
 */
function theme_baz_relative_luminance(string $hex): float {
    $hex = ltrim(theme_baz_normalise_hex($hex), '#');
    $channels = [];
    foreach ([0, 2, 4] as $offset) {
        $c = hexdec(substr($hex, $offset, 2)) / 255;
        $channels[] = $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
    }
    return (0.2126 * $channels[0]) + (0.7152 * $channels[1]) + (0.0722 * $channels[2]);
}

/**
 * WCAG contrast ratio between two hex colours, 1..21.
 *
 * @param string $a
 * @param string $b
 * @return float
 */
function theme_baz_contrast(string $a, string $b): float {
    $la = theme_baz_relative_luminance($a);
    $lb = theme_baz_relative_luminance($b);
    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

/**
 * Normalise a hex colour to #RRGGBB uppercase.
 *
 * @param string $hex
 * @return string
 */
function theme_baz_normalise_hex(string $hex): string {
    $hex = ltrim(trim($hex), '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    return '#' . strtoupper(substr($hex, 0, 6));
}

/**
 * Convert a hex colour to [hue, lightness, saturation], each 0..1.
 *
 * @param string $hex
 * @return array
 */
function theme_baz_hex_to_hsl(string $hex): array {
    $hex = ltrim(theme_baz_normalise_hex($hex), '#');
    $r = hexdec(substr($hex, 0, 2)) / 255;
    $g = hexdec(substr($hex, 2, 2)) / 255;
    $b = hexdec(substr($hex, 4, 2)) / 255;
    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $l = ($max + $min) / 2;
    if ($max === $min) {
        return [0.0, $l, 0.0];
    }
    $d = $max - $min;
    $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
    if ($max === $r) {
        $h = (($g - $b) / $d) + ($g < $b ? 6 : 0);
    } else if ($max === $g) {
        $h = (($b - $r) / $d) + 2;
    } else {
        $h = (($r - $g) / $d) + 4;
    }
    return [$h / 6, $l, $s];
}

/**
 * Convert [hue, lightness, saturation] back to a hex colour, clamping out of range
 * lightness and saturation rather than wrapping them.
 *
 * @param float $h
 * @param float $l
 * @param float $s
 * @return string
 */
function theme_baz_hsl_to_hex(float $h, float $l, float $s): string {
    $l = max(0.0, min(1.0, $l));
    $s = max(0.0, min(1.0, $s));
    if ($s == 0.0) {
        $r = $g = $b = $l;
    } else {
        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - ($l * $s);
        $p = 2 * $l - $q;
        $hue2rgb = function ($p, $q, $t) {
            if ($t < 0) {
                $t += 1;
            }
            if ($t > 1) {
                $t -= 1;
            }
            if ($t < 1 / 6) {
                return $p + ($q - $p) * 6 * $t;
            }
            if ($t < 1 / 2) {
                return $q;
            }
            if ($t < 2 / 3) {
                return $p + ($q - $p) * (2 / 3 - $t) * 6;
            }
            return $p;
        };
        $r = $hue2rgb($p, $q, $h + 1 / 3);
        $g = $hue2rgb($p, $q, $h);
        $b = $hue2rgb($p, $q, $h - 1 / 3);
    }
    return sprintf('#%02X%02X%02X', (int) round($r * 255), (int) round($g * 255), (int) round($b * 255));
}

/**
 * Get SCSS to prepend.
 *
 * @param theme_config $theme The theme config object.
 * @return array
 */
function theme_baz_get_pre_scss($theme) {
    global $CFG;

    $scss = '';
    $configurable = [
        // Config key => [variableName, ...].
        'colorloginbgtext' => ['colorloginbgtext'],
        //Block 1
        'block1sliderwrapperbg' => ['block1slidewrapperbg'],
        // Customization
        'fontweightheadings' => ['headings-font-weight'],
        'fontbody' => ['font-family-base'],
        'fontweightregular' => ['font-weight-normal'],
        'fontweightmedium' => ['font-weight-medium'],
        'fontweightbold' => ['font-weight-bold'],
        //Text
        'colorbody' => ['body-color'],
        'colorbodysecondary' => ['body-color-secondary'],
        'colorbodylight' => ['body-color-light'],
        'colorlink' => ['link-color'],
        'colorlinkhover' => ['link-hover-color'],
        // Grays.
        'white' => ['white'],
        'black' => ['black'],
        'colorgray100' => ['gray-100'],
        'colorgray200' => ['gray-200'],
        'colorgray300' => ['gray-300'],
        'colorgray400' => ['gray-400'],
        'colorgray500' => ['gray-500'],
        'colorgray600' => ['gray-600'],
        'colorgray700' => ['gray-700'],
        'colorgray800' => ['gray-800'],
        'colorgray900' => ['gray-900'],
        // Primary.
        'colorprimary100' => ['primary-color-100'],
        'colorprimary200' => ['primary-color-200'],
        'colorprimary300' => ['primary-color-300'],
        'colorprimary400' => ['primary-color-400'],
        'colorprimary500' => ['primary-color-500'],
        'colorprimary600' => ['primary-color-600'],
        'colorprimary700' => ['primary-color-700'],
        'colorprimary800' => ['primary-color-800'],
        'colorprimary900' => ['primary-color-900'],
        //Secondary
        'colorsecondary100' => ['secondary-color-100'],
        'colorsecondary200' => ['secondary-color-200'],
        'colorsecondary300' => ['secondary-color-300'],
        'colorsecondary400' => ['secondary-color-400'],
        'colorsecondary500' => ['secondary-color-500'],
        'colorsecondary600' => ['secondary-color-600'],
        'colorsecondary700' => ['secondary-color-700'],
        'colorsecondary800' => ['secondary-color-800'],
        'colorsecondary900' => ['secondary-color-900'],
        //Others
        'colorbodybg' => ['body-bg'],
        'colorcontainerbg' => ['container-bg'],
        'colorborder' => ['border-color'],
        //Topbar
        'colortopbarbg' => ['topbar-bg'],
        'colortopbartext' => ['topbar-text'],
        'colortopbarbtn' => ['topbar-btn'],
        'colortopbarbtntext' => ['topbar-btn-text'],
        'colortopbarbtnhover' => ['topbar-btn-hover'],
        'colortopbarbtnhovertext' => ['topbar-btn-hover-text'],
        //'stickybreadcrumbs' => ['stickybreadcrumbs'],
        'wrappermdfw' => ['wrappermdfw']
    ];

    // Prepend variables first.
    foreach ($configurable as $configkey => $targets) {
        $value = isset($theme->settings->{$configkey}) ? $theme->settings->{$configkey} : null;
        if (empty($value)) {
            continue;
        }
        array_map(function ($target) use (&$scss, $value) {
            $scss .= '$' . $target . ': ' . $value . ";\n";
        }, (array) $targets);
    }

    // Prepend pre-scss.
    if (!empty($theme->settings->scsspre)) {
        $scss .= $theme->settings->scsspre;
    }

    // Brand colours.
    //
    // theme_baz ships one palette of forty tones as !default values in
    // scss/_brand.scss. A site that wants its own supplies just two colours in the
    // theme settings; the rest are derived from them, and because this callback is
    // PREPENDED the derived values win over the shipped defaults.
    //
    // A site that sets neither emits nothing here and keeps the shipped palette,
    // so existing sites are unaffected. Both must be set: deriving an accent
    // palette from a navy, or vice versa, would produce a monochrome result.
    $brandnavy = (string) ($theme->settings->brandnavy ?? '');
    $brandaccent = (string) ($theme->settings->brandaccent ?? '');
    if (trim($brandnavy) !== '' && trim($brandaccent) !== '') {
        $scss .= "\n// Brand palette derived from theme settings.\n";
        foreach (theme_baz_brand_palette($brandnavy, $brandaccent) as $name => $hex) {
            $scss .= '$' . $name . ': ' . $hex . ";\n";
        }
    }

    return $scss;
}

/**
 * Content for the floating "Need help" panel.
 *
 * Returns the helpdesk entry point where that plugin is enabled, and the legacy support
 * block otherwise, so a site adopts the ticket flow by switching the plugin on.
 *
 * @return string|null Null when there is nothing to show.
 */
/**
 * Blocks to show in a student's course rail, rendered as cards.
 *
 * The bespoke course layout does not render the side-pre region, and asking
 * the block manager for it during a real request returns nothing -- every
 * region comes back empty, while the identical call from CLI returns the
 * block. Rather than fight that, the blocks are instantiated directly, which
 * is what theme_baz_support_panel() already does for the Support block and is
 * known to work on this page.
 *
 * Which blocks appear is named in theme_baz/student_course_blocks, so a site
 * chooses what belongs in a student's rail instead of inheriting whatever an
 * admin happened to place. Empty by default: no site gains blocks it did not
 * ask for.
 *
 * @param int $courseid
 * @return array of ['title' => string, 'content' => string] for the template
 */
function theme_baz_student_course_blocks(int $courseid): array {
    global $DB, $PAGE;

    $names = trim((string) theme_baz_get_setting('student_course_blocks'));
    if ($names === '') {
        return [];
    }

    $wanted = array_filter(array_map('trim', explode(',', $names)));
    if (!$wanted) {
        return [];
    }

    require_once(__DIR__ . '/../../blocks/moodleblock.class.php');

    $out = [];
    foreach ($wanted as $blockname) {
        // Only blocks the site actually has installed and has not hidden.
        if (!$DB->record_exists('block', ['name' => $blockname, 'visible' => 1])) {
            continue;
        }
        try {
            $instances = $DB->get_records_select(
                'block_instances',
                'blockname = :bn AND ' . $DB->sql_like('pagetypepattern', ':ptp'),
                ['bn' => $blockname, 'ptp' => 'course-view%']
            );
            foreach ($instances as $instance) {
                $block = block_instance($blockname, $instance, $PAGE);
                if (!$block) {
                    continue;
                }
                $content = $block->get_content();
                $text = $content ? trim((string) $content->text) : '';
                if ($text === '') {
                    continue;
                }
                $out[] = [
                    'title' => (string) $block->title,
                    'content' => $text,
                ];
                // One card per block type is enough: the same block placed at
                // several contexts would otherwise repeat in the rail.
                break;
            }
        } catch (\Throwable $e) {
            // A block that throws must not take the course page down.
            debugging('theme_baz student course block ' . $blockname . ': '
                . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    return $out;
}

function theme_baz_support_panel(): ?string {
    $panel = theme_baz_support_panel_body();
    $contact = theme_baz_support_contact();

    // The contact details are the fallback route to a human. They belong on the
    // panel even when no ticketing route exists at all, which is why an empty
    // panel still renders when there is something to show.
    if ($panel === null) {
        return $contact !== '' ? $contact : null;
    }

    return $panel . $contact;
}

/**
 * Support email address and phone number for the help panel, as markup.
 *
 * Both are optional and independent: a site may publish one, the other, both or
 * neither. Rendered as mailto:/tel: links so they are actionable on a phone,
 * which is where a stuck student usually is.
 *
 * @param string|null $lead heading above the details; null uses the help-panel
 *                          wording, which assumes a ticket button sits above it.
 * @return string HTML, empty when the site has published neither.
 */
function theme_baz_support_contact(?string $lead = null): string {
    $email = trim((string) theme_baz_get_setting('supportcontactemail'));
    $phone = trim((string) theme_baz_get_setting('supportcontactphone'));

    if ($email === '' && $phone === '') {
        return '';
    }

    // The label depends on where this sits. Under the "Raise a ticket" button it
    // is the alternative to raising one ("Or reach us directly"); standing alone
    // in the sidebar that reads as an "or" with nothing before it.
    if ($lead === null) {
        $lead = get_string('help_contact_lead', 'theme_baz');
    }

    $out = '<div class="sw-help-contact">'
         . '<span class="sw-help-contact-lead">' . s($lead) . '</span>';

    if ($email !== '') {
        $out .= '<a class="sw-help-contact-row" href="mailto:' . s($email) . '">'
             . '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/>'
             . '<path d="m3 7 9 6 9-6"/></svg>'
             . '<span>' . s($email) . '</span></a>';
    }

    if ($phone !== '') {
        // tel: needs the punctuation stripped; the label keeps it for reading.
        $dial = preg_replace('/[^0-9+]/', '', $phone);
        $out .= '<a class="sw-help-contact-row" href="tel:' . s($dial) . '">'
             . '<svg viewBox="0 0 24 24" aria-hidden="true">'
             . '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.8.7 2.6a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.5-1.2a2 2 0 0 1 2.1-.5c.8.4 1.7.6 2.6.7a2 2 0 0 1 1.7 2z"/>'
             . '</svg><span>' . s($phone) . '</span></a>';
    }

    return $out . '</div>';
}

/**
 * The ticketing route for the help panel: an SSO endpoint, the helpdesk plugin,
 * or the Support block, in that order.
 *
 * @return string|null
 */
function theme_baz_support_panel_body(): ?string {
    global $DB, $PAGE, $CFG;

    // An external helpdesk, reached through whatever single-sign-on endpoint the
    // site provides. Checked first because a site that names one has said where
    // its support actually lives; the block below is only a fallback for sites
    // that never set anything.
    //
    // The value is a URL. A root-relative path is resolved against wwwroot, so a
    // site can point at a local endpoint that performs the SSO handshake and
    // redirects onward -- which is how IIITD reaches its helpdesk, rather than
    // linking the helpdesk directly and leaving the student to log in again.
    $ticketurl = trim((string) theme_baz_get_setting('supportticketurl'));
    if ($ticketurl !== '') {
        if (strpos($ticketurl, '/') === 0) {
            $ticketurl = $CFG->wwwroot . $ticketurl;
        }
        // Opens in a new tab: the destination is a different application, and
        // students lose their place if the LMS navigates away.
        return '<p class="sw-help-lead">' . s(get_string('help_lead', 'theme_baz')) . '</p>'
             . '<a class="sw-help-btn" href="' . s($ticketurl) . '"'
             . ' target="_blank" rel="noopener noreferrer">'
             . s(get_string('help_raise', 'theme_baz')) . '</a>';
    }

    if (get_config('local_helpdesk', 'enabled') === '1') {
        $raise = (new moodle_url('/local/helpdesk/raise.php'))->out(false);
        $track = (new moodle_url('/local/helpdesk/mytickets.php'))->out(false);
        return '<p class="sw-help-lead">' . s(get_string('help_lead', 'theme_baz')) . '</p>'
             . '<a class="sw-help-btn" href="' . $raise . '">' . s(get_string('help_raise', 'theme_baz')) . '</a>'
             . '<a class="sw-help-link" href="' . $track . '">' . s(get_string('help_track', 'theme_baz')) . '</a>';
    }

    require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
    try {
        $instances = $DB->get_records_select('block_instances',
            'blockname = :bn AND ' . $DB->sql_like('pagetypepattern', ':ptp'),
            ['bn' => 'support', 'ptp' => 'my-index%']);
        foreach ($instances as $instance) {
            $block = block_instance('support', $instance, $PAGE);
            if (!$block) {
                continue;
            }
            $content = $block->get_content();
            if ($content && trim((string) $content->text) !== '') {
                return $content->text;
            }
        }
    } catch (\Throwable $e) {
        debugging('theme_baz support panel: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
    return null;
}

/**
 * Build the guest access hint HTML code.
 *
 * @param int $courseid The course ID.
 * @return string.
 */
function theme_baz_get_course_guest_access_hint($courseid) {
    global $CFG;
    require_once($CFG->dirroot . '/enrol/self/lib.php');

    $html = '';
    $instances = enrol_get_instances($courseid, true);
    $plugins = enrol_get_plugins(true);
    foreach ($instances as $instance) {
        if (!isset($plugins[$instance->enrol])) {
            continue;
        }
        $plugin = $plugins[$instance->enrol];
        if ($plugin->show_enrolme_link($instance)) {
            $html = html_writer::tag('div', get_string(
                'showhintcourseguestaccesslink',
                'theme_baz',
                array('url' => $CFG->wwwroot . '/enrol/index.php?id=' . $courseid)
            ));
            break;
        }
    }

    return $html;
}


/**
 * Distinct non-empty `cohort_batch` profile values, keyed by a safe token.
 *
 * Populates the per-slide audience picker on the dashboard banner settings
 * page. The key is a base64 token, NOT the raw value: cohort_batch values
 * routinely contain commas (e.g. "Jan, 2026 - TP"), and
 * admin_setting_configmultiselect stores its selection comma-joined — so a raw
 * value as the key would be impossible to split back apart. base64 has no
 * commas, so the stored string round-trips cleanly. Decode with
 * theme_baz_cohort_token_decode(). Mirrors local_dashboardpoll's cohort list.
 *
 * @return array<string,string> token => human-readable cohort value.
 */
function theme_baz_get_cohort_batch_choices(): array {
    global $DB;
    $sql = "SELECT DISTINCT uid.data
              FROM {user_info_data} uid
              JOIN {user_info_field} uif ON uif.id = uid.fieldid
             WHERE uif.shortname = :sn
               AND uid.data <> ''
          ORDER BY uid.data ASC";
    $choices = [];
    foreach ($DB->get_fieldset_sql($sql, ['sn' => 'cohort_batch']) as $v) {
        $choices[base64_encode($v)] = $v;
    }
    return $choices;
}

/**
 * Decode a stored banner-cohort token list back to cohort_batch values.
 *
 * @param string $stored Comma-joined base64 tokens (an configmultiselect value).
 * @return string[] The decoded cohort values.
 */
function theme_baz_cohort_token_decode(string $stored): array {
    $values = [];
    foreach (explode(',', $stored) as $token) {
        $token = trim($token);
        if ($token === '') {
            continue;
        }
        $decoded = base64_decode($token, true);
        if ($decoded !== false && $decoded !== '') {
            $values[] = $decoded;
        }
    }
    return $values;
}

/**
 * Resolve the dashboard banner slides visible to the current user.
 *
 * Reads the RemUI-style banner settings, filters each slide by its per-slide
 * cohort audience rule (All / only these batches / all except these batches),
 * and returns the surviving slides sorted by their configured display order.
 * Both the standard dashboard layout and the bespoke student dashboard call
 * this, so targeting/ordering lives in exactly one place.
 *
 * The audience is the `cohort_batch` custom profile field. A slide's mode is:
 *   0 = all students, 1 = only the listed batches, 2 = all except the listed.
 * The per-slide cohort list is stored as base64 tokens (see
 * theme_baz_get_cohort_batch_choices) and decoded here.
 *
 * @return array<int,array{img:string,link:?string,haslink:bool,alt:string}>
 */
function theme_baz_get_dashboard_banners(): array {
    global $USER, $PAGE;

    if ((string) theme_baz_get_setting('displaydashboardbanner') !== '1') {
        return [];
    }

    $count = (int) theme_baz_get_setting('dashboardbannercount');
    $count = max(1, min($count, 5));

    // The viewer's cohort_batch, read once. Guests / unresolvable users get ''.
    $usercohort = '';
    if (isloggedin() && !isguestuser()) {
        $usercohort = (string) (\local_commonutils\user_helper::get_single_profile_field(
            (int) $USER->id, 'cohort_batch') ?? '');
    }

    // Display order: one setting holds a comma-separated list of slot indices in
    // the order an admin arranged them (drag-to-reorder board). Build slot =>
    // position; slots missing from the list sort after the listed ones in their
    // natural slot order (handled by the PHP_INT_MAX + index tie-break below).
    $position = [];
    $pos = 0;
    foreach (explode(',', (string) theme_baz_get_setting('dashboardbannerorder')) as $token) {
        $slot = (int) trim($token);
        if ($slot >= 1 && $slot <= $count && !isset($position[$slot])) {
            $position[$slot] = $pos++;
        }
    }

    $slides = [];
    for ($i = 1; $i <= $count; $i++) {
        $img = $PAGE->theme->setting_file_url('dashboardbannerimg' . $i, 'dashboardbannerimg' . $i);
        if (empty($img)) {
            // Slides without an image are skipped.
            continue;
        }

        // Audience gate.
        $mode = (int) theme_baz_get_setting('dashboardbannermode' . $i);
        if ($mode === 1 || $mode === 2) {
            $list = theme_baz_cohort_token_decode(
                (string) theme_baz_get_setting('dashboardbannercohorts' . $i));
            $inlist = in_array($usercohort, $list, true);
            // 1 = only these batches (must be in list); 2 = all except (must not be).
            if (($mode === 1 && !$inlist) || ($mode === 2 && $inlist)) {
                continue;
            }
        }

        $link = trim((string) theme_baz_get_setting('dashboardbannerlink' . $i));
        $alt = trim((string) theme_baz_get_setting('dashboardbanneralt' . $i));

        $slides[] = [
            'order'   => $position[$i] ?? PHP_INT_MAX,
            'index'   => $i,
            'img'     => $img,
            'link'    => $link !== '' ? $link : null,
            'haslink' => $link !== '',
            'alt'     => $alt !== '' ? $alt : get_string('sd_banner_alt', 'theme_baz'),
        ];
    }

    // Sort by configured display order, keeping the slide index as a stable
    // tie-break so equal/unset orders fall back to their natural order.
    usort($slides, function($a, $b) {
        return ($a['order'] <=> $b['order']) ?: ($a['index'] <=> $b['index']);
    });

    // Drop the sort-only keys before handing slides to the templates.
    return array_map(fn($s) => [
        'img'     => $s['img'],
        'link'    => $s['link'],
        'haslink' => $s['haslink'],
        'alt'     => $s['alt'],
    ], $slides);
}

/**
 * Build the course page information banners HTML code.
 * This function evaluates and composes all information banners which may appear on a course page below the full header.
 *
 * @return string.
 */
function theme_baz_get_course_information_banners() {
    global $CFG, $COURSE, $PAGE, $USER, $OUTPUT;

    // Require user library.
    require_once($CFG->dirroot . '/user/lib.php');

    // Initialize HTML code.
    $html = '';

    // Check if user is admin, teacher or editing teacher.
    if (
        user_has_role_assignment($USER->id, '1') ||
        user_has_role_assignment($USER->id, '2') ||
        user_has_role_assignment($USER->id, '3') ||
        user_has_role_assignment($USER->id, '4')
    ) {
        $allowtoshowhint = true;
    } else {
        $allowtoshowhint = false;
    }

    // If the setting showhintcoursehidden is set and the visibility of the course is hidden and
    // a hint for the visibility will be shown.
    if (
        get_config('theme_baz', 'showhintcoursehidden') == 1
        && $allowtoshowhint == true
        && $PAGE->has_set_url()
        && $PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)
        && $COURSE->visible == false
    ) {

        // Prepare template context.
        $templatecontext = array('courseid' => $COURSE->id);

        // If the user has the capability to change the course settings, an additional link to the course settings is shown.
        if (has_capability('moodle/course:update', context_course::instance($COURSE->id))) {
            $templatecontext['showcoursesettingslink'] = true;
        } else {
            $templatecontext['showcoursesettingslink'] = false;
        }

        // Render template and add it to HTML code.
        $html .= $OUTPUT->render_from_template('theme_baz/course-hint-hidden', $templatecontext);
    }

    // If the setting showhintcourseguestaccess is set and the user is accessing the course with guest access,
    // a hint for users is shown.
    // We also check that the user did not switch the role. This is a special case for roles that can fully access the course
    // without being enrolled. A role switch would show the guest access hint additionally in that case and this is not
    // intended.
    if (
        get_config('theme_baz', 'showhintcourseguestaccess') == 1
        && is_guest(\context_course::instance($COURSE->id), $USER->id)
        && $PAGE->has_set_url()
        && $PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)
        && !is_role_switched($COURSE->id)
    ) {

        // Require self enrolment library.
        require_once($CFG->dirroot . '/enrol/self/lib.php');

        // Prepare template context.
        $templatecontext = array(
            'courseid' => $COURSE->id,
            'role' => role_get_name(get_guest_role())
        );

        // Search for an available self enrolment link in this course.
        $templatecontext['showselfenrollink'] = false;
        $instances = enrol_get_instances($COURSE->id, true);
        $plugins = enrol_get_plugins(true);
        foreach ($instances as $instance) {
            // If the enrolment plugin isn't enabled currently, skip it.
            if (!isset($plugins[$instance->enrol])) {
                continue;
            }

            // Remember the enrolment plugin.
            $plugin = $plugins[$instance->enrol];

            // If there is a self enrolment link.
            if ($plugin->show_enrolme_link($instance)) {
                $templatecontext['showselfenrollink'] = true;
                break;
            }
        }

        // Render template and add it to HTML code.
        $html .= $OUTPUT->render_from_template('theme_baz/course-hint-guestaccess', $templatecontext);
    }

    // If the setting showhintcourseselfenrol is set, a hint for users is shown that the course allows unrestricted self
    // enrolment. This hint is only shown if the course is visible, the self enrolment is visible and if the user has the
    // capability "theme/baz:viewhintcourseselfenrol".
    if (
        get_config('theme_baz', 'showhintcourseselfenrol') == 1
        && $allowtoshowhint == true
        && $PAGE->has_set_url()
        && $PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)
        && $COURSE->visible == true
    ) {

        // Get the active enrol instances for this course.
        $enrolinstances = enrol_get_instances($COURSE->id, true);

        // Prepare to remember when self enrolment is / will be possible.
        $selfenrolmentpossiblecurrently = false;
        $selfenrolmentpossiblefuture = false;
        foreach ($enrolinstances as $instance) {
            // Check if unrestricted self enrolment is possible currently or in the future.
            $now = (new \DateTime("now", \core_date::get_server_timezone_object()))->getTimestamp();
            if (
                $instance->enrol == 'self' && empty($instance->password) && $instance->customint6 == 1 &&
                (empty($instance->enrolenddate) || $instance->enrolenddate > $now)
            ) {

                // Build enrol instance object with all necessary information for rendering the note later.
                $instanceobject = new stdClass();

                // Remember instance name.
                if (empty($instance->name)) {
                    $instanceobject->name = get_string('pluginname', 'enrol_self') .
                        " (" . get_string('defaultcoursestudent', 'core') . ")";
                } else {
                    $instanceobject->name = $instance->name;
                }

                // Remember type of unrestrictedness.
                if (empty($instance->enrolenddate) && empty($instance->enrolstartdate)) {
                    $instanceobject->unrestrictedness = 'unlimited';
                    $selfenrolmentpossiblecurrently = true;
                } else if (
                    empty($instance->enrolstartdate) &&
                    !empty($instance->enrolenddate) && $instance->enrolenddate > $now
                ) {
                    $instanceobject->unrestrictedness = 'until';
                    $selfenrolmentpossiblecurrently = true;
                } else if (
                    empty($instance->enrolenddate) &&
                    !empty($instance->enrolstartdate) && $instance->enrolstartdate > $now
                ) {
                    $instanceobject->unrestrictedness = 'from';
                    $selfenrolmentpossiblefuture = true;
                } else if (
                    empty($instance->enrolenddate) &&
                    !empty($instance->enrolstartdate) && $instance->enrolstartdate <= $now
                ) {
                    $instanceobject->unrestrictedness = 'since';
                    $selfenrolmentpossiblecurrently = true;
                } else if (
                    !empty($instance->enrolstartdate) && $instance->enrolstartdate > $now &&
                    !empty($instance->enrolenddate) && $instance->enrolenddate > $now
                ) {
                    $instanceobject->unrestrictedness = 'fromuntil';
                    $selfenrolmentpossiblefuture = true;
                } else if (
                    !empty($instance->enrolstartdate) && $instance->enrolstartdate <= $now &&
                    !empty($instance->enrolenddate) && $instance->enrolenddate > $now
                ) {
                    $instanceobject->unrestrictedness = 'sinceuntil';
                    $selfenrolmentpossiblecurrently = true;
                } else {
                    // This should not happen, thus continue to next instance.
                    continue;
                }

                // Remember enrol start date.
                if (!empty($instance->enrolstartdate)) {
                    $instanceobject->startdate = $instance->enrolstartdate;
                } else {
                    $instanceobject->startdate = null;
                }

                // Remember enrol end date.
                if (!empty($instance->enrolenddate)) {
                    $instanceobject->enddate = $instance->enrolenddate;
                } else {
                    $instanceobject->enddate = null;
                }

                // Remember this instance.
                $selfenrolinstances[$instance->id] = $instanceobject;
            }
        }

        // If there is at least one unrestricted enrolment instance,
        // show the hint with information about each unrestricted active self enrolment in the course.
        if (
            !empty($selfenrolinstances) &&
            ($selfenrolmentpossiblecurrently == true || $selfenrolmentpossiblefuture == true)
        ) {

            // Prepare template context.
            $templatecontext = array();

            // Add the start of the hint t the template context
            // depending on the fact if enrolment is already possible currently or will be in the future.
            if ($selfenrolmentpossiblecurrently == true) {
                $templatecontext['selfenrolhintstart'] = get_string('showhintcourseselfenrolstartcurrently', 'theme_baz');
            } else if ($selfenrolmentpossiblefuture == true) {
                $templatecontext['selfenrolhintstart'] = get_string('showhintcourseselfenrolstartfuture', 'theme_baz');
            }

            // Iterate over all enrolment instances to output the details.
            foreach ($selfenrolinstances as $selfenrolinstanceid => $selfenrolinstanceobject) {
                // If the user has the capability to config self enrolments, enrich the instance name with the settings link.
                if (has_capability('enrol/self:config', \context_course::instance($COURSE->id))) {
                    $url = new moodle_url('/enrol/editinstance.php', array(
                        'courseid' => $COURSE->id,
                        'id' => $selfenrolinstanceid, 'type' => 'self'
                    ));
                    $selfenrolinstanceobject->name = html_writer::link($url, $selfenrolinstanceobject->name);
                }

                // Add the enrolment instance information to the template context depending on the instance configuration.
                if ($selfenrolinstanceobject->unrestrictedness == 'unlimited') {
                    $templatecontext['selfenrolinstances'][] = get_string(
                        'showhintcourseselfenrolunlimited',
                        'theme_baz',
                        array('name' => $selfenrolinstanceobject->name)
                    );
                } else if ($selfenrolinstanceobject->unrestrictedness == 'until') {
                    $templatecontext['selfenrolinstances'][] = get_string(
                        'showhintcourseselfenroluntil',
                        'theme_baz',
                        array(
                            'name' => $selfenrolinstanceobject->name,
                            'until' => userdate($selfenrolinstanceobject->enddate)
                        )
                    );
                } else if ($selfenrolinstanceobject->unrestrictedness == 'from') {
                    $templatecontext['selfenrolinstances'][] = get_string(
                        'showhintcourseselfenrolfrom',
                        'theme_baz',
                        array(
                            'name' => $selfenrolinstanceobject->name,
                            'from' => userdate($selfenrolinstanceobject->startdate)
                        )
                    );
                } else if ($selfenrolinstanceobject->unrestrictedness == 'since') {
                    $templatecontext['selfenrolinstances'][] = get_string(
                        'showhintcourseselfenrolsince',
                        'theme_baz',
                        array(
                            'name' => $selfenrolinstanceobject->name,
                            'since' => userdate($selfenrolinstanceobject->startdate)
                        )
                    );
                } else if ($selfenrolinstanceobject->unrestrictedness == 'fromuntil') {
                    $templatecontext['selfenrolinstances'][] = get_string(
                        'showhintcourseselfenrolfromuntil',
                        'theme_baz',
                        array(
                            'name' => $selfenrolinstanceobject->name,
                            'until' => userdate($selfenrolinstanceobject->enddate),
                            'from' => userdate($selfenrolinstanceobject->startdate)
                        )
                    );
                } else if ($selfenrolinstanceobject->unrestrictedness == 'sinceuntil') {
                    $templatecontext['selfenrolinstances'][] = get_string(
                        'showhintcourseselfenrolsinceuntil',
                        'theme_baz',
                        array(
                            'name' => $selfenrolinstanceobject->name,
                            'until' => userdate($selfenrolinstanceobject->enddate),
                            'since' => userdate($selfenrolinstanceobject->startdate)
                        )
                    );
                }
            }

            // If the user has the capability to config self enrolments, add the call for action to the template context.
            if (has_capability('enrol/self:config', \context_course::instance($COURSE->id))) {
                $templatecontext['calltoaction'] = true;
            } else {
                $templatecontext['calltoaction'] = false;
            }

            // Render template and add it to HTML code.
            $html .= $OUTPUT->render_from_template('theme_baz/course-hint-selfenrol', $templatecontext);
        }
    }

    // If the setting showswitchedroleincourse is set and the user has switched his role,
    // a hint for the role switch will be shown.
    if (
        get_config('theme_baz', 'showswitchedroleincourse') == 1
        && is_role_switched($COURSE->id)
    ) {

        // Get the role name switched to.
        $opts = \user_get_user_navigation_info($USER, $PAGE);
        $role = $opts->metadata['rolename'];

        // Get the URL to switch back (normal role).
        $url = new moodle_url(
            '/course/switchrole.php',
            array(
                'id' => $COURSE->id,
                'sesskey' => sesskey(),
                'switchrole' => 0,
                'returnurl' => $PAGE->url->out_as_local_url(false)
            )
        );

        // Prepare template context.
        $templatecontext = array(
            'role' => $role,
            'url' => $url->out()
        );

        // Render template and add it to HTML code.
        $html .= $OUTPUT->render_from_template('theme_baz/course-hint-switchedrole', $templatecontext);
    }

    // Return HTML code.
    return $html;
}

/**
 * Serves the H5P Custom CSS.
 *
 * @param string $filename The filename.
 * @param theme_config $theme The theme config object.
 *
 * @throws dml_exception
 */
function theme_baz_serve_hvp_css($filename, $theme) {
    global $CFG, $PAGE;

    require_once($CFG->dirroot . '/lib/configonlylib.php'); // For min_enable_zlib_compression.

    $PAGE->set_context(context_system::instance());
    $themename = $theme->name;

    $settings = new \theme_baz\util\theme_settings();
    $content = $settings->hvpcss;

    $md5content = md5($content);
    $md5stored = get_config('theme_baz', 'hvpccssmd5');
    if ((empty($md5stored)) || ($md5stored != $md5content)) {
        // Content changed, so the last modified time needs to change.
        set_config('hvpccssmd5', $md5content, $themename);
        $lastmodified = time();
        set_config('hvpccsslm', $lastmodified, $themename);
    } else {
        $lastmodified = get_config($themename, 'hvpccsslm');
        if (empty($lastmodified)) {
            $lastmodified = time();
        }
    }

    // Sixty days only - the revision may get incremented quite often.
    $lifetime = 60 * 60 * 24 * 60;

    header('HTTP/1.1 200 OK');

    header('Etag: "' . $md5content . '"');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastmodified) . ' GMT');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $lifetime) . ' GMT');
    header('Pragma: ');
    header('Cache-Control: public, max-age=' . $lifetime);
    header('Accept-Ranges: none');
    header('Content-Type: text/css; charset=utf-8');
    if (!min_enable_zlib_compression()) {
        header('Content-Length: ' . strlen($content));
    }

    echo $content;

    die;
}


function theme_baz_get_theme_email_context() {
    global $CFG;

    $theme = theme_config::load('baz');

    require_once($CFG->dirroot . '/theme/baz/classes/theme_email.php');

    $theme_mail = new theme_email();
    $context = $theme_mail->get_context();

    return $context;

}

/**
 * Return the files from the additionalresources file area as templatecontext structure.
 * It was designed to compose the files for the settings-additionalresources-filelist.mustache template.
 * This function always loads the files from the filearea which is not really performant.
 * Thus, you have to take care where and how often you use it (or add some caching).
 *
 * @return array|null
 * @throws coding_exception
 * @throws dml_exception
 */
function theme_baz_get_additionalresources_templatecontext() {
    global $OUTPUT;

    // Static variable to remember the files for subsequent calls of this function.
    static $filesforcontext = null;

    if ($filesforcontext == null) {
        // Get the system context.
        $systemcontext = \context_system::instance();

        // Get filearea.
        $fs = get_file_storage();

        // Get all files from filearea.
        $files = $fs->get_area_files($systemcontext->id, 'theme_baz', 'additionalresources', false, 'itemid', false);

        // Iterate over the files and fill the templatecontext of the file list.
        $filesforcontext = [];
        foreach ($files as $af) {
            $urlpersistent = new moodle_url('/pluginfile.php/1/theme_baz/additionalresources/0/'.$af->get_filename());
            $urlrevisioned = new moodle_url('/pluginfile.php/1/theme_baz/additionalresources/'.theme_get_revision().
                    '/'.$af->get_filename());
            $filesforcontext[] = ['filename' => $af->get_filename(),
                                        'filetype' => $af->get_mimetype(),
                                        'filesize' => display_size($af->get_filesize()),
                                        'fileicon' => $OUTPUT->image_icon(file_file_icon($af), get_mimetype_description($af)),
                                        'fileurlpersistent' => $urlpersistent->out(),
                                        'fileurlrevisioned' => $urlrevisioned->out(), ];
        }
    }

    return $filesforcontext;
}

/**
 * Return the files from the customfonts file area as templatecontext structure.
 * It was designed to compose the files for the settings-customfonts-filelist.mustache template.
 * This function always loads the files from the filearea which is not really performant.
 * Thus, you have to take care where and how often you use it (or add some caching).
 *
 * @return array|null
 * @throws coding_exception
 * @throws dml_exception
 */
function theme_baz_get_customfonts_templatecontext() {
    global $OUTPUT;

    // Static variable to remember the files for subsequent calls of this function.
    static $filesforcontext = null;

    if ($filesforcontext == null) {
        // Get the system context.
        $systemcontext = \context_system::instance();

        // Get filearea.
        $fs = get_file_storage();

        // Get all files from filearea.
        $files = $fs->get_area_files($systemcontext->id, 'theme_baz', 'fontfiles', false, 'itemid', false);

        // Get the webfonts extensions list.
        $webfonts = theme_baz_get_webfonts_extensions();

        // Iterate over the files.
        $filesforcontext = [];
        foreach ($files as $af) {
            // Get the filename.
            $filename = $af->get_filename();

            // Check if the file is really a font file (as we can't really rely on the upload restriction in settings.php)
            // according to its file suffix (as the filetype might not have a known mimetype).
            // If it isn't a font file, skip it.
            $filenamesuffix = pathinfo($filename, PATHINFO_EXTENSION);
            if (!in_array('.'.$filenamesuffix, $webfonts)) {
                continue;
            }

            // Otherwise, fill the templatecontext of the file list.
            $urlpersistent = new moodle_url('/pluginfile.php/1/theme_baz/fontfiles/0/'.$filename);
            $filesforcontext[] = ['filename' => $filename,
                    'fileurlpersistent' => $urlpersistent->out(), ];
        }
    }

    return $filesforcontext;
}

/**
 * Helper function which returns an array of accepted webfonts extensions (including the dots).
 *
 * @return array
 */
function theme_baz_get_webfonts_extensions() {
    return ['.eot', '.otf', '.svg', '.ttf', '.woff', '.woff2'];
}

/**
 * Helper function which makes sure that all webfont file types are registered in the system.
 * The webfont file types need to be registered in the system, otherwise the admin settings filepicker wouldn't allow restricting
 * the uploadable file types to webfonts only.
 *
 * Please note: If custom filetypes are defined in config.php, registering additional filetypes is not possible
 * due to a restriction in the set_custom_types() function in Moodle core. In this case, this function does not
 * register anything and will return false.
 *
 * @return boolean true if the filetypes were registered, false if not.
 * @throws coding_exception
 */
function theme_baz_register_webfonts_filetypes() {
    global $CFG;

    // If customfiletypes are set in config.php or PHP tests are running, we can't do anything.
    if (array_key_exists('customfiletypes', $CFG->config_php_settings) || PHPUNIT_TEST) {
        return false;
    }

    // Our array of webfont file types to register.
    // As we want to keep things simple, we do not set a particular icon for these file types.
    // Likewise, we do not set any type groups or use descriptions from the language pack.
    $webfonts = [
            'eot' => [
                    'extension' => 'eot',
                    'mimetype' => 'application/vnd.ms-fontobject',
                    'coreicon' => 'unknown',
            ],
            'otf' => [
                    'extension' => 'otf',
                    'mimetype' => 'font/otf',
                    'coreicon' => 'unknown',
            ],
            'svg' => [
                    'extension' => 'svg',
                    'mimetype' => 'image/svg+xml',
                    'coreicon' => 'unknown',
            ],
            'ttf' => [
                    'extension' => 'ttf',
                    'mimetype' => 'font/ttf',
                    'coreicon' => 'unknown',
            ],
            'woff' => [
                    'extension' => 'woff',
                    'mimetype' => 'font/woff',
                    'coreicon' => 'unknown',
            ],
            'woff2' => [
                    'extension' => 'woff2',
                    'mimetype' => 'font/woff2',
                    'coreicon' => 'unknown',
            ],
    ];

    // First, get the list of currently registered file types.
    $currenttypes = core_filetypes::get_types();

    // Iterate over the webfonts file types.
    foreach ($webfonts as $f) {
        // If the file type is already registered, skip it.
        if (array_key_exists($f['extension'], $currenttypes)) {
            continue;
        }

        // Otherwise, register the file type.
        core_filetypes::add_type($f['extension'], $f['mimetype'], $f['coreicon']);
    }

    return true;
}

/**
 * Build the bespoke context card for the forum post form (post.php) — reply,
 * edit, or new discussion. Returns the label, the parent/target title, author
 * (for reply/edit) and a back URL. Null when not on the post form or on error.
 *
 * @param moodle_page $page
 * @return array|null
 */
function theme_baz_build_forum_post($page) {
    global $DB, $USER;
    if ($page->pagetype !== 'mod-forum-post') {
        return null;
    }
    try {
        $cm = $page->cm;
        $cmid = !empty($cm) ? (int) $cm->id : 0;
        $initials = function($u) {
            return core_text::strtoupper(core_text::substr((string) $u->firstname, 0, 1) .
                core_text::substr((string) $u->lastname, 0, 1));
        };
        $replyid = optional_param('reply', 0, PARAM_INT);
        $editid = optional_param('edit', 0, PARAM_INT);
        $forumid = optional_param('forum', 0, PARAM_INT);

        if ($replyid || $editid) {
            $postid = $replyid ?: $editid;
            $post = $DB->get_record('forum_posts', ['id' => $postid], '*', IGNORE_MISSING);
            if (!$post) {
                return null;
            }
            $author = $DB->get_record('user', ['id' => $post->userid], '*', IGNORE_MISSING);
            return [
                'label' => $replyid ? get_string('sd_f_replyingto', 'theme_baz')
                    : get_string('sd_f_editingpost', 'theme_baz'),
                'title' => format_string($post->subject),
                'authorname' => $author ? fullname($author) : '',
                'authorinitials' => $author ? $initials($author) : '',
                'date' => userdate($post->created, get_string('strftimedaydate')),
                'hasauthor' => (bool) $author,
                'backurl' => (new moodle_url('/mod/forum/discuss.php', ['d' => $post->discussion]))->out(false),
            ];
        }
        if ($forumid) {
            $forum = $DB->get_record('forum', ['id' => $forumid], '*', IGNORE_MISSING);
            return [
                'label' => get_string('sd_f_newdiscussionin', 'theme_baz'),
                'title' => $forum ? format_string($forum->name) : format_string($cm ? $cm->name : ''),
                'hasauthor' => false,
                'backurl' => $cmid ? (new moodle_url('/mod/forum/view.php', ['id' => $cmid]))->out(false) : '',
            ];
        }
        return null;
    } catch (\Throwable $e) {
        debugging('theme_baz_build_forum_post: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return null;
    }
}

/**
 * Build the bespoke forum-list hero + toolbar for the student activity shell:
 * title, real stats (topics / unread / your posts), a Start-a-discussion URL,
 * subscribe state, sort URLs, and the segmented filter tabs. Null when not on a
 * forum view page or on any error.
 *
 * @param moodle_page $page
 * @return array|null
 */
function theme_baz_build_forum_list($page) {
    global $DB, $USER, $CFG;
    // Read the magic $page->cm into a local first — empty($page->cm) reports
    // "not set" even when loaded. See [[page-course-empty-gotcha]].
    $cm = $page->cm;
    if ($page->pagetype !== 'mod-forum-view' || empty($cm)) {
        return null;
    }
    try {
        $forumid = (int) $cm->instance;
        $cmid = (int) $cm->id;
        $total = (int) $DB->count_records('forum_discussions', ['forum' => $forumid]);

        // Unread (only meaningful when tracking is on for this user/forum).
        $unread = 0;
        try {
            require_once($CFG->dirroot . '/mod/forum/lib.php');
            if (function_exists('forum_tp_count_forum_unread_posts')) {
                $u = forum_tp_count_forum_unread_posts($cm, $page->course);
                $unread = (int) $u;
            }
        } catch (\Throwable $e) {
            $unread = 0;
        }

        // Your posts in this forum.
        $myposts = 0;
        try {
            $myposts = (int) $DB->count_records_sql(
                "SELECT COUNT(p.id)
                   FROM {forum_posts} p JOIN {forum_discussions} d ON d.id = p.discussion
                  WHERE d.forum = ? AND p.userid = ?", [$forumid, $USER->id]);
        } catch (\Throwable $e) {
            $myposts = 0;
        }

        // Subscribe state (best-effort).
        $subscribed = false;
        try {
            $forum = $DB->get_record('forum', ['id' => $forumid], '*', IGNORE_MISSING);
            if ($forum && class_exists('\mod_forum\subscriptions')) {
                $subscribed = \mod_forum\subscriptions::is_subscribed($USER->id, $forum, null, $cm);
            }
        } catch (\Throwable $e) {
            $subscribed = false;
        }

        // "Start a discussion" is permission-driven — only offer it when the
        // user may actually create a discussion here (capability + forum type +
        // groups), mirroring core's forum.capabilities.create gate.
        $cancreate = false;
        try {
            require_once($CFG->dirroot . '/mod/forum/lib.php');
            $forumrec = $forum ?: $DB->get_record('forum', ['id' => $forumid], '*', IGNORE_MISSING);
            if ($forumrec && function_exists('forum_user_can_post_discussion')) {
                $cancreate = forum_user_can_post_discussion(
                    $forumrec, null, -1, $cm, \context_module::instance($cmid));
            }
        } catch (\Throwable $e) {
            $cancreate = false;
        }

        $view = function($params) use ($cmid) {
            return (new moodle_url('/mod/forum/view.php', array_merge(['id' => $cmid], $params)))->out(false);
        };

        // Forum description / intro (the standard activity-header is hidden on the
        // bespoke forum view, so surface it as a description card in the template).
        $intro = '';
        try {
            $forumrec2 = $forumrec ?? ($forum ?? $DB->get_record('forum', ['id' => $forumid], '*', IGNORE_MISSING));
            if ($forumrec2) {
                $intro = format_module_intro('forum', $forumrec2, $cmid);
            }
        } catch (\Throwable $e) {
            $intro = '';
        }

        return [
            'title' => format_string($cm->name),
            'intro' => $intro,
            'hasintro' => trim(html_to_text($intro, 0, false)) !== '',
            'cancreate' => $cancreate,
            'coursename' => format_string($page->course->fullname),
            'total' => $total,
            'unread' => $unread,
            'hasunread' => $unread > 0,
            'myposts' => $myposts,
            'subscribed' => $subscribed,
            'createurl' => (new moodle_url('/mod/forum/post.php', ['forum' => $forumid]))->out(false),
            'cmid' => $cmid,
            'forumid' => $forumid,
            /* Segmented filter tabs: Latest is a real sort; Unread / My posts are
               approximations (no native filter) linking to closest behaviour. */
            'tab_latest' => $view(['o' => 'l']),
            'tab_unread' => $view(['o' => 'l']),
            'mypostsurl' => (new moodle_url('/mod/forum/user.php',
                ['id' => $USER->id, 'mode' => 'discussions', 'course' => $page->course->id]))->out(false),
            'sort_newest' => $view(['o' => 'l']),
            'sort_oldest' => $view(['o' => 'c']),
        ];
    } catch (\Throwable $e) {
        debugging('theme_baz_build_forum_list: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return null;
    }
}

/**
 * Build the bespoke forum-discussion thread hero + segmented reply-mode control
 * for the student activity shell. Returns title, first-post author, participant
 * initials, post count, subscribe/favourite state, and the Nested/Flat/Threaded
 * mode URLs. Null when not on a forum discussion page or on any error.
 *
 * @param moodle_page $page
 * @return array|null
 */
function theme_baz_build_forum_thread($page) {
    global $DB, $USER;
    if ($page->pagetype !== 'mod-forum-discuss') {
        return null;
    }
    try {
        $did = optional_param('d', 0, PARAM_INT);
        if (!$did) {
            return null;
        }
        $disc = $DB->get_record('forum_discussions', ['id' => $did], '*', IGNORE_MISSING);
        if (!$disc) {
            return null;
        }
        $first = $DB->get_record('forum_posts', ['id' => $disc->firstpost], '*', IGNORE_MISSING);
        $authoruser = $first ? $DB->get_record('user', ['id' => $first->userid], '*', IGNORE_MISSING) : null;

        // Distinct participants (initials for the stack) + total post count.
        $prows = $DB->get_records_sql(
            "SELECT DISTINCT u.id, u.firstname, u.lastname
               FROM {forum_posts} p JOIN {user} u ON u.id = p.userid
              WHERE p.discussion = ?", [$did], 0, 6);
        $postcount = (int) $DB->count_records('forum_posts', ['discussion' => $did]);
        $initials = function($u) {
            return core_text::strtoupper(core_text::substr((string) $u->firstname, 0, 1) .
                core_text::substr((string) $u->lastname, 0, 1));
        };
        $participants = [];
        foreach ($prows as $u) {
            $participants[] = ['initials' => $initials($u), 'name' => fullname($u)];
        }

        // Subscribe / favourite state (best-effort; visual state only if lookups fail).
        $subscribed = false;
        $favourited = false;
        try {
            $subscribed = $DB->record_exists('forum_discussion_subs',
                ['discussion' => $did, 'userid' => $USER->id, 'preference' => 1]);
        } catch (\Throwable $e) {
            $subscribed = false;
        }
        try {
            $favourited = $DB->record_exists('favourite',
                ['component' => 'mod_forum', 'itemtype' => 'discussions',
                 'itemid' => $did, 'userid' => $USER->id]);
        } catch (\Throwable $e) {
            $favourited = false;
        }

        // Segmented reply-mode control (Nested / Flat / Threaded).
        $current = (int) get_user_preferences('forum_displaymode', 3);
        $base = ['d' => $did];
        $modes = [
            ['label' => 'Nested', 'url' => (new moodle_url('/mod/forum/discuss.php',
                array_merge($base, ['mode' => 4])))->out(false),
                'active' => in_array($current, [3, 4])],
            ['label' => 'Flat', 'url' => (new moodle_url('/mod/forum/discuss.php',
                array_merge($base, ['mode' => 1])))->out(false),
                'active' => in_array($current, [1, -1])],
            ['label' => 'Threaded', 'url' => (new moodle_url('/mod/forum/discuss.php',
                array_merge($base, ['mode' => 2])))->out(false),
                'active' => $current === 2],
        ];

        $threadcm = $page->cm; // local first — magic-prop empty() gotcha.
        $cmid = !empty($threadcm) ? (int) $threadcm->id : 0;

        return [
            'title' => format_string($disc->name),
            'forumurl' => $cmid ? (new moodle_url('/mod/forum/view.php', ['id' => $cmid]))->out(false) : '',
            'replyurl' => $disc->firstpost
                ? (new moodle_url('/mod/forum/post.php', ['reply' => (int) $disc->firstpost]))->out(false) : '',
            'authorname' => $authoruser ? fullname($authoruser) : '',
            'authorinitials' => $authoruser ? $initials($authoruser) : '',
            'created' => $first ? userdate($first->created, get_string('strftimedaydate')) : '',
            'postcount' => $postcount,
            'participants' => $participants,
            'partcount' => count($participants),
            'modes' => $modes,
            'discussionid' => $did,
            'forumid' => (int) $disc->forum,
            'subscribed' => $subscribed,
            'favourited' => $favourited,
        ];
    } catch (\Throwable $e) {
        debugging('theme_baz_build_forum_thread: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return null;
    }
}

/**
 * Build named previous/next activity cards + a jump-to selector for the bespoke
 * student activity shell. Mirrors core_renderer::activity_navigation()'s ordering
 * but returns data for theme_baz/student-activity (named cards instead of the rui
 * icon-only links). Scoped to the bespoke shell, so non-student pages are untouched.
 *
 * @param moodle_page $page
 * @param renderer_base $output
 * @return array|null [prev=>[name,url]|null, next=>..., jump=>html] or null when N/A.
 */
function theme_baz_build_activity_nav($page, $output) {
    // Read the magic moodle_page props into locals first — empty()/isset() on
    // $page->cm wrongly report "not set" even when loaded. See [[page-course-empty-gotcha]].
    $cm = $page->cm;
    $context = $page->context;
    if (empty($cm) || empty($context) || $context->contextlevel != CONTEXT_MODULE) {
        return null;
    }
    try {
        if ($cm->is_stealth()) {
            return null;
        }
        $modinfo = get_fast_modinfo($page->course->id);
        $mods = [];
        $activitylist = [];
        foreach ($modinfo->get_cms() as $module) {
            if (!$module->uservisible || $module->is_stealth() || empty($module->url)) {
                continue;
            }
            $mods[$module->id] = $module;
            if ($module->id == $cm->id) {
                continue;
            }
            $modname = $module->get_formatted_name();
            if (!$module->visible) {
                $modname .= ' ' . get_string('hiddenwithbrackets');
            }
            $linkurl = new moodle_url($module->url, ['forceview' => 1]);
            $activitylist[$linkurl->out(false)] = $modname;
        }
        if (count($mods) <= 1) {
            return null;
        }
        $modids = array_keys($mods);
        $position = array_search($cm->id, $modids);
        if ($position === false) {
            return null;
        }
        $card = function($module) {
            $name = $module->get_formatted_name();
            if (!$module->visible) {
                $name .= ' ' . get_string('hiddenwithbrackets');
            }
            return [
                'name' => $name,
                'url'  => (new moodle_url($module->url, ['forceview' => 1]))->out(false),
            ];
        };
        $prev = ($position > 0) ? $card($mods[$modids[$position - 1]]) : null;
        $next = ($position < count($modids) - 1) ? $card($mods[$modids[$position + 1]]) : null;

        $select = new url_select($activitylist, '', ['' => get_string('jumpto')]);
        $select->set_label(get_string('jumpto'), ['class' => 'sr-only']);

        return [
            'prev' => $prev,
            'next' => $next,
            'jump' => $output->render($select),
        ];
    } catch (\Throwable $e) {
        debugging('theme_baz_build_activity_nav: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return null;
    }
}

/**
 * Build the bespoke course module-navigation for the activity "focus mode" overlay.
 *
 * Reuses the same course modinfo assembly (sections -> activities + completion) that the
 * bespoke course page (\theme_baz\output\student_coursepage) uses, so the overlay matches
 * the working course view. Returns the current course's visible sections, each with its
 * visible activities (name, url, icon, completion state, and whether it is the current one).
 *
 * @param moodle_page $page
 * @return array|null Context for the ba-mnav overlay, or null when not applicable.
 */
function theme_baz_build_activity_modnav($page) {
    $cm = $page->cm;
    $course = $page->course;
    if (empty($course) || empty($course->id) || $course->id == SITEID) {
        return null;
    }
    try {
        global $USER;
        $userid = (int) $USER->id;
        $currentcmid = !empty($cm) ? (int) $cm->id : 0;
        $modinfo = get_fast_modinfo($course, $userid);
        $completion = new \completion_info($course);
        $completionon = $completion->is_enabled();
        $icons = \theme_baz\output\student_coursepage::ICONS;
        $live = \theme_baz\output\student_coursepage::LIVE;
        $bucket = function($modname, $name) use ($live) {
            if (in_array($modname, $live, true)) {
                return 'live';
            }
            $map = [
                'resource' => 'file', 'folder' => 'file', 'url' => 'link',
                'book' => 'lesson', 'lesson' => 'lesson', 'quiz' => 'quiz', 'assign' => 'assign',
                'forum' => 'forum', 'feedback' => 'feedback', 'choice' => 'feedback', 'scheduler' => 'scheduler',
            ];
            if ($modname === 'page') {
                return preg_match('/video/i', $name) ? 'video' : 'page';
            }
            return $map[$modname] ?? 'other';
        };

        $sections = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            if (!$section->uservisible) {
                continue;
            }
            $cmids = $modinfo->sections[$section->section] ?? [];
            $acts = [];
            foreach ($cmids as $cmid) {
                $mod = $modinfo->cms[$cmid];
                if (!$mod->uservisible || $mod->deletioninprogress || $mod->modname === 'label' || empty($mod->url)) {
                    continue;
                }
                $name = $mod->get_formatted_name();
                $tracked = false;
                $done = false;
                if ($completionon && $completion->is_enabled($mod) != COMPLETION_TRACKING_NONE) {
                    $tracked = true;
                    $cd = $completion->get_data($mod, false, $userid);
                    $done = in_array((int) $cd->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true);
                }
                $acts[] = [
                    'name'    => $name,
                    'url'     => (new moodle_url($mod->url, ['forceview' => 1]))->out(false),
                    'iconsvg' => $icons[$bucket($mod->modname, $name)] ?? $icons['other'],
                    'tracked' => $tracked,
                    'done'    => $done,
                    'current' => ($currentcmid && (int) $mod->id === $currentcmid),
                ];
            }
            if (!$acts) {
                continue;
            }
            $secname = trim((string) $modinfo->get_section_info($section->section)->name);
            if ($secname === '') {
                $secname = get_section_name($course, $section);
            }
            $sections[] = [
                'name'    => $secname,
                'acts'    => $acts,
                'current' => (bool) array_filter($acts, function($a) {
                    return !empty($a['current']);
                }),
            ];
        }
        if (!$sections) {
            return null;
        }
        return [
            'coursename' => format_string($course->fullname),
            'courseurl'  => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'sections'   => $sections,
        ];
    } catch (\Throwable $e) {
        debugging('theme_baz_build_activity_modnav: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return null;
    }
}