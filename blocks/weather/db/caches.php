<?php
/**
 * Weather block - cache definitions.
 *
 * @package    block_weather
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [
    'forecast' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
        'ttl' => 1800, // 30 minutes, so pages stay fast and the weather service is not called on every page view.
    ],
];
