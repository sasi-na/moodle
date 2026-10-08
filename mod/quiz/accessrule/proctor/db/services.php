<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Web service definitions for quizaccess_proctor.
 *
 * @package   quizaccess_proctor
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$functions = [
    'quizaccess_proctor_upload_snapshot' => [
        'classname'   => 'quizaccess_proctor\external',
        'methodname'  => 'upload_snapshot',
        'description' => 'Store a webcam snapshot for a proctored attempt.',
        'type'        => 'write',
        'ajax'        => true,
    ],
    'quizaccess_proctor_log_event' => [
        'classname'   => 'quizaccess_proctor\external',
        'methodname'  => 'log_event',
        'description' => 'Log a proctoring incident for an attempt.',
        'type'        => 'write',
        'ajax'        => true,
    ],
];
