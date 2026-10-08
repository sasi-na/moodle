<?php

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    /*
    |--------------------------------------------------------------------------
    | Student block visibility
    |--------------------------------------------------------------------------
    */

    'block/liverecordingstats:view' => [

        'captype' => 'read',

        'contextlevel' => CONTEXT_BLOCK,

        'archetypes' => [

            'student' => CAP_ALLOW
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Faculty stats access
    |--------------------------------------------------------------------------
    */

    'block/liverecordingstats:viewstats' => [

        'riskbitmask' => RISK_PERSONAL,

        'captype' => 'read',

        'contextlevel' => CONTEXT_COURSE,

        'archetypes' => [

            'editingteacher' => CAP_ALLOW,

            'teacher' => CAP_ALLOW,

            'manager' => CAP_ALLOW
        ]
    ]
];