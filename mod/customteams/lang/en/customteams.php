<?php

$string['pluginname'] = 'Custom Teams Meeting';
$string['modulename'] = 'Custom Teams Meeting';
$string['modulenameplural'] = 'Custom Teams Meetings';
$string['pluginadministration'] = 'Custom Teams Meeting administration';

$string['customteams:addinstance'] = 'Add Teams meeting';
$string['customteams:view'] = 'View Teams meeting';

$string['meetingname'] = 'Meeting name';
$string['meetingname_help'] = 'Enter a name for the Teams meeting.';

$string['starttime'] = 'Start time';
$string['starttime_help'] = 'Select when the meeting should start.';

$string['endtime'] = 'End time';
$string['endtime_help'] = 'Select when the meeting should end.';

$string['endtimebeforestart'] = 'End time must be after start time';

$string['nomeetings'] = 'There are no Teams meetings in this course.';
$string['joinmeeting'] = 'Join Teams Meeting';
$string['meetingnotavailable'] = 'The class link will be available at the scheduled date and time';

$string['modulename_help'] = 'Creates a Microsoft Teams meeting and shares it with course participants. Teachers are presenters, students are attendees.';

// Organizer picker.
$string['organizer'] = 'Teams account';
$string['organizer_help'] = 'The shared Teams account that will host this meeting. The list shows only the accounts assigned to this course\'s batch and programme.';
$string['resolvedcohort'] = 'Detected cohort';
$string['resolvedcohortvalue'] = 'Batch: {$a->batch} &middot; Programme: {$a->program}';
$string['unknown'] = 'not detected';

// Errors.
$string['errornotconfigured'] = 'The Microsoft Teams integration is not configured. An administrator must set the tenant, client ID and client secret in the Custom Teams Meeting settings.';
$string['errortoken'] = 'Could not obtain a Microsoft Graph token ({$a}). Check the tenant, client ID and client secret.';
$string['errorcurl'] = 'Could not reach Microsoft Graph: {$a}';
$string['erroruserlookup'] = 'The Teams account {$a} does not exist in the Microsoft tenant.';
$string['errormeetingcreate'] = 'Microsoft Graph refused to create the meeting: {$a}';
$string['errornoorganizer'] = 'Choose the Teams account that will host this meeting.';
$string['errornomicrosoftemail'] = 'Microsoft email not configured. Please contact administrator.';
$string['errornobatch'] = 'No Teams accounts are available: this course is not under a recognised batch category. Ask an administrator to check the course category, or to import the Teams account map.';
$string['errornoprogram'] = 'No Teams accounts are available: the batch "{$a}" was recognised but the programme category was not. Ask an administrator to check the course category.';
$string['errornoaccounts'] = 'No Teams accounts are mapped to {$a->batch} / {$a->program}. Ask an administrator to add one to the Teams account map.';
$string['errorbadorganizer'] = 'That Teams account is not available for this course. Choose one from the list.';
$string['errorbadtimezone'] = 'The configured meeting time zone ({$a}) is not a valid time zone identifier.';
$string['erroremptyjson'] = 'The account map is empty. Use {} to clear every account.';
$string['errorbadjson'] = 'The account map is not valid JSON: {$a}';
$string['errorjsonshape'] = 'The account map must be a JSON object of batch names.';
$string['errorjsonshapeat'] = 'Unexpected structure at "{$a}": expected a list of accounts.';
$string['errorjsonnobatch'] = 'The account map contains an entry with an empty batch name.';
$string['errorjsonnoprogram'] = 'Batch "{$a}" contains an entry with an empty programme name.';
$string['errorjsonbadupn'] = '"{$a->upn}" under {$a->batch} / {$a->program} is not a valid account address.';
$string['errorclash'] = 'That Teams account is already hosting "{$a->name}" from {$a->start} to {$a->end}. Choose another account or a different time.';

