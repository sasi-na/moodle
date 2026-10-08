<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Extend course navigation.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function block_liverecordingstats_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    context_course $context
) {

    // "Recorded Session Stats" has been retired from the course navigation.
    return;
}