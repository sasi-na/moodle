<?php
namespace local_exams;

defined('MOODLE_INTERNAL') || die();

/**
 * Gating + lightweight per-request helpers for the exam portal.
 *
 * The navigation hooks run on every page load for every logged-in user, so the
 * role/profile lookups here are memoized per request keyed by userid — the same
 * approach as local_studentid\manager::is_verifier / is_eligible_batch.
 *
 * @package   local_exams
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {

    /** Master switch: is the feature turned on at all? */
    public static function is_enabled(): bool {
        return (bool) get_config('local_exams', 'enabled');
    }

    /** Should portal links be injected into the navigation? */
    public static function shownav(): bool {
        return self::is_enabled() && (bool) get_config('local_exams', 'shownav');
    }

    /**
     * Whether this user is treated as faculty (cohort analytics view) rather
     * than a student. Memoized per request.
     *
     * Faculty = holds local/exams:viewanalytics (managers via archetype) OR is
     * assigned the configured faculty role at system level.
     */
    public static function is_faculty(int $userid): bool {
        global $DB, $USER;

        static $cache = [];
        if (array_key_exists($userid, $cache)) {
            return $cache[$userid];
        }

        // Site admins and system-level analytics holders (managers) see everything.
        if (is_siteadmin($userid)) {
            return $cache[$userid] = true;
        }
        $syscontext = \context_system::instance();
        if ($userid == $USER->id && has_capability('local/exams:viewanalytics', $syscontext)) {
            return $cache[$userid] = true;
        }

        // A configured dedicated faculty role assigned at system level.
        $rolename = trim((string) get_config('local_exams', 'facultyrole'));
        if ($rolename !== '') {
            $roleid = $DB->get_field('role', 'id', ['shortname' => $rolename]);
            if ($roleid && user_has_role_assignment($userid, $roleid, $syscontext->id)) {
                return $cache[$userid] = true;
            }
        }

        // Teachers: anyone who can view all grades in at least one course is
        // treated as faculty (their analytics are scoped to those courses).
        $courses = get_user_capability_course('moodle/grade:viewall', $userid, false, 'id');
        if (!empty($courses)) {
            return $cache[$userid] = true;
        }

        return $cache[$userid] = false;
    }

    /**
     * Course ids whose exam analytics this user may see.
     *
     * Returns null for users who may see ALL exams (site admins / system-level
     * analytics capability); otherwise the courses where they can view all
     * grades (teachers). An empty array means "no access".
     *
     * @param int $userid
     * @return int[]|null
     */
    public static function analytics_course_ids(int $userid): ?array {
        global $USER;

        static $cache = [];
        if (array_key_exists($userid, $cache)) {
            return $cache[$userid];
        }

        $syscontext = \context_system::instance();
        if (is_siteadmin($userid) ||
                ($userid == $USER->id && has_capability('local/exams:viewanalytics', $syscontext))) {
            return $cache[$userid] = null; // All courses.
        }

        $courses = get_user_capability_course('moodle/grade:viewall', $userid, false, 'id');
        $ids = $courses ? array_map(function($c) {
            return (int) $c->id;
        }, $courses) : [];
        return $cache[$userid] = $ids;
    }

    /**
     * Whether a student's exam results may be shown. Memoized per request.
     *
     * Currently driven by the site-wide `resultsreleased` flag; the batch-aware
     * release window can be layered in here later (subsuming the jan2025results
     * normalized-batch matching) without touching callers.
     */
    public static function results_visible(int $userid): bool {
        static $cache = [];
        if (array_key_exists($userid, $cache)) {
            return $cache[$userid];
        }
        return $cache[$userid] = (bool) get_config('local_exams', 'resultsreleased');
    }

    /**
     * Lightweight identity data for the portal header / PDF. Memoized per request.
     *
     * @return object {fullname, idnumber, email, batch, program}
     */
    public static function get_student_data(int $userid): object {
        global $DB, $CFG;

        static $cache = [];
        if (array_key_exists($userid, $cache)) {
            return $cache[$userid];
        }

        require_once($CFG->dirroot . '/user/profile/lib.php');

        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        profile_load_data($user);

        $data = (object) [
            'userid'   => (int) $user->id,
            'fullname' => fullname($user),
            'idnumber' => $user->idnumber,
            'email'    => $user->email,
            'batch'    => $user->profile_field_cohort_batch ?? '',
            'program'  => $user->profile_field_program ?? '',
        ];

        return $cache[$userid] = $data;
    }
}
