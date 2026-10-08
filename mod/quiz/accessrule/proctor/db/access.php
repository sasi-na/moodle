<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // View the proctoring evidence review for a quiz.
    'quizaccess/proctor:viewreport' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    // Be exempt from proctoring (staff previewing/testing).
    'quizaccess/proctor:bypass' => [
        'riskbitmask'  => 0,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

];
