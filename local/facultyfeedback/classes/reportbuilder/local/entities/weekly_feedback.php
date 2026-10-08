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

namespace local_facultyfeedback\reportbuilder\local\entities;

use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\{date, number, text};
use core_reportbuilder\local\helpers\{database, format};
use core_reportbuilder\local\report\{column, filter};
use lang_string;
use local_facultyfeedback\manager;

/**
 * Weekly faculty feedback entity.
 *
 * Exposes one weekly mod_feedback activity's rated responses as report columns: how many responses,
 * their average out of {@see manager::SCALE_MAX}, the 1-5 distribution, and the Net Promoter Score.
 * The entity owns no table of its own; it hangs its joins off the feedback and course aliases the
 * datasource hands it.
 *
 * Everything about *what counts* is taken from {@see manager} rather than restated here — the rated
 * question type, the scale, and the NPS buckets. If the report and the course-page pop-up ever
 * disagreed on a number, faculty would trust neither, so there is deliberately one definition.
 *
 * Two join strategies, for the same reasons documented in local_cagrades' ca_grade entity:
 *
 *  - The per-week figures come from a LATERAL join correlated on the feedback id. One row costs a
 *    couple of indexed lookups. An uncorrelated pivot would have to aggregate every rated response
 *    on the site on every query, however tightly the report is filtered.
 *  - The teachers list comes from a plain derived table over course contexts, which covers only the
 *    387 courses running weekly feedback and is small enough (~0.3s) not to need correlating.
 *    Correlating it per row would make a full 2,970-row export markedly slower.
 *
 * A week where nobody answered yields NULL for the average and the NPS rather than zero — 639 of the
 * site's 2,970 course-weeks are in exactly that state, and reporting them as 0.00 would be a lie.
 *
 * @package    local_facultyfeedback
 * @copyright  2026 JAIN Online
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class weekly_feedback extends base {

    /**
     * Database tables that this entity uses.
     *
     * "feedback" and "course" are supplied by the datasource via {@see set_table_aliases()}; the
     * other two name the joins this entity adds itself.
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return [
            'feedback',
            'course',
            'ffagg',
            'ffteachers',
        ];
    }

    /**
     * The default title for this entity.
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entityweeklyfeedback', 'local_facultyfeedback');
    }

    /**
     * Initialise the entity.
     *
     * @return base
     */
    public function initialise(): base {
        $this->add_joins($this->get_feedback_joins());

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
     * The joins this entity needs on top of the feedback and course tables.
     *
     * @return string[]
     */
    protected function get_feedback_joins(): array {
        global $DB;

        $feedback = $this->get_table_alias('feedback');
        $course = $this->get_table_alias('course');
        $agg = $this->get_table_alias('ffagg');
        $teachers = $this->get_table_alias('ffteachers');

        // Per-week figures, correlated on this feedback activity. Values of '0' mean the question was
        // left unanswered and are excluded, exactly as manager::build_summary() does.
        [$item, $value, $completed] = database::generate_aliases(3);
        $cast = $DB->sql_cast_char2real("{$value}.value", true);
        $counts = [];
        for ($rating = 1; $rating <= manager::SCALE_MAX; $rating++) {
            $counts[] = "SUM(CASE WHEN {$value}.value = '{$rating}' THEN 1 ELSE 0 END) AS n{$rating}";
        }

        $aggjoin = "LEFT JOIN LATERAL (
            SELECT COUNT({$value}.id) AS responses,
                   SUM({$cast}) AS ratingsum,
                   " . implode(",
                   ", $counts) . "
              FROM {feedback_item} {$item}
              JOIN {feedback_value} {$value}
                     ON {$value}.item = {$item}.id
                    AND {$value}.value <> '0'
              JOIN {feedback_completed} {$completed}
                     ON {$completed}.id = {$value}.completed
             WHERE {$item}.feedback = {$feedback}.id
               AND {$item}.typ = '" . manager::RATED_TYPE . "'
        ) {$agg} ON TRUE";

        // The course's teachers, as one derived table over course contexts.
        [$context, $assignment, $role, $user] = database::generate_aliases(4);
        $fullname = $DB->sql_concat("{$user}.firstname", "' '", "{$user}.lastname");
        $teachersjoin = "LEFT JOIN (
            SELECT {$context}.instanceid AS courseid,
                   " . $DB->sql_group_concat($fullname, ', ', "{$user}.lastname") . " AS teachers
              FROM {context} {$context}
              JOIN {role_assignments} {$assignment}
                     ON {$assignment}.contextid = {$context}.id
              JOIN {role} {$role}
                     ON {$role}.id = {$assignment}.roleid
                    AND {$role}.archetype IN ('editingteacher', 'teacher')
              JOIN {user} {$user}
                     ON {$user}.id = {$assignment}.userid
                    AND {$user}.deleted = 0
             WHERE {$context}.contextlevel = " . CONTEXT_COURSE . "
          GROUP BY {$context}.instanceid
        ) {$teachers} ON {$teachers}.courseid = {$course}.id";

        return [$aggjoin, $teachersjoin];
    }

    /**
     * SQL for the average rating, NULL when the week drew no responses.
     *
     * @return string
     */
    protected function get_average_sql(): string {
        $agg = $this->get_table_alias('ffagg');
        return "CASE WHEN {$agg}.responses > 0
                     THEN {$agg}.ratingsum / {$agg}.responses
                END";
    }

    /**
     * SQL for the Net Promoter Score as a whole number, NULL when the week drew no responses.
     *
     * Promoters minus detractors over all responses. The buckets come from {@see manager} so the
     * report cannot drift from the pop-up; ratings in neither bucket are passive and dilute the
     * score without moving it.
     *
     * @return string
     */
    protected function get_nps_sql(): string {
        $agg = $this->get_table_alias('ffagg');

        $promoters = [];
        foreach (manager::NPS_PROMOTERS as $rating) {
            $promoters[] = "{$agg}.n{$rating}";
        }
        $detractors = [];
        foreach (manager::NPS_DETRACTORS as $rating) {
            $detractors[] = "{$agg}.n{$rating}";
        }

        return "CASE WHEN {$agg}.responses > 0
                     THEN ROUND(((" . implode(' + ', $promoters) . ")
                                - (" . implode(' + ', $detractors) . "))
                                * 100.0 / {$agg}.responses)
                END";
    }

    /**
     * SQL for one rating's share of all responses, NULL when the week drew no responses.
     *
     * @param int $rating
     * @return string
     */
    protected function get_share_sql(int $rating): string {
        $agg = $this->get_table_alias('ffagg');
        return "CASE WHEN {$agg}.responses > 0
                     THEN {$agg}.n{$rating} * 100.0 / {$agg}.responses
                END";
    }

    /**
     * All columns this entity offers.
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        $feedback = $this->get_table_alias('feedback');
        $agg = $this->get_table_alias('ffagg');
        $teachers = $this->get_table_alias('ffteachers');

        $columns = [];

        // The week. timeopen is DSM's own declared week start and is set on every rated activity,
        // which is why the plugin keys weeks on it rather than on response dates.
        $columns[] = (new column(
            'weekcommencing',
            new lang_string('colweekcommencing', 'local_facultyfeedback'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$feedback}.timeopen")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate'], get_string('strftimedate', 'core_langconfig'));

        $columns[] = (new column(
            'weekcloses',
            new lang_string('colweekcloses', 'local_facultyfeedback'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$feedback}.timeclose")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate'], get_string('strftimedate', 'core_langconfig'));

        $columns[] = (new column(
            'activityname',
            new lang_string('colactivityname', 'local_facultyfeedback'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$feedback}.name")
            ->set_is_sortable(true);

        // Responses and the rating total. Both are exposed so that a correct course-level average is
        // obtainable as SUM(rating total) / SUM(responses). Averaging the per-week average column
        // instead gives the mean of the means, which on course 939 reads 3.98 rather than 3.99.
        $columns[] = (new column(
            'responses',
            new lang_string('colresponsecount', 'local_facultyfeedback'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("COALESCE({$agg}.responses, 0)", 'responses')
            ->set_is_sortable(true);

        $columns[] = (new column(
            'ratingtotal',
            new lang_string('colratingtotal', 'local_facultyfeedback'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_FLOAT)
            ->add_field("{$agg}.ratingsum", 'ratingtotal')
            ->set_is_sortable(true);

        $columns[] = (new column(
            'average',
            new lang_string('colaverageofscale', 'local_facultyfeedback', manager::SCALE_MAX),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_FLOAT)
            ->add_field($this->get_average_sql(), 'average')
            ->set_is_sortable(true)
            // A real number, not a formatted string, so downloads stay numeric.
            ->add_callback(static function(?float $value): ?float {
                return $value === null ? null : round($value, 2);
            });

        $columns[] = (new column(
            'nps',
            new lang_string('nps', 'local_facultyfeedback'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field($this->get_nps_sql(), 'nps')
            ->set_is_sortable(true);

        // Distribution: a count and a share of respondents per rating, highest first.
        for ($rating = manager::SCALE_MAX; $rating >= 1; $rating--) {
            $columns[] = (new column(
                'count' . $rating,
                new lang_string('colratingcount', 'local_facultyfeedback', $rating),
                $this->get_entity_name()
            ))
                ->add_joins($this->get_joins())
                ->set_type(column::TYPE_INTEGER)
                ->add_field("COALESCE({$agg}.n{$rating}, 0)", 'count' . $rating)
                ->set_is_sortable(true);

            $columns[] = (new column(
                'share' . $rating,
                new lang_string('colratingshare', 'local_facultyfeedback', $rating),
                $this->get_entity_name()
            ))
                ->add_joins($this->get_joins())
                ->set_type(column::TYPE_FLOAT)
                ->add_field($this->get_share_sql($rating), 'share' . $rating)
                ->set_is_sortable(true)
                ->add_callback(static function(?float $value): ?float {
                    return $value === null ? null : round($value, 1);
                });
        }

        // The course's teachers. Context, not attribution: every course here has 2-7 of them, so no
        // single person owns the score.
        $columns[] = (new column(
            'teachers',
            new lang_string('colteachers', 'local_facultyfeedback'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$teachers}.teachers", 'teachers')
            ->set_is_sortable(false);

        return $columns;
    }

    /**
     * All filters this entity offers. Each is also registered as a condition.
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $feedback = $this->get_table_alias('feedback');
        $agg = $this->get_table_alias('ffagg');
        $teachers = $this->get_table_alias('ffteachers');

        $filters = [];

        $filters[] = (new filter(
            date::class,
            'weekcommencing',
            new lang_string('colweekcommencing', 'local_facultyfeedback'),
            $this->get_entity_name(),
            "{$feedback}.timeopen"
        ))
            ->add_joins($this->get_joins());

        // Filtering on responses is the practical way to include or exclude the weeks that drew
        // nothing, and to skip statistically thin weeks.
        $filters[] = (new filter(
            number::class,
            'responses',
            new lang_string('colresponsecount', 'local_facultyfeedback'),
            $this->get_entity_name(),
            "COALESCE({$agg}.responses, 0)"
        ))
            ->add_joins($this->get_joins());

        $filters[] = (new filter(
            number::class,
            'average',
            new lang_string('colaverageofscale', 'local_facultyfeedback', manager::SCALE_MAX),
            $this->get_entity_name(),
            $this->get_average_sql()
        ))
            ->add_joins($this->get_joins());

        $filters[] = (new filter(
            number::class,
            'nps',
            new lang_string('nps', 'local_facultyfeedback'),
            $this->get_entity_name(),
            $this->get_nps_sql()
        ))
            ->add_joins($this->get_joins());

        $filters[] = (new filter(
            text::class,
            'activityname',
            new lang_string('colactivityname', 'local_facultyfeedback'),
            $this->get_entity_name(),
            "{$feedback}.name"
        ))
            ->add_joins($this->get_joins());

        $filters[] = (new filter(
            text::class,
            'teachers',
            new lang_string('colteachers', 'local_facultyfeedback'),
            $this->get_entity_name(),
            "{$teachers}.teachers"
        ))
            ->add_joins($this->get_joins());

        return $filters;
    }
}
