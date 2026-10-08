<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // A student may view their own exam results / scorecard.
    'local/exams:viewownresults' => [
        'riskbitmask'  => 0,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'user'    => CAP_ALLOW,
            'student' => CAP_ALLOW,
        ],
    ],

    // A student may download their own PDF result sheet.
    'local/exams:downloadresultsheet' => [
        'riskbitmask'  => 0,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'user'    => CAP_ALLOW,
            'student' => CAP_ALLOW,
        ],
    ],

    // Faculty/managers may view cohort analytics (other students' PII).
    'local/exams:viewanalytics' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Faculty/managers may export analytics.
    'local/exams:export' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

];
