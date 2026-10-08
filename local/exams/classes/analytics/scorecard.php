<?php
namespace local_exams\analytics;

defined('MOODLE_INTERNAL') || die();

/**
 * Cross-semester student scorecard.
 *
 * Reuses the batch -> program -> semester category-hierarchy grade query proven
 * in local_ams\external::get_student_status(), extended to pull per-quiz marks
 * (finalgrade / grademax) so we can show marks and percentages, not just
 * present/absent counts.
 *
 * Read-only: routes to the DB read replica automatically when configured.
 *
 * @package   local_exams
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scorecard {

    /**
     * Build the full scorecard for a user.
     *
     * @param int $userid
     * @return array {semesters: [...], overall: {...}}
     */
    public static function get_for_user(int $userid): array {
        global $DB;

        $sql = "SELECT q.id AS quizid,
                       cm.id AS cmid,
                       c.fullname AS coursename,
                       q.name AS quizname,
                       cat_sem.name AS semester,
                       cat_prog.name AS program,
                       cat_batch.name AS batch,
                       gg.finalgrade,
                       gi.grademax
                  FROM {user_enrolments} ue
                  JOIN {enrol} e               ON ue.enrolid = e.id
                  JOIN {course} c              ON e.courseid = c.id
                  JOIN {course_categories} cat_sem   ON c.category = cat_sem.id
                  JOIN {course_categories} cat_prog  ON cat_sem.parent = cat_prog.id
                  JOIN {course_categories} cat_batch ON cat_prog.parent = cat_batch.id
                  JOIN {quiz} q                ON q.course = c.id
                  JOIN {course_modules} cm     ON cm.instance = q.id
                                              AND cm.module = (SELECT id FROM {modules} WHERE name = 'quiz')
                  JOIN {grade_items} gi        ON gi.iteminstance = q.id
                                              AND gi.itemmodule = 'quiz'
                                              AND gi.courseid = c.id
             LEFT JOIN {grade_grades} gg       ON gg.itemid = gi.id AND gg.userid = ue.userid
                 WHERE ue.userid = :userid
                   AND cat_sem.name LIKE 'Semester%'
                   -- Respect gradebook visibility. This query joins EVERY graded quiz in every
                   -- enrolled course, continuous assessments included, so without this a learner's
                   -- previous-semester CA marks stay on the scorecard and in the PDF after
                   -- local_cagrades\past_ca has hidden them everywhere else.
                   --
                   -- The row is dropped rather than having its grade nulled: a nulled grade would
                   -- count as 'absent', which both leaks that the assessment exists and corrupts the
                   -- present/absent tallies and the trend chart.
                   --
                   -- gg.id IS NULL is kept, because that is a genuine absence with no mark to hide.
                   -- The hidden > 1 arms carry Moodle's hidden-until semantics, matching
                   -- grade_grade::is_hidden() and grade_item::is_hidden().
                   AND (gg.id IS NULL
                        OR ((gg.hidden = 0 OR (gg.hidden > 1 AND gg.hidden <= :now1))
                            AND (gi.hidden = 0 OR (gi.hidden > 1 AND gi.hidden <= :now2))))
              ORDER BY cat_batch.name, cat_prog.name, cat_sem.name, q.name";

        // Filtered for staff too, deliberately. The only staff path here is the result-sheet PDF,
        // and result_sheet_generator caches one file per learner - so a staff-generated sheet holding
        // hidden marks could later be handed to the learner it belongs to. Staff have the gradebook
        // and the report builder source for the unfiltered view.
        $rows = $DB->get_records_sql($sql, [
            'userid' => $userid,
            'now1' => time(),
            'now2' => time(),
        ]);

        $semesters = [];
        $ototal = $opresent = $oabsent = 0;
        $omarks = $omax = 0.0;

        foreach ($rows as $row) {
            $semkey = $row->semester;
            if (!isset($semesters[$semkey])) {
                $semesters[$semkey] = [
                    'name'          => $row->semester,
                    'semnum'        => self::semester_number($row->semester),
                    'program'       => $row->program,
                    'batch'         => $row->batch,
                    'total'         => 0,
                    'present'       => 0,
                    'absent'        => 0,
                    'marksobtained' => 0.0,
                    'marksmax'      => 0.0,
                    'quizzes'       => [],
                ];
            }

            $has = $row->finalgrade !== null;
            $grade = $has ? (float) $row->finalgrade : null;
            $max = (float) $row->grademax;
            $pct = ($has && $max > 0) ? round($grade / $max * 100, 1) : null;

            $semesters[$semkey]['total']++;
            $semesters[$semkey][$has ? 'present' : 'absent']++;
            if ($has) {
                $semesters[$semkey]['marksobtained'] += $grade;
                $semesters[$semkey]['marksmax'] += $max;
                $omarks += $grade;
                $omax += $max;
            }

            $semesters[$semkey]['quizzes'][] = [
                'quizid'     => (int) $row->quizid,
                'cmid'       => (int) $row->cmid,
                'coursename' => $row->coursename,
                'quizname'   => $row->quizname,
                'present'    => $has,
                'finalgrade' => $has ? round($grade, 2) : null,
                'grademax'   => round($max, 2),
                'percentage' => $pct,
                'pctlabel'   => $pct === null ? null : ($pct . '%'),
                'status'     => $has ? 'present' : 'absent',
            ];

            $ototal++;
            $has ? $opresent++ : $oabsent++;
        }

        // Finalise per-semester percentages and sort by semester number.
        $semesters = array_values($semesters);
        foreach ($semesters as &$s) {
            $s['percentage'] = $s['marksmax'] > 0 ? round($s['marksobtained'] / $s['marksmax'] * 100, 1) : null;
            $s['marksobtained'] = round($s['marksobtained'], 2);
            $s['marksmax'] = round($s['marksmax'], 2);
        }
        unset($s);
        usort($semesters, function($a, $b) {
            return $a['semnum'] <=> $b['semnum'];
        });

        return [
            'semesters' => $semesters,
            'overall'   => [
                'totalexams' => $ototal,
                'present'    => $opresent,
                'absent'     => $oabsent,
                'percentage' => $omax > 0 ? round($omarks / $omax * 100, 1) : null,
            ],
        ];
    }

    /**
     * Parse the semester number from a category name like "Semester - 03".
     *
     * @param string $name
     * @return int 0 when not parseable (sorts first).
     */
    public static function semester_number(string $name): int {
        if (preg_match('/semester\D*(\d+)/i', $name, $m)) {
            return (int) $m[1];
        }
        return 0;
    }
}
