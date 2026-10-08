<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Keep scheduler-created Zoom meetings in sync with their calendar events.
 */
$observers = [
    [
        'eventname' => '\core\event\calendar_event_updated',
        'callback' => '\local_zoomattendance\observer::event_updated',
    ],
    [
        'eventname' => '\core\event\calendar_event_deleted',
        'callback' => '\local_zoomattendance\observer::event_deleted',
    ],
];
