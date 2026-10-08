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

use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Finding continuous assessment grade items, and the best-two-of-three rule.
 *
 * The single home for the two things the report source and the rollout CLI must agree on: which
 * grade items count as continuous assessments, and what the final CA mark is. If they disagreed, the
 * report would print one number and the gradebook would show learners another.
 *
 * @package    local_cagrades
 * @copyright  2026 JAIN Online
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ca_items {

    /** @var int How many numbered CAs exist. Only CA 1-3 are in use on this site. */
    public const CA_COUNT = 3;

    /** @var int How many of them count towards the final CA mark. */
    public const CA_KEEP = 2;

    /** @var string Name of the grade category holding the CAs. */
    public const CATEGORY_NAME = 'Continuous Assessment';

    /**
     * Regular expression matching a normalised CA item name.
     *
     * Matched against the name lower-cased with everything that is not a letter or digit stripped
     * out, because there is no site-wide convention: 39 distinct spellings are in use, from
     * "Continous Assessment - 1" to "Continuous Assessmnet 2" and "CONTINUOUS ASSESSMENT 3". It
     * deliberately excludes "Self Assessment" items, which are practice quizzes carrying no marks.
     *
     * The pattern must contain no "?" - Moodle counts one anywhere in a statement as a bind
     * placeholder, and report builder joins cannot carry bound parameters at all - hence "as+e"
     * rather than "ass?e" to tolerate both "assessment" and "asessment".
     *
     * @var string
     */
    public const NAME_PATTERN = '^cont[a-z]*as+e[a-z]*0*[0-9]+$';

    /**
     * SQL normalising a grade item name for matching: lower-cased, letters and digits only.
     *
     * @param string $field the itemname field to normalise, e.g. "gi.itemname"
     * @return string
     */
    public static function normalised_name_sql(string $field): string {
        return "REGEXP_REPLACE(LOWER({$field}), '[^a-z0-9]', '')";
    }

    /**
     * SQL pulling the CA number out of an already-normalised name.
     *
     * @param string $normalised output of {@see self::normalised_name_sql()}
     * @return string
     */
    public static function number_sql(string $normalised): string {
        return "CAST(REGEXP_SUBSTR({$normalised}, '[0-9]+$') AS UNSIGNED)";
    }

    /**
     * SQL restricting grade_items to continuous assessments.
     *
     * @param string $alias the grade_items alias
     * @return string
     */
    public static function where_sql(string $alias): string {
        $normalised = self::normalised_name_sql("{$alias}.itemname");

        return "{$alias}.itemtype IN ('mod', 'manual')
                AND {$normalised} REGEXP '" . self::NAME_PATTERN . "'";
    }

    /**
     * SQL restricting grade_items to continuous assessments AND the category total above them.
     *
     * {@see self::where_sql()} matches the numbered CAs by name. It cannot match the category total,
     * whose grade item carries no itemname at all - the name lives on the grade_categories row. That
     * total IS the CA mark out of 30, so anything hiding CA from a learner has to cover both or the
     * aggregate stays on screen after its components have gone: 597 such totals hold 215,132 graded
     * rows on this site.
     *
     * An EXISTS rather than a join, so callers do not each have to add one.
     *
     * @param string $alias the grade_items alias
     * @return string
     */
    public static function where_with_category_sql(string $alias): string {
        return "(" . self::where_sql($alias) . "
                OR ({$alias}.itemtype = 'category'
                    AND EXISTS (SELECT 1
                                  FROM {grade_categories} cagc
                                 WHERE cagc.id = {$alias}.iteminstance
                                   AND cagc.courseid = {$alias}.courseid
                                   AND cagc.fullname = '" . self::CATEGORY_NAME . "')))";
    }

    /**
     * The continuous assessment grade items of one course, keyed by CA number.
     *
     * Where a course holds a duplicate CA quiz - an unused, ungraded leftover sitting beside the real
     * one - both are returned under the same CA number, so callers can spot the duplication rather
     * than silently picking one. Use {@see self::has_duplicates()} to test for that.
     *
     * @param int $courseid
     * @return array[] CA number => stdClass[] grade item records, ascending by CA number
     */
    public static function for_course(int $courseid): array {
        global $DB;

        $normalised = self::normalised_name_sql('gi.itemname');
        $canumber = self::number_sql($normalised);

        $records = $DB->get_records_sql(
            "SELECT gi.id, gi.courseid, gi.categoryid, gi.itemtype, gi.itemmodule, gi.iteminstance,
                    gi.itemname, gi.grademax, gi.grademin, gi.gradetype, gi.hidden, gi.locked,
                    gi.sortorder, {$canumber} AS canum
               FROM {grade_items} gi
              WHERE gi.courseid = :courseid
                AND " . self::where_sql('gi') . "
           ORDER BY {$canumber}, gi.id",
            ['courseid' => $courseid]);

        $bynumber = [];
        foreach ($records as $record) {
            $canum = (int) $record->canum;
            if ($canum < 1 || $canum > self::CA_COUNT) {
                continue;
            }
            $bynumber[$canum][] = $record;
        }
        ksort($bynumber);

        return $bynumber;
    }

    /**
     * Whether any CA number in the given set is held by more than one grade item.
     *
     * @param array[] $bynumber output of {@see self::for_course()}
     * @return bool
     */
    public static function has_duplicates(array $bynumber): bool {
        foreach ($bynumber as $items) {
            if (count($items) > 1) {
                return true;
            }
        }
        return false;
    }

    /**
     * The distinct maximum grades across a set of CA items.
     *
     * A course whose three CAs are not all out of the same maximum cannot have a "best two divided
     * by two, out of 30" rule applied to it in any defensible way, so callers refuse those.
     *
     * @param array[] $bynumber output of {@see self::for_course()}
     * @return float[] distinct maxima, ascending
     */
    public static function distinct_maxima(array $bynumber): array {
        $maxima = [];
        foreach ($bynumber as $items) {
            foreach ($items as $item) {
                $maxima[(string) round((float) $item->grademax, 5)] = (float) $item->grademax;
            }
        }
        $values = array_values($maxima);
        sort($values, SORT_NUMERIC);

        return $values;
    }

    /**
     * The final CA mark: the best two of three, averaged, expressed out of $outof.
     *
     * Mirrors what the gradebook does for a "Mean of grades" category with "keep the highest 2" and
     * "aggregate only non-empty grades" off (see grade_category::apply_limit_rules() and
     * grade_category::aggregate_values_and_adjust_bounds()): each grade is first normalised to a
     * fraction of its own maximum, the two highest fractions are kept, their mean is taken, and the
     * result is scaled back up to the category maximum. With all three CAs out of the same maximum -
     * which is the only shape this rule is applied to - that is exactly (best two summed) / 2.
     *
     * An ungraded CA counts as zero, which is what makes the divisor always two.
     *
     * Assumes each CA's minimum grade is zero, as every CA item on this site is a quiz. The rollout
     * CLI refuses any course whose CA items say otherwise, so the assumption cannot go stale
     * silently.
     *
     * @param array $grades CA number => grade or null; missing keys count as ungraded
     * @param array $maxima CA number => maximum grade
     * @param float $outof what the result is out of
     * @return float|null null when the learner has no CA grade at all
     */
    public static function final_mark(array $grades, array $maxima, float $outof): ?float {
        $fractions = [];
        $anygraded = false;

        for ($canum = 1; $canum <= self::CA_COUNT; $canum++) {
            $max = (float) ($maxima[$canum] ?? 0);
            if ($max <= 0) {
                // No such CA in this course - it cannot contribute, not even as a zero.
                continue;
            }
            $grade = $grades[$canum] ?? null;
            if ($grade !== null) {
                $anygraded = true;
            }
            $fractions[] = ((float) $grade) / $max;
        }

        if (!$anygraded || !$fractions) {
            return null;
        }

        rsort($fractions, SORT_NUMERIC);
        $kept = array_slice($fractions, 0, self::CA_KEEP);

        return (array_sum($kept) / count($kept)) * $outof;
    }

    /**
     * The sum of the best two CA grades, in raw marks.
     *
     * Reported alongside the final mark so that the arithmetic behind it is visible: this is the
     * "30 + 28" of "(30 + 28) / 2 = 29".
     *
     * @param array $grades CA number => grade or null
     * @return float|null null when the learner has no CA grade at all
     */
    public static function best_two_total(array $grades): ?float {
        $values = [];
        $anygraded = false;
        for ($canum = 1; $canum <= self::CA_COUNT; $canum++) {
            $grade = $grades[$canum] ?? null;
            if ($grade !== null) {
                $anygraded = true;
            }
            $values[] = (float) $grade;
        }

        if (!$anygraded) {
            return null;
        }

        rsort($values, SORT_NUMERIC);

        return array_sum(array_slice($values, 0, self::CA_KEEP));
    }

    /**
     * The grade category holding a course's continuous assessments, if it has one.
     *
     * @param int $courseid
     * @return stdClass|false
     */
    public static function get_category(int $courseid) {
        global $DB;

        return $DB->get_record('grade_categories', [
            'courseid' => $courseid,
            'fullname' => self::CATEGORY_NAME,
        ]);
    }
}
