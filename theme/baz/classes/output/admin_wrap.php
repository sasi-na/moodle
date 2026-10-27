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
 * Bespoke shell for the administration pages.
 *
 * Every page keeps its own content and JS: core renders into main_content and this
 * only supplies the chrome (sidebar, breadcrumb, page head, settings search). The
 * wrapped content is restyled from SCSS scoped by the wrapclass, so no setting,
 * filter, table or form is altered.
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
 * Renderable + templatable for an administration page in the bespoke shell.
 */
class admin_wrap implements renderable, templatable {

    /** @var array Top-level admin sections, in nav order: tree name => icon key. */
    protected const SECTIONS = [
        'users' => 'users',
        'courses' => 'courses',
        'grades' => 'grades',
        'reports' => 'reports',
        'modules' => 'plugins',
        'security' => 'security',
        'appearance' => 'appearance',
        'server' => 'server',
        'development' => 'development',
    ];

    /**
     * Which URL prefixes belong to which section, for pages that are not part of
     * the settings tree (Browse users, Manage courses, the report scripts, …).
     *
     * Longest match wins, so the order here is significant.
     *
     * @var array
     */
    protected const URLMAP = [
        '/admin/user' => 'users',
        '/admin/roles' => 'users',
        '/cohort/' => 'users',
        '/user/profile/' => 'users',
        '/course/management' => 'courses',
        '/course/edit' => 'courses',
        '/course/index' => 'courses',
        '/backup/' => 'courses',
        '/enrol/' => 'courses',
        '/group/' => 'courses',
        '/grade/' => 'grades',
        '/report/' => 'reports',
        '/reportbuilder/' => 'reports',
        '/question/' => 'grades',
        '/admin/tool/task' => 'server',
        '/admin/tool/log' => 'server',
        '/admin/tool/customlang' => 'appearance',
        '/admin/plugins' => 'modules',
        '/admin/localplugins' => 'modules',
    ];


    /**
     * Is this page an administrative surface?
     *
     * The 'admin' pagelayout is administrative by definition, but 'standard' and
     * 'report' also serve ordinary pages, so those layouts consult this allow-list
     * rather than wrapping everything a site admin happens to open.
     *
     * @return bool
     */
    public static function is_admin_surface(bool $requirepath = true): bool {
        // No page is left on the classic layout by choice any more, so there is no
        // path list here. What stays excluded is excluded structurally: the popup,
        // embedded, maintenance, secure, print and login layouts are never hooked,
        // because wrapping them breaks them -- a sidebar inside a 400px modal or a
        // file-picker iframe, chrome on the secure quiz browser, or a maintenance
        // page that must not touch config or caches.
        //
        // $requirepath is kept so the three call sites need not change, and so the
        // distinction is still expressible if a layout ever needs it back.
        unset($requirepath);
        return true;
    }

    /**
     * Pagetypes on the `popup` pagelayout that are REAL pages, not popups.
     *
     * Core marks a page `popup` to mean "no navigation chrome", and most such pages
     * genuinely are modals or windows -- help.php, the editor file managers, the LTI
     * launch endpoints -- where an application shell would be absurd. But a handful
     * are full pages a user simply navigates to, and those were the only admin
     * surfaces left rendering classic. They are listed rather than detected because
     * nothing in the markup distinguishes "opened in a window" from "navigated to".
     *
     * Deliberately NOT listed, because they really are opened as popups/modals:
     * question preview, mod_quiz comment + reviewquestion, CodeRunner questiontestrun,
     * mod_chat gui_basic, rating/index. Those keep the bare popup treatment.
     */
    protected const POPUP_PAGETYPES = [
        'admin-reportbuilder-edit',
        'course-scales',
        'admin-tool-policy-viewall',
    ];

    /**
     * May this `popup`-layout page be wrapped in the shell?
     *
     * @return bool
     */
    public static function popup_allowed(): bool {
        global $PAGE;

        return in_array($PAGE->pagetype, self::POPUP_PAGETYPES, true);
    }

    /**
     * Is the bespoke admin shell switched on for the current user?
     *
     * Off / pilot / all, shared with the dashboard surfaces -- see
     * theme_baz\local\gate. Defaults off when never configured.
     *
     * @return bool
     */
    protected static function gate_allows(): bool {
        return \theme_baz\local\gate::allows('admin_view_pages', false);
    }

