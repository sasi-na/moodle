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
 * Callbacks for local_facultyfeedback.
 *
 * @package   local_facultyfeedback
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Adds the weekly-feedback field to the course header for faculty.
 *
 * This callback fires on *every* page, so — exactly like
 * local_dashboardpoll_render_navbar_output — it bails out immediately unless we
 * are on a real course page and the current user is allowed to see the figures.
 * Only then is the AMD module queued, keeping it off every other page and off
 * every learner.
 *
 * Nothing is added to the navbar markup itself; the module places the field in
 * the course header where it belongs.
 *
 * @return string always ''
 */
function local_facultyfeedback_render_navbar_output() {
    global $PAGE;

    try {
        if (!isloggedin() || isguestuser()) {
            return '';
        }
        if (!\local_facultyfeedback\manager::is_enabled()) {
            return '';
        }
        // Course pages only: 'course-view-topics', 'course-view-weeks', etc.
        if (strpos((string) $PAGE->pagetype, 'course-view-') !== 0) {
            return '';
        }

        // Read the magic page property into a local first: empty()/isset() on
        // $PAGE->course reports "not set" even when the course is loaded.
        $course = $PAGE->course;
        if (empty($course) || empty($course->id)) {
            return '';
        }
        $courseid = (int) $course->id;
        if ($courseid === SITEID) {
            return '';
        }

        if (!\local_facultyfeedback\manager::can_view($courseid)) {
            return '';
        }

        $context = \local_facultyfeedback\manager::badge_context($courseid);
        if ($context === null) {
            // This course is not part of the weekly feedback programme.
            return '';
        }

        // The field's whole context is already computed, so it renders without a
        // further round trip; the pop-up fetches its own detail on click.
        $PAGE->requires->js_call_amd('local_facultyfeedback/badge', 'init', [$context]);
    } catch (\Throwable $e) {
        // Never break a course page over this.
        debugging('local_facultyfeedback: navbar callback failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }

    return '';
}
