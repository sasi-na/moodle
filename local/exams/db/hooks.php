<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Hook callbacks for local_exams.
 *
 * On a quiz attempt changing state we do the smallest possible work — enqueue a
 * row for the background analytics task. No computation on the submission path.
 *
 * @package   local_exams
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$callbacks = [
    [
        'hook'     => mod_quiz\hook\attempt_state_changed::class,
        'callback' => local_exams\hook_callbacks::class . '::attempt_state_changed',
        'priority' => 200,
    ],
];
