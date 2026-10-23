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
 * Zoom Online block
 *
 * @package    block_zoomonline
 * @copyright  2024 Ciaran Mac Donncha
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Zoom Online';

// General settings headings.
$string['headerconfig'] = 'Zoom Online Configuration';
$string['descconfig'] = 'Configure the settings for the Zoom Online block.';
$string['process_zoom_meetings'] = 'Process Zoom Meetings';

// Zoom Client ID and Secret.
$string['clientid'] = 'Zoom Client ID';
$string['clientid_desc'] = 'Enter your Zoom OAuth Client ID.';
$string['clientsecret'] = 'Zoom Client Secret';
$string['clientsecret_desc'] = 'Enter your Zoom OAuth Client Secret.';
$string['accountid'] = 'Zoom Account ID';
$string['accountid_desc'] = 'Enter your Zoom Account ID used for OAuth.';

// AWS S3 Settings.
$string['aws_key'] = 'AWS S3 Access Key';
$string['aws_key_desc'] = 'Enter your AWS S3 Access Key ID.';
$string['aws_secret'] = 'AWS S3 Secret Key';
$string['aws_secret_desc'] = 'Enter your AWS S3 Secret Access Key.';
$string['aws_region'] = 'AWS S3 Region';
$string['aws_region_desc'] = 'Enter the AWS region where your S3 bucket is located.';
$string['aws_bucket'] = 'AWS S3 Bucket Name';
$string['aws_bucket_desc'] = 'Enter the name of your AWS S3 bucket where videos will be stored.';

// Embed Type Options.
$string['embedtype'] = 'Embed Type';
$string['embedtypedesc'] = 'Choose whether to embed videos in a Book or a Label.';
$string['embedbook'] = 'Book';
$string['embedlabel'] = 'Label';

// Attendance Tracking.
$string['useattendance'] = 'Enable Attendance Tracking';
$string['useattendance_desc'] = 'Enable or disable attendance tracking for Zoom meetings.';

// Storage Type Options.
$string['storagetype'] = 'Storage Type';
$string['storagetype_desc'] = 'Choose where to store recorded videos: in AWS S3 or locally on the Moodle server.';
$string['storagetype_aws'] = 'AWS S3';
$string['storagetype_local'] = 'Local Storage';

$string['localfolder'] = 'Temp Storage Folder (system tmp used if not specified)';
$string['localfolder_desc'] =
        'Specify the path for temporarily storing videos locally on the Moodle server before being uploaded to aws or moodle storage.';

$string['privacy:metadata'] = 'The block only retrieves videos and attendance from ZOOM related to an online class, no personal data on specific users are stored.';

$string['latetime'] = 'Late threshold (seconds)';
$string['latetime_desc'] = 'Number of seconds a student can be late before being marked as "Late".';

$string['attendance_threshold'] = 'Attendance percentage threshold';
$string['attendance_threshold_desc'] = 'Minimum percentage of session duration that must be attended to avoid "Late" status.';

$string['early_departure_threshold'] = 'Early departure threshold (seconds)';
$string['early_departure_threshold_desc'] =
        'Number of seconds from session end that a student can leave before being marked as "Late".';

$string['zoomonline:myaddinstance'] = 'Add a new Zoom Online block to My home';
$string['zoomonline:addinstance'] = 'Add a new Zoom Online block';
$string['zoomonline:view'] = 'View the Zoom Online block';

$string['attendance_module_warning'] = 'Warning - Attendance module not installed. Please install <a href="https://moodle.org/plugins/mod_attendance">mod_attendance</a>.';
$string['zoom_api_missing_warning'] = 'Warning - Zoom API client_id, client_secret, or account_id missing. Please check plugin configuration.';
$string['course_end_warning_manage'] = 'Warning - This course\'s end date is in the past, so this will not generate links.';
$string['course_end_warning_view'] = 'There are no more online classes for this module.';

$string['zoom_play_with_transcript'] = "Click here to play video in Zoom with transcript, you must be signed into Zoom";

$string['username_field'] = 'Username Field for Attendance Matching';
$string['username_field_desc'] = 'Select whether to use the email address or the Zoom name for matching student in Moodle, zoom name matches the zoom display name with the Moodle student username so ensure they set this to their student number when joining, if they have a school zoom account then the email will be returned and they email can be compared with the student email. Note if the student is using a non school zoom account and using a free zoom then the email will not be returned in the payload, they only way to match will be to ask the student to use their student number as their display name and ensure zoom name is selected in the above dropdown ';
$string['username_field_email'] = 'Email';
$string['username_field_zoomname'] = 'Zoom Name';

$string['error'] = 'Error';
$string['failed_update_visibility'] = 'Failed to update visibility: ';

$string['privacy:metadata:lecturer_id'] = 'The ID of the teacher who created the Zoom meeting.';
$string['privacy:metadata:zoom_meeting_id'] = 'The Zoom meeting ID associated with the course/group.';
$string['privacy:metadata:course_id'] = 'The Moodle course ID associated with the meeting.';
$string['privacy:metadata:groupid'] = 'The Moodle group ID for which the meeting was created.';
$string['privacy:metadata:visible'] = 'Indicates whether the meeting is visible to students (1=visible, 2=hidden).';
$string['privacy:metadata:summary'] = 'Data related to Zoom meetings created in the plugin.';
$string['zoomonline:managevisibility'] = 'Manage Zoom Online visibility';

$string['privacy:metadata:block_zoomonline_links'] = 'Description of the data stored by block_zoomonline_links.';
$string['privacy:metadata:zoom_email'] = 'The user email is sent to Zoom to associate meetings with their account.';
$string['privacy:metadata:zoom_firstname'] = 'First name of the user, sent to Zoom for meeting display name.';
$string['privacy:metadata:zoom_lastname'] = 'Last name of the user, sent to Zoom for meeting display name.';
$string['privacy:metadata:zoom_summary'] = 'This plugin integrates with Zoom API to create and manage meetings.';

$string['timezone'] = 'Default Timezone';
$string['timezone_desc'] = 'Set the default timezone for Zoom meetings.';

$string['force_groups'] = 'Enforce group membership';
$string['force_groups_desc'] = 'If enabled, users will be split into different join links per group';
