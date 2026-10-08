<?php
namespace local_exams\analytics;

defined('MOODLE_INTERNAL') || die();

/**
 * Faculty cohort analytics.
 *
 * Reuses the precomputed {local_exams_cohort_stats} and {local_exams_attempt_stats}
 * tables so the faculty views are cheap point-reads, not live aggregation over
 * the question engine.
 *
 * @package   local_exams
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class faculty {

    /**
     * Exams that have cohort data, with headline stats.
     *
     * @param int $limit
     * @return array {exams: [...], overall: {...}}
     */
    public static function exam_list(int $userid, int $limit = 200): array {
        global $DB;

        // Scope to the courses this user may see (null = all).
        $courseids = \local_exams\manager::analytics_course_ids($userid);
        $coursefilter = '';
        $params = [];
        if ($courseids !== null) {
            if (empty($courseids)) {
                return ['exams' => [], 'hasdata' => false,
                        'overall' => ['examcount' => 0, 'totalattempts' => 0, 'avgpct' => null]];
            }
            [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');
            $coursefilter = " AND q.course $insql";
        }

        $sql = "SELECT cs.quizid, cs.numattempts, cs.statsjson,
                       q.name AS quizname, q.grade AS grademax,
                       c.fullname AS coursename, cm.id AS cmid
                  FROM {local_exams_cohort_stats} cs
                  JOIN {quiz} q ON q.id = cs.quizid
                  JOIN {course} c ON c.id = q.course
                  JOIN {course_modules} cm ON cm.instance = q.id
                                          AND cm.module = (SELECT id FROM {modules} WHERE name = 'quiz')
                 WHERE cs.numattempts > 0 $coursefilter
              ORDER BY cs.timecomputed DESC";
        $records = $DB->get_records_sql($sql, $params, 0, $limit);

        $exams = [];
        $totalattempts = 0;
        $sumpct = 0.0;
        $n = 0;
        foreach ($records as $r) {
            $s = json_decode($r->statsjson, true) ?: [];
            $grademax = (float) $r->grademax;
            $meanpct = ($grademax > 0 && isset($s['mean'])) ? round($s['mean'] / $grademax * 100, 1) : null;
            $exams[] = [
                'quizid'     => (int) $r->quizid,
                'cmid'       => (int) $r->cmid,
                'quizname'   => format_string($r->quizname),
                'coursename' => format_string($r->coursename),
                'attempts'   => (int) $r->numattempts,
                'mean'       => $s['mean'] ?? null,
                'meanpct'    => $meanpct,
                'meanpctlabel' => $meanpct === null ? '—' : ($meanpct . '%'),
                'median'     => $s['median'] ?? null,
                'max'        => $s['max'] ?? null,
                'analysisurl' => (new \moodle_url('/local/exams/index.php',
                    ['view' => 'faculty', 'quizid' => $r->quizid]))->out(false),
            ];
            $totalattempts += (int) $r->numattempts;
            if ($meanpct !== null) {
                $sumpct += $meanpct;
                $n++;
            }
        }

        return [
            'exams'   => $exams,
            'hasdata' => !empty($exams),
            'overall' => [
                'examcount'     => count($exams),
                'totalattempts' => $totalattempts,
                'avgpct'        => $n > 0 ? round($sumpct / $n, 1) : null,
            ],
        ];
    }

    /**
     * Deep analysis for one exam: distribution, item analysis, toppers.
     *
     * @param int $quizid
     * @return array
     */
    public static function exam_analysis(int $quizid, int $userid): array {
        global $DB;

        $quiz = $DB->get_record('quiz', ['id' => $quizid], 'id, name, grade, course', MUST_EXIST);

        // Enforce course scoping for non-admin faculty.
        $courseids = \local_exams\manager::analytics_course_ids($userid);
        if ($courseids !== null && !in_array((int) $quiz->course, $courseids, true)) {
            throw new \moodle_exception('noteligible', 'local_exams');
        }

        $cohort = cohort_stats::get($quizid);

        // Aggregate per-question facility + topic performance from attempt stats.
        $stats = $DB->get_records('local_exams_attempt_stats', ['quizid' => $quizid], '', 'id, statsjson');
        $byslot = [];   // slot => [name, fracsum, correct, count].
        $bytopic = [];  // tag => [sum, count].
        $attemptcount = 0;
        foreach ($stats as $row) {
            $s = json_decode($row->statsjson, true);
            if (!$s) {
                continue;
            }
            $attemptcount++;
            foreach (($s['perquestion'] ?? []) as $pq) {
                $slot = $pq['slot'];
                if (!isset($byslot[$slot])) {
                    $byslot[$slot] = ['name' => $pq['name'], 'fracsum' => 0.0, 'correct' => 0, 'count' => 0];
                }
                $frac = $pq['fraction'] === null ? 0 : (float) $pq['fraction'];
                $byslot[$slot]['fracsum'] += $frac;
                $byslot[$slot]['correct'] += ($pq['outcome'] === 'correct') ? 1 : 0;
                $byslot[$slot]['count']++;
            }
            foreach (($s['topics'] ?? []) as $t) {
                if (!isset($bytopic[$t['tag']])) {
                    $bytopic[$t['tag']] = ['sum' => 0.0, 'count' => 0];
                }
                $bytopic[$t['tag']]['sum'] += $t['percentage'];
                $bytopic[$t['tag']]['count']++;
            }
        }

        $items = [];
        ksort($byslot);
        foreach ($byslot as $slot => $d) {
            $facility = $d['count'] > 0 ? round($d['fracsum'] / $d['count'] * 100, 1) : 0;
            $items[] = [
                'slot'        => $slot,
                'name'        => $d['name'],
                'facility'    => $facility,
                'correctrate' => $d['count'] > 0 ? round($d['correct'] / $d['count'] * 100, 1) : 0,
                'difficulty'  => $facility >= 70 ? 'easy' : ($facility >= 40 ? 'medium' : 'hard'),
            ];
        }

        $topics = [];
        foreach ($bytopic as $tag => $d) {
            $topics[] = ['tag' => $tag, 'percentage' => $d['count'] > 0 ? round($d['sum'] / $d['count'], 1) : 0];
        }

        // Toppers: highest quiz grades.
        $namefields = \core_user\fields::for_name()->get_sql('u')->selects;
        $toppers = $DB->get_records_sql(
            "SELECT qg.id, qg.grade, qg.userid $namefields
               FROM {quiz_grades} qg
               JOIN {user} u ON u.id = qg.userid
              WHERE qg.quiz = :q AND qg.grade IS NOT NULL
           ORDER BY qg.grade DESC", ['q' => $quizid], 0, 10);
        $grademax = (float) $quiz->grade;
        $topperrows = [];
        $rank = 1;
        foreach ($toppers as $t) {
            $topperrows[] = [
                'rank'     => $rank++,
                'name'     => fullname($t),
                'grade'    => round((float) $t->grade, 2),
                'pct'      => $grademax > 0 ? round($t->grade / $grademax * 100, 1) . '%' : '—',
            ];
        }

        return [
            'quizid'       => $quizid,
            'quizname'     => format_string($quiz->name),
            'attemptcount' => $attemptcount,
            'cohort'       => $cohort,
            'items'        => $items,
            'topics'       => $topics,
            'toppers'      => $topperrows,
        ];
    }
}