// Settings.
$string['settings_azure'] = 'Microsoft Graph credentials';
$string['settings_azure_desc'] = 'App registration used to create meetings. Requires the application permissions Calendars.ReadWrite, User.Read.All and OnlineMeetings.ReadWrite.All, with admin consent granted.';
$string['settings_tenantid'] = 'Directory (tenant) ID';
$string['settings_tenantid_desc'] = 'The Azure AD tenant that owns the shared Teams accounts.';
$string['settings_clientid'] = 'Application (client) ID';
$string['settings_clientid_desc'] = 'The app registration\'s client ID.';
$string['settings_clientsecret'] = 'Client secret';
$string['settings_clientsecret_desc'] = 'The app registration\'s client secret value. Rotate this in Azure and update it here; no redeploy is needed.';

$string['settings_meeting'] = 'Meeting defaults';
$string['settings_timezone'] = 'Meeting time zone';
$string['settings_timezone_desc'] = 'IANA time zone sent to Microsoft Graph for meeting start and end times, for example Asia/Kolkata.';
$string['settings_autorecord'] = 'Record meetings automatically';
$string['settings_autorecord_desc'] = 'Sets recordAutomatically on each meeting. This also needs a Teams application access policy granted to the organizer accounts; without it, meetings are still created but recording is not enabled.';

$string['settings_organizer'] = 'Organizer accounts';
$string['settings_organizer_desc'] = 'Meetings are hosted by shared Teams accounts mapped to a batch and programme. Import the map with mod/customteams/cli/import_accounts.php.';
$string['settings_accountmap'] = 'Teams account map';
$string['settings_accountmap_desc'] = 'Which shared Teams accounts serve which batch and programme, as JSON: an object of batch names, each holding an object of programme names, each holding a list of accounts. A batch or programme name must match the course category name it refers to. An account is either a plain address, or an object with "upn" and optionally "displayname" and "sortorder".<br /><br />Example:<pre>{
    "July 2025": {
        "MBA": ["mbageneral.04@example.ac.in"],
        "MCA": [{"upn": "mcageneral.03@example.ac.in", "displayname": "MCA Section A"}]
    }
}</pre>Saving replaces the map. Accounts removed here are deactivated rather than deleted, so meetings already booked against them keep a resolvable organizer. Batch names are normalised on save ("July-24" becomes "July 2024"), and the box is redrawn from the saved map, so check it after saving. To load a whole intake at once use mod/customteams/cli/import_accounts.php instead.';

$string['settings_fallbackaccounts'] = 'Fallback accounts';
$string['settings_fallbackaccounts_desc'] = 'Accounts offered when a course matches nothing in the map, one user principal name per line. Leave empty to show an error instead.';
$string['settings_allowprofilefallback'] = 'Allow the user profile field fallback';
$string['settings_allowprofilefallback_desc'] = 'If no account is chosen, fall back to the organizer\'s "microsoft_email" user profile field. Off by default: the account map is the supported path and this requires per-teacher data entry.';

// Account check.
$string['checkaccounts'] = 'Check Teams accounts';
$string['checkaccountslink'] = 'Check these accounts against the Microsoft tenant';
$string['checkintro'] = 'This checks all {$a} mapped accounts against the Microsoft tenant, reporting any that no longer exist or have been disabled.';
$string['checkrun'] = 'Run check';
$string['checkback'] = 'Back';
$string['checknoaccounts'] = 'No Teams accounts are mapped yet. Add them in the Teams account map setting, or load them with mod/customteams/cli/import_accounts.php.';
$string['checkcolaccount'] = 'Account';
$string['checkcolstatus'] = 'Status';
$string['checkcolusedby'] = 'Used by';
$string['checkok'] = 'OK';
$string['checkproblem'] = 'Problem';
$string['checkallok'] = 'All {$a} accounts exist and are enabled.';
$string['checksummary'] = '{$a->failed} of {$a->total} accounts need attention.';
$string['checknotfound'] = 'No such account in the tenant.';
$string['checkdisabled'] = 'The account exists but is disabled, so it cannot host a meeting.';
$string['checkfailed'] = 'The check did not succeed for this account.';
$string['checknoresponse'] = 'Microsoft Graph returned no result for this account.';

// Privacy.
$string['privacy:metadata'] = 'The Custom Teams Meeting plugin sends meeting details to Microsoft Graph but does not store personal data in Moodle beyond the meeting record.';
