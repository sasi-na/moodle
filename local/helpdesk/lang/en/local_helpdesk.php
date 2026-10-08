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
 * Strings for local_helpdesk.
 *
 * Nothing here names an institution: the responder identity a student sees is resolved
 * from the ticket's assignee at render time, so the same strings serve every site.
 *
 * @package    local_helpdesk
 * @copyright  2026 onwards
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Student support';

$string['helpdesk:raise'] = 'Raise a support request';
$string['helpdesk:manage'] = 'Manage the support queue';
$string['helpdesk:respondforwarded'] = 'Respond to forwarded requests';
$string['helpdesk:oversee'] = 'View support reporting and oversight';
$string['helpdesk:setlegalhold'] = 'Place a request on legal hold';
$string['helpdesk:managemacros'] = 'Manage canned replies';

$string['privacy:metadata:ticket'] = 'Support tickets raised by the user.';
$string['privacy:metadata:ticket:subject'] = 'The subject line of the request.';
$string['privacy:metadata:ticket:category'] = 'The category chosen for the request.';
$string['privacy:metadata:ticket:timecreated'] = 'When the request was raised.';
$string['privacy:metadata:message'] = 'Messages written on a support ticket.';
$string['privacy:metadata:message:message'] = 'The text of the message.';
$string['privacy:metadata:message:timecreated'] = 'When the message was written.';

// Settings.
$string['enabled'] = 'Enable student support';
$string['enabled_desc'] = 'Turn the helpdesk on for this site. Off by default, so a site that does not use it is unaffected. When off, the entry points are hidden and the scheduled tasks do nothing.';
$string['referenceprefix'] = 'Ticket number prefix';
$string['referenceprefix_desc'] = 'Prefix for ticket numbers, for example NITK gives NITK-00042. Students quote these, so keep it short and recognisable.';

$string['slaheading'] = 'Turnaround';
$string['slaheading_desc'] = 'All clocks are wall-clock: they run overnight and at weekends. A student told "within 24 hours" gets 24 hours whatever day it is. Set the coverage hours below to record when the team is actually staffed; it is used for reporting and for what students are told, and has no effect on when a deadline falls.';
$string['firstresponsehours'] = 'First response (hours)';
$string['firstresponsehours_desc'] = 'Hours allowed for the first reply the student can see. Internal notes between staff do not count.';
$string['resolutionhours'] = 'Resolution (hours)';
$string['resolutionhours_desc'] = 'Hours allowed to resolve, end to end. Forwarding to a teaching assistant or faculty member does not pause or transfer this clock.';
$string['specialisthours'] = 'Specialist response (hours)';
$string['specialisthours_desc'] = 'Hours a forwarded ticket may sit unanswered before it is flagged. Keep it below the resolution window so there is still time to pass the answer on.';
$string['coveragehours'] = 'Coverage hours';
$string['coveragehours_desc'] = 'When the support team is staffed, for example 09:00-22:00. Recorded for reporting only.';

$string['retentionheading'] = 'Retention';
$string['retentionheading_desc'] = 'Closed tickets are kept in full for the period below, then stripped rather than deleted: attachments and message text go, and the ticket keeps its category, channel, timings, turnaround outcome and satisfaction score so reporting survives without holding personal data. Tickets marked for legal hold are never touched, and a deleted user\'s tickets are stripped immediately.';
$string['retentiondays'] = 'Retain closed tickets (days)';
$string['retentiondays_desc'] = 'Days after closure before a ticket is anonymised.';

$string['channelheading'] = 'Additional channels';
$string['channelheading_desc'] = 'Shown to students alongside the in-platform form. Leave blank to hide.';
$string['supportemail'] = 'Support email address';
$string['supportemail_desc'] = 'Displayed as an alternative way to raise a ticket.';
$string['slackurl'] = 'Slack channel invite';
$string['slackurl_desc'] = 'Link students follow to join the support channel.';

// Tasks.
$string['task_scansla'] = 'Raise support turnaround flags';
$string['task_retention'] = 'Apply support ticket retention';

// Status.
$string['status_new'] = 'New';
$string['status_open'] = 'Open';
$string['status_inprogress'] = 'In progress';
$string['status_escalated'] = 'Escalated';
$string['status_resolved'] = 'Resolved';
$string['status_closed'] = 'Closed';
$string['status_merged'] = 'Merged';

