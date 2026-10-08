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

namespace local_cagrades\reportbuilder\datasource;

use core_course\reportbuilder\local\entities\{course_category, enrolment};
use core_enrol\reportbuilder\local\entities\enrol;
use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\{course, user};
use core_reportbuilder\local\helpers\database;
use local_cagrades\reportbuilder\local\entities\ca_grade;

/**
 * Continuous assessment grades datasource.
 *
 * One row per student per course, carrying that student's CA grades. Courses, categories, enrolment
 * methods and users all come from the core entities, so course/category names and every user field
 * - including the custom profile fields such as Provisional Enrollment Number, USN and Cohort/Batch
 * - are available as columns and filters without any code here.
 *
 * The report is deliberately restricted to users holding a student-archetype role in the course:
 * that is what makes the numbers agree with the per-course gradebook export, which teachers and
 * other staff do not appear in.
 *
 * @package    local_cagrades
 * @copyright  2026 JAIN Online
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ca_grades extends datasource {

    /**
     * Return user friendly name of the datasource.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('sourcecagrades', 'local_cagrades');
    }

    /**
     * Initialise report.
     */
    protected function initialise(): void {
        $courseentity = new course();
        $course = $courseentity->get_table_alias('course');

        $this->set_main_table('course', $course);
        $this->add_entity($courseentity);

        // Exclude the site course, it holds no CA quizzes.
        $siteparam = database::generate_param_name();
        $this->add_base_condition_sql("{$course}.id != :{$siteparam}", [$siteparam => SITEID]);

        // Course category, so reports can be scoped to a programme or semester.
        $coursecatentity = new course_category();
        $categories = $coursecatentity->get_table_alias('course_categories');
        $this->add_entity($coursecatentity
            ->add_join("JOIN {course_categories} {$categories} ON {$categories}.id = {$course}.category"));

        // Enrolment method.
        $enrolentity = new enrol();
        $enrol = $enrolentity->get_table_alias('enrol');
        $this->add_entity($enrolentity
            ->add_join("JOIN {enrol} {$enrol} ON {$enrol}.courseid = {$course}.id AND {$enrol}.status = 0"));

        // User enrolments, limited to active enrolments held by a student of the course.
        $enrolmententity = (new enrolment())
            ->set_table_alias('enrol', $enrol);
        $userenrolment = $enrolmententity->get_table_alias('user_enrolments');
        $this->add_entity($enrolmententity
            ->add_joins($enrolentity->get_joins())
            ->add_join("JOIN {user_enrolments} {$userenrolment}
                            ON {$userenrolment}.enrolid = {$enrol}.id
                           AND {$userenrolment}.status = 0
                           AND " . $this->get_student_role_sql($course, "{$userenrolment}.userid")));

        // User.
        $userentity = new user();
        $user = $userentity->get_table_alias('user');
        $this->add_entity($userentity
            ->add_joins($enrolmententity->get_joins())
            ->add_join("JOIN {user} {$user} ON {$user}.id = {$userenrolment}.userid AND {$user}.deleted = 0"));

        // Continuous assessment grades, read off the course and user aliases above.
        $caentity = (new ca_grade())
            ->set_table_aliases([
                'course' => $course,
                'user' => $user,
            ]);
        $this->add_entity($caentity
            ->add_joins($userentity->get_joins()));

        $this->add_all_from_entities([
            $courseentity->get_entity_name(),
            $coursecatentity->get_entity_name(),
            $enrolentity->get_entity_name(),
            $enrolmententity->get_entity_name(),
            $userentity->get_entity_name(),
            $caentity->get_entity_name(),
        ]);
    }

    /**
     * SQL restricting a user id to holders of a student-archetype role in the given course.
     *
     * Entity joins cannot carry bound parameters, so the role ids and context level are cast to int
     * and inlined. Falling back to "1 = 0" rather than to no restriction keeps a site with no
     * student role from silently reporting on staff as well.
     *
     * @param string $coursealias
     * @param string $useridfield
     * @return string
     */
    protected function get_student_role_sql(string $coursealias, string $useridfield): string {
        $roleids = array_map('intval', array_keys(get_archetype_roles('student') ?: []));
        if (empty($roleids)) {
            return '1 = 0';
        }

        [$roleassignments, $context] = database::generate_aliases(2);
        $rolelist = implode(', ', $roleids);
        $contextcourse = (int) CONTEXT_COURSE;

        return "EXISTS (SELECT 1
                          FROM {role_assignments} {$roleassignments}
                          JOIN {context} {$context}
                               ON {$context}.id = {$roleassignments}.contextid
                              AND {$context}.contextlevel = {$contextcourse}
                              AND {$context}.instanceid = {$coursealias}.id
                         WHERE {$roleassignments}.userid = {$useridfield}
                           AND {$roleassignments}.roleid IN ({$rolelist}))";
    }

    /**
     * Return the columns that will be added to the report as part of default setup.
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        return [
            'course_category:name',
            'course:shortname',
            'course:fullname',
            'user:fullname',
            'user:email',
            'ca_grade:ca1',
            'ca_grade:ca2',
            'ca_grade:ca3',
            'ca_grade:cafinal',
        ];
    }

    /**
     * Return the filters that will be added to the report once it is created.
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        return [
            'course_category:name',
            'course:shortname',
            'user:fullname',
        ];
    }

    /**
     * Return the conditions that will be added to the report once it is created.
     *
     * @return string[]
     */
    public function get_default_conditions(): array {
        return [
            'course_category:name',
            'ca_grade:caitemcount',
        ];
    }

    /**
     * Return the default sorting that will be added to the report once it is created.
     *
     * @return array
     */
    public function get_default_column_sorting(): array {
        return [
            'course:fullname' => SORT_ASC,
            'user:fullname' => SORT_ASC,
        ];
    }
}
