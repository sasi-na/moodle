<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Library functions for Superset Dashboard block
 *
 * @package    block_supersetembed
 * @copyright  2025 JO LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add Analytics navigation node for admins
 *
 * @param global_navigation $navigation
 */
function block_supersetembed_extend_navigation(global_navigation $navigation) {
    global $USER, $PAGE;
    
    // Only show to site admins
    if (!is_siteadmin($USER->id)) {
        return;
    }
    
    // Add Analytics node after Calendar
    $analyticsnode = $navigation->add(
        get_string('analytics', 'block_supersetembed'),
        new moodle_url('/blocks/supersetembed/analytics.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'analytics',
        new pix_icon('i/report', get_string('analytics', 'block_supersetembed'))
    );
    
    // Set it as active if we're on the analytics page
    if ($PAGE->url->compare(new moodle_url('/blocks/supersetembed/analytics.php'), URL_MATCH_BASE)) {
        $analyticsnode->make_active();
    }
}
