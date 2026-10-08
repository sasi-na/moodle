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
 * Strings for component 'block_supersetembed', language 'en'
 *
 * @package    block_supersetembed
 * @copyright  2025 JO LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Superset Dashboard';
$string['supersetembed'] = 'Superset Dashboard';
$string['supersetembed:addinstance'] = 'Add a new Superset Dashboard block';
$string['supersetembed:myaddinstance'] = 'Add a new Superset Dashboard block to Dashboard';
$string['analytics'] = 'Analytics';

// Settings
$string['apiurl'] = 'API Gateway URL';
$string['apiurl_desc'] = 'The URL of the API Gateway endpoint to fetch guest tokens (e.g., https://qa.ams.dashboard.thekaios.com/api/v1/superset/guest-token)';
$string['superseturl'] = 'Superset URL';
$string['superseturl_desc'] = 'The base URL of your Superset instance (e.g., https://superset.thekaios.com)';
$string['dashboardid'] = 'Dashboard ID';
$string['dashboardid_desc'] = 'The UUID of the Superset dashboard to embed (e.g., b4d937de-338a-46d0-8df8-e37f51601290)';

// Errors
$string['pleaseconfigure'] = 'Please configure the Superset Dashboard block settings in Site Administration.';
$string['error:fetchtoken'] = 'Error fetching guest token from API';
$string['error:embeddashboard'] = 'Error embedding dashboard';

// Privacy
$string['privacy:metadata'] = 'The Superset Dashboard block only displays data from an external Superset instance and does not store any personal data.';