// Flags.
$string['flag_noresponse'] = 'No first response';
$string['flag_noaction'] = 'No action taken';
$string['flag_specialistsilent'] = 'Specialist has not replied';

// Pages.
$string['notenabled'] = 'Student support is not enabled on this site.';
$string['raisetitle'] = 'Contact support';
$string['raiseintro'] = 'Describe your issue and our team will get back to you.';
$string['mytickets'] = 'Your tickets';
$string['ticket'] = 'Ticket';
$string['submitticket'] = 'Submit ticket';
$string['supportteam'] = 'Support team';
$string['openTickets'] = 'Open';
$string['closedtickets'] = 'Closed';
$string['noticketsyet'] = 'You have not raised any tickets yet.';
$string['raisedon'] = 'Raised {$a}';
$string['duein'] = 'Response due in {$a}';
$string['overdueby'] = 'Overdue by {$a}';
$string['respondwithin'] = 'We aim to reply within {$a} hours.';
$string['otherchannels'] = 'Other ways to reach us';
$string['viaemail'] = 'Email us at {$a}';
$string['viaslack'] = 'Join the support channel on Slack';
$string['ticketcreated'] = 'Your ticket has been created.';
$string['ticketcreated_detail'] = 'Ticket {$a->reference}. We will reply within {$a->hours} hours.';
$string['internalnote'] = 'Internal note';
$string['writtenby'] = 'Written by {$a}';
$string['addreply'] = 'Add a reply';
$string['send'] = 'Send';

// Queue.
$string['queue'] = 'Support queue';
$string['filter_open'] = 'Open';
$string['filter_unassigned'] = 'Unassigned';
$string['filter_mine'] = 'Assigned to me';
$string['filter_breached'] = 'Overdue';
$string['filter_resolved'] = 'Resolved';
$string['filter_closed'] = 'Closed';
$string['filter_all'] = 'All';
$string['allcategories'] = 'All categories';
$string['apply'] = 'Apply';
$string['noticketshere'] = 'Nothing here.';
$string['unassigned'] = 'Unassigned';
$string['take'] = 'Take';
$string['anonymised'] = 'Anonymised';
$string['withspecialist'] = 'with {$a}';
$string['queuetruncated'] = 'Showing the first 200 requests. Narrow the filter to see the rest.';

// Staff actions.
$string['raisedby'] = 'Raised by';
$string['assignedto'] = 'Assigned to';
$string['forwardedto'] = 'Forwarded to';
$string['forwardfor'] = 'Forward for specialist input';
$string['forwardnote'] = 'What do you need from them?';
$string['forwardhint'] = 'The student is not told. Their reply reaches the student under your name, and the resolution clock keeps running.';
$string['forward'] = 'Forward';
$string['nosuchspecialist'] = 'That person cannot be forwarded a support request.';
$string['nospecialists'] = 'There is nobody to forward to yet. Answering forwarded requests is an appointment to the support rota: give a role local/helpdesk:respondforwarded, then assign that role to your teaching assistants and faculty as a system role under Site administration.';
$string['markresolved'] = 'Mark resolved';
$string['closeticket'] = 'Close';
$string['reopenticket'] = 'Reopen';
$string['returntoqueue'] = 'Return to queue';
$string['returnreason'] = 'Why are you returning it?';
$string['setlegalhold'] = 'Place on legal hold';
$string['releasehold'] = 'Release legal hold';
$string['onlegalhold'] = 'Legal hold';

// Forwarded.
$string['forwardedtome'] = 'Forwarded to you';
$string['forwardedintro'] = 'These need your input. Please reply within {$a} hours; the student sees your answer under the program manager\'s name.';
$string['nothingforwarded'] = 'Nothing has been forwarded to you.';
$string['alreadyanswered'] = 'You have replied to these';
$string['respond'] = 'Respond';

// Form fields.
$string['field_category'] = 'Issue category';
$string['field_subject'] = 'Subject';
$string['field_description'] = 'Describe the issue';
$string['field_attachments'] = 'Attach a screenshot or document';
$string['err_required'] = 'This field is required.';
$string['err_toolong'] = 'Please keep this under {$a} characters.';

// Categories.
$string['cat_academic'] = 'Academic';
$string['cat_technical'] = 'Technical';
$string['cat_administrative'] = 'Administrative';
$string['cat_other'] = 'Other';

