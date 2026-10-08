<?php

defined('MOODLE_INTERNAL') || die();

/*
 * Scheduled tasks for local_journalgrader.
 */

$tasks = [

    [

        /*
         * Queue processor.
         */

        'classname' =>

            'local_journalgrader\task\process_queue',

        /*
         * Non-blocking task.
         */

        'blocking' => 0,

        /*
         * Run every minute.
         */

        'minute' => '*',

        'hour' => '*',

        'day' => '*',

        'month' => '*',

        'dayofweek' => '*',

        /*
         * Prevent random execution drift.
         */

        'disabled' => 0
    ]
];