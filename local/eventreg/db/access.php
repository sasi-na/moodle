<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // Create/edit/publish/close/delete forms and view every response + export.
    // RISK_PERSONAL because responses expose learner identities and answers.
    'local/eventreg:manage' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // View a form's responses and download the report, WITHOUT any ability to
    // create/edit/delete/publish. Granted to teachers. Forms live in the system
    // context but teacher roles are assigned per course, so this is checked with
    // get_user_capability_course() in lib.php, not a plain system require_capability().
    'local/eventreg:viewreports' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
        ],
    ],

];
