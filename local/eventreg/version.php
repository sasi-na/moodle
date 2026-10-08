<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_eventreg';
$plugin->version   = 2026090701;
$plugin->requires  = 2024100100; // Moodle 4.5+.
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.0';
$plugin->dependencies = [
    // manager uses \local_commonutils\user_helper to read the cohort_batch field.
    'local_commonutils' => ANY_VERSION,
];
