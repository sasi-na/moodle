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
 * Who may see what.
 *
 * @package   local_batchvitals
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_batchvitals;

defined('MOODLE_INTERNAL') || die();

/**
 * Resolves a user's scope: the whole site, or a set of programme categories.
 */
class access {

    /** Site-wide capability: sees every configured batch. */
    const CAP_ALL = 'local/batchvitals:view';

    /** Category-scoped capability: sees one programme. */
    const CAP_PROGRAMME = 'local/batchvitals:viewprogramme';

    /** Shortname of the role that carries CAP_PROGRAMME. */
    const ROLE_SHORTNAME = 'programmestatsviewer';

    /** @var array Memoised has_any() results, keyed by user id. */
    protected static $hasany = [];

    /**
     * Whether the user sees every batch (site admins and managers).
     *
     * @param int|null $userid Defaults to the current user.
     * @return bool
     */
    public static function is_sitewide(?int $userid = null): bool {
        return has_capability(self::CAP_ALL, \context_system::instance(), $userid);
    }

    /**
     * Cheap "should the menu item appear at all" test for the navigation hook.
     *
     * This runs on every page load, so it must not walk the category tree. One
     * indexed query is enough: does the user hold any category-level role that
     * allows the programme capability? Memoised per request.
     *
     * @param int|null $userid Defaults to the current user.
     * @return bool
     */
    public static function has_any(?int $userid = null): bool {
        global $DB, $USER;

        $userid = $userid ?: (int) $USER->id;
        if (array_key_exists($userid, self::$hasany)) {
            return self::$hasany[$userid];
        }

        if (self::is_sitewide($userid)) {
            return self::$hasany[$userid] = true;
        }

        self::$hasany[$userid] = $DB->record_exists_sql(
            "SELECT 1
               FROM {role_assignments} ra
               JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :lvl
               JOIN {role_capabilities} rc ON rc.roleid = ra.roleid
              WHERE ra.userid = :userid
                AND rc.capability = :cap
                AND rc.permission = :allow",
            ['lvl' => CONTEXT_COURSECAT, 'userid' => $userid,
             'cap' => self::CAP_PROGRAMME, 'allow' => CAP_ALLOW]);

        return self::$hasany[$userid];
    }

    /**
     * Everyone currently holding the coordinator role, with their programme.
     *
     * Read at settings-page render time only, never on the admin tree build,
     * so this costs nothing on ordinary page loads.
     *
     * @return array Rows ordered by batch, then programme, then surname.
     */
    public static function coordinators(): array {
        global $DB;

        $roleid = $DB->get_field('role', 'id', ['shortname' => self::ROLE_SHORTNAME]);
        if (!$roleid) {
            return [];
        }
        return array_values($DB->get_records_sql(
            "SELECT ra.id,
                    u.id AS userid, u.firstname, u.lastname, u.email,
                    cc.id AS catid, cc.name AS catname,
                    pc.name AS batchname,
                    ctx.id AS ctxid, ra.timemodified
               FROM {role_assignments} ra
               JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :lvl
               JOIN {course_categories} cc ON cc.id = ctx.instanceid
               JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
          LEFT JOIN {course_categories} pc ON pc.id = cc.parent
              WHERE ra.roleid = :roleid
           ORDER BY pc.name, cc.name, u.lastname, u.firstname",
            ['lvl' => CONTEXT_COURSECAT, 'roleid' => $roleid]));
    }

    /**
     * The programme rows from the cache this user may actually see.
     *
     * Checked with has_capability() against each cached programme rather than by
     * reading role assignments directly, so an override or a grant on a parent
     * category (or at system level) is honoured.
     *
     * @param array $programmes Programme rows from vitals::load().
     * @param int|null $userid Defaults to the current user.
     * @return array Filtered rows, original order preserved.
     */
    public static function filter_programmes(array $programmes, ?int $userid = null): array {
        $out = [];
        foreach ($programmes as $p) {
            $ctx = \context_coursecat::instance((int) $p['id'], IGNORE_MISSING);
            if ($ctx && has_capability(self::CAP_PROGRAMME, $ctx, $userid)) {
                $out[] = $p;
            }
        }
        return $out;
    }
}