    /**
     * Render the page in the bespoke admin shell, if it is turned on and applicable.
     *
     * Three layouts share this, so the gate, the context and the fallback live here
     * rather than being copied into each of them.
     *
     * @param array $extraclasses body classes the layout has already collected
     * @param bool $darkmodeon
     * @param bool $darkmodetheme
     * @param bool $requireadminpath only wrap known administrative paths
     * @return bool true when the page has been rendered and the layout should return
     */
    public static function maybe_render(array $extraclasses, bool $darkmodeon = false,
            bool $darkmodetheme = false, bool $requireadminpath = false): bool {
        global $CFG, $OUTPUT, $SITE, $USER;

        if (!isloggedin() || isguestuser()) {
            return false;
        }
        if (!self::gate_allows()) {
            return false;
        }
        // Everyone except students: they already have their own redesigned pages,
        // and shell::nav() has nothing for them. Note this is deliberately not
        // is_staff() -- a user holding only an unmapped custom role, or no role at
        // all, is neither student-only nor staff, and would otherwise be the one
        // audience still left on the classic layout.
        if (\theme_baz\local\shell::audience($USER->id) === \theme_baz\local\shell::AUD_STUDENT) {
            return false;
        }
        if (!self::is_admin_surface($requireadminpath)) {
            return false;
        }

        try {
            $themesettings = new \theme_baz\util\theme_settings();
            $adminwrap = new self();
            $templatecontext = array_merge([
                'sitename' => format_string($SITE->shortname, true,
                    ['context' => \context_course::instance(SITEID), 'escape' => false]),
                'output' => $OUTPUT,
                'bodyattributes' => $OUTPUT->body_attributes(array_merge($extraclasses, ['baz-admin-exp'])),
                'siteurl' => $CFG->wwwroot,
                'navdraweropen' => false,
                'darkmodeon' => $darkmodeon,
                'darkmodetheme' => $darkmodetheme,
            ], $themesettings->global_settings(), $adminwrap->export_for_template($OUTPUT));
            echo $OUTPUT->render_from_template('theme_baz/admin-wrap', $templatecontext);
            return true;
        } catch (\Throwable $e) {
            debugging('theme_baz admin wrap: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $CFG, $PAGE, $SITE, $USER;

        $title = $this->page_title();
        $crumbs = $this->crumbs($title);
        $active = $this->active_section();

        // Blocks. Exactly one block instance targets admin pages on these sites
        // (admin_bookmarks, on the admin-* pagetype, in side-pre) but dropping the
        // region would remove it, and would leave edit mode with nothing to act on.
        // The rail is only rendered when there is something in it.
        $blockshtml = $output->blocks('side-pre');

        // The shell is hooked into layouts that keep content in regions of their
        // own, and emitting main_content alone silently threw those away: 259
        // sidecourseblocks instances on jain (quickmail on 154 courses) vanished
        // from wrapped course pages. blocks() is safe on a layout that does not
        // declare the region -- region_has_content() returns false for an unknown
        // region -- so these can be asked for unconditionally.
        $coursesideblocks = $output->blocks('sidecourseblocks');
        $ctopbl = $output->blocks('ctopbl');
        $cbottombl = $output->blocks('cbottombl');
        $cstopbl = $output->blocks('cstopbl');
        $csbottombl = $output->blocks('csbottombl');

        // The rail appears only when a block actually lives there. Deliberately NOT
        // keyed off addblockbutton(): in edit mode that is non-empty on every page,
        // which opened an otherwise empty rail and took roughly a quarter of the
        // width off the content (measured 1300px -> 980px), throwing out the
        // alignment of every wrapped form and listing.
        $hasblocks = (strpos($blockshtml, 'data-block=') !== false)
            || (strpos($coursesideblocks, 'data-block=') !== false);

        // Controls the wrapped layouts build that the shell was also dropping:
        // the activity header (completion, dates, description) and the
        // region-main action menu.
        // Read the magic page property into a local first: empty() on
        // $PAGE->activityheader lies, the same way it does for $PAGE->course.
        $headercontent = '';
        $activityheader = $PAGE->activityheader;
        if ($activityheader instanceof \core\output\renderable) {
            try {
                $headercontent = $output->render($activityheader);
            } catch (\Throwable $e) {
                $headercontent = '';
            }
        }
        // region_main_settings_menu() lives on core_renderer, not renderer_base,
        // and can throw on a page with no settings context. Neither is worth
        // losing the whole shell over, so both are guarded here rather than by
        // the outer try/catch, which would silently drop back to classic.
        $regionmainsettingsmenu = '';
        if (method_exists($output, 'region_main_settings_menu')) {
            try {
                $regionmainsettingsmenu = $output->region_main_settings_menu();
            } catch (\Throwable $e) {
                $regionmainsettingsmenu = '';
            }
        }

        $courseid = self::course_id();
        $incourse = (bool) $courseid;

        // The rest of the page's own navigation, deduped against whichever sidebar
        // this viewer actually gets rather than against a fixed list.
        $sections = $this->nav_for_viewer($incourse, $courseid, $active);
        $skip = [];
        foreach ($sections as $section) {
            if (!empty($section['url'])) {
                $skip[] = parse_url($section['url'], PHP_URL_PATH);
            }
        }
        // The persistent block, and the paths it already covers -- the faculty
        // audience nav carries Overview (/my/) and My courses, which would
        // otherwise appear twice.
        $homenav = $this->home_nav();
        $homepaths = [];
        foreach ($homenav as $item) {
            $homepaths[] = self::url_key($item['url']);
        }
        // Matched on path AND query, not path alone: the persistent block's Calendar
        // is the bare site calendar, while the course nav's is the same script scoped
        // to this course. On paths alone the course-scoped one was silently dropped.
        // Nothing in the persistent block carries a query, so every dedupe it is
        // meant to do still happens.
        $sections = array_values(array_filter($sections, function($section) use ($homepaths) {
            return empty($section['url'])
                || !in_array(self::url_key($section['url']), $homepaths, true);
        }));

        // The course Calendar and the persistent one are the same script, so on the
        // calendar page both blocks would light up. The contextual item is the more
        // specific answer to "where am I", so the persistent one yields.
        foreach ($homenav as $i => $item) {
            $itempath = parse_url($item['url'], PHP_URL_PATH);
            foreach ($sections as $section) {
                if (!empty($section['active']) && !empty($section['url'])
                        && parse_url($section['url'], PHP_URL_PATH) === $itempath) {
                    $homenav[$i]['active'] = false;
                }
            }
        }

        $pagenav = [];
        if ($incourse) {
            try {
                // Deduped against the persistent block as well as the contextual one.
                $pagenav = $this->page_nav_groups(array_merge($skip, $homepaths));
            } catch (\Throwable $e) {
                $pagenav = [];
            }
        }

        $coursename = '';
        if ($incourse) {
            $course = $PAGE->course;
            $coursename = format_string($course->fullname, true,
                ['context' => \context_course::instance($courseid), 'escape' => false]);
            $crumbs = $this->crumbs_after_course($crumbs, $course);
        }

        return [
            'awtitle' => $title,
            'aweyebrow' => $incourse ? $coursename : $this->eyebrow($crumbs, $active),
            'awroot' => $incourse ? $coursename : $this->root_label(),
            'awclass' => $this->wrapclass(),
            'crumbs' => $crumbs,
            'hascrumbs' => !empty($crumbs),
            'incourse' => $incourse,
            'coursefacts' => $this->course_facts($output, $incourse),
            'coursename' => $coursename,
            'courseurl' => $incourse ? (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false) : '',
            'homenav' => $homenav,
            'sections' => $sections,
            'hassections' => !empty($sections),
            // Names the contextual block. Inside a course that is the course; for a
            // staff audience it is their own area. Admins have no block at all.
            'sectionslabel' => $incourse
                ? get_string('course')
                : get_string('aw_nav_yourarea', 'theme_baz'),
            // Only inside a course: on an admin page the secondary navigation is the
            // whole 532-node settings tree, which the sidebar already models.
            'pagenav' => $pagenav,
            'haspagenav' => !empty($pagenav),
            'searchurl' => (new moodle_url('/' . $CFG->admin . '/search.php'))->out(false),
            // Where "back out of here" leads. For a site admin that is the settings
            // tree; for anyone else that page is inaccessible, so it is the dashboard.
            'adminhomeurl' => is_siteadmin($USER->id)
                ? (new moodle_url('/' . $CFG->admin . '/search.php'))->out(false)
                : (new moodle_url('/my/'))->out(false),
            'homelabel' => is_siteadmin($USER->id)
                ? get_string('aw_nav_overview', 'theme_baz')
                : get_string('myhome'),
            'rolebadge' => $this->role_badge(),
            'issiteadmin' => is_siteadmin($USER->id),
            'dashboardurl' => (new moodle_url('/my/'))->out(false),
            'sitename' => format_string($SITE->shortname, true,
                ['context' => \context_course::instance(SITEID), 'escape' => false]),
            'sidepreblocks' => $blockshtml,
            'coursesideblocks' => $coursesideblocks,
            'hasblocks' => $hasblocks,
            'ctopbl' => $ctopbl,
            'hasctopbl' => strpos($ctopbl, 'data-block=') !== false,
            'cbottombl' => $cbottombl,
            'hascbottombl' => strpos($cbottombl, 'data-block=') !== false,
            'cstopbl' => $cstopbl,
            'hascstopbl' => strpos($cstopbl, 'data-block=') !== false,
            'csbottombl' => $csbottombl,
            'hascsbottombl' => strpos($csbottombl, 'data-block=') !== false,
            'headercontent' => $headercontent,
            'hasheadercontent' => trim(strip_tags($headercontent, '<img><iframe>')) !== '',
            'regionmainsettingsmenu' => $regionmainsettingsmenu,
            'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
            'isediting' => $PAGE->user_is_editing(),
        ];
    }

    /**
     * The breadcrumb, from the page's own navbar so it is never invented.
     *
     * @return array
     */
    protected function crumbs(string $title = ''): array {
        global $PAGE;

        $out = [];
        try {
            $items = $PAGE->navbar->get_items();
        } catch (\Throwable $e) {
            return [];
        }
        // The shell always renders "Site administration" as the trail root, so core's
        // own copy of it would show twice.
        $root = get_string('administrationsite');
        foreach ($items as $item) {
            $text = trim(html_to_text((string) $item->text, 0, false));
            if ($text === '' || $text === $root) {
                continue;
            }
            $out[] = [
                'label' => $text,
                'url' => ($item->action instanceof moodle_url) ? $item->action->out(false) : '',
                'haslink' => ($item->action instanceof moodle_url),
            ];
        }
        // Collapse repeats: Moodle's navbar carries the current page twice on many
        // admin screens (once from the settings navigation, once as the page node).
        $deduped = [];
        foreach ($out as $crumb) {
            $last = end($deduped);
            if ($last === false || $last['label'] !== $crumb['label']) {
                $deduped[] = $crumb;
            }
        }
        // Drop any trailing crumb that just repeats the h1.
        while ($deduped && end($deduped)['label'] === $title) {
            array_pop($deduped);
        }
        // Otherwise the last crumb IS the current page, shown as the h1 instead.
        if ($deduped && $title === '') {
            array_pop($deduped);
        }
        return $deduped;
    }

    /**
     * Drop everything up to and including the course from the trail.
     *
     * The chrome already shows the course as the trail root, and core's navbar walks
     * Site administration / Dashboard / Courses / category / course before reaching
     * the page, all of which is noise once the course is the root.
     *
     * @param array $crumbs
     * @param \stdClass $course
     * @return array
     */
    protected function crumbs_after_course(array $crumbs, \stdClass $course): array {
        $names = array_filter([
            trim((string) ($course->fullname ?? '')),
            trim((string) ($course->shortname ?? '')),
        ]);
        $cut = -1;
        foreach ($crumbs as $i => $crumb) {
            if (in_array($crumb['label'], $names, true)) {
                $cut = $i;
            }
        }
        return ($cut >= 0) ? array_values(array_slice($crumbs, $cut + 1)) : $crumbs;
    }

    /**
     * The page's own name, for the h1.
     *
     * $PAGE->heading is the site fullname on admin pages, so it cannot be used.
     *
     * @return string
     */
    protected function page_title(): string {
        global $PAGE;

        try {
            $items = $PAGE->navbar->get_items();
            $last = end($items);
            if ($last) {
                $text = trim(html_to_text((string) $last->text, 0, false));
                if ($text !== '') {
                    return $text;
                }
            }
        } catch (\Throwable $e) {
            // Fall through to the title.
        }

        $parts = array_values(array_filter(array_map('trim', explode('|', (string) $PAGE->title))));
        return $parts ? $parts[0] : get_string('administrationsite');
    }

    /**
     * Small label above the h1: the section this page belongs to.
     *
     * @param array $crumbs
     * @param string|null $active
     * @return string
     */
    protected function eyebrow(array $crumbs, ?string $active): string {
        if ($active) {
            $label = $this->section_label($active);
            if ($label !== '') {
                return $label;
            }
        }
        // Deliberately NOT the last crumb: on a top-level settings page that is
        // "Dashboard", which is not where the page lives.
        return $this->root_label();
    }

    /**
     * Sidebar sections, filtered so nothing links to a page the viewer cannot open.
     *
     * Ten nodes in the live tree fail check_access() and answer 404, so a nav that
     * skips this filter renders dead links.
     *
     * @param string|null $active
     * @return array
     */
    protected function sections(?string $active): array {
        global $CFG;

        $root = $this->root();
        $out = [];
        foreach (self::SECTIONS as $name => $icon) {
            if ($root) {
                $node = $root->locate($name);
                if (!$node || !$node->check_access()) {
                    continue;
                }
            }
            $out[] = [
                'name' => $name,
                'icon' => $icon,
                // Mustache cannot switch, so each item flags its own icon.
                'icon_' . $icon => true,
                'label' => $this->section_label($name),
                'url' => (new moodle_url('/' . $CFG->admin . '/category.php', ['category' => $name]))->out(false),
                'active' => ($name === $active),
            ];
        }
        return $out;
    }

    /**
     * The id of the course this page belongs to, or 0 at site level.
     *
     * empty() on $PAGE->course lies -- it is a magic property -- so it is read into
     * a local variable first.
     *
     * @return int
     */
    protected static function course_id(): int {
        global $PAGE;

        $course = $PAGE->course;
        if (empty($course) || empty($course->id)) {
            return 0;
        }
        return ((int) $course->id === (int) SITEID) ? 0 : (int) $course->id;
    }

    /**
     * Every destination the page's own secondary navigation offers, grouped the way
     * core groups it, minus whatever the sidebar above already links to.
     *
     * The curated course sidebar is a hand-written list of eight items. Core's
     * secondary navigation carries 48 destinations on a course page and 13 on an
     * activity page, so the hand-written list silently removed the gradebook, the
     * question bank, the eight course reports and all of course reuse. Deriving the
     * remainder from $PAGE->secondarynav makes parity structural: whatever core
     * offers this user on this page, the shell offers too, including after a core
     * upgrade or a new plugin.
     *
     * Secondary nav is already capability-filtered, so unlike the admin tree this
     * needs no check_access() pass to avoid dead links.
     *
     * @param string[] $skippaths url paths the sidebar already covers
     * @return array
     */
    protected function page_nav_groups(array $skippaths): array {
        global $PAGE;

        if (!$PAGE->has_secondary_navigation()) {
            return [];
        }
        $currentpath = $PAGE->url ? $PAGE->url->get_path() : '';
        $groups = [];
        $seen = [];
        foreach ($PAGE->secondarynav->children as $node) {
            $group = $this->nav_node_to_group($node, $skippaths, $currentpath, $seen);
            if ($group !== null) {
                $groups[] = $group;
            }
        }
        return $groups;
    }

    /**
     * One secondary-navigation node as a sidebar group: a link, a group of links, or
     * null when everything in it is already reachable from the sidebar.
     *
     * @param \navigation_node $node
     * @param string[] $skippaths
     * @param string $currentpath
     * @param array $seen paths already emitted, by reference, so a destination that
     *                    appears both as a parent and as its own child is listed once
     * @return array|null
     */
    protected function nav_node_to_group($node, array $skippaths, string $currentpath, array &$seen): ?array {
        $selfurl = self::node_url($node);
        $selfpath = $selfurl ? parse_url($selfurl, PHP_URL_PATH) : '';

        // Descendants are flattened at any depth, not just one level: core nests
        // Manual enrolments and Role renaming under Participants > Enrolment
        // methods, and a one-level walk silently dropped both.
        $children = [];
        $collect = function($parent) use (&$collect, &$children, &$seen, $skippaths, $currentpath) {
            foreach ($parent->children as $child) {
                $curl = self::node_url($child);
                if ($curl) {
                    $cpath = parse_url($curl, PHP_URL_PATH);
                    if (!in_array($cpath, $skippaths, true) && !isset($seen[$curl])) {
                        $seen[$curl] = true;
                        $children[] = [
                            'label' => trim((string) $child->text),
                            'url' => $curl,
                            'active' => $cpath === $currentpath,
                        ];
                    }
                }
                if ($child->children && count($child->children)) {
                    $collect($child);
                }
            }
        };
        $collect($node);

        $skipself = !$selfurl || in_array($selfpath, $skippaths, true) || isset($seen[$selfurl]);
        if ($skipself && !$children) {
            return null;
        }
        if (!$skipself) {
            $seen[$selfurl] = true;
        }

        $selfactive = !$skipself && $selfpath === $currentpath;
        $childactive = (bool) array_filter($children, fn($c) => $c['active']);

        return [
            'label' => trim((string) $node->text),
            'url' => $skipself ? '' : $selfurl,
            'haslink' => !$skipself,
            'children' => $children,
            'haschildren' => !empty($children),
            // A group is expanded when it holds the page you are on, so the current
            // location is visible without the viewer hunting for it.
            'open' => $selfactive || $childactive,
        ];
    }

    /**
     * A navigation node's target as a plain url string, or '' if it has none.
     *
     * Nodes carry either a moodle_url or an action_link, and inactive nodes carry
     * neither, so this normalises all three.
     *
     * @param \navigation_node $node
     * @return string
     */
    protected static function node_url($node): string {
        $action = $node->action ?? null;
        if ($action instanceof \moodle_url) {
            return $action->out(false);
        }
        if ($action instanceof \action_link && $action->url instanceof \moodle_url) {
            return $action->url->out(false);
        }
        return '';
    }

    /**
     * A url reduced to what identifies the destination: path, plus query when it has
     * one. Two links to the same script with different parameters are different
     * destinations, which comparing paths alone cannot express.
     *
     * @param string $url
     * @return string
     */
    protected static function url_key(string $url): string {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $query = parse_url($url, PHP_URL_QUERY) ?: '';
        return $query !== '' ? $path . '?' . $query : $path;
    }

    /**
     * The persistent block. Shared with both /my/ dashboards via
     * theme_baz\local\shell so the left menu cannot drift between them.
     *
     * @return array
     */
    protected function home_nav(): array {
        return \theme_baz\local\shell::home_nav();
    }

    /**
     * The sidebar the viewer should actually get.
     *
     * The shell is no longer admin-only, so the site sections are only right for a
     * site administrator. Staff get the nav their audience already defines in
     * theme_baz\local\shell, flattened into the shape this template expects.
     *
     * @param bool $incourse
     * @param int $courseid
     * @param string|null $active
     * @return array
     */
    protected function nav_for_viewer(bool $incourse, int $courseid, ?string $active): array {
        global $USER;

        if ($incourse) {
            return $this->course_nav($courseid);
        }
        // A site admin gets no contextual block: every one of the ten sections is
        // one click inside Site administration, which is pinned above, so listing
        // them here only made the menu long and unstable.
        if (is_siteadmin($USER->id)) {
            return [];
        }

        $audience = \theme_baz\local\shell::audience($USER->id);
        if ($audience === null) {
            return $this->default_nav();
        }

        // shell::nav() groups its items and supplies raw SVG path data; the template
        // takes a flat list, so the groups are flattened and the path passed through.
        $out = [];
        foreach (\theme_baz\local\shell::nav($audience) as $group) {
            foreach ($group['items'] as $item) {
                $out[] = [
                    'name' => $item['icon'] ?? '',
                    'label' => $item['label'],
                    'url' => $item['url'],
                    'iconpath' => $item['iconpath'] ?? '',
                    'active' => false,
                ];
            }
        }
        return $out;
    }

    /**
     * The badge under the logo. "Site Admin" is only true for a site administrator;
     * staff get the label their own audience nav uses.
     *
     * @return string
     */
    protected function role_badge(): string {
        global $USER;

        if (is_siteadmin($USER->id)) {
            return get_string('ad_admin', 'theme_baz');
        }
        $audience = \theme_baz\local\shell::audience($USER->id);
        if ($audience !== null) {
            $groups = \theme_baz\local\shell::nav($audience);
            if (!empty($groups[0]['label'])) {
                return (string) $groups[0]['label'];
            }
        }
        return '';
    }

    /**
     * The label the trail is rooted at. Only a site administrator is actually in
     * Site administration; for everyone else that root is both wrong and a dead link.
     *
     * @return string
     */
    protected function root_label(): string {
        global $USER, $SITE;

        if (is_siteadmin($USER->id)) {
            return get_string('administrationsite');
        }
        return format_string($SITE->shortname, true,
            ['context' => \context_course::instance(SITEID), 'escape' => false]);
    }

    /**
     * Fallback sidebar for a viewer who is neither a site administrator nor one of
     * the staff audiences -- an unmapped custom role, or no role at all. The site
     * sections would be both wrong and mostly inaccessible for them.
     *
     * @return array
     */
    protected function default_nav(): array {
        $items = [
            ['myhome', 'moodle', new moodle_url('/my/'), 'courses'],
            ['mycourses', 'moodle', new moodle_url('/my/courses.php'), 'courses'],
            ['calendar', 'calendar', new moodle_url('/calendar/view.php'), 'reports'],
        ];
        $out = [];
        foreach ($items as [$key, $component, $url, $icon]) {
            if (!get_string_manager()->string_exists($key, $component)) {
                continue;
            }
            $out[] = [
                'name' => $key,
                'icon' => $icon,
                'icon_' . $icon => true,
                'label' => get_string($key, $component),
                'url' => $url->out(false),
                'active' => false,
            ];
        }
        return $out;
    }

    /**
     * Course-scoped sidebar, for administration screens inside a course.
     *
     * The site sidebar would be actively misleading here: an admin editing one
     * course's groups is not "in Site administration", which is what the chrome
     * claimed before. Capabilities are not checked per item because the whole shell
     * is gated on is_siteadmin.
     *
     * @param int $courseid
     * @return array
     */
    protected function course_nav(int $courseid): array {
        global $PAGE, $CFG;

        $path = $PAGE->url ? $PAGE->url->get_path() : '';
        $ctxid = 0;
        try {
            $ctxid = \context_course::instance($courseid)->id;
        } catch (\Throwable $e) {
            $ctxid = 0;
        }

        $items = [
            ['coursehome', 'courses', new moodle_url('/course/view.php', ['id' => $courseid]), 'course'],
            ['participants', 'users', new moodle_url('/user/index.php', ['id' => $courseid]), 'users'],
            ['enrolmethods', 'users', new moodle_url('/enrol/instances.php', ['id' => $courseid]), 'enrol'],
            ['groups', 'users', new moodle_url('/group/index.php', ['id' => $courseid]), 'group'],
            ['coursesettings', 'appearance', new moodle_url('/course/edit.php', ['id' => $courseid]), 'settings'],
            // Course-scoped deliberately: the persistent Calendar link above is the
            // site calendar, where New event defaults to a user event. Carrying
            // `course` preselects this course, which is the only way staff reach
            // "create an event in this course" from inside it.
            ['coursecalendar', 'calendar',
                new moodle_url('/calendar/view.php', ['view' => 'month', 'course' => $courseid]), 'calendar'],
            ['completion', 'reports', new moodle_url('/course/completion.php', ['id' => $courseid]), 'completion'],
            ['permissions', 'security', new moodle_url('/admin/roles/permissions.php', ['contextid' => $ctxid]), 'permissions'],
            ['backup', 'server', new moodle_url('/backup/backup.php', ['id' => $courseid]), 'backup'],
        ];

        $out = [];
        foreach ($items as [$key, $icon, $url, $match]) {
            $target = $url->out(false);
            $out[] = [
                'name' => $key,
                'icon' => $icon,
                'icon_' . $icon => true,
                'label' => $this->course_nav_label($key),
                'url' => $target,
                'active' => (strpos($path, $url->get_path()) === 0),
            ];
        }

        return array_merge($out, $this->module_nav($path));
    }

    /**
     * The course header's row of labelled facts, for an in-course page.
     *
     * Exists so that `.rui-course-info-box` is present in this shell. Features
     * that add a field to the course header find it by that selector and
     * silently do nothing without it -- local_facultyfeedback's weekly-feedback
     * figure is the case that surfaced this: its AMD module tries
     * `.rui-course-info-box`, then `#page-header .header-actions-container`,
     * then `#page-header`, and this shell emitted none of the three, so the
     * badge simply never appeared. Anything else using the same convention was
     * equally invisible.
     *
     * The inner markup is core_renderer::course_details() rather than a copy of
     * it, so Start date / End date cannot drift from the classic header. The
     * only thing added here is the `.rui-course-info-box` wrapper that
     * course_details() is normally rendered inside.
     *
     * No new CSS: both `.rui-course-info-box` and `.rui-course-details` are
     * styled at the top level of scss/moodle/course.scss, so the row and any
     * field appended into it inherit the classic type and spacing.
     *
     * @param renderer_base $output the renderer rendering this template, used
     *                               rather than $OUTPUT so the call cannot pick
     *                               up a different renderer than the one in play
     * @param bool $incourse
     * @return string HTML, '' when this is not a real course page
     */
    protected function course_facts(renderer_base $output, bool $incourse): string {
        global $COURSE;

        if (!$incourse || !method_exists($output, 'course_details')) {
            return '';
        }
        try {
            // Read the magic page property into a local: empty() on $PAGE->course
            // reports "not set" even when the course is loaded.
            $course = $COURSE;
            if (empty($course) || empty($course->id) || (int) $course->id === SITEID) {
                return '';
            }
            $details = $output->course_details();
            if (trim((string) $details) === '') {
                return '';
            }
            return \html_writer::div($details, 'rui-course-info-box aw-coursefacts');
        } catch (\Throwable $e) {
            // A header without the fact row is survivable; a 500 on every
            // in-course admin page is not.
            return '';
        }
    }

    /**
     * Per-activity links contributed by plugins, for an activity page.
     *
     * The eight items above are course-level and fixed, which meant anything a
     * plugin added to the ACTIVITY's settings navigation was rendered nowhere:
     * page_nav_groups() reads $PAGE->secondarynav, but it is only consulted on
     * non-course pages, and course_nav() never looked at navigation at all.
     *
     * local_fileshare is the case that exposed this — it surfaces its bulk
     * per-quiz page solely through local_fileshare_extend_settings_navigation(),
     * which adds a node under `modulesettings`, so under this shell the entry
     * point simply did not exist. Any other plugin using the same standard hook
     * was equally invisible.
     *
     * Read from `modulesettings` rather than from secondary navigation because
     * that is the node core's settings_navigation::load_local_plugin_settings()
     * hands plugins, and it is present on an activity page whether or not the
     * theme asked for secondary nav.
     *
     * Nodes carry their own capability checks (the plugin decides whether to add
     * them at all), so nothing is re-checked here. Anything without a URL, or
     * pointing at a path the sidebar already lists, is dropped.
     *
     * @param string $path current page path, for the active flag
     * @return array sidebar items, empty when this is not an activity page
     */
    protected function module_nav(string $path): array {
        global $PAGE;

        try {
            // Both are magic moodle_page properties, so they are read into locals
            // before being tested: empty() on a magic prop calls __isset(), which
            // moodle_page does not implement for these, and so reports empty for
            // a property that is in fact set.
            $cm = $PAGE->cm;
            if (empty($cm->id)) {
                return [];
            }
            $settingsnav = $PAGE->settingsnav;
            if (!$settingsnav) {
                return [];
            }
            $modulenode = $settingsnav->find('modulesettings', \navigation_node::TYPE_SETTING);
            if (!$modulenode) {
                return [];
            }
        } catch (\Throwable $e) {
            // A shell missing a few links is recoverable; a 500 on every activity
            // page is not.
            return [];
        }

        $out = [];
        $seen = [];
        $ownview = '/mod/' . $cm->modname . '/view.php';
        foreach ($modulenode->children as $child) {
            $url = self::node_url($child);
            if (!$url) {
                continue;
            }
            $childpath = parse_url($url, PHP_URL_PATH) ?: '';
            $childquery = parse_url($url, PHP_URL_QUERY) ?: '';
            // The activity's own view page is the page you are already on, and
            // core lists it here; it is noise in a sidebar.
            //
            // Match on path AND query, not path alone. Several modules hang a
            // distinct destination off their own view.php with a query -- most
            // visibly mod_assign, whose "Submissions" entry is
            // /mod/assign/view.php?id=N&action=grading. Comparing paths threw
            // that away, so teachers lost the link to the grading table
            // entirely. Anything carrying an action is a different page.
            $isownview = strpos($childpath, $ownview) === 0
                && !preg_match('/(^|&)action=/', $childquery);
            $key = $childpath . ($childquery !== '' ? '?' . $childquery : '');
            if ($childpath === '' || isset($seen[$key]) || $isownview) {
                continue;
            }
            $seen[$key] = true;
            $out[] = [
                'name' => 'mod_' . $child->key,
                'icon' => 'course',
                'icon_course' => true,
                'label' => (string) $child->get_content(),
                'url' => $url,
                'active' => ($childpath !== '' && strpos($path, $childpath) === 0),
            ];
        }
        return $out;
    }

    /**
     * Label for a course-nav item, taken from core strings so it matches the rest of
     * the interface. Every key is checked to exist before it is used, because a
     * missing string renders as [[key]] on the live page.
     *
     * @param string $key
     * @return string
     */
    protected function course_nav_label(string $key): string {
        $map = [
            'coursehome' => ['course', 'moodle'],
            'participants' => ['participants', 'moodle'],
            'enrolmethods' => ['enrolmentinstances', 'enrol'],
            'groups' => ['groups', 'moodle'],
            'coursesettings' => ['settings', 'moodle'],
            'coursecalendar' => ['calendar', 'calendar'],
            'completion' => ['coursecompletion', 'completion'],
            'permissions' => ['permissions', 'role'],
            'backup' => ['backup', 'moodle'],
        ];
        [$k, $component] = $map[$key];
        if (!get_string_manager()->string_exists($k, $component)) {
            return ucfirst($key);
        }
        return get_string($k, $component);
    }

    /**
     * The admin tree, but only if something else already built it.
     *
     * Building it costs real time, and this shell also wraps pages that are not part
     * of the tree, so it is never built here just to draw a sidebar.
     *
     * @return \admin_root|null
     */
    protected function root(): ?\admin_root {
        global $ADMIN, $CFG;

        if (empty($ADMIN)) {
            return null;
        }
        require_once($CFG->libdir . '/adminlib.php');
        return ($ADMIN instanceof \admin_root) ? $ADMIN : null;
    }

    /**
     * Human label for a top-level section, taken from the tree where possible so it
     * matches what the classic interface calls it.
     *
     * @param string $name
     * @return string
     */
    protected function section_label(string $name): string {
        static $cache = [];
        if (isset($cache[$name])) {
            return $cache[$name];
        }

        $root = $this->root();
        if ($root) {
            $node = $root->locate($name);
            if ($node) {
                return $cache[$name] = (string) $node->visiblename;
            }
        }

        // Fallbacks for when the tree has not been built on this request.
        $map = [
            'users' => ['users', 'admin'],
            'courses' => ['courses', ''],
            'grades' => ['grades', 'grades'],
            'reports' => ['reports', ''],
            'modules' => ['plugins', 'admin'],
            'security' => ['security', 'admin'],
            'appearance' => ['appearance', 'admin'],
            'server' => ['server', 'admin'],
            'development' => ['development', 'admin'],
        ];
        if (!isset($map[$name])) {
            return $cache[$name] = ucfirst($name);
        }
        [$key, $component] = $map[$name];
        $component = $component === '' ? 'moodle' : $component;
        if (!get_string_manager()->string_exists($key, $component)) {
            return $cache[$name] = ucfirst($name);
        }
        return $cache[$name] = get_string($key, $component);
    }

    /**
     * Which sidebar section the current page sits under.
     *
     * @return string|null
     */
    protected function active_section(): ?string {
        global $PAGE;

        $url = $PAGE->url ? $PAGE->url->get_path() : '';
        $root = $this->root();

        // Settings and category pages know their own node, so ask the tree.
        $nodename = '';
        if (strpos($url, '/category.php') !== false) {
            $nodename = (string) $PAGE->url->param('category');
        } else if (strpos($url, '/settings.php') !== false) {
            $nodename = (string) $PAGE->url->param('section');
        }
        if ($nodename !== '' && $root) {
            $top = $this->top_ancestor($root, $nodename);
            if ($top !== null) {
                return $top;
            }
        }

        // Everything else is matched on its URL, longest prefix first.
        $best = null;
        $bestlen = 0;
        foreach (self::URLMAP as $prefix => $section) {
            if (strpos($url, $prefix) === 0 && strlen($prefix) > $bestlen) {
                $best = $section;
                $bestlen = strlen($prefix);
            }
        }
        return $best;
    }

    /**
     * Walk the tree once to find which top-level section contains a node.
     *
     * @param \admin_root $root
     * @param string $nodename
     * @return string|null
     */
    protected function top_ancestor(\admin_root $root, string $nodename): ?string {
        static $map = null;

        if ($map === null) {
            $map = [];
            foreach (array_keys(self::SECTIONS) as $section) {
                $node = $root->locate($section);
                if ($node instanceof \admin_category) {
                    $this->collect($node, $section, $map);
                }
            }
        }
        return $map[$nodename] ?? null;
    }

    /**
     * Record every descendant name against its top-level section.
     *
     * @param \admin_category $node
     * @param string $section
     * @param array $map
     */
    protected function collect(\admin_category $node, string $section, array &$map): void {
        $map[$node->name] = $section;
        foreach ($node->children as $child) {
            if ($child instanceof \admin_category) {
                $this->collect($child, $section, $map);
            } else if (!empty($child->name)) {
                $map[$child->name] = $section;
            }
        }
    }

    /**
     * A class describing the shape of the wrapped content, so the SCSS can restyle
     * a settings form differently from a report or a management table.
     *
     * @return string
     */
    protected function wrapclass(): string {
        global $PAGE;

        $url = $PAGE->url ? $PAGE->url->get_path() : '';
        $map = [
            '/settings.php' => 'aw-settings',
            '/category.php' => 'aw-category',
            '/search.php' => 'aw-search',
            '/admin/index.php' => 'aw-notifications',
            // The three check reports share one Status/Check/Summary/Action shape.
            '/report/status/' => 'aw-checks',
            '/report/security/' => 'aw-checks',
            '/report/performance/' => 'aw-checks',
            '/admin/tool/task/' => 'aw-tasks',
            // 568 rows and 577KB on this site: the plugins control panel needs the
            // same data-viewport treatment as the task tables, not the generic one.
            '/admin/plugins.php' => 'aw-plugins',
            '/admin/tasklogs.php' => 'aw-report',
            '/admin/user.php' => 'aw-table',
            '/admin/user/' => 'aw-table',
            // More specific first: the map returns on the first match, and the
            // permission matrices need their own treatment, not the table one.
            '/admin/roles/define.php' => 'aw-permissions',
            '/admin/roles/override.php' => 'aw-permissions',
            '/admin/roles/permissions.php' => 'aw-permissions',
            '/admin/roles/' => 'aw-table',
            '/cohort/' => 'aw-table',
            '/admin/tool/capability/' => 'aw-table',
            '/course/management.php' => 'aw-manage',
            '/user/editadvanced.php' => 'aw-form',
            // Activity settings is the biggest moodleform on the site: for a quiz,
            // 161 fields across 33 fieldsets (17 collapsible) at 661KB. It was on
            // aw-generic, so it had neither the collapsible-section treatment nor the
            // sticky-row fix -- meaning Save floated over the fields.
            '/course/modedit.php' => 'aw-form',
            '/overrideedit.php' => 'aw-form',
            '/user/profile/' => 'aw-list',
            '/admin/tool/uploaduser/' => 'aw-form',
            '/course/scales.php' => 'aw-table',
        ];
        foreach ($map as $needle => $class) {
            if (strpos($url, $needle) !== false) {
                return $class;
            }
        }
        if (strpos($url, '/report/') === 0 || strpos($url, '/reportbuilder/') === 0) {
            return 'aw-report';
        }
        if ($PAGE->pagelayout === 'report') {
            return 'aw-report';
        }
        return 'aw-generic';
    }
}
