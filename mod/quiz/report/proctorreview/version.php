<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'quiz_proctorreview';
$plugin->version   = 2026061600;
$plugin->requires  = 2024100100;
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.0';
$plugin->dependencies = [
    'quizaccess_proctor' => ANY_VERSION,
];
