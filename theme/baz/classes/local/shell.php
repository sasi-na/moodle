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
 * Which shell a viewer gets, and what its sidebar contains.
 *
 * @package   theme_baz
 * @copyright 2026 onwards
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_baz\local;

defined('MOODLE_INTERNAL') || die();

/**
 * The bespoke shell was built for students. This resolves a viewer to an audience so
 * the same chrome can serve staff, with navigation coming from the audience rather
 * than being hardcoded.
 *
 * Students keep the exact path they already had: audience() defers to experience for
 * them, so nothing about their rendering changes. Staff audiences are inert until a
 * site turns on staff_shell.
 */
class shell {

    /** @var string Learner. */
    public const AUD_STUDENT = 'student';
    /** @var string Programme manager, who also owns the support queue. */
    public const AUD_PM = 'pm';
    /** @var string Teaching assistant. */
    public const AUD_TA = 'ta';
    /** @var string Mentor or faculty. */
    public const AUD_FACULTY = 'faculty';

    /**
     * The shell audience for a user, or null for the standard theme.
     *
     * @param int|null $userid
     * @return string|null
     */
    public static function audience(?int $userid = null): ?string {
        global $USER;
        $userid = $userid ?? (int) $USER->id;

        static $cache = [];
        if (array_key_exists($userid, $cache)) {
            return $cache[$userid];
        }

        if (empty($userid) || !isloggedin() || isguestuser()) {
            return $cache[$userid] = null;
        }

        // Site administrators are deliberately excluded: they keep the standard
        // administrative interface.
        if (is_siteadmin($userid)) {
            return $cache[$userid] = null;
        }

        if (roles::is_student_only($userid)) {
            return $cache[$userid] = self::AUD_STUDENT;
        }

        if (get_config('theme_baz', 'staff_shell') !== '1') {
            return $cache[$userid] = null;
        }

        return $cache[$userid] = self::staff_audience($userid);
    }

    /**
     * Map a staff member's roles to an audience.
     *
     * Resolved from archetypes so a site that renames or clones roles still works,
     * with an explicit shortname map for roles whose archetype does not describe them.
     *
     * @param int $userid
     * @return string|null
     */
    protected static function staff_audience(int $userid): ?string {
        global $DB;

        $overrides = self::shortname_map();
        $sql = "SELECT DISTINCT r.shortname, r.archetype
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE ra.userid = :userid";
        $roles = $DB->get_records_sql($sql, ['userid' => $userid]);

        $found = [];
        foreach ($roles as $role) {
            if (isset($overrides[$role->shortname])) {
                $found[] = $overrides[$role->shortname];
                continue;
            }
            switch ($role->archetype) {
                case 'manager':
                    $found[] = self::AUD_PM;
                    break;
                case 'editingteacher':
                    $found[] = self::AUD_FACULTY;
                    break;
                case 'teacher':
                    $found[] = self::AUD_TA;
                    break;
            }
        }

        if (!$found) {
            return null;
        }

        // Most privileged wins when someone holds several roles.
        foreach ([self::AUD_PM, self::AUD_FACULTY, self::AUD_TA] as $audience) {
            if (in_array($audience, $found, true)) {
                return $audience;
            }
        }
        return null;
    }

