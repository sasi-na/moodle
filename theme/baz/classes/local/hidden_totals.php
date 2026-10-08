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
 * Course-total suppression for grades hidden from a single learner.
 *
 * @package   theme_baz
 * @copyright 2026 onlinejain.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_baz\local;

use grade_grade;
use grade_item;
use context_course;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/gradelib.php');

/**
 * Restates a course total over only the grades a learner can actually see.
 *
 * grade_grade::is_hidden() answers this for a single mark, but not for a total
 * computed from marks the learner cannot see: previous-semester CA leaves are
 * hidden per-learner by local_cagrades\past_ca while the course total that sums
 * them stays visible. Core's reports suppress that total via
 * grade_report::blank_hidden_total_and_adjust_bounds(), which is protected and
 * needs a report instance; this reproduces its decision on top of the same
 * public core primitive, grade_grade::get_hiding_affected().
 */
class hidden_totals {

    /** @var array<string, array> get_hiding_affected() results, keyed "userid:courseid". */
    protected static $affected = [];

    /**
     * Load the hiding picture for several courses at once.
     *
     * The grades overview renders one total per enrolled course, so resolving
     * this course by course would cost two queries per course. Two queries cover
     * the whole page; everything after them is pure PHP.
     *
     * @param int[] $courseids
     * @param int $userid
     */
    public static function preload(array $courseids, int $userid): void {
        global $DB;

        $courseids = array_values(array_unique(array_map('intval', $courseids)));
        $courseids = array_values(array_filter($courseids, function(int $courseid) use ($userid) {
            return !isset(self::$affected[$userid . ':' . $courseid]);
        }));
        if (!$courseids) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);

        $items = [];
        foreach ($DB->get_records_select('grade_items', "courseid $insql", $params) as $rec) {
            $items[(int) $rec->courseid][(int) $rec->id] = new grade_item($rec, false);
        }

        $grades = [];
        $sql = "SELECT g.*
                  FROM {grade_grades} g
                  JOIN {grade_items} gi ON gi.id = g.itemid
                 WHERE g.userid = :hiduserid AND gi.courseid $insql";
        foreach ($DB->get_records_sql($sql, $params + ['hiduserid' => $userid]) as $rec) {
            $grades[(int) $rec->itemid] = new grade_grade($rec, false);
        }

        foreach ($courseids as $courseid) {
            self::$affected[$userid . ':' . $courseid] =
                self::resolve($items[$courseid] ?? [], $grades, $userid);
        }
    }

    /**
     * Adjust a course total for the grades this learner cannot see.
     *
     * Mirrors what core's grade_report::blank_hidden_total_and_adjust_bounds()
     * does with GRADE_REPORT_SHOW_TOTAL_IF_CONTAINS_HIDDEN: report the total over
     * the marks that remain visible, rather than one summed from marks the learner
     * cannot see. When nothing visible is left - every previous-semester CA leaf is
     * hidden, so the remaining maximum is zero - there is no total to state and it
     * blanks instead.
     *
     * @param int $courseid
     * @param int $userid
     * @param grade_item $courseitem The course-total item.
     * @param float|null $finalgrade The stored total.
     * @return array [float|null grade, float grademax] - a null grade means "show nothing".
     */
    public static function adjust(int $courseid, int $userid, grade_item $courseitem, ?float $finalgrade): array {
        $unchanged = [$finalgrade, (float) $courseitem->grademax];

        $key = $userid . ':' . $courseid;
        if (!isset(self::$affected[$key])) {
            self::preload([$courseid], $userid);
        }
        $affected = self::$affected[$key] ?? null;
        if ($affected === null) {
            return $unchanged;
        }

        // Not sure whether hiding changed this total: say nothing rather than guess.
        if (array_key_exists($courseitem->id, $affected['unknowngrades'])) {
            return [null, (float) $courseitem->grademax];
        }
        if (!array_key_exists($courseitem->id, $affected['altered'])) {
            return $unchanged;
        }

        // Staff previewing a learner's grades see what core shows them: everything.
        if (has_capability('moodle/grade:viewhidden', context_course::instance($courseid), $userid)) {
            return $unchanged;
        }

        $grade = $affected['altered'][$courseitem->id];
        $grademax = (float) ($affected['alteredgrademax'][$courseitem->id] ?? $courseitem->grademax);
        if ($grade === null || $grademax <= 0) {
            return [null, $grademax];
        }
        return [(float) $grade, $grademax];
    }

    /**
     * Run core's hiding analysis over one course's items.
     *
     * @param grade_item[] $courseitems Keyed by item id.
     * @param grade_grade[] $grades This learner's grades across every preloaded course, keyed by item id.
     * @param int $userid
     * @return array|null Null when the course has no grade items. Only the keys
     *      adjust() reads are guaranteed present.
     */
    protected static function resolve(array $courseitems, array $grades, int $userid): ?array {
        if (!$courseitems) {
            return null;
        }

        // Most of a learner's courses hide nothing, and get_hiding_affected() is the
        // expensive part (it resolves every item's dependencies). A non-zero `hidden`
        // covers both states, the flag and a hidden-until timestamp, so this needs no
        // query of its own - the rows are already in hand.
        $hasanyhidden = false;
        foreach ($courseitems as $itemid => $item) {
            if (!empty($item->hidden) || !empty($grades[$itemid]->hidden)) {
                $hasanyhidden = true;
                break;
            }
        }
        if (!$hasanyhidden) {
            return ['altered' => [], 'unknowngrades' => [], 'alteredgrademax' => []];
        }

        // get_hiding_affected() requires one grade per item, so absent marks are filled in.
        $coursegrades = [];
        foreach ($courseitems as $itemid => $unused) {
            if (isset($grades[$itemid])) {
                $coursegrades[$itemid] = $grades[$itemid];
            } else {
                $gg = new grade_grade();
                $gg->userid = $userid;
                $gg->itemid = $itemid;
                $coursegrades[$itemid] = $gg;
            }
            $coursegrades[$itemid]->grade_item =& $courseitems[$itemid];
        }

        return grade_grade::get_hiding_affected($coursegrades, $courseitems);
    }
}
