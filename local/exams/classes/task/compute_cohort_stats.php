<?php
namespace local_exams\task;

defined('MOODLE_INTERNAL') || die();

use local_exams\analytics\cohort_stats;

/**
 * Recomputes per-quiz cohort aggregates for quizzes that have new attempt stats
 * since their cohort row was last computed. Keeps percentile/rank reads O(1).
 *
 * @package   local_exams
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class compute_cohort_stats extends \core\task\scheduled_task {

    /** Max quizzes to recompute per run. */
    const BATCH = 200;

    public function get_name(): string {
        return get_string('task_compute_cohort_stats', 'local_exams');
    }

    public function execute(): void {
        global $DB;

        // Quizzes with attempt stats newer than their cohort aggregate (or none yet).
        $sql = "SELECT s.quizid, MAX(s.timecomputed) AS latest
                  FROM {local_exams_attempt_stats} s
             LEFT JOIN {local_exams_cohort_stats} c ON c.quizid = s.quizid
              GROUP BY s.quizid, c.timecomputed, c.id
                HAVING c.id IS NULL OR MAX(s.timecomputed) > c.timecomputed";

        // Some DBs dislike aggregates referencing non-grouped columns in HAVING;
        // fall back to a simpler two-step approach for portability.
        try {
            $rows = $DB->get_records_sql($sql, [], 0, self::BATCH);
            $quizids = array_keys($rows);
        } catch (\dml_exception $e) {
            $quizids = $this->stale_quizids_fallback();
        }

        $count = 0;
        foreach ($quizids as $quizid) {
            try {
                cohort_stats::compute((int) $quizid);
                $count++;
            } catch (\Throwable $e) {
                mtrace('local_exams: cohort compute failed for quiz ' . $quizid . ': ' . $e->getMessage());
            }
        }
        mtrace('local_exams: recomputed cohort stats for ' . $count . ' quiz(zes).');
    }

    /**
     * Portable fallback: compare each cohort row's timecomputed in PHP.
     *
     * @return int[]
     */
    protected function stale_quizids_fallback(): array {
        global $DB;

        $latest = $DB->get_records_sql(
            "SELECT quizid, MAX(timecomputed) AS latest
               FROM {local_exams_attempt_stats}
           GROUP BY quizid", [], 0, self::BATCH);

        $cohort = $DB->get_records_menu('local_exams_cohort_stats', null, '', 'quizid, timecomputed');

        $stale = [];
        foreach ($latest as $quizid => $row) {
            if (!isset($cohort[$quizid]) || $row->latest > $cohort[$quizid]) {
                $stale[] = (int) $quizid;
            }
        }
        return $stale;
    }
}
