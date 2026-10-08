<?php
namespace local_exams\analytics;

defined('MOODLE_INTERNAL') || die();

/**
 * Per-quiz cohort aggregates for comparison + percentile/rank.
 *
 * The distribution/mean/median/topper are precomputed and cached in
 * {local_exams_cohort_stats}; a student's rank + percentile are computed live
 * with two indexed COUNT aggregates on {quiz_grades} (cheap, accurate, replica).
 *
 * @package   local_exams
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cohort_stats {

    /** Number of histogram buckets across 0-100%. */
    const BINS = 10;

    /**
     * Read cached cohort stats, computing + storing them if missing/stale.
     *
     * @param int $quizid
     * @param bool $forcerecompute
     * @return array
     */
    public static function get(int $quizid, bool $forcerecompute = false): array {
        global $DB;

        if (!$forcerecompute) {
            $rec = $DB->get_record('local_exams_cohort_stats', ['quizid' => $quizid]);
            if ($rec) {
                return json_decode($rec->statsjson, true);
            }
        }
        return self::compute($quizid);
    }

    /**
     * Compute and persist cohort aggregates for a quiz.
     *
     * @param int $quizid
     * @return array
     */
    public static function compute(int $quizid): array {
        global $DB;

        $quiz = $DB->get_record('quiz', ['id' => $quizid], 'id, grade', MUST_EXIST);
        $grademax = (float) $quiz->grade;

        $grades = $DB->get_fieldset_select('quiz_grades', 'grade', 'quiz = ? AND grade IS NOT NULL', [$quizid]);
        $grades = array_map('floatval', $grades);

        $n = count($grades);
        $stats = [
            'quizid'   => $quizid,
            'grademax' => round($grademax, 2),
            'count'    => $n,
            'mean'     => null,
            'median'   => null,
            'sd'       => null,
            'max'      => null,
            'min'      => null,
            'histogram'=> [],
        ];

        if ($n > 0) {
            sort($grades);
            $sum = array_sum($grades);
            $mean = $sum / $n;
            $median = ($n % 2)
                ? $grades[intdiv($n, 2)]
                : ($grades[$n / 2 - 1] + $grades[$n / 2]) / 2;
            $var = 0.0;
            foreach ($grades as $g) {
                $var += ($g - $mean) ** 2;
            }
            $sd = sqrt($var / $n);

            $stats['mean']   = round($mean, 2);
            $stats['median'] = round($median, 2);
            $stats['sd']     = round($sd, 2);
            $stats['max']    = round(end($grades), 2);
            $stats['min']    = round($grades[0], 2);

            // Percentage histogram.
            $buckets = array_fill(0, self::BINS, 0);
            foreach ($grades as $g) {
                $pct = $grademax > 0 ? ($g / $grademax * 100) : 0;
                $bin = min(self::BINS - 1, (int) floor($pct / (100 / self::BINS)));
                $buckets[$bin]++;
            }
            foreach ($buckets as $i => $c) {
                $lo = $i * (100 / self::BINS);
                $hi = $lo + (100 / self::BINS);
                $stats['histogram'][] = [
                    'label' => round($lo) . '-' . round($hi) . '%',
                    'count' => $c,
                ];
            }
        }

        // Persist (upsert).
        $now = time();
        $existing = $DB->get_record('local_exams_cohort_stats', ['quizid' => $quizid]);
        $row = (object) [
            'quizid'       => $quizid,
            'statsjson'    => json_encode($stats),
            'numattempts'  => $n,
            'timecomputed' => $now,
        ];
        if ($existing) {
            $row->id = $existing->id;
            $DB->update_record('local_exams_cohort_stats', $row);
        } else {
            $DB->insert_record('local_exams_cohort_stats', $row);
        }

        return $stats;
    }

    /**
     * A student's rank (1 = top) and percentile among quiz attempters.
     *
     * @param int $quizid
     * @param float $grade The student's overall quiz grade.
     * @return array {rank, total, percentile}
     */
    public static function rank_for(int $quizid, float $grade): array {
        global $DB;

        $total = (int) $DB->count_records_select('quiz_grades', 'quiz = ? AND grade IS NOT NULL', [$quizid]);
        if ($total === 0) {
            return ['rank' => null, 'total' => 0, 'percentile' => null];
        }
        $above = (int) $DB->count_records_select('quiz_grades', 'quiz = ? AND grade > ?', [$quizid, $grade]);
        $below = (int) $DB->count_records_select('quiz_grades', 'quiz = ? AND grade < ?', [$quizid, $grade]);

        return [
            'rank'       => $above + 1,
            'total'      => $total,
            'percentile' => round($below / $total * 100, 1),
        ];
    }
}
