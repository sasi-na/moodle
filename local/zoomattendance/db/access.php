<?php

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    /*
    |--------------------------------------------------------------------------
    | Review recorded-session summaries
    |
    | Allows faculty to approve or reject the summaries students submit after
    | watching a recording, and thereby decide their attendance.
    |--------------------------------------------------------------------------
    */
    'local/zoomattendance:reviewsummaries' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Bypass the "meeting not started" join gate
    |
    | Staff who run the session (and may need the link before the host starts)
    | skip the student waiting page and get the join URL immediately.
    |--------------------------------------------------------------------------
    */
    'local/zoomattendance:bypassjoingate' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Schedule a Zoom meeting from inside a course
    |
    | Faculty with this capability get the "Schedule Zoom meeting" course link,
    | which creates the Zoom meeting and the matching calendar event in one
    | step. Bypasses moodle/calendar:manageentries deliberately so non-editing
    | teachers can schedule class meetings too.
    |--------------------------------------------------------------------------
    */
    'local/zoomattendance:schedulemeeting' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
];