// Message providers.
$string['messageprovider:ticketcreated'] = 'Confirmation that your support ticket was created';
$string['messageprovider:ticketreplied'] = 'A reply on your support ticket';
$string['messageprovider:ticketstatus'] = 'Status change on your support ticket';
$string['messageprovider:ticketnew'] = 'A new support ticket reached the queue';
$string['messageprovider:ticketforwarded'] = 'A support ticket was forwarded to you';
$string['messageprovider:slaflag'] = 'A support ticket breached its response time';

// Student-facing notifications.
$string['notify_created_subject'] = 'Ticket {$a->reference} received';
$string['notify_created_body'] = 'Hi {$a->firstname},

Your ticket {$a->reference} has been received on {$a->created}.

Subject: {$a->subject}

{$a->pmname} will get back to you within {$a->hours} hours. You can follow progress at any time from Support in the LMS.';
$string['notify_created_contact'] = '{$a->pmname} can be reached on {$a->contact}.';

$string['notify_replied_subject'] = 'Reply on ticket {$a->reference}';
$string['notify_replied_body'] = 'Hi {$a->firstname},

{$a->pmname} has replied to your ticket {$a->reference}:

{$a->reply}

You can reply from Support in the LMS.';

$string['notify_status_subject'] = 'Ticket {$a->reference} is now {$a->status}';
$string['notify_status_body'] = 'Hi {$a->firstname},

Your ticket {$a->reference} has been marked {$a->status} by {$a->pmname}.

The full history stays available under Support in the LMS.';

// Staff-facing notifications.
$string['notify_new_subject'] = 'New {$a->category} ticket {$a->reference}';
$string['notify_new_body'] = 'Ticket {$a->reference} was raised via {$a->channel}.

Student:  {$a->student}
Category: {$a->category}
Subject:  {$a->subject}

A first response is due within {$a->hours} hours.';

$string['notify_forwarded_subject'] = 'Ticket {$a->reference} forwarded to you';
$string['notify_forwarded_body'] = 'Ticket {$a->reference} has been forwarded to you for input.

Subject:  {$a->subject}
Category: {$a->category}
Note:     {$a->note}

Time left on the overall resolution target: {$a->remaining}. Your reply is shown to the student under the assigned support identity, not your name.';

$string['notify_flag_subject'] = 'Ticket {$a->reference} flagged: {$a->reason}';
$string['notify_flag_body'] = 'Ticket {$a->reference} has been flagged for oversight.

Reason:  {$a->reason}
Pending: {$a->pending}
Subject: {$a->subject}

This flag is for follow-up only; the ticket has not been reassigned.';

// Responder contact settings.
$string['pmcontact'] = 'Support contact number';
$string['pmcontact_desc'] = 'Shown to students in the ticket confirmation as the number to reach the support team on. Used when the assigned responder has no number of their own.';
$string['pmcontactfield'] = 'Responder contact field';
$string['pmcontactfield_desc'] = 'Shortname of the user profile field holding a responder\'s contact number, for example <code>mobile</code>. Core columns <code>phone1</code> and <code>phone2</code> also work. Leave empty to always use the number above.';

// Queue filters added to complete the PRD's dashboard cards.
$string['filter_nearing'] = 'Nearing breach';
$string['filter_resolvedtoday'] = 'Resolved today';

// Queue search and export.
$string['searchtickets'] = 'Search';
$string['searchtickets_help'] = 'Ticket number, student name, or registered email';
$string['exportcsv'] = 'Export CSV';

// CSV column headings.
$string['col_reference'] = 'Ticket';
$string['col_student'] = 'Student';
$string['col_category'] = 'Category';
$string['col_channel'] = 'Channel';
$string['col_status'] = 'Status';
$string['col_assignee'] = 'Assigned to';
$string['col_created'] = 'Created';
$string['col_firstreply'] = 'First reply';
$string['col_resolved'] = 'Resolved';
$string['col_ontime'] = 'Within TAT';

// Optional course on a ticket, and course-aware forwarding.
$string['field_course'] = 'Which course is this about?';
$string['field_course_help'] = 'Optional. Naming the course lets the support team send your question straight to the person who teaches it. Leave it as "Not about a specific course" for account, access or general queries.';
$string['field_course_none'] = 'Not about a specific course';
$string['specialist_thiscourse'] = 'this course';
$string['ticketcourse'] = 'Course';
