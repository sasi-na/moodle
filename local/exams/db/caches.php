<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Cache definitions for local_exams.
 *
 * The runner checkpoint store keeps in-progress answers off the relational DB.
 * In production point this cache store at persistent Redis (AOF) for crash
 * recovery; it falls back to the default application store otherwise.
 *
 * @package   local_exams
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$definitions = [
    'checkpoints' => [
        'mode'        => cache_store::MODE_APPLICATION,
        'simplekeys'  => true,
        'simpledata'  => true,
        'staticacceleration' => false,
    ],
];
