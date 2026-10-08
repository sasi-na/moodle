<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Full-page Superset Analytics Dashboard
 *
 * @package    block_supersetembed
 * @copyright  2025 JO LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

// Only allow site admins
if (!is_siteadmin($USER->id)) {
    print_error('nopermissions', 'error', '', 'Access analytics dashboard');
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/blocks/supersetembed/analytics.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('analytics', 'block_supersetembed'));
$PAGE->set_heading(get_string('analytics', 'block_supersetembed'));

// Get configuration
$superseturl = get_config('block_supersetembed', 'superseturl');
$dashboardid = get_config('block_supersetembed', 'dashboardid');

if (empty($superseturl)) {
    $superseturl = 'https://superset.thekaios.com';
}
if (empty($dashboardid)) {
    $dashboardid = '4';
}

$tokenproxyurl = new moodle_url('/blocks/supersetembed/get_token.php');

echo $OUTPUT->header();

// Container for dashboard
echo '<div id="superset-analytics-container" style="width: 100%; height: calc(100vh - 200px); background: #f8f9fa; position: relative;">
    <div class="text-center p-5">
        <div class="spinner-border"></div>
        <p>Loading analytics dashboard...</p>
    </div>
</div>';

// JavaScript to load dashboard
echo "
<script>
(function() {
    var container = document.getElementById('superset-analytics-container');
    
    fetch('{$tokenproxyurl}', {
        method: 'GET',
        credentials: 'same-origin'
    })
    .then(function(response) {
        if (!response.ok) {
            return response.json().then(function(err) {
                throw new Error(JSON.stringify(err));
            });
        }
        return response.json();
    })
    .then(function(data) {
        if (!data.token) {
            throw new Error('No token in response');
        }
        
        var dashboardUrl = '{$superseturl}/superset/dashboard/{$dashboardid}/?guest_token=' + data.token + '&standalone=3';
        
        var iframe = document.createElement('iframe');
        iframe.src = dashboardUrl;
        iframe.style.width = '100%';
        iframe.style.height = '100%';
        iframe.style.border = 'none';
        iframe.frameBorder = '0';
        
        container.innerHTML = '';
        container.appendChild(iframe);
    })
    .catch(function(error) {
        console.error('Analytics Error:', error);
        container.innerHTML = '<div class=\"alert alert-danger m-3\">Error loading analytics: ' + error.message + '</div>';
    });
})();
</script>
";

echo $OUTPUT->footer();
