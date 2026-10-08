<?php
namespace quizaccess_proctor;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;

/**
 * Web services receiving proctoring evidence from the browser.
 *
 * Ownership is enforced (the attempt must belong to the calling user) and the
 * per-attempt snapshot cap is applied. Failures never propagate to the exam —
 * the client treats errors as evidence gaps, not blockers.
 *
 * @package   quizaccess_proctor
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external extends external_api {

    /**
     * Resolve + authorise an attempt for the current user.
     *
     * @param int $attemptid
     * @return array [attempt record, module context, settings]
     */
    protected static function resolve(int $attemptid): array {
        global $DB, $USER;

        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], '*', MUST_EXIST);
        if ((int) $attempt->userid !== (int) $USER->id) {
            throw new \required_capability_exception(\context_system::instance(),
                'quizaccess/proctor:bypass', 'nopermissions', '');
        }
        $cm = get_coursemodule_from_instance('quiz', $attempt->quiz, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        $settings = \quizaccess_proctor::get_settings((int) $attempt->quiz);
        return [$attempt, $context, $settings];
    }

    // ---- upload_snapshot --------------------------------------------------

    public static function upload_snapshot_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid'  => new external_value(PARAM_INT, 'Quiz attempt id'),
            'imagedata'  => new external_value(PARAM_RAW, 'data: URL of the snapshot'),
            'type'       => new external_value(PARAM_ALPHA, 'identity|periodic|screen|flagged', VALUE_DEFAULT, 'periodic'),
            'capturedat' => new external_value(PARAM_INT, 'Client capture time (epoch)', VALUE_DEFAULT, 0),
        ]);
    }

    public static function upload_snapshot_returns(): external_single_structure {
        return new external_single_structure([
            'status'     => new external_value(PARAM_ALPHA, 'stored|skipped|rejected'),
            'snapshotid' => new external_value(PARAM_INT, 'New snapshot id (0 if not stored)'),
        ]);
    }

    public static function upload_snapshot(int $attemptid, string $imagedata, string $type, int $capturedat): array {
        self::validate_parameters(self::upload_snapshot_parameters(), [
            'attemptid' => $attemptid, 'imagedata' => $imagedata, 'type' => $type, 'capturedat' => $capturedat,
        ]);

        [$attempt, $context, $settings] = self::resolve($attemptid);
        if (!$settings || empty($settings->enabled)) {
            return ['status' => 'rejected', 'snapshotid' => 0];
        }

        // Enforce the per-attempt cap on periodic snapshots.
        if ($type !== 'identity' && storage::count_periodic($attemptid) >= (int) $settings->maxsnapshots) {
            return ['status' => 'skipped', 'snapshotid' => 0];
        }

        $when = $capturedat ?: time();
        $id = storage::store_snapshot($context, (int) $attempt->quiz, $attemptid,
            (int) $attempt->userid, $type, $imagedata, $when);

        return ['status' => $id ? 'stored' : 'rejected', 'snapshotid' => $id];
    }

    // ---- log_event --------------------------------------------------------

    public static function log_event_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid'  => new external_value(PARAM_INT, 'Quiz attempt id'),
            'eventtype'  => new external_value(PARAM_ALPHANUMEXT, 'Event type, e.g. tabswitch'),
            'occurredat' => new external_value(PARAM_INT, 'Client event time (epoch)', VALUE_DEFAULT, 0),
            'detail'     => new external_value(PARAM_TEXT, 'Optional detail', VALUE_DEFAULT, ''),
            'severity'   => new external_value(PARAM_INT, 'Severity 0-3', VALUE_DEFAULT, 1),
        ]);
    }

    public static function log_event_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHA, 'logged|rejected'),
        ]);
    }

    public static function log_event(int $attemptid, string $eventtype, int $occurredat, string $detail,
            int $severity = 1): array {
        self::validate_parameters(self::log_event_parameters(), [
            'attemptid' => $attemptid, 'eventtype' => $eventtype, 'occurredat' => $occurredat,
            'detail' => $detail, 'severity' => $severity,
        ]);

        [$attempt, $context, $settings] = self::resolve($attemptid);
        if (!$settings || empty($settings->enabled) || empty($settings->logevents)) {
            return ['status' => 'rejected'];
        }

        storage::log_event((int) $attempt->quiz, $attemptid, (int) $attempt->userid,
            $eventtype, $occurredat ?: time(), $detail !== '' ? $detail : null, $severity);

        return ['status' => 'logged'];
    }
}
