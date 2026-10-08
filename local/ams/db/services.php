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
    'local_ams_update_student' => [
        'classname'   => 'local_ams\external',
        'methodname'  => 'update_student',
        'classpath'   => 'local/ams/classes/external.php',
        'description' => 'Update profile fields (elective, program) for an existing student.',
        'type'        => 'write',
        'ajax'        => true,
    ],
    'local_ams_get_student_lastaccess' => [
        'classname'   => 'local_ams\external',
        'methodname'  => 'get_student_lastaccess',
        'classpath'   => 'local/ams/classes/external.php',
        'description' => 'Get a student last access time by provisional enrollment number.',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'local_ams_update_user_status' => [
        'classname'   => 'local_ams\external',
        'methodname'  => 'update_user_status',
        'classpath'   => 'local/ams/classes/external.php',
        'description' => 'Set a user status (active or suspended) by provisional enrollment number.',
        'type'        => 'write',
        'ajax'        => true,
    ],
    'local_ams_reset_password' => [
        'classname'   => 'local_ams\external',
        'methodname'  => 'reset_password',
        'classpath'   => 'local/ams/classes/external.php',
        'description' => 'Reset a learner password and email the new credentials, by enrolment number or email.',
        'type'        => 'write',
        'ajax'        => true,
    ],
];

$services = [
    'AMS Student Creation Service' => [
        'functions' => [
            'local_ams_create_student',
            'local_ams_enrol_student',
            'local_ams_get_student_status',
            'local_ams_update_student',
            'local_ams_get_student_lastaccess',
            'local_ams_update_user_status',
            'local_ams_reset_password',
        ],
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'ams_service',
    ],
];
