<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Dashboard poll';
$string['dashboardpoll:manage'] = 'Manage dashboard polls';
$string['dashboardpoll:viewreports'] = 'View dashboard poll reports and download responses';

// Read-only reports list (teachers).
$string['pollresults'] = 'Poll results';

// Plugin settings.
$string['settings'] = 'Dashboard poll settings';
$string['showvotecounts'] = 'Show vote counts by default';
$string['showvotecounts_desc'] = 'Sets the default for the per-poll "After voting, show" option on newly created polls: when on, new polls default to showing percentages and vote counts; when off, percentages only. Each poll can override this on its edit form.';

// Admin / manage page.
$string['managepolls'] = 'Manage dashboard polls';
$string['createpoll'] = 'Create poll';
$string['editpoll'] = 'Edit poll';
$string['question'] = 'Poll question';
$string['question_help'] = 'The question shown to students in the dashboard popup.';
$string['options'] = 'Options';
$string['option'] = 'Option {no}';
$string['addoptions'] = 'Add {no} more option(s)';
$string['cohort'] = 'Target cohort';
$string['cohort_help'] = 'Restrict this poll to students whose "Cohort/Batch" profile matches. Choose "All Cohort" to show it to every student.';
$string['allcohort'] = 'All Cohort';
$string['status'] = 'Status';
$string['statusdraft'] = 'Draft';
$string['statusactive'] = 'Active';
$string['statusclosed'] = 'Closed';
$string['responses'] = 'Responses';
$string['actions'] = 'Actions';
$string['activate'] = 'Activate';
$string['close'] = 'Close';
$string['viewreport'] = 'View report';
$string['downloadcsv'] = 'Download CSV';
$string['nopolls'] = 'No polls yet. Create one to get started.';
$string['confirmactivate'] = 'Activate this poll? Any other active poll for the same audience will be closed.';
$string['confirmclose'] = 'Close this poll? Students will no longer see it.';
$string['pollsaved'] = 'Poll saved.';
$string['pollactivated'] = 'Poll activated.';
$string['pollclosed'] = 'Poll closed.';
$string['polldeleted'] = 'Poll deleted.';
$string['confirmdelete'] = 'Delete the poll "{$a}" and all its responses? This can\'t be undone.';
$string['needtwooptions'] = 'Please provide at least two non-empty options.';
$string['needquestion'] = 'Please enter a question.';
$string['optionslocked'] = 'This poll already has responses, so its options can\'t be changed.';
$string['newpoll'] = 'New poll';

// Multi-select configuration.
$string['multiselect'] = 'Multiple selections';
$string['multiselect_label'] = 'Let students choose more than one option';
$string['multiselect_help'] = 'When enabled, students can pick several options instead of just one. Use "Number of selections" to require an exact number of picks.';
$string['maxchoices'] = 'Number of selections';
$string['maxchoices_help'] = 'How many options a student must choose before the poll counts as answered. The popup keeps reappearing (with earlier picks preserved) until they have selected this many. Must be a whole number from 1 up to the number of options.';
$string['maxchoicesnumeric'] = 'Enter a whole number.';
$string['maxchoicesmin'] = 'Choose at least 1 selection.';
$string['maxchoicestoohigh'] = 'The number of selections can\'t be more than the number of options ({$a}).';
$string['multiselectlockedsingle'] = 'Single choice (this poll already has responses, so this can\'t be changed).';
$string['multiselectlockedmulti'] = 'Multiple selections — {$a} required (this poll already has responses, so this can\'t be changed).';
$string['toomanychoices'] = 'You have selected more options than this poll allows.';

// Results display (per poll).
$string['resultsdisplay'] = 'After voting, show';
$string['resultscol'] = 'Results shown';
$string['resultsdisplay_help'] = 'What a student sees once they have answered. "Thank-you message only" hides all tallies and shows just a confirmation. The other choices reveal the results card with percentages, vote counts, or both. This is display-only and can be changed at any time, even after the poll has responses.';
$string['resultsdisplay_none'] = 'Thank-you message only';
$string['resultsdisplay_percent'] = 'Results as percentages';
$string['resultsdisplay_count'] = 'Results as vote counts';
$string['resultsdisplay_both'] = 'Results as percentages and counts';

// Report page.
$string['report'] = 'Poll report: {$a}';
$string['totalresponses'] = 'Respondents';
$string['totalselections'] = 'Total selections';
$string['responserate'] = 'Response rate';
$string['noresponses'] = 'No responses yet.';
$string['backtomanage'] = 'Back to polls';
$string['votes'] = '{$a} votes';
$string['multiselectnote'] = 'Students could choose more than one option, so percentages are a share of respondents and may add up to more than 100%.';

// CSV columns.
$string['col_userid'] = 'User ID';
$string['col_fullname'] = 'Full name';
$string['col_email'] = 'Email';
$string['col_idnumber'] = 'ID number';
$string['col_option'] = 'Selected option';
$string['col_time'] = 'Time answered';

// Popup (client-side).
$string['popuptitle'] = 'Quick poll';
$string['submit'] = 'Submit';
$string['maybelater'] = 'Maybe later';
$string['thanks'] = 'Thanks for your response!';
$string['thankssaved'] = 'Your answer has been recorded.';
$string['pickone'] = 'Please select an option.';
$string['selectmultiple'] = 'You can select more than one option.';
$string['selectexactly'] = 'Select {$a} options.';
$string['submitfailed'] = 'Could not record your response. Please try again.';
$string['closebtn'] = 'Close';
$string['yourpick'] = 'Your answer';

// Privacy.
$string['privacy:metadata:responses'] = 'Records which poll option a user selected.';
$string['privacy:metadata:responses:pollid'] = 'The poll answered.';
$string['privacy:metadata:responses:optionid'] = 'The option the user selected.';
$string['privacy:metadata:responses:userid'] = 'The user who answered.';
$string['privacy:metadata:responses:timecreated'] = 'When the response was recorded.';
