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

declare(strict_types=1);

namespace local_cagrades;

defined('MOODLE_INTERNAL') || die();

/**
 * Hiding continuous assessment marks from learners once the semester has passed.
 *
 * Learners must see only their CURRENT semester's CA marks. Current means the highest semester
 * number the learner is actually enrolled in - there is no other signal available: not one of the
 * 1,066,896 user_enrolments carries a timeend, only 15 of 1,902 courses carry an enddate, and no
 * profile field holds a semester.
 *
 * The visibility is set per learner on {grade_grades}.hidden, not per course on
 * {grade_items}.hidden, because grade_items is course-wide and cannot express the rule: in one
 * Semester-3 course a learner whose current semester IS 3 must still see the CA, while a learner
 * repeating it from an earlier batch, now in Semester 4, must not. Same item, two answers.
 * grade_grades.hidden is also what core honours everywhere - the web reports, the mobile app's
 * gradereport_user_get_grade_items, and exports - which a theme-side filter would not be.
 *
 * Two rules bound what is touched:
 *
 *  - ONE-DIRECTIONAL. This only ever sets hidden = 1, never 0. A mark the teaching team has
 *    deliberately hidden stays hidden, and nothing here can reveal a mark that is not already
 *    visible.
 *  - THE CURRENT SEMESTER IS NEVER TOUCHED. Whatever is visible there stays visible and whatever is
 *    hidden stays hidden, so the existing per-item release the teaching team drives is untouched.
 *
 * A learner still working through an earlier semester's course keeps seeing it: a row is left alone
 * while it is ungraded, or while its CA quiz is still open. That is what stops a backlog re-attempt
 * from going dark mid-term.
 *
 * @package    local_cagrades
 * @copyright  2026 JAIN Online
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class past_ca {

    /** @var string The run name this tool books its one-writer lock under. */
    public const RUN_NAME = 'hide_past_ca';

    /** @var int How many grade_grades rows one UPDATE covers. */
    public const BATCH_SIZE = 5000;

    /**
     * Every semester category on the site, mapped to its semester number.
     *
     * Resolved in PHP through theme_baz's parser rather than in SQL. The two would have to agree
     * exactly - "Semester - 02 - BBA" must read as 2, not 22 - and keeping one regex in one place is
     * the only way to be sure of that. There are ~133 such categories, so the map is small enough to
     * inline into a CASE expression.
     *
     * @return int[] category id => semester number
     */
    public static function semester_map(): array {
        global $DB;

        if (!class_exists(\theme_baz\local\semester::class)) {
            throw new \coding_exception(
                'local_cagrades\past_ca needs theme_baz\local\semester, the site\'s single source of '
                . 'semester truth. Install theme_baz or give this plugin its own parser.');
        }

        $map = [];
        $categories = $DB->get_records_select('course_categories',
            $DB->sql_like('name', ':name', false), ['name' => '%semester%'], '', 'id, name');
        foreach ($categories as $category) {
            $semester = \theme_baz\local\semester::semester_of($category->name);
            if ($semester !== null) {
                $map[(int) $category->id] = $semester;
            }
        }

        return $map;
    }

    /**
     * A CASE expression giving the semester number of the category a course sits in.
     *
     * @param int[] $map output of {@see self::semester_map()}
     * @param string $field the course's category field, e.g. "c.category"
     * @return string SQL, or "NULL" when there are no semester categories at all
     */
    protected static function semester_case(array $map, string $field): string {
        if (!$map) {
            return 'NULL';
        }

        $whens = '';
        foreach ($map as $categoryid => $semester) {
            $whens .= " WHEN {$categoryid} THEN {$semester}";
        }

        return "(CASE {$field}{$whens} ELSE NULL END)";
    }

    /**
     * The grade_grades rows that should be hidden but are not.
     *
     * Selection, in one statement rather than per learner, because there are ~38,700 learners and
     * ~600,000 CA grades:
     *
     *  - the row is a graded CA (an ungraded one has no mark to hide, and is still in progress)
     *  - it is not hidden already, so a re-run is cheap and the one-directional rule holds
     *  - its course sits in a semester category whose number is BELOW the highest semester number
     *    that learner is enrolled in
     *  - no CA quiz anywhere in that course is still open
     *
     * The "graded" test is per ROW and the "still open" test is per COURSE, deliberately. Under
     * best-two-of-three a learner routinely skips one CA, so 648 of the 706 courses holding CA items
     * have an ungraded CA row somewhere - treating "any ungraded CA" as "the whole course is still in
     * progress" would exempt almost everything and make this a no-op. Per row, an ungraded CA simply
     * has no mark to hide. An open quiz, on the other hand, IS a course-wide fact: it means the
     * learner can still sit that assessment, and it must exempt the category total as well as the
     * leaves, or the total would vanish while its components were still on screen.
     *
     * A learner with no parseable semester anywhere gets maxsem = NULL, the comparison is never
     * true, and nothing of theirs is hidden - the same fail-open theme_baz uses when it cannot read
     * a semester.
     *
     * @param int|null $userid restrict to one learner, for spot checks
     * @return array [$sql, $params] selecting gg.id
     */
    public static function candidate_sql(?int $userid = null): array {
        $map = self::semester_map();
        $coursesem = self::semester_case($map, 'c.category');
        $enrolsem = self::semester_case($map, 'ec.category');

        // Both the numbered CAs and the "Continuous Assessment" category total above them: that
        // total is the CA mark out of 30, so hiding only the leaves would leave it on screen.
        $cawhere = ca_items::where_with_category_sql('gi');
        $caleaf = ca_items::where_sql('gi2');

        $params = ['now' => time()];

        // Only active enrolments count towards "where the learner is now", matching the
        // enrol_get_users_courses($userid, true) the theme uses to decide the same thing.
        $maxsem = "SELECT ue.userid, MAX({$enrolsem}) AS maxsem
                     FROM {user_enrolments} ue
                     JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
                     JOIN {course} ec ON ec.id = e.courseid
                    WHERE ue.status = 0
                 GROUP BY ue.userid";

        // A CA quiz in this course that the learner could still sit. Only quizzes have a close time;
        // a 'manual' CA has nothing that can be open, so a course holding only those never matches.
        $stillopen = "SELECT 1
                        FROM {grade_items} gi2
                        JOIN {quiz} q2 ON q2.id = gi2.iteminstance AND gi2.itemmodule = 'quiz'
                       WHERE gi2.courseid = gi.courseid
                         AND {$caleaf}
                         AND (q2.timeclose = 0 OR q2.timeclose > :now)";

        $useronly = '';
        if ($userid !== null) {
            $useronly = ' AND gg.userid = :userid';
            $params['userid'] = $userid;
        }

        $sql = "SELECT gg.id
                  FROM {grade_grades} gg
                  JOIN {grade_items} gi ON gi.id = gg.itemid AND {$cawhere}
                  JOIN {course} c ON c.id = gi.courseid
                  JOIN ({$maxsem}) m ON m.userid = gg.userid
                 WHERE gg.hidden = 0
                   AND gg.finalgrade IS NOT NULL
                   AND {$coursesem} IS NOT NULL
                   AND m.maxsem IS NOT NULL
                   AND {$coursesem} < m.maxsem
                   AND NOT EXISTS ({$stillopen})
                       {$useronly}";

        return [$sql, $params];
    }

    /**
     * How many rows the rule would hide right now.
     *
     * @param int|null $userid restrict to one learner
     * @return int
     */
    public static function count_candidates(?int $userid = null): int {
        global $DB;

        [$sql, $params] = self::candidate_sql($userid);

        return (int) $DB->count_records_sql("SELECT COUNT(*) FROM ({$sql}) candidates", $params);
    }

    /**
     * One batch of the rows the rule would hide, oldest id first.
     *
     * @param int $limit
     * @param int|null $userid restrict to one learner
     * @return int[] grade_grades ids
     */
    public static function candidate_ids(int $limit, ?int $userid = null): array {
        global $DB;

        [$sql, $params] = self::candidate_sql($userid);

        return array_map('intval',
            $DB->get_fieldset_sql("SELECT id FROM ({$sql}) candidates ORDER BY id", $params, 0, $limit));
    }

    /**
     * Hide the given grade_grades rows.
     *
     * Written as a direct UPDATE rather than through grade_grade::set_hidden(). The first run has
     * ~265,000 rows to cover and set_hidden() loads, updates and re-saves one record at a time; at
     * that volume it does not finish in a maintenance window. The column carries no derived state -
     * nothing recalculates from it, it is read straight back by the reports - so setting it directly
     * is equivalent to what set_hidden() would leave behind for a plain hide.
     *
     * The WHERE deliberately repeats "hidden = 0". Two overlapping runs, or a staff member hiding an
     * item between the select and the update, then cannot turn into a double write, and the returned
     * count stays truthful.
     *
     * @param int[] $ids
     * @return int how many rows changed
     */
    public static function hide(array $ids): int {
        global $DB;

        if (!$ids) {
            return 0;
        }

        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'gg');
        $before = $DB->count_records_select('grade_grades', "id {$insql} AND hidden = 0", $params);
        $DB->execute("UPDATE {grade_grades} SET hidden = 1 WHERE id {$insql} AND hidden = 0", $params);

        return $before;
    }

    /**
     * A learner's graded CA marks per semester, and which of them are hidden.
     *
     * For verifying a run against a named learner: the current semester's counts must be identical
     * before and after, and the semesters below it must end up fully hidden.
     *
     * @param int $userid
     * @return array[] semester number => ['graded' => int, 'hidden' => int], plus 'maxsem'
     */
    public static function learner_summary(int $userid): array {
        global $DB;

        $map = self::semester_map();
        $coursesem = self::semester_case($map, 'c.category');

        // The same predicate the sweep uses, or the verification would count a different population
        // from the one that was changed.
        $cawhere = ca_items::where_with_category_sql('gi');

        $rows = $DB->get_records_sql(
            "SELECT {$coursesem} AS semnum,
                    COUNT(*) AS graded,
                    SUM(CASE WHEN gg.hidden <> 0 THEN 1 ELSE 0 END) AS hidden
               FROM {grade_grades} gg
               JOIN {grade_items} gi ON gi.id = gg.itemid AND {$cawhere}
               JOIN {course} c ON c.id = gi.courseid
              WHERE gg.userid = :userid
                AND gg.finalgrade IS NOT NULL
                AND {$coursesem} IS NOT NULL
           GROUP BY semnum
           ORDER BY semnum", ['userid' => $userid]);

        $summary = [];
        foreach ($rows as $row) {
            $summary[(int) $row->semnum] = [
                'graded' => (int) $row->graded,
                'hidden' => (int) $row->hidden,
            ];
        }

        return $summary;
    }

    /**
     * The highest semester number a learner is enrolled in, by the same rule the sweep uses.
     *
     * @param int $userid
     * @return int|null null when none of the learner's categories parse as a semester
     */
    public static function max_semester(int $userid): ?int {
        global $DB;

        $map = self::semester_map();
        $enrolsem = self::semester_case($map, 'ec.category');

        $max = $DB->get_field_sql(
            "SELECT MAX({$enrolsem})
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
               JOIN {course} ec ON ec.id = e.courseid
              WHERE ue.userid = :userid AND ue.status = 0", ['userid' => $userid]);

        return $max === null || $max === false ? null : (int) $max;
    }
}
