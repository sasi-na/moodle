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
 * Who may sit the end-semester exam.
 *
 * @package   block_endsemesterexam
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_endsemesterexam\local;

defined('MOODLE_INTERNAL') || die();

/**
 * The single answer to "does this learner get the exam button".
 *
 * The block asks it to decide whether to render, redirect.php asks it to decide
 * whether to start an SSO handshake, and theme_baz's bespoke dashboard -- which
 * renders no blocks and so has to surface the button itself -- asks it too.
 * Keeping one implementation is the point: a gate that lives in three places
 * drifts, and the failure mode is a button that appears and then refuses.
 */
class audience {

    /**
     * The learner's exam identifier, or null when they may not sit the exam.
     *
     * An empty string is a valid "yes" -- the category gate authorises without
     * producing an identifier -- so callers MUST compare against null and not
     * test truthiness.
     *
     * @param int $userid
     * @return string|null
     */
    public static function identifier(int $userid): ?string {

        if (config::gatemode() === config::GATE_PROFILEFIELD) {
            $registerno = self::profile_field($userid, config::registerfield());
            return ($registerno === null || $registerno === '') ? null : $registerno;
        }

        return self::in_category($userid, config::category()) ? '' : null;
    }

    /**
     * Whether the learner may sit the exam.
     *
     * @param int $userid
     * @return bool
     */
    public static function allows(int $userid): bool {
        return self::identifier($userid) !== null;
    }

    /**
     * Value of a custom profile field, trimmed; null when unset.
     *
     * @param int $userid
     * @param string $shortname
     * @return string|null
     */
    public static function profile_field(int $userid, string $shortname): ?string {
        global $DB;

        $value = $DB->get_field_sql(
            "SELECT uid.data
               FROM {user_info_data} uid
               JOIN {user_info_field} uif ON uif.id = uid.fieldid
              WHERE uid.userid = :userid AND uif.shortname = :shortname",
            ['userid' => $userid, 'shortname' => $shortname]
        );

        return $value !== false ? trim((string) $value) : null;
    }

    /**
     * Whether the learner is enrolled in any course under a top-level category.
     *
     * Matches on the category path so sub-categories count, which is what the
     * batch-per-category structure this was written for relies on.
     *
     * @param int $userid
     * @param int $categoryid
     * @return bool
     */
    protected static function in_category(int $userid, int $categoryid): bool {
        global $DB;

        if ($categoryid <= 0) {
            return false;
        }

        return (bool) $DB->get_field_sql(
            "SELECT 1
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
               JOIN {course} c ON c.id = e.courseid
               JOIN {course_categories} cc ON cc.id = c.category
              WHERE ue.userid = :userid
                AND (cc.id = :catid OR " . $DB->sql_like('cc.path', ':path') . ")",
            [
                'userid' => $userid,
                'catid'  => $categoryid,
                'path'   => '%/' . $categoryid . '/%',
            ]
        );
    }
}
