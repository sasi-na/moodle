<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [

    'mod/customteams:addinstance' => [
        'riskbitmask' => RISK_XSS,

        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,

        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
            'teacher' => CAP_ALLOW
        ],

        'clonepermissionsfrom' => 'moodle/course:manageactivities'
    ],

    'mod/customteams:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,

        'archetypes' => [
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ]
    ]
];