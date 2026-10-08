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

namespace local_facultyfeedback\reportbuilder\datasource;

use core_course\reportbuilder\local\entities\course_category;
use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\course;
use core_reportbuilder\local\helpers\database;
use local_facultyfeedback\manager;
use local_facultyfeedback\reportbuilder\local\entities\weekly_feedback as weekly_feedback_entity;

/**
 * Weekly faculty feedback datasource.
 *
 * One row per course per weekly feedback: the same responses the course-page pop-up aggregates, but
 * across every course at once, so a programme or semester can be compared, exported and scheduled.
 * Courses and categories come from the core entities, so course name, short name, ID number,
 * visibility, dates and the full category tree are available as columns and filters for free.
 *
 * Scope is set by two base conditions: the site course is excluded, and a feedback activity only
 * appears if it carries a rated question. That second condition is the whole selector — the same
 * {@see manager::RATED_TYPE} the plugin uses — which is why the Student Satisfaction Survey, the
 * 360-degree feedback and the Apply for Change of Elective forms never show up: they hold no rated
 * questions at all.
 *
 * Deliberately aggregate-only. The finest grain is one weekly activity, never an individual
 * response, so nothing here can identify a respondent.
 *
 * Note on reading it: grouping by course and averaging the per-week average gives the mean of the
 * means, not the course's real figure. Sum the "Rating total" and "Responses" columns and divide.
 * Both columns carry that warning in their help text.
 *
 * @package    local_facultyfeedback
 * @copyright  2026 JAIN Online
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class weekly_feedback extends datasource {

    /**
     * Return user friendly name of the datasource.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('sourceweeklyfeedback', 'local_facultyfeedback');
    }

    /**
     * Initialise report.
     */
    protected function initialise(): void {
        $feedbackentity = new weekly_feedback_entity();
        $feedback = $feedbackentity->get_table_alias('feedback');

        // One row per weekly feedback activity.
        $this->set_main_table('feedback', $feedback);

        // Course, and the category it sits in.
        $courseentity = new course();
        $course = $courseentity->get_table_alias('course');
        $this->add_entity($courseentity
            ->add_join("JOIN {course} {$course} ON {$course}.id = {$feedback}.course"));

        $coursecatentity = new course_category();
        $categories = $coursecatentity->get_table_alias('course_categories');
        $this->add_entity($coursecatentity
            ->add_joins($courseentity->get_joins())
            ->add_join("JOIN {course_categories} {$categories} ON {$categories}.id = {$course}.category"));

        // The figures, read off the feedback and course aliases above.
        $feedbackentity->set_table_alias('course', $course);
        $this->add_entity($feedbackentity
            ->add_joins($courseentity->get_joins()));

        // The site course runs no weekly feedback.
        $siteparam = database::generate_param_name();
        $this->add_base_condition_sql("{$course}.id != :{$siteparam}", [$siteparam => SITEID]);

        // Only activities carrying a rated question. This is the selector; see the class comment.
        $typeparam = database::generate_param_name();
        $this->add_base_condition_sql(
            "EXISTS (SELECT 1
                       FROM {feedback_item} ffbaseitem
                      WHERE ffbaseitem.feedback = {$feedback}.id
                        AND ffbaseitem.typ = :{$typeparam})",
            [$typeparam => manager::RATED_TYPE]
        );

        $this->add_all_from_entities([
            $courseentity->get_entity_name(),
            $coursecatentity->get_entity_name(),
            $feedbackentity->get_entity_name(),
        ]);
    }

    /**
     * Columns shown by default in a new report.
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        return [
            'course_category:name',
            'course:shortname',
            'weekly_feedback:weekcommencing',
            'weekly_feedback:responses',
            'weekly_feedback:average',
            'weekly_feedback:nps',
        ];
    }

    /**
     * Filters offered by default in a new report.
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        return [
            'course_category:name',
            'course:shortname',
            'weekly_feedback:weekcommencing',
            'weekly_feedback:responses',
        ];
    }

    /**
     * Conditions offered by default in a new report.
     *
     * @return string[]
     */
    public function get_default_conditions(): array {
        return [
            'course_category:name',
            'weekly_feedback:weekcommencing',
            'weekly_feedback:responses',
        ];
    }

    /**
     * Default sorting: category, then course, then week in order.
     *
     * @return array
     */
    public function get_default_column_sorting(): array {
        return [
            'course_category:name' => SORT_ASC,
            'course:shortname' => SORT_ASC,
            'weekly_feedback:weekcommencing' => SORT_ASC,
        ];
    }
}
