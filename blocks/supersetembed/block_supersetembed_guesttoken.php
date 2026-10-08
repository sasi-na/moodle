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
                'style' => 'width: 100%; min-height: 600px; border: 2px solid #007bff; border-radius: 4px; background: #f8f9fa;'
            )
        );

        // Use local proxy to avoid CORS issues
        $tokenproxyurl = new moodle_url('/blocks/supersetembed/get_token.php');
        
        // Add inline script to embed immediately
        $inlinescript = "
        <script>
        (function() {
            var container = document.getElementById('$embedid');
            if (!container) {
                console.error('Superset: Container not found!');
                return;
            }
            
            console.log('Superset: Starting embed process...');
            console.log('Superset URL:', '$superseturl');
            console.log('Dashboard ID:', '$dashboardid');
            
            container.innerHTML = '<div class=\"text-center p-5\"><div class=\"spinner-border\"></div><p>Loading Superset SDK...</p></div>';
            
            // Try loading from npm CDN first
            var sdkUrl = 'https://cdn.jsdelivr.net/npm/@superset-ui/embedded-sdk@0.1.0-alpha.10/bundle/index.js';
            console.log('Superset: Loading SDK from CDN:', sdkUrl);
            
            var script = document.createElement('script');
            script.src = sdkUrl;
            script.onload = function() {
                console.log('Superset: SDK loaded successfully from CDN');
                container.innerHTML = '<div class=\"text-center p-5\"><div class=\"spinner-border\"></div><p>Fetching guest token...</p></div>';
                
                // Fetch token via Moodle proxy (bypasses CORS)
                console.log('Superset: Fetching token via proxy...');
                fetch('$tokenproxyurl', {
                    method: 'GET',
                    credentials: 'same-origin'
                })
                .then(function(response) {
                    console.log('Superset: Proxy response status:', response.status);
                    if (!response.ok) {
                        throw new Error('Proxy returned status ' + response.status);
                    }
                    return response.json();
                })
                .then(function(data) {
                    console.log('Superset: Token received via proxy');
                    container.innerHTML = '<div class=\"text-center p-5\"><div class=\"spinner-border\"></div><p>Embedding dashboard...</p></div>';
                    
                    // Use the SDK from CDN
                    if (window.supersetEmbeddedSdk) {
                        container.innerHTML = '';
                        window.supersetEmbeddedSdk.embedDashboard({
                            id: '$dashboardid',
                            supersetDomain: '$superseturl',
                            mountPoint: container,
                            fetchGuestToken: function() { return data.token; },
                            dashboardUiConfig: {
                                hideTitle: false,
                                hideChartControls: false,
                                hideTab: false
                            }
                        });
                        console.log('Superset: Dashboard embedded successfully');
                    } else {
                        throw new Error('supersetEmbeddedSdk not available');
                    }
                })
                .catch(function(error) {
                    console.error('Superset: Error:', error);
                    container.innerHTML = '<div class=\"alert alert-danger\"><strong>Error:</strong> ' + error.message + '<br><small>Check browser console (F12) for details</small></div>';
                });
            };
            script.onerror = function(e) {
                console.error('Superset: Failed to load SDK from CDN');
                container.innerHTML = '<div class=\"alert alert-danger\"><strong>Failed to load Superset Embedded SDK</strong><br>Could not load from CDN. Check your internet connection.</div>';
            };
            document.head.appendChild(script);
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
