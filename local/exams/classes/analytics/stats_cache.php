<?php
namespace local_exams\analytics;

defined('MOODLE_INTERNAL') || die();

/**
 * Read/write wrapper around precomputed per-attempt analytics.
 *
 * Result pages read here; the value is computed once (by the background task on
 * submission, or lazily on first read) and cached in {local_exams_attempt_stats}.
 *
 * @package   local_exams
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stats_cache {

    /**
     * Get cached stats for an attempt, computing + storing on miss.
     *
     * @param int $attemptid
     * @param bool $forcerecompute
     * @return array
     */
    public static function get(int $attemptid, bool $forcerecompute = false): array {
        global $DB;

        if (!$forcerecompute) {
            $rec = $DB->get_record('local_exams_attempt_stats', ['attemptid' => $attemptid]);
            if ($rec) {
                return json_decode($rec->statsjson, true);
            }
        }

        $stats = attempt_stats::compute($attemptid);
        self::store($attemptid, $stats);
        return $stats;
    }

    /**
     * Persist computed stats (upsert by attemptid).
     *
     * @param int $attemptid
     * @param array $stats
     */
    public static function store(int $attemptid, array $stats): void {
        global $DB;

        $row = (object) [
            'attemptid'    => $attemptid,
            'quizid'       => (int) ($stats['quizid'] ?? 0),
            'userid'       => (int) ($stats['userid'] ?? 0),
            'statsjson'    => json_encode($stats),
            'timecomputed' => time(),
        ];

        $existing = $DB->get_record('local_exams_attempt_stats', ['attemptid' => $attemptid], 'id');
        if ($existing) {
            $row->id = $existing->id;
            $DB->update_record('local_exams_attempt_stats', $row);
        } else {
            $DB->insert_record('local_exams_attempt_stats', $row);
        }
    }
}
