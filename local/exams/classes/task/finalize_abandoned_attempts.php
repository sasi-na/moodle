<?php
namespace local_exams\task;

defined('MOODLE_INTERNAL') || die();

use local_exams\runner\materializer;

/**
 * Scheduled task: recover runner attempts that were never submitted (browser
 * crash / closed tab) by materializing them from their last checkpoint once
 * their time is up, plus retry any submitted-but-unmaterialized attempts.
 *
 * @package   local_exams
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class finalize_abandoned_attempts extends \core\task\scheduled_task {

    /** Grace seconds past the time limit before we finalise. */
    const GRACE = 180;

    /** Minutes after submit before we retry a stuck materialization. */
    const STUCK_MINUTES = 10;

    public function get_name(): string {
        return get_string('task_finalize_abandoned', 'local_exams');
    }

    public function execute(): void {
        global $DB;

        $now = time();
        $count = 0;

        // 1) In-progress runner attempts whose time limit has expired.
        $sql = "SELECT ra.attemptid
                  FROM {local_exams_runner_attempt} ra
                  JOIN {quiz_attempts} qa ON qa.id = ra.attemptid
                  JOIN {quiz} q ON q.id = ra.quizid
                 WHERE ra.status = :status
                   AND qa.state = :state
                   AND q.timelimit > 0
                   AND (qa.timestart + q.timelimit + :grace) < :now";
        $expired = $DB->get_fieldset_sql($sql, [
            'status' => 'inprogress', 'state' => 'inprogress',
            'grace' => self::GRACE, 'now' => $now,
        ]);

        // 2) Submitted attempts that never got materialized (stuck worker).
        $stuckbefore = $now - (self::STUCK_MINUTES * MINSECS);
        $stuck = $DB->get_fieldset_select('local_exams_runner_attempt', 'attemptid',
            'status = ? AND timesubmitted > 0 AND timesubmitted < ?', ['submitted', $stuckbefore]);

        foreach (array_unique(array_merge($expired, $stuck)) as $attemptid) {
            try {
                materializer::materialize((int) $attemptid);
                $count++;
            } catch (\Throwable $e) {
                mtrace('local_exams: finalize failed for attempt ' . $attemptid . ': ' . $e->getMessage());
            }
        }

        if ($count) {
            mtrace('local_exams: finalized ' . $count . ' runner attempt(s).');
        }
    }
}
