<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Add Analytics to top navigation
 *
 * @package    local_analytics
 * @copyright  2025 JO LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extend primary navigation to add Analytics link
 */
function local_analytics_extend_navigation(global_navigation $navigation) {
    global $USER, $PAGE;
    
    // Only show to site admins
    if (!is_siteadmin($USER->id)) {
        return;
    }
    
    // Add Analytics to primary navigation
    $analyticsnode = $navigation->add(
        get_string('analytics', 'local_analytics'),
        new moodle_url('/blocks/supersetembed/analytics.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'analytics',
        new pix_icon('i/report', get_string('analytics', 'local_analytics'))
    );
    
    // Force it to show in primary navigation
    $analyticsnode->showinflatnavigation = true;
}


