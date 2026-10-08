<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled tasks for local_exams.
 *
 * @package   local_exams
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$tasks = [
    [
        'classname' => 'local_exams\task\compute_attempt_stats',
        'blocking'  => 0,
        'minute'    => '*',
        'hour'      => '*',
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
    ],
    [
        'classname' => 'local_exams\task\compute_cohort_stats',
        'blocking'  => 0,
        'minute'    => '*/5',
        'hour'      => '*',
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
    ],
    [
        'classname' => 'local_exams\task\finalize_abandoned_attempts',
        'blocking'  => 0,
        'minute'    => '*/3',
        'hour'      => '*',
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
    ],
];
