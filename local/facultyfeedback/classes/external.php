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

namespace local_facultyfeedback;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Web service backing the weekly-feedback pop-up.
 *
 * Returns the modal's rendered body rather than a data tree. The pop-up is
 * display-only and its trend chart needs SVG geometry computed from the data,
 * so rendering server-side keeps that arithmetic in one place
 * ({@see manager::chart()}) instead of duplicating it in JavaScript.
 *
 * The capability is re-checked here, independently of whether the course-header
 * field was rendered — a learner calling this directly gets an exception.
 *
 * @package   local_facultyfeedback
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external extends external_api {

    /**
     * Parameters for get_course_summary.
     *
     * @return external_function_parameters
     */
    public static function get_course_summary_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course to summarise'),
        ]);
    }

    /**
     * Return shape for get_course_summary.
     *
     * @return external_single_structure
     */
    public static function get_course_summary_returns(): external_single_structure {
        return new external_single_structure([
            'title'    => new external_value(PARAM_TEXT, 'Modal title'),
            'subtitle' => new external_value(PARAM_TEXT, 'Course name and short name'),
            'html'     => new external_value(PARAM_RAW, 'Rendered modal body'),
        ]);
    }

    /**
     * Build the pop-up body for one course.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_course_summary($courseid): array {
        global $DB, $OUTPUT;

        ['courseid' => $courseid] = self::validate_parameters(
            self::get_course_summary_parameters(),
            ['courseid' => $courseid]
        );

        $context = \context_course::instance($courseid);
        self::validate_context($context);
        require_capability('local/facultyfeedback:view', $context);

        if (!manager::is_enabled()) {
            throw new \moodle_exception('disabled', 'local_facultyfeedback');
        }

        $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname, shortname', MUST_EXIST);
        $summary = manager::modal_context($courseid);
        if ($summary === null) {
            throw new \moodle_exception('nofeedback', 'local_facultyfeedback');
        }

        // Hold the same line the course-header field does. Below the threshold
        // the field renders no button, so there is no legitimate caller — and a
        // response set this thin is close enough to identifying its respondents
        // that the API should not serve it either.
        if (empty($summary['hasenough'])) {
            throw new \moodle_exception('nofeedback', 'local_facultyfeedback');
        }

        // Deep-link to the latest weekly activity's own analysis page, but only
        // when this user may actually open it — the capability to see the
        // aggregate does not imply mod/feedback:viewreports.
        $cmid = self::latest_cmid($courseid, (int) $summary['latestfeedbackid']);
        $summary['haslatesturl'] = ($cmid > 0);
        $summary['latesturl'] = $cmid
            ? (new \moodle_url('/mod/feedback/analysis.php', ['id' => $cmid]))->out(false)
            : '';

        $coursename = format_string($course->fullname, true, ['context' => $context]);
        $shortname = format_string($course->shortname, true, ['context' => $context]);
        $subtitle = $coursename . ' · ' . $shortname;

        // The modal's own title bar carries the generic "Weekly feedback", so
        // the course it belongs to is named at the top of the body.
        $summary['subtitle'] = $subtitle;

        // No set_context() here: validate_context() above has already pointed
        // $PAGE at this course, and re-setting it is at best a no-op.
        return [
            'title'    => get_string('modaltitle', 'local_facultyfeedback'),
            'subtitle' => $subtitle,
            'html'     => $OUTPUT->render_from_template('local_facultyfeedback/summary', $summary),
        ];
    }

    /**
     * Course-module id for a feedback instance, so the pop-up can link to its
     * own analysis page.
     *
     * @param int $courseid
     * @param int $feedbackid
     * @return int 0 when the module is missing, hidden, or the user cannot read its reports
     */
    protected static function latest_cmid(int $courseid, int $feedbackid): int {
        if (!$feedbackid) {
            return 0;
        }
        try {
            $modinfo = get_fast_modinfo($courseid);
            foreach ($modinfo->get_instances_of('feedback') as $cm) {
                if ((int) $cm->instance !== $feedbackid) {
                    continue;
                }
                if (!$cm->uservisible) {
                    return 0;
                }
                // Don't offer a link into a page the user would be bounced from.
                if (!has_capability('mod/feedback:viewreports', \context_module::instance($cm->id))) {
                    return 0;
                }
                return (int) $cm->id;
            }
        } catch (\Throwable $e) {
            debugging('local_facultyfeedback: cmid lookup failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        return 0;
    }
}
