<?php
namespace local_exams\runner;

defined('MOODLE_INTERNAL') || die();

/**
 * In-progress answer checkpoints for the runner.
 *
 * Stored in the MUC application cache (point it at persistent Redis in
 * production), keyed by attempt id — never the relational DB, so periodic
 * checkpoints from 20k concurrent users add no DB write load.
 *
 * @package   local_exams
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class checkpoint_store {

    protected static function cache(): \cache {
        return \cache::make('local_exams', 'checkpoints');
    }

    protected static function key(int $attemptid): string {
        return 'attempt_' . $attemptid;
    }

    /**
     * Save (replace) the full answer map for an attempt.
     *
     * @param int $attemptid
     * @param array $answers name => value map of question form fields
     */
    public static function save(int $attemptid, array $answers): void {
        self::cache()->set(self::key($attemptid), [
            'answers' => $answers,
            'time'    => time(),
        ]);
    }

    /**
     * Read the stored answers for an attempt.
     *
     * @param int $attemptid
     * @return array name => value map (empty if none)
     */
    public static function load(int $attemptid): array {
        $data = self::cache()->get(self::key($attemptid));
        return is_array($data) && isset($data['answers']) ? $data['answers'] : [];
    }

    /**
     * Drop the checkpoint once an attempt has been materialized.
     *
     * @param int $attemptid
     */
    public static function clear(int $attemptid): void {
        self::cache()->delete(self::key($attemptid));
    }
}
