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
 * Theme BAZ - My Courses page header stats.
 *
 * Fills the total / in progress / completed tiles of the My Courses greeting
 * header from the course data the myoverview block has already fetched
 * (event dispatched by blocks/myoverview view.js in accordion mode), so no
 * second webservice call or server-side completion pass is needed. All three
 * tiles come from the same dataset, so they can never disagree.
 *
 * The counts only reflect the student's current (highest) semester: each
 * course's direct category name is its semester (e.g. "Semester - 03"), so we
 * find the highest semester number across the enrolment and count only those
 * courses. If no course carries a recognisable semester we fall back to the
 * full enrolment so the tiles are never left empty.
 *
 * @module     theme_baz/mycourses
 * @copyright  2026 onlinejain.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    "use strict";

    var SELECTORS = {
        stats: '[data-region="mycourses-stats"]',
        total: '[data-region="mycourses-stat-total"]',
        inprogress: '[data-region="mycourses-stat-inprogress"]',
        completed: '[data-region="mycourses-stat-completed"]',
    };

    var HIDDEN_CLASS = 'rui-mycourses-stats--hidden';

    // How long to wait for usable course data before hiding the tiles
    // (e.g. the rendered event never fires because the accordion is off).
    var FALLBACK_MS = 6000;

    /**
     * Replace a skeleton tile's placeholder with the computed value.
     *
     * @param {Element} stats The stats container element.
     * @param {string} selector Tile value selector.
     * @param {number} value Count to display.
     */
    function fillTile(stats, selector, value) {
        var el = stats.querySelector(selector);
        if (el) {
            el.textContent = value;
            el.classList.remove('rui-skeleton');
        }
    }

    /**
     * Is this event's course list the user's full enrolment, so the derived
     * counts are valid page-level stats?
     *
     * @param {object} detail The event detail from blocks/myoverview view.js.
     * @return {boolean}
     */
    function coversAllCourses(detail) {
        return !detail.searching
            && (detail.grouping === 'all' || detail.grouping === 'allincludinghidden');
    }

    /**
     * Pull the semester number out of a course's category name, e.g.
     * "Semester - 03" -> 3. Returns null when the name carries no semester.
     *
     * @param {object} course A course from the rendered event.
     * @return {?number}
     */
    function semesterOf(course) {
        var match = (course.coursecategory || '').match(/semester\D*(\d+)/i);
        return match ? parseInt(match[1], 10) : null;
    }

    /**
     * Reduce the enrolment to just the courses in the highest semester the
     * student has. Falls back to the full list when no semester is found.
     *
     * @param {Array} courses All enrolled courses from the rendered event.
     * @return {Array}
     */
    function currentSemesterCourses(courses) {
        var highest = null;
        courses.forEach(function(course) {
            var sem = semesterOf(course);
            if (sem !== null && (highest === null || sem > highest)) {
                highest = sem;
            }
        });
        if (highest === null) {
            return courses;
        }
        return courses.filter(function(course) {
            return semesterOf(course) === highest;
        });
    }

    return {
        init: function() {
            var stats = document.querySelector(SELECTORS.stats);
            if (!stats) {
                return;
            }

            // The block never dispatches course data for site admins (the
            // semester accordion is disabled for them), so skip the skeleton
            // shimmer and hide the tiles straight away.
            if (document.body.classList.contains('is-siteadmin')) {
                stats.classList.add(HIDDEN_CLASS);
                return;
            }

            var fallbackTimer = window.setTimeout(function() {
                stats.classList.add(HIDDEN_CLASS);
            }, FALLBACK_MS);

            document.addEventListener('theme_baz/mycourses:rendered', function(e) {
                var detail = e.detail || {};
                if (detail.searching) {
                    // Transient subset; keep whatever the tiles show now.
                    return;
                }
                window.clearTimeout(fallbackTimer);
                if (!coversAllCourses(detail)) {
                    // A grouping like "In progress" covers a subset only —
                    // hide the tiles rather than show numbers that don't
                    // match their labels. A later switch back to "All"
                    // re-fires the event and unhides them.
                    stats.classList.add(HIDDEN_CLASS);
                    return;
                }
                var courses = currentSemesterCourses(detail.courses || []);
                var inprogress = 0;
                var completed = 0;
                courses.forEach(function(course) {
                    if (!course.hasprogress) {
                        return;
                    }
                    if (course.progress >= 100) {
                        completed++;
                    } else if (course.progress > 0) {
                        inprogress++;
                    }
                });
                fillTile(stats, SELECTORS.total, courses.length);
                fillTile(stats, SELECTORS.inprogress, inprogress);
                fillTile(stats, SELECTORS.completed, completed);
                stats.classList.remove(HIDDEN_CLASS);
            });
        }
    };
});
