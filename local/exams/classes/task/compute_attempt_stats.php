<?php
namespace local_exams\task;

defined('MOODLE_INTERNAL') || die();

use local_exams\analytics\stats_cache;

/**
 * Drains the attempt-stats queue, computing + caching per-attempt analytics in
 * batches off the submission hot path.
 *
 * @package   local_exams
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class compute_attempt_stats extends \core\task\scheduled_task {

    /** How many queued attempts to process per run. */
    const BATCH = 500;

    public function get_name(): string {
        return get_string('task_compute_attempt_stats', 'local_exams');
    }

    public function execute(): void {
        global $DB;

        $rows = $DB->get_records('local_exams_stats_queue', null, 'id ASC', '*', 0, self::BATCH);
        if (!$rows) {
            return;
        }

        $done = 0;
        foreach ($rows as $row) {
            try {
                $stats = \local_exams\analytics\attempt_stats::compute((int) $row->attemptid);
                stats_cache::store((int) $row->attemptid, $stats);
                $done++;
            } catch (\Throwable $e) {
                // Skip an attempt that can't be computed (e.g. deleted) but log it.
                mtrace('local_exams: failed to compute stats for attempt ' . $row->attemptid . ': ' . $e->getMessage());
            }
            // Always remove from the queue so a poison row can't block the batch.
            $DB->delete_records('local_exams_stats_queue', ['id' => $row->id]);
        }

        mtrace('local_exams: computed analytics for ' . $done . ' attempt(s).');
    }
}
