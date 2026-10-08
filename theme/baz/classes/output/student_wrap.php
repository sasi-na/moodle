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
 * Generic bespoke-shell wrapper for student-only pages that keep their own
 * content + JS (My Files, Profile). The page's content is rendered into
 * region-main by core; this only supplies the navy/gold app shell + a header,
 * and the content is restyled via SCSS — so every existing feature/filter is
 * preserved untouched.
 *
 * @package   theme_baz
 * @copyright 2026 onlinejain.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_baz\output;

use renderable;
use templatable;
use renderer_base;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

/**
 * Renderable + templatable for the generic wrapped student page.
 */
class student_wrap implements renderable, templatable {

    /** @var string The page key: 'files' | 'profile' | 'messages' | 'notifications' | 'userprofile' | … */
    protected $page;

    /** @var int|null For 'userprofile', the id of the user being viewed. */
    protected $userid;

    /**
     * @param string $page page key
     * @param int|null $userid viewed user id (for 'userprofile'); null = current user
     */
    public function __construct(string $page, ?int $userid = null) {
        $this->page = $page;
        $this->userid = $userid;
    }

    /**
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $CFG, $PAGE;

        // Settings sub-pages (forum/editor/calendar/… preferences, blog pages)
        // share one view; the title is the matching settings-nav link label
        // (robust — page titles vary between "Site: Heading" and "Heading | Site").
        $settingstitle = get_string('preferences');
        if ($this->page === 'settings') {
            $found = '';
            try {
                $curpath = $PAGE->url ? $PAGE->url->get_path() : '';
                $settings = $PAGE->settingsnav->find('usercurrentsettings', null);
                if ($settings) {
                    foreach ($settings->children as $grp) {
                        foreach ($grp->children as $link) {
                            if ($link->action instanceof \moodle_url
                                    && $link->action->get_path() === $curpath) {
                                $found = (string) $link->text;
                                break 2;
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                $found = '';
            }
            if ($found === '') {
                // Fallback: last non-empty segment of the page title (usually the heading).
                $parts = array_values(array_filter(array_map('trim', preg_split('/[|:]/', (string) $PAGE->title))));
                $found = !empty($parts) ? end($parts) : '';
            }
            if ($found !== '') {
                $settingstitle = $found;
            }
        }

        $conf = [
            'settings' => [
                'title'   => $settingstitle,
                'eyebrow' => get_string('sw_prefs_eyebrow', 'theme_baz'),
                'wrapclass' => 'sw-settings',
                'active'  => 'preferences',
            ],
            'files' => [
                'title'   => get_string('sw_files_title', 'theme_baz'),
                'eyebrow' => get_string('sw_files_eyebrow', 'theme_baz'),
                'wrapclass' => 'sw-files',
                'active'  => 'files',
            ],
            'profile' => [
                'title'   => get_string('sw_profile_title', 'theme_baz'),
                'eyebrow' => get_string('sw_profile_eyebrow', 'theme_baz'),
                'wrapclass' => 'sw-profile',
                'active'  => 'profile',
            ],
            'messages' => [
                'title'   => get_string('sw_messages_title', 'theme_baz'),
                'eyebrow' => get_string('sw_messages_eyebrow', 'theme_baz'),
                'wrapclass' => 'sw-messages',
                'active'  => 'messages',
            ],
            'notifications' => [
                'title'   => get_string('sw_notifications_title', 'theme_baz'),
                'eyebrow' => get_string('sw_notifications_eyebrow', 'theme_baz'),
                'wrapclass' => 'sw-notifications',
                'active'  => 'notifications',
            ],
            'preferences' => [
                'title'   => get_string('sw_prefs_title', 'theme_baz'),
                'eyebrow' => get_string('sw_prefs_eyebrow', 'theme_baz'),
                'wrapclass' => 'sw-prefs',
                'active'  => 'preferences',
            ],
            'editprofile' => [
                'title'   => get_string('sw_editprofile_title', 'theme_baz'),
                'eyebrow' => get_string('sw_editprofile_eyebrow', 'theme_baz'),
                'wrapclass' => 'sw-editprofile',
                'active'  => 'preferences',
            ],
            'password' => [
                'title'   => get_string('sw_password_title', 'theme_baz'),
                'eyebrow' => get_string('sw_password_eyebrow', 'theme_baz'),
                'wrapclass' => 'sw-password',
                'active'  => 'preferences',
            ],
            'badges' => [
                'title'   => get_string('sw_badges_title', 'theme_baz'),
                'eyebrow' => get_string('sw_badges_eyebrow', 'theme_baz'),
                'wrapclass' => 'sw-badges',
                'active'  => 'badges',
            ],
            'blog' => [
                'title'   => get_string('sw_blog_title', 'theme_baz'),
                'eyebrow' => get_string('sw_blog_eyebrow', 'theme_baz'),
                'wrapclass' => 'sw-blog',
                'active'  => 'blog',
            ],
            'eventreg' => [
                'title'   => get_string('sw_eventreg_title', 'theme_baz'),
                'eyebrow' => get_string('sw_eventreg_eyebrow', 'theme_baz'),
                'wrapclass' => 'sw-eventreg',
                'active'  => '',
            ],
            // Each support page sets its own heading -- raise, the ticket list and a
            // ticket reference -- so the heading is the title rather than one label.
            'helpdesk' => [
                'title'   => (string) $PAGE->heading !== ''
                    ? format_string($PAGE->heading)
                    : get_string('sw_helpdesk_title', 'theme_baz'),
                'eyebrow' => get_string('sw_helpdesk_eyebrow', 'theme_baz'),
                'wrapclass' => 'sw-helpdesk',
                'active'  => '',
            ],
            // Titled from the page heading so the theme carries no string
            // dependency on a plugin a given site may not have installed.
            'queue' => [
                'title'   => (string) $PAGE->heading !== ''
                    ? format_string($PAGE->heading)
                    : get_string('sw_helpdesk_title', 'theme_baz'),
                'eyebrow' => get_string('sw_staff_eyebrow', 'theme_baz'),
                'wrapclass' => 'sw-helpdesk sw-queue',
                'active'  => 'queue',
            ],
            'forwarded' => [
                'title'   => (string) $PAGE->heading !== ''
                    ? format_string($PAGE->heading)
                    : get_string('sw_helpdesk_title', 'theme_baz'),
                'eyebrow' => get_string('sw_staff_eyebrow', 'theme_baz'),
                'wrapclass' => 'sw-helpdesk sw-forwarded',
                'active'  => 'forwarded',
            ],
            // A ticket opened by staff: titled by its subject, and nothing in the
            // sidebar is highlighted because it belongs to no one section.
            'staffticket' => [
                'title'   => (string) $PAGE->heading !== ''
                    ? format_string($PAGE->heading)
                    : get_string('sw_helpdesk_title', 'theme_baz'),
                'eyebrow' => get_string('sw_staff_eyebrow', 'theme_baz'),
                'wrapclass' => 'sw-helpdesk sw-staffticket',
                'active'  => '',
            ],
            'enrol' => [
                'title'   => get_string('sw_enrol_title', 'theme_baz'),
                'eyebrow' => get_string('sw_enrol_eyebrow', 'theme_baz'),
                'wrapclass' => 'sw-enrol',
                'active'  => 'courses',
            ],
            // Any other student-facing page on this layout: notices, confirmations,
            // and the interstitials core renders with no pagelayout of its own.
            // These have no single title of their own, so the page's heading is
            // the title -- and nothing in the sidebar is highlighted, because the
            // page belongs to no one section.
            'generic' => [
                'title'   => self::page_title(),
                'eyebrow' => get_string('sw_generic_eyebrow', 'theme_baz'),
                'wrapclass' => 'sw-generic',
                'active'  => '',
            ],
            // Viewing another user's profile: reuses the profile-tree card styling
            // (sw-profile) plus a profile hero built from the viewed user.
            'userprofile' => [
                'title'   => get_string('sw_profile_title', 'theme_baz'),
                'eyebrow' => get_string('sw_profile_eyebrow', 'theme_baz'),
                'wrapclass' => 'sw-profile sw-userprofile',
                'active'  => '',
            ],
        ];
        $c = $conf[$this->page] ?? $conf['files'];

        $showeidcard = false;
        if (class_exists('\local_studentid\manager')) {
            try {
                $showeidcard = \local_studentid\manager::is_eligible_batch($GLOBALS['USER']->id);
            } catch (\Throwable $e) {
                $showeidcard = false;
            }
        }
        $support = $this->render_support();
        $askjo = \theme_baz\local\askjo::build();

        // Preferences view renders bespoke setting cards (built from the real
        // settings-nav links) instead of the wrapped standard markup.
        $prefcards = ($this->page === 'preferences') ? $this->build_pref_cards() : [];

        // Viewing another user: build the profile hero (avatar + name + Message).
        $profilehero = ($this->page === 'userprofile') ? $this->build_profile_hero($output) : null;
        $wraptitle = ($profilehero && !empty($profilehero['name'])) ? $profilehero['name'] : $c['title'];

        // Enrolment page: the course title lives in the layout header (not in
        // main_content), so surface the course name as the shell hero title.
        if ($this->page === 'enrol' && !empty($GLOBALS['PAGE']->course->id)
                && $GLOBALS['PAGE']->course->id != SITEID) {
            $wraptitle = format_string($GLOBALS['PAGE']->course->fullname);
        }

        // "Custom description on enrollment page" (the enrolldesc / enrollvideo
        // course custom fields). The classic layout renders these from
        // efull_header, which the bespoke shell never calls, so a student saw an
        // enrolment page with no description at all.
        $enroldesc = $this->render_enroldesc($output);

        // Staff get the same chrome with their own sidebar; the learner nav below
        // is meaningless to them and the template swaps it out wholesale.
        $audience = \theme_baz\local\shell::audience();
        $isstaffshell = $audience !== null && $audience !== \theme_baz\local\shell::AUD_STUDENT;
        $staffnav = [];
        if ($isstaffshell) {
            foreach (\theme_baz\local\shell::nav($audience) as $group) {
                foreach ($group['items'] as &$item) {
                    $item['active'] = $c['active'] !== '' && strpos($item['url'], $c['active']) !== false;
                }
                unset($item);
                $staffnav[] = $group;
            }
        }

        return [
            'config'        => ['wwwroot' => $CFG->wwwroot],
            'isstaffshell'  => $isstaffshell,
            'staffnav'      => $staffnav,
            'wraptitle'     => $wraptitle,
            'wrapeyebrow'   => $c['eyebrow'],
            'wrapclass'     => $c['wrapclass'],
            'isfiles'       => $c['active'] === 'files',
            'isprofile'     => $c['active'] === 'profile',
            'profilehero'   => $profilehero,
            'hasprofilehero' => !empty($profilehero),
            'prefcards'     => $prefcards,
            'hasprefcards'  => !empty($prefcards),
            'enroldesc'     => $enroldesc,
            'hasenroldesc'  => $enroldesc !== '',

            'dashboardurl'  => (new moodle_url('/my/'))->out(false),
            'coursesurl'    => (new moodle_url('/my/courses.php'))->out(false),
            'calendarurl'   => (new moodle_url('/calendar/view.php'))->out(false),
            'mygradesurl'   => (new moodle_url('/grade/report/overview/index.php'))->out(false),
            'filesurl'      => (new moodle_url('/user/files.php'))->out(false),
            'showeidcard'   => $showeidcard,
            'eidcardurl'    => (new moodle_url('/local/studentid/index.php'))->out(false),
            'support_html'  => $support,
            'hassupport'    => $support !== null,
            'askjo'         => $askjo,
            'hasaskjo'      => !empty($askjo),
        ];
    }

    /**
     * A usable title for a page that has no title of its own.
     *
     * $PAGE->heading is the page's own heading where it sets one. Failing that
     * the title is used, split on the separators Moodle joins with -- titles
     * arrive as both "Site: Heading" and "Heading | Site", so the site name may
     * be on either end and the longest remaining part is the best guess at the
     * heading. Everything can legitimately be empty, hence the final fallback.
     *
     * @return string
     */
    protected static function page_title(): string {
        global $PAGE, $SITE;

        $heading = trim((string) $PAGE->heading);
        $sitename = trim(format_string($SITE->fullname));
        if ($heading !== '' && $heading !== $sitename) {
            return format_string($heading);
        }

        $parts = array_values(array_filter(array_map('trim',
            preg_split('/[|:]/', (string) $PAGE->title))));
        // Drop the site name wherever it sits, then take the longest remainder.
        $parts = array_values(array_filter($parts, function($part) use ($sitename) {
            return $sitename === '' || strcasecmp($part, $sitename) !== 0;
        }));
        if ($parts) {
            usort($parts, function($a, $b) {
                return \core_text::strlen($b) <=> \core_text::strlen($a);
            });
            return format_string($parts[0]);
        }

        return get_string('sw_generic_title', 'theme_baz');
    }

