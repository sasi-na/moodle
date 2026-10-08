<?php
defined('MOODLE_INTERNAL') || die();

/**
 * MUC cache definitions for local_dashboardpoll.
 *
 * `active` caches the resolved active poll per cohort key (including the
 * aggregate vote tallies), since get_active runs on every student dashboard
 * load. It is invalidated whenever a poll is activated/closed/edited or a
 * response is recorded.
 */
$definitions = [
    'active' => [
        'mode'               => cache_store::MODE_APPLICATION,
        'simplekeys'         => true,
        'simpledata'         => false,
        'staticacceleration' => true,
    ],
];
