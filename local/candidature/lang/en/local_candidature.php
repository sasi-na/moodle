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
 * Strings for local_candidature.
 *
 * @package   local_candidature
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Candidate approval';
$string['queue'] = 'Candidate approvals';
$string['settings'] = 'Candidate approval';

$string['candidature:review'] = 'Review candidates in a programme';
$string['candidature:reviewall'] = 'Review candidates in every programme';
$string['candidature:oversee'] = 'Be notified when a candidate is left undecided';

// Settings.
$string['enabled'] = 'Enable the approval queue';
$string['enabled_desc'] = 'When off, nothing is gated and no candidate is ever placed in the queue.';
$string['startfrom'] = 'Applies to accounts created on or after';
$string['startfrom_desc'] = 'A date as <code>YYYY-MM-DD</code>. Only accounts created on or after it can enter the queue, and only if they hold no role and no course enrolment. <strong>Leave empty and nobody is ever captured</strong> — this is what stops the queue sweeping up existing staff and learners when it is switched on.';
$string['slahours'] = 'Review window (hours)';
$string['slahours_desc'] = 'How long a candidate may wait for a decision before oversight is notified.';
$string['rejectblockslogin'] = 'Rejection blocks sign-in';
$string['rejectblockslogin_desc'] = 'Sets the "Block Access" profile field on rejection, which refuses the sign-in outright. Turn this off to let a rejected candidate still sign in and see the reason. Approving always clears the field, including when an earlier rejection is overturned.';
$string['programfield'] = 'Programme profile field';
$string['programfield_desc'] = 'Shortname of the profile field holding the candidate\'s programme, used to route them to the right reviewer. Leave empty to leave every candidate unassigned.';
$string['batchfield'] = 'Batch profile field';
$string['batchfield_desc'] = 'Shortname of the profile field holding the candidate\'s batch. Combined with the programme field to find the category.';

$string['task_scansla'] = 'Flag candidates left undecided';

// The holding page.
$string['pendingtitle'] = 'Your account is being reviewed';
$string['pending_heading'] = 'Thanks — your profile is with us';
$string['pending_body'] = 'Your details have been submitted and are being reviewed. This usually takes up to {$a} hours. You will get an email as soon as a decision is made, and your courses will appear here once your account is approved.';
$string['pending_submitted'] = 'Submitted {$a}';
$string['pending_contact'] = 'Contact support';
$string['rejected_heading'] = 'Your application was not approved';
$string['rejected_body'] = 'Your profile has been reviewed and could not be approved. If you think this is a mistake, or you have been asked to correct something, update your profile or contact support.';

// The queue.
$string['queue_note'] = 'Candidates are shown oldest first. Oversight is notified about anyone still waiting after {$a} hours.';
$string['queue_empty'] = 'Nothing to review.';
$string['unassigned'] = 'Unassigned';
$string['flagged'] = 'flagged';
$string['notyours'] = 'Another programme';
$string['truncated'] = 'Only the first 300 candidates are shown. Narrow the filter to see the rest.';
$string['reasonplaceholder'] = 'Reason (optional)';

$string['approve'] = 'Approve';
$string['reject'] = 'Reject';
$string['reopen'] = 'Reopen';

$string['col_candidate'] = 'Candidate';
$string['col_programme'] = 'Programme';
$string['col_submitted'] = 'Submitted';
$string['col_waiting'] = 'Waiting';
$string['col_status'] = 'Status';
$string['col_decision'] = 'Decision';

$string['filter_pending'] = 'Pending';
$string['filter_flagged'] = 'Overdue';
$string['filter_approved'] = 'Approved';
$string['filter_rejected'] = 'Rejected';
$string['filter_all'] = 'All';

$string['status_pending'] = 'Pending';
$string['status_approved'] = 'Approved';
$string['status_rejected'] = 'Rejected';

$string['done_approved'] = 'Candidate approved. They now have access.';
$string['done_rejected'] = 'Candidate rejected.';
$string['done_reopened'] = 'Candidate returned to the queue.';

// Message providers.
$string['messageprovider:decision'] = 'A decision on your account';
$string['messageprovider:submitted'] = 'A candidate is waiting for your review';
$string['messageprovider:slaflag'] = 'A candidate has been left undecided';

$string['notify_submitted_subject'] = '{$a->name} is waiting for review';
$string['notify_submitted_body'] = '{$a->name} ({$a->email}) has completed their profile and is waiting for a decision.

Programme: {$a->programme}

A decision is expected within {$a->hours} hours.';

$string['notify_approved_subject'] = 'Your account has been approved';
$string['notify_approved_body'] = 'Hi {$a->firstname},

Your account on {$a->sitename} has been approved. You can sign in now and your courses will be waiting for you.';

$string['notify_rejected_subject'] = 'About your account';
$string['notify_rejected_body'] = 'Hi {$a->firstname},

Your account on {$a->sitename} has been reviewed and could not be approved at this time.

Reason: {$a->reason}

If you believe this is a mistake, please contact support.';

$string['notify_flag_subject'] = '{$a->name} has been waiting too long';
$string['notify_flag_body'] = '{$a->name} has been waiting for a decision longer than the {$a->hours}-hour review window.

Programme: {$a->programme}
Waiting:   {$a->pending}

This is for follow-up only; the candidate has not been reassigned or decided.';

$string['privacy:metadata:local_candidature'] = 'The approval state of a candidate account.';
$string['privacy:metadata:local_candidature:userid'] = 'The candidate.';
$string['privacy:metadata:local_candidature:status'] = 'Whether the candidate is pending, approved or rejected.';
$string['privacy:metadata:local_candidature:reason'] = 'The reason recorded with a decision.';
$string['privacy:metadata:local_candidature:submittedtime'] = 'When the profile was submitted for review.';
