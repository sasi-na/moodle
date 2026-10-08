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
 * The Virtual Labs marking scheme.
 *
 * Each assessment section of a lab course offers two questions and only the higher score counts. The
 * sections add up to 100, and a learner who lands between the moderation floor and the pass mark is
 * topped up to the pass mark:
 *
 *   CA 1 / 2 / 3    best of two 10-mark questions + best of two 15-mark questions   = 25 each
 *   Graded practice MCA/BCA: five 2-mark plus five 3-mark questions, all counted     = 25
 *                   Accounting: three sections of two questions (10, 10, 5), best of each = 25
 *   Final score     the four components summed, then floored to the pass mark        = 100
 *
 * The structure lives in the **course sections**, not in the activity names - every lab asks different
 * questions, so there is nothing in a question's name to key on. Section names in turn are
 * inconsistent ("Continuous Assessment - 1", "Continuous Assessments -1", "CONTINUOUS ASSESSMENT 1",
 * "Continous Assessment - 03", "DAA Lab Continuous Assessments -1"), so they are normalised the same
 * way {@see ca_items} normalises CA quiz names.
 *
 * A section's name only routes it; its **shape** - how many questions, out of what - is what decides.
 * Both must agree, or the course is refused rather than guessed at.
 *
 * @package    local_cagrades
 * @copyright  2026 JAIN Online
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lab_scheme {

    /** @var float What the whole course is marked out of. */
    public const TOTAL = 100.0;

    /** @var float What each component is worth. */
    public const COMPONENT = 25.0;

    /** @var float The pass mark, and the value the moderation floor lifts a learner to. */
    public const PASS_MARK = 40.0;

    /** @var float The lowest score the moderation floor will lift. */
    public const FLOOR_FROM = 35.0;

    /** @var int How many continuous assessments a lab course runs. */
    public const CA_COUNT = 3;

    /** @var string[] Grade-item ID numbers given to the component categories, for the total's formula. */
    public const IDNUMBERS = [
        'ca1' => 'vlca1',
        'ca2' => 'vlca2',
        'ca3' => 'vlca3',
        'practice' => 'vlpr',
    ];

    /**
     * Normalise a course section name for matching: lower-cased, letters and digits only.
     *
     * @param string $name
     * @return string
     */
    public static function normalise(string $name): string {
        return (string) preg_replace('/[^a-z0-9]/', '', \core_text::strtolower(trim($name)));
    }

    /**
     * What role a course section plays in the scheme.
     *
     * Returns one of:
     *   "ca:<n>"        a continuous assessment
     *   "practice"      the graded practice section (MCA/BCA labs)
     *   "practice:<n>"  the nth graded practice section (Accounting labs)
     *   "handson"       non-graded hands-on practice, excluded from the total
     *   "other"         anything else, including live-session and unnamed sections
     *
     * @param string $name the raw section name
     * @return string
     */
    public static function classify_section(string $name): string {
        $n = self::normalise($name);
        if ($n === '') {
            return 'other';
        }

        // Hands-on first: those sections are also called "practice", but carry no marks.
        if (strpos($n, 'handson') !== false) {
            return 'handson';
        }

        // "cont<anything>ass<anything><number>", tolerating "assessments", "assesment" and
        // "continous", and allowing a prefix such as "DAA Lab ...".
        if (preg_match('/cont[a-z]*as+e[a-z]*?0*([0-9]+)/', $n, $m)) {
            return 'ca:' . (int) $m[1];
        }

        // "Practice Questions (Section 2)" - the Accounting labs split practice across sections.
        // Checked before the plain practice case, which those names do not carry "graded" for.
        if ((strpos($n, 'practice') !== false || strpos($n, 'practical') !== false)
                && preg_match('/section0*([0-9]+)/', $n, $m)) {
            return 'practice:' . (int) $m[1];
        }

        // "Practice Questions (Graded)", "Practice Programs (Graded)", "PRACTICAL ASSESSMENT ( Graded)".
        if (strpos($n, 'practice') !== false || strpos($n, 'practical') !== false) {
            return 'practice';
        }

        return 'other';
    }

    /**
     * The graded activities of one course, grouped by course section.
     *
     * Only itemnumber 0 counts: anything else is an outcome or a second grade an activity type adds,
     * not the question's mark.
     *
     * @param int $courseid
     * @return array section id => stdClass{section, name, role, items[]}
     */
    public static function sections_for_course(int $courseid): array {
        global $DB;

        $sections = $DB->get_records('course_sections', ['course' => $courseid], 'section',
            'id, section, name');

        $out = [];
        foreach ($sections as $s) {
            $items = $DB->get_records_sql(
                "SELECT gi.id, gi.itemname, gi.itemmodule, gi.iteminstance, gi.grademax, gi.grademin,
                        gi.categoryid, gi.hidden, gi.sortorder
                   FROM {course_modules} cm
                   JOIN {modules} m ON m.id = cm.module
                   JOIN {grade_items} gi ON gi.courseid = cm.course
                        AND gi.itemmodule = m.name
                        AND gi.iteminstance = cm.instance
                        AND gi.itemnumber = 0
                  WHERE cm.course = :courseid
                    AND cm.section = :sectionid
                    AND cm.deletioninprogress = 0
               ORDER BY gi.sortorder, gi.id",
                ['courseid' => $courseid, 'sectionid' => $s->id]);

            if (!$items) {
                continue;
            }

            $record = new stdClass();
            $record->section = (int) $s->section;
            $record->name = (string) $s->name;
            $record->role = self::classify_section((string) $s->name);
            $record->items = array_values($items);
            $out[(int) $s->id] = $record;
        }

        return $out;
    }

    /**
     * Group a section's items by their maximum grade.
     *
     * @param stdClass[] $items
     * @return array maximum (as a string key) => stdClass[] items
     */
    public static function group_by_maximum(array $items): array {
        $groups = [];
        foreach ($items as $item) {
            $groups[self::maxkey((float) $item->grademax)][] = $item;
        }
        // Ascending by maximum, so a CA's 10-mark pair comes before its 15-mark pair.
        uksort($groups, static fn(string $a, string $b): int => (float) $a <=> (float) $b);

        return $groups;
    }

    /**
     * A stable array key for a grade maximum.
     *
     * @param float $max
     * @return string
     */
    public static function maxkey(float $max): string {
        return (string) round($max, 5);
    }

    /**
     * Work out what a course's gradebook should look like, or why it cannot be done.
     *
     * @param int $courseid
     * @return stdClass{components: array, excluded: array, problems: string[], flavour: string}
     */
    public static function analyse_course(int $courseid): stdClass {
        $result = new stdClass();
        $result->components = [];
        $result->excluded = [];
        $result->problems = [];
        $result->flavour = 'unknown';

        $sections = self::sections_for_course($courseid);
        $practicesections = [];

        foreach ($sections as $section) {
            $role = $section->role;

            if ($role === 'handson') {
                $result->excluded = array_merge($result->excluded, $section->items);
                continue;
            }

            if (str_starts_with($role, 'ca:')) {
                $canum = (int) substr($role, 3);
                if ($canum < 1 || $canum > self::CA_COUNT) {
                    $result->problems[] = "section \"{$section->name}\" looks like CA {$canum}, " .
                        'which is outside CA 1-' . self::CA_COUNT;
                    continue;
                }
                if (isset($result->components['ca' . $canum])) {
                    $result->problems[] = "more than one section claims to be CA {$canum}";
                    continue;
                }
                $pair = self::pair_of_pairs($section, "CA {$canum}");
                if (is_string($pair)) {
                    $result->problems[] = $pair;
                    continue;
                }
                $result->components['ca' . $canum] = $pair;
                continue;
            }

            if ($role === 'practice' || str_starts_with($role, 'practice:')) {
                $practicesections[] = $section;
                continue;
            }

            // Unnamed, live sessions, and the Accounting labs' demo question: graded but not part of
            // the scheme, so they must not affect the total.
            $result->excluded = array_merge($result->excluded, $section->items);
        }

        // Every CA has to be there.
        for ($canum = 1; $canum <= self::CA_COUNT; $canum++) {
            if (!isset($result->components['ca' . $canum])) {
                $result->problems[] = "no section found for CA {$canum}";
            }
        }

        $practice = self::analyse_practice($practicesections);
        if (is_string($practice)) {
            $result->problems[] = $practice;
        } else {
            $result->components['practice'] = $practice;
            $result->flavour = $practice->flavour;
        }

        return $result;
    }

    /**
     * Validate a CA section: two questions of one maximum, two of another, best of each.
     *
     * @param stdClass $section
     * @param string $label for the message
     * @return stdClass|string the component, or why it was refused
     */
    protected static function pair_of_pairs(stdClass $section, string $label) {
        $groups = self::group_by_maximum($section->items);

        if (count($groups) !== 2) {
            return "{$label} holds " . self::describe($section->items) .
                ' - expected two questions of one maximum and two of another';
        }
        foreach ($groups as $max => $items) {
            if (count($items) !== 2) {
                return "{$label} holds " . self::describe($section->items) .
                    " - expected exactly two questions out of {$max}";
            }
        }

        $maxima = array_map('floatval', array_keys($groups));
        if (abs(array_sum($maxima) - self::COMPONENT) > 0.00001) {
            return "{$label} is worth " . self::num(array_sum($maxima)) . ', not ' .
                self::num(self::COMPONENT) . ' (' . self::describe($section->items) . ')';
        }

        $component = new stdClass();
        $component->kind = 'bestof';
        $component->outof = self::COMPONENT;
        $component->sectionname = $section->name;
        $component->parts = [];
        $partnum = 1;
        foreach ($groups as $max => $items) {
            $part = new stdClass();
            $part->outof = (float) $max;
            $part->items = $items;
            $part->label = $partnum++;
            $component->parts[] = $part;
        }

        return $component;
    }

    /**
     * Validate the practice component, in either of its two shapes.
     *
     * @param stdClass[] $sections the sections classified as practice
     * @return stdClass|string the component, or why it was refused
     */
    protected static function analyse_practice(array $sections) {
        if (!$sections) {
            return 'no graded practice section found';
        }

        // Accounting labs: several practice sections, each a pair to take the best of.
        if (count($sections) > 1) {
            $component = new stdClass();
            $component->kind = 'bestof';
            $component->flavour = 'accounting';
            $component->outof = self::COMPONENT;
            $component->sectionname = 'Practice Questions';
            $component->parts = [];
            $total = 0.0;
            $partnum = 1;
            foreach ($sections as $section) {
                $groups = self::group_by_maximum($section->items);
                if (count($groups) !== 1 || count(reset($groups)) !== 2) {
                    return "practice section \"{$section->name}\" holds " .
                        self::describe($section->items) . ' - expected two questions of one maximum';
                }
                $max = (float) array_key_first($groups);
                $part = new stdClass();
                $part->outof = $max;
                $part->items = reset($groups);
                $part->label = $partnum++;
                $component->parts[] = $part;
                $total += $max;
            }
            if (abs($total - self::COMPONENT) > 0.00001) {
                return 'the practice sections are worth ' . self::num($total) . ' in total, not ' .
                    self::num(self::COMPONENT);
            }
            return $component;
        }

        // MCA/BCA labs: one section, every question counted.
        $section = reset($sections);
        $total = 0.0;
        foreach ($section->items as $item) {
            $total += (float) $item->grademax;
        }
        if (abs($total - self::COMPONENT) > 0.00001) {
            return "practice section \"{$section->name}\" is worth " . self::num($total) . ', not ' .
                self::num(self::COMPONENT) . ' (' . self::describe($section->items) . ')';
        }

        $component = new stdClass();
        $component->kind = 'sum';
        $component->flavour = 'programming';
        $component->outof = self::COMPONENT;
        $component->sectionname = $section->name;
        $component->items = $section->items;

        return $component;
    }

    /**
     * The best of a set of attempts, expressed out of $outof.
     *
     * Mirrors a "Highest grade" grade category with "aggregate only non-empty grades" off, which is
     * what the gradebook is configured to do: each grade is normalised against its own maximum, the
     * largest is taken, and the result is scaled to the category maximum. An unattempted question
     * counts as zero, so a learner who answered only one of the two still scores that one - which is
     * exactly the rule as written.
     *
     * @param array $grades grade item id => grade or null
     * @param array $maxima grade item id => maximum grade
     * @param float $outof
     * @return float|null null when nothing in the set was attempted
     */
    public static function best_of(array $grades, array $maxima, float $outof): ?float {
        $fractions = [];
        $anygraded = false;

        foreach ($maxima as $itemid => $max) {
            $max = (float) $max;
            if ($max <= 0) {
                continue;
            }
            $grade = $grades[$itemid] ?? null;
            if ($grade !== null) {
                $anygraded = true;
            }
            $fractions[] = ((float) $grade) / $max;
        }

        if (!$anygraded || !$fractions) {
            return null;
        }

        return max($fractions) * $outof;
    }

    /**
     * One component's mark: the best of each of its parts added up, or a plain sum of its questions.
     *
     * @param stdClass $component from {@see analyse_course()}
     * @param array $grades grade item id => grade or null
     * @return float|null null when the learner attempted none of the component's questions
     */
    public static function component_mark(stdClass $component, array $grades): ?float {
        if ($component->kind === 'sum') {
            $total = 0.0;
            $anygraded = false;
            foreach ($component->items as $item) {
                $grade = $grades[(int) $item->id] ?? null;
                if ($grade !== null) {
                    $anygraded = true;
                    $total += (float) $grade;
                }
            }
            return $anygraded ? $total : null;
        }

        $total = 0.0;
        $anygraded = false;
        foreach ($component->parts as $part) {
            $partgrades = [];
            $partmaxima = [];
            foreach ($part->items as $item) {
                $partmaxima[(int) $item->id] = (float) $item->grademax;
                $partgrades[(int) $item->id] = $grades[(int) $item->id] ?? null;
            }
            $best = self::best_of($partgrades, $partmaxima, (float) $part->outof);
            if ($best !== null) {
                $anygraded = true;
                $total += $best;
            }
        }

        return $anygraded ? $total : null;
    }

    /**
     * The moderation floor: a learner between FLOOR_FROM and PASS_MARK is lifted to PASS_MARK.
     *
     * Expressed as a top-up rather than a flat bonus, so a learner already at the pass mark is
     * untouched and nobody can be lifted past it by the floor alone.
     *
     * The score is **rounded to a whole mark first**, and the floor is judged on the rounded figure -
     * that rounded mark is the mark of record, so a total of 34.6 rounds to 35, which is in the band.
     * Faculty enter fractional marks (12.86, 6.67), so without this a learner's result would carry
     * meaningless decimals.
     *
     * @param float $score
     * @return float
     */
    public static function apply_floor(float $score): float {
        $rounded = round($score);
        if ($rounded >= self::FLOOR_FROM && $rounded < self::PASS_MARK) {
            return self::PASS_MARK;
        }
        return $rounded;
    }

    /**
     * A learner's final course score: the components summed, then floored.
     *
     * @param array $components component key => stdClass, from analyse_course()
     * @param array $grades grade item id => grade or null
     * @return float|null null when the learner has no mark anywhere in the scheme
     */
    public static function final_score(array $components, array $grades): ?float {
        $total = 0.0;
        $anygraded = false;
        foreach ($components as $component) {
            $mark = self::component_mark($component, $grades);
            if ($mark !== null) {
                $anygraded = true;
                $total += $mark;
            }
        }

        return $anygraded ? self::apply_floor($total) : null;
    }

    /**
     * The calculation formula for the course total: the components summed, rounded, then floored.
     *
     * Must stay in step with {@see apply_floor()} - the rounding happens first, so the floor is judged
     * on the whole mark. Moodle's formula engine supports if(), and(), sum(), round() and the
     * comparison operators (see lib/evalmath/evalmath.class.php) but has no variables, so the rounded
     * sum is spelled out three times.
     *
     * @return string
     */
    public static function total_formula(): string {
        $refs = [];
        foreach (self::IDNUMBERS as $idnumber) {
            $refs[] = '[[' . $idnumber . ']]';
        }
        $rounded = 'round(sum(' . implode(',', $refs) . '))';

        return '=if(and(' . $rounded . '>=' . self::num(self::FLOOR_FROM) . ',' .
            $rounded . '<' . self::num(self::PASS_MARK) . '),' .
            self::num(self::PASS_MARK) . ',' . $rounded . ')';
    }

    /**
     * A short description of a set of questions and what they are out of.
     *
     * @param stdClass[] $items
     * @return string
     */
    public static function describe(array $items): string {
        $groups = self::group_by_maximum($items);
        $parts = [];
        foreach ($groups as $max => $group) {
            $parts[] = count($group) . ' x /' . self::num((float) $max);
        }

        return implode(' + ', $parts);
    }

    /**
     * Trim trailing zeros off a number for printing.
     *
     * @param float $number
     * @return string
     */
    public static function num(float $number): string {
        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }
}