    /**
     * Role shortname to audience overrides, from the admin setting.
     *
     * @return array
     */
    protected static function shortname_map(): array {
        $raw = (string) get_config('theme_baz', 'staff_shell_roles');
        $map = [];
        foreach (preg_split('/[\r\n]+/', $raw) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '=') === false) {
                continue;
            }
            [$shortname, $audience] = array_map('trim', explode('=', $line, 2));
            if ($shortname !== '' && in_array($audience, [self::AUD_PM, self::AUD_TA, self::AUD_FACULTY], true)) {
                $map[$shortname] = $audience;
            }
        }
        return $map;
    }

    /**
     * Is the viewer on one of the staff shells?
     *
     * @param int|null $userid
     * @return bool
     */
    public static function is_staff(?int $userid = null): bool {
        $audience = self::audience($userid);
        return $audience !== null && $audience !== self::AUD_STUDENT;
    }

    /**
     * Sidebar items for a staff audience.
     *
     * Returns groups of links rather than a flat list so the sidebar can label
     * sections. Each item carries an icon key the template maps to a glyph.
     *
     * @param string $audience
     * @return array
     */
    public static function nav(string $audience): array {
        $url = function(string $path): string {
            return (new \moodle_url($path))->out(false);
        };
        $s = function(string $key): string {
            return get_string($key, 'theme_baz');
        };

        $groups = [];

        if ($audience === self::AUD_PM) {
            $items = [];
            // The queue is only reachable where the helpdesk plugin is on. It is not
            // on every tenant -- jain has it disabled, where this link returned a
            // 404. The Support group below has always been gated this way; the PM
            // group was not.
            if (self::helpdesk_available()) {
                $items[] = ['url' => $url('/local/helpdesk/queue.php'),
                    'label' => $s('shell_nav_queue'), 'icon' => 'inbox'];
            }
            // /user/index.php is the COURSE participants page and requires a course
            // id, so with no id it 404s on every tenant -- this link had never
            // worked. /admin/user.php is the site-wide equivalent and the PM role
            // holds moodle/user:update, which is what it requires.
            $items[] = ['url' => $url('/admin/user.php'),
                'label' => $s('shell_nav_learners'), 'icon' => 'people'];
            $items[] = ['url' => $url('/course/index.php'),
                'label' => $s('shell_nav_courses'), 'icon' => 'book'];
            $groups[] = ['label' => $s('shell_group_programme'), 'items' => $items];
        } else if ($audience === self::AUD_FACULTY) {
            $groups[] = ['label' => $s('shell_group_teaching'), 'items' => [
                ['url' => $url('/my/'),              'label' => $s('shell_nav_overview'), 'icon' => 'home'],
                ['url' => $url('/course/index.php'), 'label' => $s('shell_nav_mycourses'), 'icon' => 'book'],
                ['url' => $url('/grade/report/grader/index.php'), 'label' => $s('shell_nav_grading'), 'icon' => 'check'],
            ]];
        } else if ($audience === self::AUD_TA) {
            $groups[] = ['label' => $s('shell_group_teaching'), 'items' => [
                ['url' => $url('/my/'),              'label' => $s('shell_nav_overview'), 'icon' => 'home'],
                ['url' => $url('/course/index.php'), 'label' => $s('shell_nav_courses'), 'icon' => 'book'],
            ]];
        }

        if (self::helpdesk_available() && $audience !== self::AUD_PM) {
            $groups[] = ['label' => $s('shell_group_support'), 'items' => [
                ['url' => $url('/local/helpdesk/forwarded.php'), 'label' => $s('shell_nav_forwarded'), 'icon' => 'inbox'],
            ]];
        }

        foreach ($groups as &$group) {
            foreach ($group['items'] as &$item) {
                $item['iconpath'] = self::icon($item['icon']);
            }
        }
        return $groups;
    }

    /**
     * SVG path data for a nav glyph, matching the stroke style of the student sidebar.
     *
     * @param string $key
     * @return string
     */
    protected static function icon(string $key): string {
        $icons = [
            'home'   => '<path d="M3 10.5 12 3l9 7.5V21H3z"/>',
            'book'   => '<path d="M4 5h16v14H4z"/><path d="M9 5v14"/>',
            'inbox'  => '<path d="M3 13h5l2 3h4l2-3h5"/><path d="M5 5h14l2 8v6H3v-6z"/>',
            'people' => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M16 11a3 3 0 1 0 0-6"/>',
            'check'  => '<path d="M4 6h16M4 12h16M4 18h9"/><path d="M15.5 17.5l2 2 4-4"/>',
        ];
        return $icons[$key] ?? $icons['home'];
    }

    /**
     * The links that must appear, unchanged, on every bespoke surface.
     *
     * ONE source of truth, consumed by the admin pages shell AND by both /my/
     * dashboards. They each used to build their own list, so the left menu changed
     * shape when you moved between /my/ and an administration page -- the dashboard
     * offered eight items (Manage users, Courses & categories, Reports & logs,
     * Security & policies) that no other page had. Those four now live where they
     * belong, inside Site administration.
     *
     * Site administration appears only for site admins; for anyone else that page
     * is inaccessible and the link would 404.
     *
     * @param int|null $userid defaults to the current user
     * @return array
     */
    public static function home_nav(?int $userid = null): array {
        global $USER, $CFG, $PAGE;

        $userid = $userid ?? (int) $USER->id;

        // Core sets /my/index.php as the dashboard's url while the nav link is
        // /my/, so a raw string compare never matched and Dashboard was never
        // highlighted there. Normalise the directory-index form on both sides.
        $norm = static function(string $path): string {
            return preg_replace('#/index\.php$#', '/', $path);
        };
        $path = ($PAGE && $PAGE->url) ? $norm($PAGE->url->get_path()) : '';

        $items = [
            ['myhome', 'moodle', new \moodle_url('/my/'), 'dashboard'],
            ['mycourses', 'moodle', new \moodle_url('/my/courses.php'), 'courses'],
            ['calendar', 'calendar', new \moodle_url('/calendar/view.php', ['view' => 'month']), 'calendar'],
        ];
        if (is_siteadmin($userid)) {
            $items[] = ['administrationsite', 'moodle',
                new \moodle_url('/' . $CFG->admin . '/search.php'), 'siteadmin'];
        }

        $out = [];
        foreach ($items as [$key, $component, $url, $icon]) {
            $out[] = [
                'label' => get_string($key, $component),
                'url' => $url->out(false),
                'icon' => $icon,
                'icon_' . $icon => true,
                'active' => $path !== '' && $path === $norm($url->get_path()),
            ];
        }
        return $out;
    }

    /**
     * Is the helpdesk installed and switched on?
     *
     * @return bool
     */
    public static function helpdesk_available(): bool {
        return get_config('local_helpdesk', 'enabled') === '1';
    }
}
