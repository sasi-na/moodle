<?php
$string['livesessionattendance'] = 'Live Session Attendance';
$string['sessionname'] = 'Session Name';
$string['date'] = 'Date';
$string['totalusers'] = 'Users';
$string['action'] = 'Action';
$string['downloadcsv'] = 'Download CSV';
$string['nosessions'] = 'No sessions found';

// Live attendance moderation actions.
$string['actions'] = 'Actions';
$string['dismisssession'] = 'Dismiss session';
$string['markallpresent'] = 'Mark all joined present';
$string['markallenrolled'] = 'Mark enrolled present';
$string['confirmdismisstitle'] = 'Dismiss session?';
$string['confirmdismiss'] = 'Dismiss this session from the attendance list? It will be hidden from this view.';
$string['confirmmarkalltitle'] = 'Mark all joined present?';
$string['confirmmarkall'] = 'Mark every student who joined as fully present for this session? This sets their attended duration to the full session length.';
$string['confirmmarkenrolledtitle'] = 'Mark all enrolled students present?';
$string['confirmmarkenrolled'] = 'Mark every student enrolled in this course as fully present for this session — including those who did not join? Attendance will be created for enrolled students with no Zoom record.';
$string['sessiondismissed'] = 'Session dismissed from the attendance list.';
$string['attendancemarkedall'] = 'All joined students marked present for this session.';
$string['attendancemarkedenrolled'] = 'All enrolled students marked present for this session.';
$string['nosessionduration'] = 'Session duration is unknown for this meeting; students were marked present without changing durations.';

// Capability.
$string['zoomattendance:reviewsummaries'] = 'Review recorded-session summaries';
$string['zoomattendance:bypassjoingate'] = 'Bypass the "meeting not started" waiting page';
$string['zoomattendance:schedulemeeting'] = 'Schedule a Zoom meeting from a course';

// Schedule meeting.
$string['schedulemeeting'] = 'Schedule Zoom meeting';
$string['eventtitle'] = 'Title';
$string['duration'] = 'Duration';
$string['eventdescription'] = 'Description';
$string['zoomhost'] = 'Zoom account (host)';
$string['joinzoomlink'] = 'Join Zoom Meeting';
$string['meetingcreated'] = 'Zoom meeting created and added to the course calendar.';
$string['meetingcreatefailed'] = 'Could not create the Zoom meeting. No event was created — please try again.';
$string['nohostsconfigured'] = 'No Zoom host accounts are configured. Set them in the Zoom Attendance plugin settings before scheduling meetings.';
$string['invalidhost'] = 'The selected Zoom host account is not valid.';

// Meeting list / manage.
$string['schedulenew'] = 'Schedule new meeting';
$string['schedulednomeetings'] = 'No upcoming Zoom meetings are scheduled for this course yet.';
$string['time'] = 'Time';
$string['updatemeeting'] = 'Update';
$string['deletemeeting'] = 'Delete';
$string['editmeeting'] = 'Edit Zoom meeting';
$string['meetingupdated'] = 'Zoom meeting updated.';
$string['meetingdeleted'] = 'Zoom meeting deleted.';
$string['confirmdeletemeeting'] = 'Delete this Zoom meeting? It will be removed from Zoom (along with any registrations) and from the course calendar. This cannot be undone.';
$string['invalidmeeting'] = 'That meeting was not found for this course.';

// Summary review.
$string['pluginname'] = 'Zoom Attendance';
$string['tabattendance'] = 'Live Attendance';
$string['tabsummaries'] = 'Recording Summaries';
$string['tabattendancesheet'] = 'Attendance Sheet';
$string['conductedsessions'] = 'Conducted Sessions';
$string['downloadattendance'] = 'Download attendance';
$string['recordingsummaries'] = 'Recording Session Summaries';
$string['nosummaries'] = 'No summaries submitted yet';
$string['student'] = 'Student';
$string['email'] = 'Email';
$string['words'] = 'Words';
$string['submitted'] = 'Submitted';
$string['status'] = 'Status';
$string['statuspending'] = 'Pending';
$string['statusapproved'] = 'Approved';
$string['statusrejected'] = 'Rejected';
$string['viewsummary'] = 'View summary';
$string['approve'] = 'Approve';
$string['reject'] = 'Reject';
$string['rejectreason'] = 'Reason for rejection';
$string['rejectreasonrequired'] = 'A reason is required when rejecting a summary.';
$string['reviewsaved'] = 'Summary review saved.';
$string['reviewedby'] = 'Reviewed by';
$string['feedback'] = 'Feedback';

// Session-grouped review overview.
$string['colsession'] = 'Session';
$string['colconducted'] = 'Conducted on';
$string['colsubmitted'] = 'Submitted';
$string['colapproved'] = 'Approved';
$string['colrejected'] = 'Rejected';
$string['colpending'] = 'Pending review';
$string['reviewsubmissions'] = 'Review submissions';
$string['viewsubmissions'] = 'View submissions';
$string['backtosessions'] = 'Back to sessions';
$string['downloadexcel'] = 'Download Excel';
$string['submittedon'] = 'Submitted on';
$string['unknownsession'] = 'Unknown session';

// Scheduled tasks.
$string['autoapprovetask'] = 'Auto-approve pending summaries';
$string['syncmeetingstask'] = 'Sync Zoom meetings and rewrite join links';

// Settings.
$string['zoomaccountid'] = 'Zoom account ID';
$string['zoomaccountid_desc'] = 'Account ID of the Server-to-Server OAuth app.';
$string['zoomclientid'] = 'Zoom client ID';
$string['zoomclientid_desc'] = 'Client ID of the Server-to-Server OAuth app.';
$string['zoomclientsecret'] = 'Zoom client secret';
$string['zoomclientsecret_desc'] = 'Client secret of the Server-to-Server OAuth app.';
$string['webhooksecret'] = 'Zoom webhook secret token';
$string['webhooksecret_desc'] = 'Secret token used to validate the meeting-ended webhook.';
$string['webhookstrict'] = 'Reject unsigned/invalid webhooks';
$string['webhookstrict_desc'] = 'When enabled, webhook events that fail Zoom signature verification are rejected (HTTP 401). Leave OFF until you have confirmed (via the web server error log staying quiet during real sessions) that Zoom\'s signatures validate, then enable to block forged events.';
$string['zoomhostemails'] = 'Zoom host emails';
$string['zoomhostemails_desc'] = 'Comma-separated Zoom account emails that host the class meetings. Used to sync the meeting registry for the direct-join flow.';

// Direct join / waiting page.
$string['nozoomlink'] = 'No Zoom meeting link was found for this event.';
$string['waitingtitle'] = 'Session not started yet';
$string['waitingheading'] = 'Your session hasn\'t started yet';
$string['scheduledfor'] = 'Scheduled for {$a}';
$string['startsin'] = 'Starts in';
$string['startingsoon'] = 'Starting soon — waiting for your teacher to begin the session.';
$string['waitingbody'] = 'You\'ll join the class automatically — this page checks every 30 seconds. Keep it open.';
$string['checkagain'] = 'Check again';
$string['backtocourse'] = 'Back to course';

// Student attendance summary.
$string['myattendance'] = 'My Attendance';
$string['presentlive'] = 'Attended (Live)';
$string['presentrecording'] = 'Attended (Summary)';
$string['absent'] = 'Absent';
$string['attendanceoverall'] = 'You are present for {$a->present} of {$a->total} sessions ({$a->percent}%).';
$string['watchrecording'] = 'Watch recording';
