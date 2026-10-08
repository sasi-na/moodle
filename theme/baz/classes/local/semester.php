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
 * Semester helpers for theme_baz.
 *
 * @package   theme_baz
 * @copyright 2026 onlinejain.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_baz\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Derives a student's "current semester" from their enrolled courses.
 *
 * A course's direct category name is its semester (e.g. "Semester - 03").
 * This is the server-side equivalent of theme/baz/amd/src/mycourses.js so the
 * dashboard, the My Courses header tiles, and the course cards all agree.
 */
class semester {

    /**
     * Pull the semester number out of a category name, e.g. "Semester - 03" => 3.
     *
     * @param string $categoryname
     * @return int|null Null when the name carries no recognisable semester.
     */
    public static function semester_of(string $categoryname): ?int {
        if (preg_match('/semester\D*(\d+)/i', $categoryname, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    /**
     * The highest semester number across a set of courses, or null if none carry one.
     *
     * @param array $courses Objects each exposing a ->semesternum (int|null) property.
     * @return int|null
     */
    public static function highest_semester(array $courses): ?int {
        $highest = null;
        foreach ($courses as $course) {
            if ($course->semesternum !== null && ($highest === null || $course->semesternum > $highest)) {
                $highest = $course->semesternum;
            }
        }
        return $highest;
    }

    /**
     * Reduce courses to just those in the highest semester the student has.
     * Falls back to the full list when no semester is detectable (so the
     * dashboard is never left empty).
     *
     * @param array $courses Objects each exposing a ->semesternum (int|null) property.
     * @return array Re-indexed list of the current-semester courses.
     */
    public static function current_semester_courses(array $courses): array {
        $highest = self::highest_semester($courses);
        if ($highest === null) {
            return array_values($courses);
        }
        return array_values(array_filter($courses, function($course) use ($highest) {
            return $course->semesternum === $highest;
        }));
    }
}