    /**
     * Build the profile hero for the viewed user (avatar + name + online +
     * Message). Returns null if the user can't be loaded. Privacy is unchanged —
     * this only presents identity already visible on the profile page.
     *
     * @param renderer_base $output
     * @return array|null
     */
    protected function build_profile_hero(renderer_base $output): ?array {
        global $DB, $CFG, $USER;
        $uid = (int) ($this->userid ?: 0);
        if ($uid <= 0 || $uid == $USER->id) {
            return null;
        }
        try {
            $user = $DB->get_record('user', ['id' => $uid, 'deleted' => 0]);
        } catch (\Throwable $e) {
            $user = null;
        }
        if (!$user) {
            return null;
        }

        $online = !empty($user->lastaccess) && (time() - $user->lastaccess) < 300;

        // Message CTA — only when messaging is enabled and it isn't the viewer.
        $messageurl = '';
        if (!empty($CFG->messaging)) {
            $messageurl = (new moodle_url('/message/index.php', ['id' => $uid]))->out(false);
        }

        return [
            'avatar'     => $output->user_picture($user, ['size' => 92, 'link' => false, 'class' => 'sw-ph-pic']),
            'name'       => fullname($user),
            'online'     => $online,
            'messageurl' => $messageurl,
            'hasmessage' => $messageurl !== '',
        ];
    }

