<?php
namespace local_exams;

defined('MOODLE_INTERNAL') || die();

/**
 * Hook callbacks. Kept deliberately tiny — anything heavy belongs in a task.
 *
 * @package   local_exams
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /**
     * Enqueue an attempt for background analytics when it finishes.
     *
     * Fired synchronously during submission (and by the runner materializer's
     * finish_attempt), so this only writes one tiny queue row — no compute.
     *
     * @param \mod_quiz\hook\attempt_state_changed $hook
     */
    public static function attempt_state_changed(\mod_quiz\hook\attempt_state_changed $hook): void {
        global $DB;

        // Only run when the feature is on, to avoid touching unrelated installs.
        if (!manager::is_enabled()) {
            return;
        }

        $attempt = $hook->get_updated_attempt();
        if (!$attempt || ($attempt->state ?? '') !== 'finished') {
            return;
        }

        // Idempotent enqueue (attemptid is unique).
        if ($DB->record_exists('local_exams_stats_queue', ['attemptid' => $attempt->id])) {
            return;
        }
        try {
            $DB->insert_record('local_exams_stats_queue', (object) [
                'attemptid'   => (int) $attempt->id,
                'quizid'      => (int) $attempt->quiz,
                'userid'      => (int) $attempt->userid,
                'timecreated' => time(),
            ]);
        } catch (\dml_exception $e) {
            // A concurrent insert won the race; that's fine.
            return;
        }
    }
}
