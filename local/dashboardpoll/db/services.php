<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Web service definitions for local_dashboardpoll.
 *
 * @package   local_dashboardpoll
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$functions = [
    'local_dashboardpoll_get_active' => [
        'classname'   => 'local_dashboardpoll\external',
        'methodname'  => 'get_active',
        'description' => 'Resolve the active dashboard poll for the current user (with results if already answered).',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'local_dashboardpoll_submit' => [
        'classname'   => 'local_dashboardpoll\external',
        'methodname'  => 'submit',
        'description' => 'Record the current user\'s answer to a dashboard poll and return the aggregate results.',
        'type'        => 'write',
        'ajax'        => true,
    ],
];
