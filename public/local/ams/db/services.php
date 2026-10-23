<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_ams_create_student' => [
        'classname'   => 'local_ams\external',
        'methodname'  => 'create_student',
        'classpath'   => 'local/ams/classes/external.php',
        'description' => 'Create a student account and enroll in courses.',
        'type'        => 'write',
        'ajax'        => true,
    ],
    'local_ams_enrol_student' => [
        'classname'   => 'local_ams\external',
        'methodname'  => 'enrol_student',
        'classpath'   => 'local/ams/classes/external.php',
        'description' => 'Enroll a student in courses using course short codes.',
        'type'        => 'write',
        'ajax'        => true,
    ],
    'local_ams_get_student_status' => [
        'classname'   => 'local_ams\external',
        'methodname'  => 'get_student_status',
        'classpath'   => 'local/ams/classes/external.php',
        'description' => 'Get LMS account status for a student by enrolment number or email.',
        'type'        => 'read',
        'ajax'        => true,
    ],
];

$services = [
    'AMS Student Creation Service' => [
        'functions' => [
            'local_ams_create_student',
            'local_ams_enrol_student',
            'local_ams_get_student_status',
        ],
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'ams_service',
    ],
];

