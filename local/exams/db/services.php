<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Web service definitions for local_exams (exam runner).
 *
 * @package   local_exams
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$functions = [
    'local_exams_checkpoint' => [
        'classname'   => 'local_exams\external',
        'methodname'  => 'checkpoint',
        'description' => 'Persist an in-progress runner checkpoint (cache only).',
        'type'        => 'write',
        'ajax'        => true,
    ],
    'local_exams_submit_attempt' => [
        'classname'   => 'local_exams\external',
        'methodname'  => 'submit_attempt',
        'description' => 'Submit a runner attempt: persist answers + enqueue materialization.',
        'type'        => 'write',
        'ajax'        => true,
    ],
];
