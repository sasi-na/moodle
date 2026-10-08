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

namespace local_reflectiveassessment;

defined('MOODLE_INTERNAL') || die();

/**
 * Evaluator rollup: one row per person who marked, across the period's guide
 * courses. Distinct from the guide of record, which is not always the marker.
 *
 * @package   local_reflectiveassessment
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class evaluators {

    /**
     * Build the evaluator rollup for one batch/semester period.
     *
     * @param string $periodkey key from {@see summary::get_periods()}
     * @return \stdClass {period, rows, totals, generated}
     */
    public static function build(string $periodkey): \stdClass {
        $periods = summary::get_periods();
        if (!isset($periods[$periodkey])) {
            throw new \moodle_exception('invalidperiod', 'local_reflectiveassessment');
        }
        $period = $periods[$periodkey];

        $courses = summary::get_courses();
        $quizzes = summary::get_quizzes($period->courseids);

        $quiztocourse = [];
        foreach ($quizzes as $courseid => $quiz) {
            $quiztocourse[(int) $quiz->id] = $courses[$courseid]->shortname;
        }

        $byquiz = $quiztocourse ? summary::get_evaluators(array_keys($quiztocourse)) : [];

        $totals = [];
        foreach ($byquiz as $quizid => $byuser) {
            foreach ($byuser as $userid => $stats) {
                if (!isset($totals[$userid])) {
                    $totals[$userid] = [
                        'graded' => 0, 'marks' => 0.0, 'courses' => [],
                        'first' => 0, 'last' => 0,
                    ];
                }
                $totals[$userid]['graded'] += $stats['n'];
                $totals[$userid]['marks'] += $stats['marks'];
                $totals[$userid]['courses'][] = $quiztocourse[$quizid] ?? '';
                $totals[$userid]['first'] = $totals[$userid]['first']
                    ? min($totals[$userid]['first'], $stats['first']) : $stats['first'];
                $totals[$userid]['last'] = max($totals[$userid]['last'], $stats['last']);
            }
        }

        $users = summary::get_users(array_keys($totals));
        $service = summary::service_accounts();

        $rows = [];
        foreach ($totals as $userid => $stats) {
            $user = $users[$userid] ?? null;
            $shared = $user && in_array(strtolower($user->email), $service, true);

            if (!$user) {
                $status = summary::GUIDE_UNRESOLVED;
            } else if (!empty($user->deleted)) {
                $status = summary::GUIDE_DELETED;
            } else if (!empty($user->suspended)) {
                $status = summary::GUIDE_SUSPENDED;
            } else {
                $status = summary::GUIDE_ACTIVE;
            }

            sort($stats['courses']);
            $rows[] = (object) [
                'userid'    => (int) $userid,
                'evaluator' => $user ? fullname($user) : get_string('unknownuser', 'local_reflectiveassessment'),
                    'email'     => ($user && $status !== summary::GUIDE_DELETED) ? $user->email : '',
                'status'    => $status,
                'shared'    => $shared,
                'graded'    => $stats['graded'],
                'courses'   => count($stats['courses']),
                'coursecodes' => implode(', ', $stats['courses']),
                'avgmark'   => $stats['graded'] ? round($stats['marks'] / $stats['graded'], 1) : null,
                'first'     => $stats['first'],
                'last'      => $stats['last'],
            ];
        }

        usort($rows, function($a, $b) {
            return [$b->graded, $a->evaluator] <=> [$a->graded, $b->evaluator];
        });

        $graded = array_sum(array_column($rows, 'graded'));
        $shared = 0;
        foreach ($rows as $row) {
            if ($row->shared) {
                $shared += $row->graded;
            }
        }

        return (object) [
            'period' => $period,
            'rows'   => $rows,
            'totals' => (object) [
                'evaluators'  => count($rows),
                'graded'      => $graded,
                'sharedgraded' => $shared,
            ],
            'generated' => time(),
        ];
    }

    /**
     * Column headings for the export, in report order.
     *
     * @return string[]
     */
    public static function export_columns(): array {
        return [
            get_string('col_evaluator', 'local_reflectiveassessment'),
            get_string('col_guideemail', 'local_reflectiveassessment'),
            get_string('col_guidestatus', 'local_reflectiveassessment'),
            get_string('col_sharedaccount', 'local_reflectiveassessment'),
            get_string('col_graded', 'local_reflectiveassessment'),
            get_string('col_guidecourses', 'local_reflectiveassessment'),
            get_string('col_coursecodes', 'local_reflectiveassessment'),
            get_string('col_avgmarkgiven', 'local_reflectiveassessment'),
            get_string('col_firstevaluated', 'local_reflectiveassessment'),
            get_string('col_lastevaluated', 'local_reflectiveassessment'),
        ];
    }

    /**
     * Flatten one row for the export, in the same order as the headings.
     *
     * @param \stdClass $row
     * @return array
     */
    public static function export_row(\stdClass $row): array {
        $datefmt = get_string('strftimedate', 'langconfig');
        return [
            $row->evaluator,
            $row->email,
            get_string('guidestatus_' . $row->status, 'local_reflectiveassessment'),
            $row->shared ? get_string('yes') : get_string('no'),
            $row->graded,
            $row->courses,
            $row->coursecodes,
            $row->avgmark,
            $row->first ? userdate($row->first, $datefmt) : '',
            $row->last ? userdate($row->last, $datefmt) : '',
        ];
    }
}
