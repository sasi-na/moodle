<?php
/**
 * Language strings for local_eventreg.
 *
 * @package   local_eventreg
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Event registration';
$string['settings'] = 'Settings';

// Capabilities. db/access.php declares both, but neither had a string, so the
// permission screens showed the raw keys instead of a description.
$string['eventreg:manage'] = 'Create and manage event registration forms';
$string['eventreg:viewreports'] = 'View event registration responses and reports';

// Admin management.
$string['manageforms'] = 'Manage registration forms';
$string['createform'] = 'Create form';
$string['editform'] = 'Edit form';
$string['duplicateform'] = 'Duplicate';
$string['noforms'] = 'No registration forms have been created yet.';
$string['formsaved'] = 'Form saved.';
$string['formdeleted'] = 'Form deleted.';
$string['formpublished'] = 'Form published.';
$string['formclosed'] = 'Form closed.';
$string['formduplicated'] = 'Form duplicated.';
$string['confirmdelete'] = 'Delete the form "{$a}" and all of its responses? This cannot be undone.';

// Form meta fields.
$string['title'] = 'Form title';
$string['needtitle'] = 'A title is required.';
$string['intro'] = 'Description / instructions';
$string['audiencemode'] = 'Who can register';
$string['audiencemode_all'] = 'All batches';
$string['audiencemode_only'] = 'Only the selected batches';
$string['audiencemode_except'] = 'All batches except the selected';
$string['audiencemode_help'] = 'Restrict which learner batches (cohort_batch) may open and submit this form. "Only" shows it to the selected batches; "All except" hides it from them.';
$string['audiencebatches'] = 'Batches';
$string['audiencebatches_help'] = 'Used only when the mode is "Only" or "All except". Batches come from the cohort_batch profile field.';
$string['nobatches'] = 'No batches are defined (the cohort_batch profile field has no values). The form can still target "All batches".';

// Status.
$string['status'] = 'Status';
$string['statusdraft'] = 'Draft';
$string['statusopen'] = 'Open';
$string['statusclosed'] = 'Closed';
$string['publish'] = 'Publish';
$string['unpublish'] = 'Close';

// Field builder.
$string['fields'] = 'Questions';
$string['editfields'] = 'Edit questions';
$string['addfield'] = 'Add question';
$string['editfield'] = 'Edit question';
$string['nofields'] = 'This form has no questions yet. Add one to get started.';
$string['fieldsaved'] = 'Question saved.';
$string['fielddeleted'] = 'Question deleted.';
$string['confirmdeletefield'] = 'Delete the question "{$a}"?';
$string['moveup'] = 'Move up';
$string['movedown'] = 'Move down';
$string['fieldtype'] = 'Question type';
$string['fieldlabel'] = 'Question text';
$string['needlabel'] = 'Question text is required.';
$string['fieldhelp'] = 'Help text';
$string['fieldrequired'] = 'Required';
$string['fieldoptions'] = 'Choices (one per line)';
$string['fieldoptions_help'] = 'Enter one choice per line. Used by the Multiple choice, Checkboxes and Dropdown question types.';
$string['needoptions'] = 'Enter at least two choices, one per line.';
$string['ratingmin'] = 'Scale from';
$string['ratingmax'] = 'Scale to';
$string['ratinglow'] = 'Low-end label (optional)';
$string['ratinghigh'] = 'High-end label (optional)';
$string['ratingrange'] = 'The scale start must be less than the scale end.';
$string['numbermin'] = 'Minimum value (optional)';
$string['numbermax'] = 'Maximum value (optional)';
$string['filemaxfiles'] = 'Maximum number of files';
$string['filemaxbytes'] = 'Maximum file size';
$string['fileaccept'] = 'Accepted file types';
$string['fileaccept_help'] = 'Restrict what learners may upload, either by group (for example "Document files") or by extension (for example .pdf, .jpg). Leave it as "All file types" to accept anything.';
$string['fieldlocked'] = 'This form already has responses, so questions can no longer be added, removed, retyped or reordered. You may still edit wording and the required flag.';
$string['typelocked'] = 'The question type cannot be changed once responses exist.';

// Field types.
$string['type_text'] = 'Short text';
$string['type_textarea'] = 'Paragraph';
$string['type_radio'] = 'Multiple choice (pick one)';
$string['type_checkbox'] = 'Checkboxes (pick many)';
$string['type_select'] = 'Dropdown';
$string['type_date'] = 'Date';
$string['type_email'] = 'Email';
$string['type_number'] = 'Number';
$string['type_phone'] = 'Phone';
$string['type_rating'] = 'Rating / linear scale';
$string['type_file'] = 'File upload';
$string['type_description'] = 'Description (text only, no answer)';

// Learner side.
$string['register'] = 'Register';
$string['editresponse'] = 'View your response';
$string['registered'] = 'Registered';
$string['availableforms'] = 'Event registration';
$string['noavailableforms'] = 'There are no registration forms open to you right now.';
$string['submitregistration'] = 'Submit registration';
$string['registrationsaved'] = 'Your registration has been submitted. Thank you!';
$string['alreadyregistered'] = 'You have already registered for this event.';
$string['formnotopen'] = 'This registration form is not currently open.';
$string['notinaudience'] = 'This registration form is not available to your batch.';
$string['yourresponse'] = 'Your response';
$string['required'] = 'Required';
$string['invalidemail'] = 'Enter a valid email address.';
$string['invalidphone'] = 'Enter a valid phone number.';
$string['invalidnumber'] = 'Enter a valid number.';
$string['numbertoolow'] = 'The value must be at least {$a}.';
$string['numbertoohigh'] = 'The value must be at most {$a}.';
$string['requiredfile'] = 'Upload a file to answer this question.';
$string['choosedots'] = 'Choose…';

// Report + export.
$string['report'] = 'Responses: {$a}';
$string['viewreport'] = 'View responses';
$string['responses'] = 'Responses';
$string['totalresponses'] = 'Total responses';
$string['noresponses'] = 'No responses yet.';
$string['download'] = 'Download';
$string['downloadcsv'] = 'Download CSV';
$string['downloadexcel'] = 'Download Excel';
$string['downloadattachments'] = 'Download attachments ({$a->count} files, {$a->size})';
$string['nofilefields'] = 'This form has no file-upload questions.';
$string['noattachments'] = 'No attachments have been uploaded to this form yet.';
$string['reportresults'] = 'Registration responses';
$string['back'] = 'Back';
$string['fieldremoved'] = '{$a} (removed)';
$string['actions'] = 'Actions';

// Report columns.
$string['col_userid'] = 'User ID';
$string['col_fullname'] = 'Name';
$string['col_email'] = 'Email';
$string['col_mobile'] = 'Mobile';
$string['col_idnumber'] = 'ID number';
$string['col_batch'] = 'Batch';
$string['col_time'] = 'Submitted';

// Settings.
$string['defaultformat'] = 'Default download format';
$string['defaultformat_desc'] = 'The download button shown first on the responses report.';

// Privacy.
$string['privacy:metadata:submissions'] = 'One row per learner registration on a form.';
$string['privacy:metadata:submissions:formid'] = 'The form registered on.';
$string['privacy:metadata:submissions:userid'] = 'The learner who registered.';
$string['privacy:metadata:submissions:timecreated'] = 'When the registration was first submitted.';
$string['privacy:metadata:answers'] = 'The answers a learner gave to each question.';
$string['privacy:metadata:answers:fieldid'] = 'The question answered.';
$string['privacy:metadata:answers:value'] = 'The answer given.';
$string['privacy:metadata:files'] = 'Files a learner uploaded to answer a file-upload question.';
