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
 * Strings for component 'msteams', language 'en', branch 'MOODLE_20_STABLE'
 *
 * @package    mod_msteams
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com} (mod_url)
 *             2020 Robert Schrenk (mod_msteams)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


$string['urlsuccessfullycreated'] = 'Your Meeting-URL was successfully created.';

$string['clicktoopen'] = 'Click {$a} link to open resource.';
$string['contentheader'] = 'Content';
$string['createurl'] = 'Create a MS Teams Meeting';
$string['externalurl'] = 'MS Teams URL';
$string['invalidstoredurl'] = 'Cannot display this resource, the meeting-URL is invalid.';

$string['indicator:cognitivedepth'] = 'MS Teams cognitive';
$string['indicator:cognitivedepth_help'] = 'This indicator is based on the cognitive depth reached by the student in a MS Teams resource.';
$string['indicator:cognitivedepthdef'] = 'MS Teams cognitive';
$string['indicator:cognitivedepthdef_help'] = 'The participant has reached this percentage of the cognitive engagement offered by the MS Teams resources during this analysis interval (Levels = No view, View)';
$string['indicator:cognitivedepthdef_link'] = 'Learning_analytics_indicators#Cognitive_depth';
$string['indicator:socialbreadth'] = 'MS Teams social';
$string['indicator:socialbreadth_help'] = 'This indicator is based on the social breadth reached by the student in a MS Teams resource.';
$string['indicator:socialbreadthdef'] = 'MS Teams social';
$string['indicator:socialbreadthdef_help'] = 'The participant has reached this percentage of the social engagement offered by the MS Teams resources during this analysis interval (Levels = No participation, Participant alone)';
$string['indicator:socialbreadthdef_link'] = 'Learning_analytics_indicators#Social_breadth';
$string['coorganizeruserid'] = 'Co-organizer';
$string['coorganizeruserid_help'] = 'Select a Moodle user whose email address matches a Microsoft Teams account in your tenant. The plugin will request this user as a Teams co-organizer for new meetings.';
$string['invalidurl'] = 'Entered MS Teams URL is invalid';
$string['issuerid'] = 'Microsoft OAuth issuer';
$string['issuerid_desc'] = 'Select the Microsoft OAuth 2 service used to create Teams meetings. Configure the issuer and connect a system account in <a href="{$a}">OAuth 2 services</a>. The system account must grant the OnlineMeetings.ReadWrite permission.';
$string['joinmeeting'] = 'Join meeting';
$string['lobbyopen'] = 'Participants can join without waiting in the lobby';
$string['lobbyrequired'] = 'Participants must wait in the lobby until admitted';
$string['lobbysetting'] = 'Lobby access';
$string['meetingdetailstitle'] = 'Meeting details';
$string['meetingparticipantduplicate'] = 'Choose different users for presenter and co-organizer, or leave one of the fields empty.';
$string['meetingparticipantinvaliduser'] = 'The selected Moodle user is invalid, deleted, or suspended.';
$string['meetingparticipantmissingemail'] = 'The selected Moodle user {$a} does not have an email address. A valid email is required to request Teams presenter or co-organizer access.';
$string['meetingcreationinvalidresponse'] = 'Microsoft Graph did not return the meeting URL and meeting ID.';
$string['meetingcreationinvalidtimes'] = 'The meeting end date and time must be later than the start date and time.';
$string['meetingcreationinvalidissuer'] = 'The selected Microsoft OAuth issuer is invalid, disabled, or not configured.';
$string['meetingcreationnotconfigured'] = 'MS Teams meeting creation is not configured. Select a Microsoft OAuth issuer in the plugin settings.';
$string['meetingcreationnosystemaccount'] = 'The Microsoft OAuth issuer "{$a}" does not currently have a connected system account with the OnlineMeetings.ReadWrite permission. Reconnect the system account in OAuth 2 services and grant the required permission before creating meetings.';
$string['meetingcreationoauthclientfailed'] = 'The connected system account for Microsoft OAuth issuer "{$a}" could not be used to obtain a valid access token for Microsoft Graph. Reconnect the system account in OAuth 2 services and make sure the OnlineMeetings.ReadWrite permission is granted.';
$string['meetingend'] = 'Meeting end';
$string['meetingend_help'] = 'The scheduled end date and time for the Teams meeting.';
$string['meetingidentitynotice'] = 'Microsoft Teams controls the attendee identity at join time. If a user is not already signed in to Microsoft Teams or Microsoft 365, Teams may still ask for a name. This cannot be auto-filled securely from Moodle alone.';
$string['meetingidlabel'] = 'Meeting ID';
$string['meetingstart'] = 'Meeting start';
$string['meetingstart_help'] = 'The scheduled start date and time for the Teams meeting.';
$string['meetingstatus'] = 'Status';
$string['meetingstatusavailable'] = 'Available';
$string['meetingstatusended'] = 'Ended';
$string['meetingstatusinprogress'] = 'In progress';
$string['meetingstatusscheduled'] = 'Scheduled';
$string['meetingurlwillbecreated'] = 'The Teams meeting URL will be created automatically when you save the activity.';
$string['presenteruserid'] = 'Presenter';
$string['presenteruserid_help'] = 'Select a Moodle user whose email address matches a Microsoft Teams account in your tenant. The plugin will request this user as a Teams presenter for new meetings.';
$string['requirelobby'] = 'Require lobby approval before joining';
$string['requirelobby_help'] = 'If enabled, participants will wait in the Teams lobby until admitted by the organizer or another permitted presenter. If disabled, the plugin will request direct join for everyone, but Microsoft Teams tenant policy can still override this.';
$string['meetingsapplink'] = 'Meetings App URL';
$string['meetingsapplink_desc'] = 'This is URL of meeting app location';
$string['modulename'] = 'MS Teams Meeting';
$string['modulename_help'] = 'The MS Teams module enables a teacher to provide an invitation link to an MS Teams meeting as a course resource.

Meetings are created automatically using a configured Microsoft OAuth 2 system account. According to the configuration of the Microsoft O365 tenant it is possible to invite users that do not have their own Microsoft account.';
$string['modulename_link'] = 'mod/msteams/view';
$string['modulenameplural'] = 'MS Teams Meetings';
$string['page-mod-url-x'] = 'Any MS Teams module page';
$string['pluginadministration'] = 'MS Teams module administration';
$string['pluginname'] = 'MS Teams Meeting';
$string['printintro'] = 'Display MS Teams description';
$string['printintroexplain'] = 'Display MS Teams description below content? Some display types may not display description even if enabled.';

$string['privacy:metadata:msteamsapp'] = 'The MS Teams App is an external tool to connect users to O365.';
$string['privacy:metadata:msteamsapp:userlang'] = 'The users language is sent from Moodle to the MS Teams App';

$string['search:activity'] = 'MS Teams';
$string['serverurl'] = 'MS Teams URL';
$string['msteams:addinstance'] = 'Add a new MS Teams meeting';
$string['msteams:view'] = 'Open MS Teams meeting';

$string['url'] = 'Meeting-URL';
$string['url_help'] = 'The Meeting-URL is created automatically via Microsoft Graph when the activity is saved.';
