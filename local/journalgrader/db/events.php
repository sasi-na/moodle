<?php

defined('MOODLE_INTERNAL') || die();

$observers = [

    [
        'eventname' =>
            '\mod_assign\event\submission_created',

        'callback' =>
            '\local_journalgrader\observer::queue_submission'
    ],

    [
        'eventname' =>
            '\mod_assign\event\submission_updated',

        'callback' =>
            '\local_journalgrader\observer::queue_submission'
    ]
];