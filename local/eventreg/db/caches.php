<?php
defined('MOODLE_INTERNAL') || die();

/**
 * MUC cache definitions for local_eventreg.
 *
 * `forms` caches the set of open forms (id, title, audience rule) since the
 * learner-facing index / eligibility check can run on multiple page loads. It is
 * an APPLICATION cache invalidated cluster-wide via a cacherev config counter
 * (see \local_eventreg\manager::purge_all()) so every web node rebuilds.
 */
$definitions = [
    'forms' => [
        'mode'               => cache_store::MODE_APPLICATION,
        'simplekeys'         => true,
        'simpledata'         => false,
        'staticacceleration' => true,
    ],
];
