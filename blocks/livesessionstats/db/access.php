<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'block/livesessionstats:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'student' => CAP_ALLOW,
        ],
    ],
];
