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
 * Strings for local_fileshare.
 *
 * @package   local_fileshare
 * @copyright 2026 onlinejain.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Secure share links';
$string['privacy:metadata:local_fileshare'] = 'Share links created for files submitted to a quiz.';
$string['privacy:metadata:local_fileshare:filename'] = 'The name of the shared file.';
$string['privacy:metadata:local_fileshare:createdby'] = 'The user who created the share link.';
$string['privacy:metadata:local_fileshare:studentid'] = 'The user whose submitted file was shared.';
$string['privacy:metadata:local_fileshare:recipient'] = 'A note recording who the link was given to.';
$string['privacy:metadata:local_fileshare:timecreated'] = 'When the link was created.';
$string['privacy:metadata:local_fileshare:downloads'] = 'How many times the link has been used.';
$string['privacy:metadata:local_fileshare:lastaccess'] = 'When the link was last used.';

// Capabilities.
$string['fileshare:share'] = 'Create a share link for a submitted file';
$string['fileshare:manageall'] = 'See and revoke every share link on the site';

// Share form.
$string['createlink'] = 'Create a share link';
$string['file'] = 'File';
$string['expiry'] = 'Link expires after';
$string['expiry_help'] = 'How long the link keeps working. Keep it as short as the recipient needs — a link that has expired cannot be used even if it has been forwarded.';
$string['expirymax'] = 'The longest a link may last is {$a} days.';
$string['expirytooshort'] = 'Give the link at least an hour.';
$string['expirytoolong'] = 'A link may last at most {$a} days.';
$string['maxdownloads'] = 'Stop after this many downloads';
$string['maxdownloads_help'] = 'The link stops working once it has been used this many times. Leave it at 0 for no limit.';
$string['maxdownloadsinvalid'] = 'Enter 0 or a positive number.';
$string['iprestriction'] = 'Restrict to IP addresses';
$string['iprestriction_help'] = 'Optional. A comma-separated list of addresses or ranges, for example 203.0.113.0/24. Leave it empty if the recipient will open the link from anywhere.';
$string['iprestrictioninvalid'] = 'Enter addresses or ranges separated by commas, for example 203.0.113.0/24.';
$string['recipient'] = 'Who is it for';
$string['recipient_help'] = 'Recorded against the link so it is clear later who was given access.';
$string['sharewarning'] = 'This creates a link that works without a Moodle login. Anyone who has it can download this file until it expires or you revoke it.';

// Result.
$string['linkcreated'] = 'Share link created';
$string['linkcreated_desc'] = 'This link works without a login and stops working on {$a}.';
$string['linkonce'] = 'Copy the link now. The token is not stored, so it cannot be shown again — if you lose it, create another link and revoke this one.';
$string['backtoreview'] = 'Back to the attempt';

// Manage.
$string['managelinks'] = 'Share links';
$string['managelinks_desc'] = 'Links you have created. Revoking one stops it working immediately.';
$string['nolinks'] = 'You have not created any share links.';
$string['nolinks_where'] = 'Share links are created from a learner\'s file on a quiz attempt you can review — open the attempt and use “Create share link” beside the attachment, or the bulk page on the quiz itself.';
$string['student'] = 'Submitted by';
$string['state'] = 'State';
$string['used'] = 'Used';
$string['expires'] = 'Expires';
$string['lastaccess'] = 'Last used';
$string['revoke'] = 'Revoke';
$string['linkrevoked'] = 'The link has been revoked.';
$string['state_live'] = 'Live';
$string['state_expiring'] = 'Expiring';
$string['state_expired'] = 'Expired';
$string['state_revoked'] = 'Revoked';
$string['state_spent'] = 'Limit reached';

// Errors.
$string['sharingdisabled'] = 'Share links are switched off.';
$string['filenotfound'] = 'That file could not be found.';
$string['filechanged'] = 'The file has changed since the link was created.';
$string['sharerevoked'] = 'That link has been revoked.';
$string['sharespent'] = 'That link has reached its download limit.';

// Events.
$string['event_share_created'] = 'Share link created';
$string['event_share_accessed'] = 'Share link used';

// Tasks and settings.
$string['task_cleanup'] = 'Retire expired share links';
$string['setting_enabled'] = 'Enable share links';
$string['setting_enabled_desc'] = 'Allow holders of the sharing capability to create links that serve a submitted file without a Moodle login. Off until deliberately turned on.';
$string['setting_defaultexpiry'] = 'Default expiry (days)';
$string['setting_defaultexpiry_desc'] = 'How long a new link lasts unless the author shortens it.';
$string['setting_maxexpiry'] = 'Maximum expiry (days)';
$string['setting_maxexpiry_desc'] = 'The longest life an author may give a link. Capped at 90 days in code whatever is set here.';

// Bulk export.
$string['bulktitle'] = 'Share links for this quiz';
$string['bulkexport'] = 'Create links and download spreadsheet';
$string['bulkwarning'] = 'This creates a working link for all {$a} attachments at once. The spreadsheet you download will contain live links, so treat the file itself as confidential.';
$string['noattachments'] = 'No files have been submitted to this quiz.';

$string['recipientrequired'] = 'Record who the links are for before creating them.';
$string['nolinkscreated'] = 'No links could be created. You may not be permitted to review any of these attempts.';
$string['bulkskipped'] = '{$a} attachment(s) were skipped because you are not permitted to review those attempts.';
