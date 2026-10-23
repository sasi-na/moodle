<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Block for embedding Superset dashboards - Simple iframe version (no guest token)
 *
 * @package    block_supersetembed
 * @copyright  2025 JO LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class block_supersetembed_simple extends block_base {
    
    public function init() {
        $this->title = get_string('pluginname', 'block_supersetembed');
    }
    
    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }
        
        $this->content = new stdClass;
        
        // Get configuration
        $superseturl = get_config('block_supersetembed', 'superseturl');
        $dashboardid = get_config('block_supersetembed', 'dashboardid');
        
        if (empty($superseturl)) {
            $superseturl = 'https://superset.thekaios.com';
        }
        if (empty($dashboardid)) {
            $dashboardid = 'b4d937de-338a-46d0-8df8-e37f51601290';
        }
        
        // Build the direct dashboard URL
        $dashboardUrl = $superseturl . '/superset/dashboard/' . $dashboardid . '/?standalone=3';
        
        // Create iframe embed
        $this->content->text = '
            <div class="superset-embed-container" style="width: 100%; height: 600px; border: 1px solid #ddd; border-radius: 4px; overflow: hidden;">
                <iframe 
                    src="' . htmlspecialchars($dashboardUrl) . '"
                    width="100%" 
                    height="100%" 
                    frameborder="0"
                    style="border: none;"
                    allowfullscreen>
                </iframe>
            </div>
            <div style="margin-top: 10px; padding: 10px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; font-size: 0.9em;">
                <strong>Note:</strong> This uses a simple iframe embed. For proper guest token embedding, you need to:
                <ul style="margin: 5px 0; padding-left: 20px;">
                    <li>Enable EMBEDDED_SUPERSET feature in Superset config</li>
                    <li>Grant admin user "can grant guest token" permission</li>
                    <li>Contact Ganesh to configure this properly</li>
                </ul>
            </div>
        ';
        
        $this->content->footer = '';
        
        return $this->content;
    }
    
    public function applicable_formats() {
        return ['my' => true];
    }
    
    public function has_config() {
        return true;
    }
}