    /**
     * Build the bespoke Preferences setting cards from the real settings-nav
     * links (so URLs stay correct), bucketed by destination into logical groups.
     * Any link that doesn't match a bucket falls into a "More" card so nothing
     * is ever dropped.
     *
     * @return array list of cards [ {title, icon, links:[{text,url}]} ]
     */
    protected function build_pref_cards(): array {
        global $PAGE;

        // Icon markup (inner SVG) per bucket.
        $icons = [
            'account'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
            'learning'  => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
            'messaging' => '<path d="M21 11.5a8.4 8.4 0 0 1-8.5 8.5 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7A8.5 8.5 0 1 1 21 11.5z"/>',
            'blogs'     => '<path d="M4 19V5a1 1 0 0 1 1-1h10l4 4v11a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1z"/><path d="M8 8h6M8 12h8"/>',
            'more'      => '<circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/>',
        ];

        // Bucket definitions: title + icon + url-substring matchers, in order.
        $buckets = [
            'account'   => ['title' => get_string('sw_pref_account', 'theme_baz'), 'cls' => 'a',
                'match' => ['/user/edit.php', '/login/change_password.php', '/user/language.php'], 'links' => []],
            'learning'  => ['title' => get_string('sw_pref_learning', 'theme_baz'), 'cls' => 'b',
                'match' => ['/user/forum.php', '/user/editor.php', '/user/calendar.php', '/user/contentbank.php'], 'links' => []],
            'messaging' => ['title' => get_string('sw_pref_messaging', 'theme_baz'), 'cls' => 'c',
                'match' => ['/message/edit.php', '/message/notificationpreferences.php', '/message/', 'privacy', 'policy', 'datarequest'], 'links' => []],
            'blogs'     => ['title' => get_string('sw_pref_blogs', 'theme_baz'), 'cls' => 'd',
                'match' => ['/blog/'], 'links' => []],
        ];
        $more = [];

        $settings = $PAGE->settingsnav->find('usercurrentsettings', null);
        if ($settings) {
            foreach ($settings->children as $grp) {
                foreach ($grp->children as $link) {
                    if (!($link->action instanceof \moodle_url)) {
                        continue;
                    }
                    $url = $link->action->out(false);
                    $text = (string) $link->text;
                    if ($text === '') {
                        continue;
                    }
                    $placed = false;
                    foreach ($buckets as &$b) {
                        foreach ($b['match'] as $needle) {
                            if (stripos($url, $needle) !== false) {
                                $b['links'][] = ['text' => $text, 'url' => $url];
                                $placed = true;
                                break 2;
                            }
                        }
                    }
                    unset($b);
                    if (!$placed) {
                        $more[] = ['text' => $text, 'url' => $url];
                    }
                }
            }
        }

        $cards = [];
        foreach ($buckets as $key => $b) {
            if (empty($b['links'])) {
                continue;
            }
            $cards[] = ['title' => $b['title'], 'iconsvg' => $icons[$key], 'cls' => $b['cls'], 'links' => $b['links']];
        }
        if (!empty($more)) {
            $cards[] = ['title' => get_string('sw_pref_more', 'theme_baz'), 'iconsvg' => $icons['more'], 'cls' => 'e', 'links' => $more];
        }
        return $cards;
    }

    /**
     * Real support block HTML for the floating help panel.
     */
    protected function render_support(): ?string {
        return theme_baz_support_panel();
    }

    /**
     * The enrolment-page description for this course, ready to render.
     *
     * Reuses the theme's own get_enrollfields(), so the enrolldesc / enrollvideo
     * custom fields render exactly as they do on the classic enrolment layout,
     * and a course with nothing in them renders nothing.
     *
     * @param renderer_base $output
     * @return string HTML, empty when it does not apply or has no content
     */
    protected function render_enroldesc(renderer_base $output): string {
        if ($this->page !== 'enrol' || !method_exists($output, 'get_enrollfields')) {
            return '';
        }
        try {
            $html = (string) $output->get_enrollfields();
            // get_enrollfields returns the field wrappers even when every field is
            // empty, so test the text rather than the markup.
            return trim(strip_tags($html)) === '' ? '' : $html;
        } catch (\Throwable $e) {
            debugging('student_wrap enroldesc: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return '';
        }
    }
}
