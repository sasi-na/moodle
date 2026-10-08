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

namespace local_cagrades\reportbuilder\local\entities;

use coding_exception;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\{boolean_select, number, text};
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\{column, filter};
use lang_string;
use local_cagrades\aiskills_scheme;
use local_cagrades\ca_items;
use stdClass;

/**
 * Continuous assessment entity.
 *
 * Exposes a course's continuous assessment grades as report columns. The entity owns no table of its
 * own; it joins the gradebook onto the course and user aliases handed to it by the datasource.
 *
 * Grade items are identified by NAME, because there is no site-wide convention for which quiz holds
 * which CA. The names in use are inconsistent - "Continous Assessment - 1", "Continuous
 * Assessment-01", "Continious Assessment-02", "Continuous Assessmnet 2", "CONTINUOUS ASSESSMENT 3"
 * and some 35 further variants - so the name is normalised in SQL (lower-cased, with everything that
 * is not a letter or digit stripped out) and then matched against "cont<anything>as<anything><number>".
 * That deliberately excludes "Self Assessment" items, which are practice quizzes carrying no CA marks.
 *
 * Two joins are used, for performance reasons that matter at this site's scale:
 *
 *  - the per-user grades come from a LATERAL join, correlated on both course and user. An
 *    uncorrelated pivot would have to aggregate every CA grade on the site (~665k rows, ~5s) on
 *    every single query no matter how tightly the report is filtered, whereas the correlated form
 *    costs a couple of indexed lookups per reported row.
 *  - the per-course information (maximum grades, quiz names) comes from a plain derived table over
 *    grade_items only, which is small enough (~14k rows, ~0.05s) not to need correlating.
 *
 * MAX() per CA number makes the values immune to the handful of courses that contain a duplicate CA
 * quiz - an unused, ungraded leftover sitting alongside the real one.
 *
 * @package    local_cagrades
 * @copyright  2026 JAIN Online
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ca_grade extends base {

    /** @var int How many numbered CAs to expose. Only CA 1-3 exist on this site. */
    protected const CA_COUNT = ca_items::CA_COUNT;

    /** @var string LIKE pattern selecting the faculty-wise Reflective Assessment courses. */
    protected const RA_COURSE_PATTERN = '00. Reflective Assessment%';

    /**
     * @var string|null The Reflective Assessment join, built once.
     *
     * It must be byte-identical every time it is handed to a column, because report builder
     * de-duplicates joins by exact string match. Rebuilding it per column would mint fresh inner
     * aliases each time (see database::generate_aliases()) while the outer alias stayed the same, so
     * every RA column would contribute a "different" join carrying the same alias and the query would
     * fail with "Not unique table/alias".
     */
    protected ?string $rajoin = null;

    /** @var string|null The per-course Reflective Assessment maximum join, built once. See above. */
    protected ?string $raitemsjoin = null;

    /**
     * Database tables that this entity uses.
     *
     * "course" and "user" are supplied by the datasource via {@see set_table_aliases()}; the others
     * name the joins this entity adds itself.
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return [
            'course',
            'user',
            'cagrades',
            'caitems',
            'ragrade',
            'raitems',
        ];
    }

    /**
     * The default title for this entity.
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entityca', 'local_cagrades');
    }

    /**
     * Initialise the entity.
     *
     * @return base
     */
    public function initialise(): base {
        $this->add_joins($this->get_gradebook_joins());

        foreach ($this->get_all_columns() as $column) {
            $this->add_column($column);
        }

        // Every filter is also offered as a condition.
        foreach ($this->get_all_filters() as $filter) {
            $this
                ->add_filter($filter)
                ->add_condition($filter);
        }

        return $this;
    }

    /**
     * SQL matching a CA grade item and pulling its CA number out of its name.
     *
     * Everything is inlined rather than bound, because report builder joins cannot carry parameters.
     * Note the patterns must not contain "?" - Moodle counts those as bind placeholders anywhere in
     * a statement - hence "as+e" rather than "ass?e" to tolerate both "assessment" and "asessment".
     *
     * @param string $itemalias
     * @return string[] [$normalisedname, $canumber, $where]
     */
    protected function get_item_matching_sql(string $itemalias): array {
        $normalised = ca_items::normalised_name_sql("{$itemalias}.itemname");
        $canumber = ca_items::number_sql($normalised);
        $where = ca_items::where_sql($itemalias);

        return [$normalised, $canumber, $where];
    }

    /**
     * The joins this entity needs on top of the course and user tables.
     *
     * @return string[]
     */
    protected function get_gradebook_joins(): array {
        global $DB;

        if ($DB->get_dbfamily() !== 'mysql') {
            throw new coding_exception('local_cagrades requires MySQL/MariaDB: it relies on LATERAL ' .
                'joins and the REGEXP_REPLACE/REGEXP_SUBSTR functions to match continuous assessment ' .
                'grade items by name.');
        }

        $course = $this->get_table_alias('course');
        $user = $this->get_table_alias('user');
        $cagrades = $this->get_table_alias('cagrades');
        $caitems = $this->get_table_alias('caitems');

        // Per-user grades, correlated on course and user.
        [$useritem, $usergrade, $userpivot] = database::generate_aliases(3);
        [, $canumber, $itemwhere] = $this->get_item_matching_sql($useritem);
        $grades = [];
        for ($canum = 1; $canum <= self::CA_COUNT; $canum++) {
            $grades[] = "MAX(CASE WHEN {$userpivot}.canum = {$canum} THEN {$usergrade}.finalgrade END) AS ca{$canum}";
        }

        $gradesjoin = "LEFT JOIN LATERAL (
            SELECT " . implode(",
                   ", $grades) . "
              FROM (SELECT {$useritem}.id AS id, {$canumber} AS canum
                      FROM {grade_items} {$useritem}
                     WHERE {$useritem}.courseid = {$course}.id
                       AND {$itemwhere}) {$userpivot}
              JOIN {grade_grades} {$usergrade}
                     ON {$usergrade}.itemid = {$userpivot}.id
                    AND {$usergrade}.userid = {$user}.id
        ) {$cagrades} ON TRUE";

        // Per-course information, read from grade_items alone.
        [$courseitem, $coursepivot] = database::generate_aliases(2);
        [, $itemcanumber, $itemswhere] = $this->get_item_matching_sql($courseitem);
        $maxes = [];
        for ($canum = 1; $canum <= self::CA_COUNT; $canum++) {
            $maxes[] = "MAX(CASE WHEN {$coursepivot}.canum = {$canum} THEN {$coursepivot}.grademax END) AS ca{$canum}max";
        }

        $itemsjoin = "LEFT JOIN (
            SELECT {$coursepivot}.courseid AS courseid,
                   COUNT(1) AS caitemcount,
                   " . $DB->sql_group_concat("{$coursepivot}.itemname", ', ', "{$coursepivot}.itemname") . " AS caitemnames,
                   " . implode(",
                   ", $maxes) . "
              FROM (SELECT {$courseitem}.courseid AS courseid, {$courseitem}.itemname AS itemname,
                           {$courseitem}.grademax AS grademax, {$itemcanumber} AS canum
                      FROM {grade_items} {$courseitem}
                     WHERE {$itemswhere}) {$coursepivot}
             GROUP BY {$coursepivot}.courseid
        ) {$caitems} ON {$caitems}.courseid = {$course}.id";

        return [$gradesjoin, $itemsjoin];
    }

    /**
     * The entity joins plus the Reflective Assessment one.
     *
     * The RA join is deliberately not part of {@see get_gradebook_joins()}: entity joins are applied
     * to every column, so putting it there would make every existing report pay for it. As a
     * column-level join it only runs when an RA column or filter is actually selected.
     *
     * @return string[]
     */
    protected function get_ra_joins(): array {
        if ($this->rajoin === null) {
            $this->rajoin = $this->build_ra_join($this->get_table_alias('ragrade'));
        }

        return array_merge($this->get_joins(), [$this->rajoin]);
    }

    /**
     * The entity joins plus the per-course Reflective Assessment maximum.
     *
     * What the Reflective Assessment is marked out of is a property of the marking scheme, not of the
     * student, so it must not come from the per-student join: that one needs a grade_grades row to
     * exist, and Moodle creates those lazily, so a student nobody has graded yet would report a
     * maximum of nothing and a four-component total "out of 90" rather than out of 115.
     *
     * @return string[]
     */
    protected function get_ra_items_joins(): array {
        if ($this->raitemsjoin === null) {
            $this->raitemsjoin = $this->build_ra_items_join($this->get_table_alias('raitems'));
        }

        return array_merge($this->get_joins(), [$this->raitemsjoin]);
    }

    /**
     * The per-course Reflective Assessment maximum, keyed on course category.
     *
     * Uncorrelated and tiny - 31 Reflective Assessment courses across 9 categories at the time of
     * writing, 0.002s - so it needs none of the care the per-student join does. Deliberately mirrors
     * how the CA maxima are read, for the same reason.
     *
     * @param string $raitems alias for the join
     * @return string
     */
    protected function build_ra_items_join(string $raitems): string {
        $course = $this->get_table_alias('course');

        [$racourse, $raitem] = database::generate_aliases(2);
        $normalised = ca_items::normalised_name_sql("{$raitem}.itemname");

        return "LEFT JOIN (
            SELECT {$racourse}.category AS category,
                   MAX({$raitem}.grademax) AS ramax
              FROM {course} {$racourse}
              JOIN {grade_items} {$raitem}
                     ON {$raitem}.courseid = {$racourse}.id
                    AND {$raitem}.itemtype = 'mod'
                    AND {$normalised} LIKE '%reflect%'
             WHERE {$racourse}.fullname LIKE '" . self::RA_COURSE_PATTERN . "'
          GROUP BY {$racourse}.category
        ) {$raitems} ON {$raitems}.category = {$course}.category";
    }

    /**
     * The Reflective Assessment join.
     *
     * The Reflective Assessment is not an activity inside the course being reported on: it is run as
     * a set of separate faculty-wise courses ("00. Reflective Assessment - <faculty>"), one learner
     * allocated to one faculty, sitting in the same course category as the taught courses. So for the
     * AI Skills for Future four-component total there is nothing for the gradebook to aggregate - the
     * components live in two different courses - and the total can only be assembled in a report.
     *
     * Correlated on the reported course's category and the reported user, and keyed on the user's
     * grade_grades row, which is what ties a learner to the one faculty course they were allocated
     * to. MAX() keeps the value single-valued for the handful of learners sitting in two of them.
     *
     * Call it through {@see get_ra_joins()} rather than directly - the result has to be built once and
     * reused, not rebuilt per column.
     *
     * @param string $ragrade alias for the join
     * @return string
     */
    protected function build_ra_join(string $ragrade): string {
        $course = $this->get_table_alias('course');
        $user = $this->get_table_alias('user');

        [$racourse, $raitem, $ragg] = database::generate_aliases(3);
        $normalised = ca_items::normalised_name_sql("{$raitem}.itemname");

        // No bound parameters: report builder joins cannot carry any, so the patterns are inlined.
        // They hold no "?" either, which Moodle would count as a placeholder wherever it appeared.
        return "LEFT JOIN LATERAL (
            SELECT MAX({$ragg}.finalgrade) AS ragrade,
                   MAX({$raitem}.grademax) AS ramax,
                   MAX({$racourse}.shortname) AS racourse,
                   COUNT(DISTINCT {$racourse}.id) AS racourses
              FROM {course} {$racourse}
              JOIN {grade_items} {$raitem}
                     ON {$raitem}.courseid = {$racourse}.id
                    AND {$raitem}.itemtype = 'mod'
                    AND {$normalised} LIKE '%reflect%'
              JOIN {grade_grades} {$ragg}
                     ON {$ragg}.itemid = {$raitem}.id
                    AND {$ragg}.userid = {$user}.id
             WHERE {$racourse}.category = {$course}.category
               AND {$racourse}.fullname LIKE '" . self::RA_COURSE_PATTERN . "'
        ) {$ragrade} ON TRUE";
    }

    /**
     * SQL for the sum of every CA grade, NULL when the user has none of them.
     *
     * Summing the pivoted columns rather than the underlying rows keeps duplicate CA quizzes from
     * being counted twice.
     *
     * @param string $field either "ca%dmax" (per course) or "ca%d" (per user)
     * @param string $alias
     * @return string
     */
    protected function get_total_sql(string $field, string $alias): string {
        $columns = [];
        $nulls = [];
        for ($canum = 1; $canum <= self::CA_COUNT; $canum++) {
            $column = $alias . '.' . sprintf($field, $canum);
            $columns[] = "COALESCE({$column}, 0)";
            $nulls[] = "{$column} IS NULL";
        }

        return 'CASE WHEN ' . implode(' AND ', $nulls) . ' THEN NULL ELSE ' .
            implode(' + ', $columns) . ' END';
    }

    /**
     * SQL for the sum of the best two CA grades, ungraded counting as zero.
     *
     * The sum of all three less the lowest of the three, which for three values is the same thing and
     * needs no sorting. NULL when the learner has no CA grade at all.
     *
     * @return string
     */
    protected function get_best_two_sql(): string {
        $cagrades = $this->get_table_alias('cagrades');

        $zeroed = [];
        $nulls = [];
        for ($canum = 1; $canum <= self::CA_COUNT; $canum++) {
            $zeroed[] = "COALESCE({$cagrades}.ca{$canum}, 0)";
            $nulls[] = "{$cagrades}.ca{$canum} IS NULL";
        }

        $sum = implode(' + ', $zeroed);
        $least = 'LEAST(' . implode(', ', $zeroed) . ')';

        return 'CASE WHEN ' . implode(' AND ', $nulls) . " THEN NULL ELSE ({$sum}) - {$least} END";
    }

    /**
     * Give a column every CA grade and maximum, for columns that do their arithmetic in PHP.
     *
     * Field aliases are namespaced per column by report builder ("c3_ca1"), so every column that
     * needs the raw CAs can ask for them under the same names without colliding.
     *
     * @param column $column
     * @return column the same column, for chaining
     */
    protected function add_ca_fields(column $column): column {
        $cagrades = $this->get_table_alias('cagrades');
        $caitems = $this->get_table_alias('caitems');

        for ($canum = 1; $canum <= self::CA_COUNT; $canum++) {
            $column
                ->add_field("{$cagrades}.ca{$canum}", 'ca' . $canum)
                ->add_field("{$caitems}.ca{$canum}max", 'ca' . $canum . 'max');
        }

        return $column;
    }

    /**
     * The CA grades of one report row, keyed by CA number.
     *
     * @param stdClass $row
     * @return array CA number => float|null
     */
    protected static function extract_grades(stdClass $row): array {
        $grades = [];
        for ($canum = 1; $canum <= self::CA_COUNT; $canum++) {
            $value = $row->{'ca' . $canum} ?? null;
            $grades[$canum] = $value === null ? null : (float) $value;
        }

        return $grades;
    }

    /**
     * The CA maxima of one report row, keyed by CA number.
     *
     * @param stdClass $row
     * @return array CA number => float
     */
    protected static function extract_maxima(stdClass $row): array {
        $maxima = [];
        for ($canum = 1; $canum <= self::CA_COUNT; $canum++) {
            $maxima[$canum] = (float) ($row->{'ca' . $canum . 'max'} ?? 0);
        }

        return $maxima;
    }

    /**
     * What a "best two of three" mark is out of, or null where the rule does not apply.
     *
     * The rule needs all three CAs, all marked out of the same maximum. Most courses grade all three
     * out of 30, a few out of 10 or 25, and a handful either mix maxima or are missing a CA
     * altogether. Those are exactly the courses `cli/apply_ca_category.php` refuses to build a grade
     * category for, so returning null here is what keeps this column and the learner's gradebook
     * saying the same thing - including saying nothing.
     *
     * @param float[] $maxima CA number => maximum
     * @return float|null
     */
    protected static function common_maximum(array $maxima): ?float {
        $present = array_values(array_filter($maxima, static fn(float $max): bool => $max > 0));
        if (count($present) !== self::CA_COUNT) {
            return null;
        }
        $distinct = array_unique(array_map(static fn(float $max): string => (string) round($max, 5), $present));

        return count($distinct) === 1 ? $present[0] : null;
    }

    /**
     * SQL counting how many CAs the user has been graded for.
     *
     * @return string
     */
    protected function get_graded_count_sql(): string {
        $cagrades = $this->get_table_alias('cagrades');

        $counts = [];
        for ($canum = 1; $canum <= self::CA_COUNT; $canum++) {
            $counts[] = "CASE WHEN {$cagrades}.ca{$canum} IS NULL THEN 0 ELSE 1 END";
        }

        return '(' . implode(' + ', $counts) . ')';
    }

    /**
     * Returns list of all available columns.
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        $cagrades = $this->get_table_alias('cagrades');
        $caitems = $this->get_table_alias('caitems');

        $columns = [];

        // CA 1 .. CA 3, and the maximum grade each is out of.
        for ($canum = 1; $canum <= self::CA_COUNT; $canum++) {
            $columns[] = (new column(
                'ca' . $canum,
                new lang_string('ca', 'local_cagrades', $canum),
                $this->get_entity_name()
            ))
                ->add_joins($this->get_joins())
                ->set_type(column::TYPE_FLOAT)
                ->add_field("{$cagrades}.ca{$canum}", 'ca' . $canum)
                ->set_is_sortable(true);

            $columns[] = (new column(
                'ca' . $canum . 'max',
                new lang_string('camax', 'local_cagrades', $canum),
                $this->get_entity_name()
            ))
                ->add_joins($this->get_joins())
                ->set_type(column::TYPE_FLOAT)
                ->add_field("{$caitems}.ca{$canum}max", 'ca' . $canum . 'max')
                ->set_is_sortable(true);
        }

        $columns[] = (new column(
            'catotal',
            new lang_string('catotal', 'local_cagrades'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_FLOAT)
            ->add_field($this->get_total_sql('ca%d', $cagrades), 'catotal')
            ->set_is_sortable(true);

        $columns[] = (new column(
            'catotalmax',
            new lang_string('catotalmax', 'local_cagrades'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_FLOAT)
            ->add_field($this->get_total_sql('ca%dmax', $caitems), 'catotalmax')
            ->set_is_sortable(true);

        // Percentage, worked out in PHP from the two totals so that it costs no extra SQL.
        $columns[] = (new column(
            'capercent',
            new lang_string('capercent', 'local_cagrades'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_FLOAT)
            ->add_field($this->get_total_sql('ca%d', $cagrades), 'percenttotal')
            ->add_field($this->get_total_sql('ca%dmax', $caitems), 'percentmax')
            ->set_is_sortable(false)
            // Returns a float rather than a formatted string so that downloads carry a real number.
            ->add_callback(static function(?float $value, stdClass $row): ?float {
                if ($row->percenttotal === null || empty($row->percentmax)) {
                    return null;
                }
                return round(100 * (float) $row->percenttotal / (float) $row->percentmax, 1);
            });

        // The best two of three, and the final CA mark that comes out of them. Both are worked out in
        // PHP by local_cagrades\ca_items, which is also what the rollout CLI uses to build and verify
        // the gradebook category, so the report and the learner's gradebook cannot disagree.
        $columns[] = $this->add_ca_fields((new column(
            'cabesttwo',
            new lang_string('cabesttwo', 'local_cagrades'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_FLOAT))
            ->set_is_sortable(false)
            ->add_callback(static function(?float $value, stdClass $row): ?float {
                $total = ca_items::best_two_total(self::extract_grades($row));
                return $total === null ? null : round($total, 2);
            });

        $columns[] = $this->add_ca_fields((new column(
            'cafinal',
            new lang_string('cafinal', 'local_cagrades'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_FLOAT))
            ->set_is_sortable(false)
            ->add_callback(static function(?float $value, stdClass $row): ?float {
                $maxima = self::extract_maxima($row);
                $outof = self::common_maximum($maxima);
                if ($outof === null) {
                    // A missing CA or mixed maxima: there is no defensible "out of" to average
                    // towards, so say nothing rather than print a number nobody can reproduce.
                    return null;
                }
                $final = ca_items::final_mark(self::extract_grades($row), $maxima, $outof);
                return $final === null ? null : round($final, 2);
            });

        $columns[] = (new column(
            'cagradedcount',
            new lang_string('cagradedcount', 'local_cagrades'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field($this->get_graded_count_sql(), 'cagradedcount')
            ->set_is_sortable(true);

        // Which CA quizzes the course holds - per course, not per user. Handy for spotting courses
        // whose quizzes are missing or misnamed, which is the failure mode of matching by name.
        $columns[] = (new column(
            'caitemcount',
            new lang_string('caitemcount', 'local_cagrades'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("COALESCE({$caitems}.caitemcount, 0)", 'caitemcount')
            ->set_is_sortable(true);

        $columns[] = (new column(
            'caitemnames',
            new lang_string('caitemnames', 'local_cagrades'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$caitems}.caitemnames", 'caitemnames')
            ->set_is_sortable(false);

        return array_merge($columns, $this->get_ra_columns());
    }

    /**
     * The Reflective Assessment columns, and the four-component total built on them.
     *
     * Optional columns carrying their own join, for the courses whose assessment runs partly in a
     * separate Reflective Assessment course - AI Skills for Future being the reason they exist. On any
     * other course they simply come back empty.
     *
     * @return column[]
     */
    protected function get_ra_columns(): array {
        $ragrade = $this->get_table_alias('ragrade');
        $raitems = $this->get_table_alias('raitems');

        $columns = [];

        $columns[] = (new column(
            'ragrade',
            new lang_string('ragrade', 'local_cagrades'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_ra_joins())
            ->set_type(column::TYPE_FLOAT)
            ->add_field("{$ragrade}.ragrade", 'ragrade')
            ->set_is_sortable(true);

        // Read per course, not per student: see get_ra_items_joins().
        $columns[] = (new column(
            'ramax',
            new lang_string('ramax', 'local_cagrades'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_ra_items_joins())
            ->set_type(column::TYPE_FLOAT)
            ->add_field("{$raitems}.ramax", 'ramax')
            ->set_is_sortable(true);

        // Which faculty course the mark came from, so an unexpected total can be traced.
        $columns[] = (new column(
            'racourse',
            new lang_string('racourse', 'local_cagrades'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_ra_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$ragrade}.racourse", 'racourse')
            ->add_field("{$ragrade}.racourses", 'racourses')
            ->set_is_sortable(true)
            // A learner allocated to two faculty courses would otherwise silently show one of them.
            ->add_callback(static function(?string $value, stdClass $row): string {
                if ($value === null) {
                    return '';
                }
                $count = (int) ($row->racourses ?? 1);
                return $count > 1
                    ? get_string('racoursemultiple', 'local_cagrades', ['course' => $value, 'count' => $count])
                    : $value;
            });

        // Each CA weighted down to its 25, for the AI Skills for Future scheme: the quizzes are sat
        // out of 30 but count for 25, so 27/30 contributes 22.5.
        for ($canum = 1; $canum <= self::CA_COUNT; $canum++) {
            $columns[] = $this->add_ca_fields((new column(
                'caweighted' . $canum,
                new lang_string('caweighted', 'local_cagrades', $canum),
                $this->get_entity_name()
            ))
                ->add_joins($this->get_joins())
                ->set_type(column::TYPE_FLOAT))
                ->set_is_sortable(false)
                // The extra argument arrives as the third parameter: report builder calls the
                // callback as ($value, $row, $arguments, null).
                ->add_callback(static function(?float $value, stdClass $row, int $canum): ?float {
                    $grades = self::extract_grades($row);
                    $maxima = self::extract_maxima($row);
                    $weighted = aiskills_scheme::weighted($grades[$canum] ?? null, $maxima[$canum] ?? null);
                    return $weighted === null ? null : round($weighted, 2);
                }, $canum);
        }

        // The four-component AI Skills for Future total, out of 100.
        $columns[] = $this->add_ca_fields((new column(
            'catotalwithra',
            new lang_string('catotalwithra', 'local_cagrades'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_ra_joins())
            ->set_type(column::TYPE_FLOAT))
            ->add_field("{$ragrade}.ragrade", 'ragrade')
            ->set_is_sortable(false)
            ->add_callback(static function(?float $value, stdClass $row): ?float {
                $total = aiskills_scheme::total(self::extract_grades($row), self::extract_maxima($row),
                    isset($row->ragrade) ? (float) $row->ragrade : null);
                return $total === null ? null : round($total, 2);
            });

        $columns[] = $this->add_ca_fields((new column(
            'catotalwithramax',
            new lang_string('catotalwithramax', 'local_cagrades'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_ra_items_joins())
            ->set_type(column::TYPE_FLOAT))
            ->add_field("{$raitems}.ramax", 'ramax')
            ->set_is_sortable(false)
            ->add_callback(static function(?float $value, stdClass $row): ?float {
                $max = aiskills_scheme::total_maximum(self::extract_maxima($row),
                    isset($row->ramax) ? (float) $row->ramax : null);
                return $max === null ? null : round($max, 2);
            });

        return $columns;
    }

    /**
     * Return list of all available filters.
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $cagrades = $this->get_table_alias('cagrades');
        $caitems = $this->get_table_alias('caitems');

        $filters = [];

        for ($canum = 1; $canum <= self::CA_COUNT; $canum++) {
            $filters[] = (new filter(
                number::class,
                'ca' . $canum,
                new lang_string('ca', 'local_cagrades', $canum),
                $this->get_entity_name(),
                "{$cagrades}.ca{$canum}"
            ))
                ->add_joins($this->get_joins());
        }

        $filters[] = (new filter(
            number::class,
            'catotal',
            new lang_string('catotal', 'local_cagrades'),
            $this->get_entity_name(),
            $this->get_total_sql('ca%d', $cagrades)
        ))
            ->add_joins($this->get_joins());

        // Best two of three, in raw marks: the whole total less the lowest CA, ungraded counting as
        // zero. There is deliberately no filter on the final mark itself - it would need a second,
        // SQL-side definition of the rule, and two definitions of one number eventually disagree.
        // Filtering on the best-two total ranks learners identically wherever the rule applies.
        $filters[] = (new filter(
            number::class,
            'cabesttwo',
            new lang_string('cabesttwo', 'local_cagrades'),
            $this->get_entity_name(),
            $this->get_best_two_sql()
        ))
            ->add_joins($this->get_joins());

        $filters[] = (new filter(
            number::class,
            'cagradedcount',
            new lang_string('cagradedcount', 'local_cagrades'),
            $this->get_entity_name(),
            $this->get_graded_count_sql()
        ))
            ->add_joins($this->get_joins());

        $filters[] = (new filter(
            number::class,
            'ragrade',
            new lang_string('ragrade', 'local_cagrades'),
            $this->get_entity_name(),
            "{$this->get_table_alias('ragrade')}.ragrade"
        ))
            ->add_joins($this->get_ra_joins());

        // Splits graded from ungraded learners without having to reason about individual CAs.
        $filters[] = (new filter(
            boolean_select::class,
            'cagraded',
            new lang_string('cagraded', 'local_cagrades'),
            $this->get_entity_name(),
            'CASE WHEN ' . $this->get_graded_count_sql() . ' > 0 THEN 1 ELSE 0 END'
        ))
            ->add_joins($this->get_joins());

        $filters[] = (new filter(
            number::class,
            'caitemcount',
            new lang_string('caitemcount', 'local_cagrades'),
            $this->get_entity_name(),
            "COALESCE({$caitems}.caitemcount, 0)"
        ))
            ->add_joins($this->get_joins());

        $filters[] = (new filter(
            text::class,
            'caitemnames',
            new lang_string('caitemnames', 'local_cagrades'),
            $this->get_entity_name(),
            "{$caitems}.caitemnames"
        ))
            ->add_joins($this->get_joins());

        return $filters;
    }
}
