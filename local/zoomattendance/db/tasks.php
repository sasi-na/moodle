<?php
defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => '\local_zoomattendance\task\process_attendance',

        // IMPORTANT: prevents blocking other cron tasks
        'blocking' => 0,

        // Runs every 15 minutes
        'minute' => '*/15',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*'
    ],
    [
        'classname' => '\local_zoomattendance\task\sync_meetings',

        'blocking' => 0,

        // Keeps the meeting registry fresh and rewrites new event links.
        'minute' => '*/30',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*'
    ],
    [
        'classname' => '\local_zoomattendance\task\auto_approve_summaries',

        'blocking' => 0,

        // Runs once a day at 02:30 (7-day threshold; daily is sufficient)
        'minute' => '30',
        'hour' => '2',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*'
    ]
];