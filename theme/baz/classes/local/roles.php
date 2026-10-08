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
 * Role helpers for theme_baz.
 *
 * @package   theme_baz
 * @copyright 2026 onlinejain.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_baz\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Role-based checks used to decide who sees the bespoke student dashboard.
 */
class roles {

    /**
     * Is the user a "student-only" user — i.e. every role they hold is a student
     * role (by the 'student' archetype or a known student shortname)?
     *
     * Mirrors the rule introduced in course/renderer.php (commit 9ccb669c9) so the
     * student dashboard and the course action-bar logic stay in sync. Admins,
     * guests, and anyone holding any teacher/manager/etc. role return false and
     * keep the existing dashboard.
     *
     * @param int $userid
     * @return bool
     */
    public static function is_student_only(int $userid): bool {
        global $DB;

        // Runs on every dashboard load; memoize per request like the studentid checks.
        static $cache = [];
        if (array_key_exists($userid, $cache)) {
            return $cache[$userid];
        }

        if (empty($userid) || isguestuser($userid) || is_siteadmin($userid)) {
            return $cache[$userid] = false;
        }

        // Roles treated as "student": the student archetype plus our custom
        // student role shortname(s).
        $studentshortnames = ['student'];

        $userroles = $DB->get_records_sql(
            "SELECT DISTINCT r.id, r.shortname, r.archetype
               FROM {role_assignments} ra
               JOIN {role} r ON r.id = ra.roleid
              WHERE ra.userid = :userid",
            ['userid' => $userid]
        );

        if (!$userroles) {
            return $cache[$userid] = false;
        }

        // Student-only when every role the user holds is a student role.
        foreach ($userroles as $role) {
            $isstudentrole = $role->archetype === 'student'
                || in_array($role->shortname, $studentshortnames, true);
            if (!$isstudentrole) {
                return $cache[$userid] = false;
            }
        }

        return $cache[$userid] = true;
    }

    /** @var array Role archetypes treated as "teaching staff" (matches shell faculty/ta). */
    const TEACHER_ARCHETYPES = ['editingteacher', 'teacher'];

    /**
     * Course ids where the user holds a teaching-staff role (course context).
     * Best-effort data helper for the teacher dashboard; returns [] on error.
     * Audience gating is decided by {@see shell::audience()}, not here.
     *
     * @param int $userid
     * @return int[] course ids
     */
    public static function teaching_courseids(int $userid): array {
        global $DB;
        try {
            [$insql, $params] = $DB->get_in_or_equal(self::TEACHER_ARCHETYPES, SQL_PARAMS_NAMED);
            $params['userid'] = $userid;
            $params['clevel'] = CONTEXT_COURSE;
            // Role assigned directly at a course context.
            $sql = "SELECT DISTINCT ctx.instanceid AS courseid
                      FROM {role_assignments} ra
                      JOIN {role} r ON r.id = ra.roleid
                      JOIN {context} ctx ON ctx.id = ra.contextid
                     WHERE ra.userid = :userid AND r.archetype $insql AND ctx.contextlevel = :clevel";
            return array_map('intval', array_keys($DB->get_records_sql($sql, $params)));
        } catch (\Throwable $e) {
            return [];
        }
    }
}
