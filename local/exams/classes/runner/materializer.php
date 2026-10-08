<?php
namespace local_exams\runner;

defined('MOODLE_INTERNAL') || die();

use mod_quiz\quiz_attempt;

/**
 * Turns a submitted runner attempt into an authoritative graded Moodle attempt.
 *
 * Replays the client-collected responses through the real question engine in one
 * batched transaction, then finishes the attempt — producing identical
 * quiz_attempts / question_attempts / quiz_grades to a natively-taken attempt
 * (and firing attempt_state_changed, which enqueues our analytics).
 *
 * @package   local_exams
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class materializer {

    /**
     * Materialize one attempt from its stored answers.
     *
     * @param int $attemptid
     * @return bool True if it was materialized (or already finished).
     */
    public static function materialize(int $attemptid): bool {
        global $DB;

        $tracking = $DB->get_record('local_exams_runner_attempt', ['attemptid' => $attemptid]);
        $answers = [];
        if ($tracking && $tracking->answersjson !== null && $tracking->answersjson !== '') {
            $answers = json_decode($tracking->answersjson, true) ?: [];
        }
        if (!$answers) {
            // Fall back to the in-progress checkpoint (e.g. abandoned attempts).
            $answers = checkpoint_store::load($attemptid);
        }

        $attemptobj = quiz_attempt::create($attemptid);

        // Idempotent: if already finished, just tidy up.
        if ($attemptobj->is_finished()) {
            self::mark_done($attemptid);
            return true;
        }

        $timenow = time();
        // Record the collected responses, then finalise grading. Both write in
        // their own delegated transactions inside the quiz API.
        $attemptobj->process_submitted_actions($timenow, false, $answers ?: null);
        $attemptobj->process_finish($timenow, false);

        self::mark_done($attemptid);
        return true;
    }

    /**
     * Mark the runner attempt materialized and drop its checkpoint.
     *
     * @param int $attemptid
     */
    protected static function mark_done(int $attemptid): void {
        global $DB;

        $tracking = $DB->get_record('local_exams_runner_attempt', ['attemptid' => $attemptid]);
        if ($tracking) {
            $tracking->status = 'materialized';
            $tracking->timematerialized = time();
            $DB->update_record('local_exams_runner_attempt', $tracking);
        }
        checkpoint_store::clear($attemptid);
    }
}
