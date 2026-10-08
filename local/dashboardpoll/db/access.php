<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // Manage polls: create/edit/delete/activate/close. Implies viewing reports
    // and exporting responses too (managers get everything).
    'local/dashboardpoll:manage' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // View a poll's report and download the per-student CSV, WITHOUT any ability
    // to create/edit/delete/activate/close. Granted to teachers so they can see
    // results. RISK_PERSONAL because the CSV exposes respondent identities.
    // Polls live in the system context but teacher roles are assigned per course,
    // so this is checked with get_user_capability_course() (see lib.php), not a
    // plain system-context require_capability().
    'local/dashboardpoll:viewreports' => [
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
