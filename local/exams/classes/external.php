<?php
namespace local_exams;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use local_exams\runner\token;
use local_exams\runner\checkpoint_store;

/**
 * Web services for the client-side exam runner.
 *
 * All endpoints are deliberately write-light: checkpoints hit the cache only,
 * and submit just persists the final answers + enqueues the materializer, then
 * acks instantly. No grading happens on the request.
 *
 * @package   local_exams
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external extends external_api {

    // ---- checkpoint -------------------------------------------------------

    public static function checkpoint_parameters(): external_function_parameters {
        return new external_function_parameters([
            'token'       => new external_value(PARAM_RAW, 'Signed exam token'),
            'answersjson' => new external_value(PARAM_RAW, 'JSON map of question field name => value'),
        ]);
    }

    public static function checkpoint_returns(): external_single_structure {
        return new external_single_structure([
            'status'    => new external_value(PARAM_ALPHA, 'ok|expired|invalid'),
            'servernow' => new external_value(PARAM_INT, 'Authoritative server time'),
        ]);
    }

    /**
     * Persist an in-progress checkpoint to the cache (no DB write).
     *
     * @param string $token
     * @param string $answersjson
     * @return array
     */
    public static function checkpoint(string $token, string $answersjson): array {
        global $USER;
        self::validate_parameters(self::checkpoint_parameters(),
            ['token' => $token, 'answersjson' => $answersjson]);
        self::validate_context(\context_system::instance());

        $payload = token::verify($token);
        if (!$payload || (int) $payload['userid'] !== (int) $USER->id) {
            return ['status' => 'invalid', 'servernow' => time()];
        }

        $answers = json_decode($answersjson, true);
        if (is_array($answers)) {
            checkpoint_store::save((int) $payload['attemptid'], $answers);
        }

        // Inform the client if the server clock says time is up.
        $expired = !empty($payload['timelimit'])
            && time() > ((int) $payload['timestart'] + (int) $payload['timelimit']);

        return ['status' => $expired ? 'expired' : 'ok', 'servernow' => time()];
    }

    // ---- submit -----------------------------------------------------------

    public static function submit_attempt_parameters(): external_function_parameters {
        return new external_function_parameters([
            'token'       => new external_value(PARAM_RAW, 'Signed exam token'),
            'answersjson' => new external_value(PARAM_RAW, 'Final JSON map of question field name => value'),
        ]);
    }

    public static function submit_attempt_returns(): external_single_structure {
        return new external_single_structure([
            'status'    => new external_value(PARAM_ALPHA, 'submitted|invalid'),
            'attemptid' => new external_value(PARAM_INT, 'The attempt id'),
        ]);
    }

    /**
     * Persist the final answers + enqueue background materialization, then ack.
     *
     * @param string $token
     * @param string $answersjson
     * @return array
     */
    public static function submit_attempt(string $token, string $answersjson): array {
        global $USER, $DB;
        self::validate_parameters(self::submit_attempt_parameters(),
            ['token' => $token, 'answersjson' => $answersjson]);
        self::validate_context(\context_system::instance());

        $payload = token::verify($token);
        if (!$payload || (int) $payload['userid'] !== (int) $USER->id) {
            return ['status' => 'invalid', 'attemptid' => 0];
        }

        $attemptid = (int) $payload['attemptid'];
        $answers = json_decode($answersjson, true);
        $answers = is_array($answers) ? $answers : [];

        // Durable: store final answers in the tracking row (one write) + cache.
        checkpoint_store::save($attemptid, $answers);
        $tracking = $DB->get_record('local_exams_runner_attempt', ['attemptid' => $attemptid]);
        if ($tracking && $tracking->status !== 'materialized') {
            $tracking->status = 'submitted';
            $tracking->answersjson = json_encode($answers);
            $tracking->timesubmitted = time();
            $DB->update_record('local_exams_runner_attempt', $tracking);
        }

        // Enqueue the heavy work off-request.
        $task = new \local_exams\task\materialize_attempt();
        $task->set_custom_data(['attemptid' => $attemptid]);
        \core\task\manager::queue_adhoc_task($task, true);

        return ['status' => 'submitted', 'attemptid' => $attemptid];
    }
}
