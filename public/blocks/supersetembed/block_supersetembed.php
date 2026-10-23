<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Superset Dashboard Embed Block
 *
 * @package    block_supersetembed
 * @copyright  2025 JO LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class block_supersetembed extends block_base {

    /**
     * Initialize the block
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_supersetembed');
    }

    /**
     * Allow multiple instances of this block
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Check if block should be visible - only for admins
     */
    public function is_empty() {
        global $USER;
        
        // Only show to site administrators
        if (!is_siteadmin($USER->id)) {
            return true; // Block is empty (hidden) for non-admins
        }
        
        return false;
    }

    /**
     * Set which pages this block can appear on
     */
    public function applicable_formats() {
        return array(
            'site-index' => true,
            'my' => true,
            'course-view' => true,
            'mod' => false
        );
    }

    /**
     * Block has configuration
     */
    public function has_config() {
        return true;
    }

    /**
     * Get block content
     */
    public function get_content() {
        global $CFG, $PAGE;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        // Get configuration values with defaults
        $apiurl = get_config('block_supersetembed', 'apiurl');
        $superseturl = get_config('block_supersetembed', 'superseturl');
        $dashboardid = get_config('block_supersetembed', 'dashboardid');

        // Use default values if not configured
        if (empty($apiurl)) {
            $apiurl = 'https://qa.ams.dashboard.thekaios.com/api/v1/superset/guest-token';
        }
        if (empty($superseturl)) {
            $superseturl = 'https://superset.thekaios.com';
        }
        if (empty($dashboardid)) {
            $dashboardid = 'b4d937de-338a-46d0-8df8-e37f51601290';
        }

        // Generate unique ID for this embedded dashboard
        $embedid = 'superset-embed-main';

        // Create container for the embedded dashboard with inline script
        $container = html_writer::div(
            html_writer::div('Initializing...', 'text-center p-3'),
            'superset-embed-container',
            array(
                'id' => $embedid,
                'style' => 'width: 100%; height: 700px; border-radius: 4px; background: #f8f9fa;'
            )
        );

        // Use local proxy to avoid CORS issues
        $tokenproxyurl = new moodle_url('/blocks/supersetembed/get_token.php');
        
        // Add inline script to load dashboard via iframe with guest token
        $inlinescript = "
        <script>
        (function() {
            var container = document.getElementById('$embedid');
            if (!container) {
                console.error('Superset: Container not found!');
                return;
            }
            
            console.log('Superset: Fetching guest token...');
            container.innerHTML = '<div class=\"text-center p-5\"><div class=\"spinner-border\"></div><p>Loading dashboard...</p></div>';
            
            // Fetch token via Moodle proxy (bypasses CORS)
            fetch('$tokenproxyurl', {
                method: 'GET',
                credentials: 'same-origin'
            })
            .then(function(response) {
                console.log('Superset: Token response status:', response.status);
                if (!response.ok) {
                    return response.json().then(function(err) {
                        throw new Error(JSON.stringify(err));
                    });
                }
                return response.json();
            })
            .then(function(data) {
                console.log('Superset: Token received');
                if (!data.token) {
                    throw new Error('No token in response');
                }
                
                // Build iframe URL with guest token
                var dashboardUrl = '$superseturl/superset/dashboard/$dashboardid/?guest_token=' + data.token + '&standalone=3';
                console.log('Superset: Loading dashboard URL');
                
                // Create iframe
                var iframe = document.createElement('iframe');
                iframe.src = dashboardUrl;
                iframe.style.width = '100%';
                iframe.style.height = '700px';
                iframe.style.border = 'none';
                iframe.frameBorder = '0';
                
                container.innerHTML = '';
                container.appendChild(iframe);
            })
            .catch(function(error) {
                console.error('Superset: Error:', error);
                container.innerHTML = '<div class=\"alert alert-danger m-3\">Error: ' + error.message + '<br>Check browser console (F12) for details</div>';
            });
        })();
        </script>
        ";

        $this->content->text = $container . $inlinescript;

        return $this->content;
    }

    /**
     * Serialize and store config data
     */
    public function instance_config_save($data, $nolongerused = false) {
        parent::instance_config_save($data, $nolongerused);
    }
}
