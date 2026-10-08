<?php
namespace local_exams\task;

defined('MOODLE_INTERNAL') || die();

use local_exams\runner\materializer;

/**
 * Adhoc task: materialize one submitted runner attempt into a graded Moodle
 * attempt off the submission request.
 *
 * @package   local_exams
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class materialize_attempt extends \core\task\adhoc_task {

    public function execute(): void {
        $data = (array) $this->get_custom_data();
        $attemptid = (int) ($data['attemptid'] ?? 0);
        if (!$attemptid) {
            return;
        }
        try {
            materializer::materialize($attemptid);
            mtrace('local_exams: materialized attempt ' . $attemptid);
        } catch (\Throwable $e) {
            // Re-throw so the adhoc task is retried with backoff by cron.
            throw $e;
        }
    }
}
