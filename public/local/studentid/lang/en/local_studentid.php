<?php
defined('MOODLE_INTERNAL') || die();

// Plugin
$string['pluginname']          = 'Student e-ID Card';
$string['pluginadministration'] = 'Student e-ID Card Administration';

// Capabilities
$string['local/studentid:submit']      = 'Submit student ID requests';
$string['local/studentid:verify']      = 'Verify and approve student ID requests';
$string['local/studentid:generate']    = 'Generate student e-ID cards';
$string['local/studentid:viewreports'] = 'View student ID reports and logs';

// Navigation
$string['studentdashboard']      = 'Student ID Dashboard';
$string['verifierdashboard']     = 'Verifier Dashboard';
$string['myidcard']              = 'My Student e-ID Card';

// Verifier role
$string['verifierrolename']        = 'Student ID Verifier';
$string['verifierroledescription'] = 'Can review, approve, reject, and generate student e-ID card requests. Has no access to other Moodle features.';
$string['requesthistory']        = 'Request History';
$string['auditlogs']             = 'Audit Logs';
$string['menu:verifierdashboard'] = 'Verifier Dashboard';

// Status labels
$string['statusdraft']       = 'Draft';
$string['statussubmitted']   = 'Submitted';
$string['statusunderreview'] = 'Under Review';
$string['statusapproved']    = 'Approved';
$string['statusrejected']    = 'Rejected';
$string['statusreplaced']    = 'Replaced';
$string['status']            = 'Status';
$string['step_card_ready']   = 'Card Ready';

// Form field labels
$string['fullname']        = 'Full Name';
$string['email']           = 'Email';
$string['bloodgroup']      = 'Blood Group';
$string['dob']             = 'Date of Birth';
$string['contact']         = 'Contact Number';
$string['city']            = 'City';
$string['state']           = 'State';
$string['country']         = 'Country';
$string['photo']           = 'Passport Photo';
$string['identitydocument'] = 'Identity Document';
$string['comments']        = 'Comments';
$string['usn']             = 'USN (University Seat Number)';
$string['program']         = 'Program';
$string['batch']           = 'Batch';
$string['notset']          = 'Not set';
$string['prefilled']       = 'Pre-filled';

// Form help text
$string['photo_help']      = 'Upload a recent passport-size photograph. Accepted formats: JPG, PNG. Maximum size: 2 MB. Minimum dimensions: 300×400 pixels.';

// Form section headers
$string['hdr_studentinfo']    = 'Student Information';
$string['hdr_requestdetails'] = 'Request Details';
$string['hdr_photo']          = 'Passport Photograph';

// Buttons / actions
$string['submitrequest']       = 'Submit Request';
$string['saverequest']         = 'Save';
$string['updaterequest']       = 'Update Request';
$string['approve']             = 'Approve';
$string['reject']              = 'Reject';
$string['downloadid']          = 'Download e-ID Card';
$string['preview']             = 'Preview';
$string['inspect']             = 'Inspect';
$string['newrequest']          = 'New e-ID Card Request';
$string['requestnewid']        = 'Request New e-ID Card';
$string['editdraft']           = 'Edit Draft';
$string['review']              = 'Review';
$string['generatecard']        = 'Generate e-ID Card';
$string['mark_under_review']   = 'Mark Under Review';
$string['confirm_approve']     = 'Confirm Approval';
$string['confirm_reject']      = 'Confirm Rejection';
$string['back_to_dashboard']   = 'Back to Dashboard';
$string['clear']               = 'Clear';

// Confirmations
$string['confirm_submit']   = 'Are you sure you want to submit this request for review? You will not be able to edit it after submission.';
$string['confirm_generate'] = 'Generate the e-ID card for this student? This action cannot be undone.';

// Validation errors
$string['errornobloodgroup']    = 'Please select your blood group';
$string['errornophoto']         = 'Please upload your photo';
$string['errornodocument']      = 'Please upload identity document';
$string['errornoabcid']         = 'Please upload your ABC Id document (PDF)';
$string['errorinvalidfile']     = 'Invalid file type. Please upload JPG or PNG only';
$string['errorfilesize']        = 'File size exceeds 2 MB limit. Please compress the image';
$string['errorphotoresolution'] = 'Photo must be at least 300×400 pixels (passport ratio)';
$string['erroractiveexists']    = 'You already have an active request. Please wait for it to be processed';
$string['errorcommentrequired'] = 'A comment is required when rejecting a request';
$string['errorinvalidstatus']   = 'This action is not allowed for the current request status';
$string['errorinvalidaccess']   = 'You do not have permission to access this resource';
$string['erroridcardgen']       = 'e-ID card PDF generation failed. Please ensure Python 3 and PyMuPDF are installed on the server. Detail: {$a}';

// Notifications / success messages
$string['requestsubmitted']    = 'Your e-ID card request has been saved. Review the details and click "Submit Request" when ready.';
$string['requestupdated']      = 'Your e-ID card request has been updated successfully';
$string['requestapproved']     = 'Request approved successfully';
$string['requestrejected']     = 'Request rejected. The student has been notified';
$string['idcard_generated']    = 'e-ID card generated successfully and the student has been notified';
$string['status_set_under_review'] = 'Request marked as Under Review';

// Dashboard labels
$string['pendingrequests']    = 'Pending Requests';
$string['approvedrequests']   = 'Approved Requests';
$string['rejectedrequests']   = 'Rejected Requests';
$string['underreviewrequests'] = 'Under Review';
$string['approvedtoday']        = 'Approved Today';
$string['rejectedtoday']        = 'Rejected Today';
$string['idsdownloadedtotal']   = 'IDs Downloaded';
$string['idsdownloadedtoday']   = 'Downloaded Today';
$string['no_request_yet']     = 'No Request Yet';
$string['request_status']     = 'Request Status';
$string['no_requests_found']  = 'No requests found matching your filters';
$string['search_placeholder'] = 'Search by name, USN...';
$string['days_in_queue']      = 'Days in Queue';
$string['student_name']       = 'Student Name';
$string['actions']            = 'Actions';
$string['timecreated']        = 'Created';
$string['timesubmitted']      = 'Submitted';
$string['timemodified']       = 'Last Modified';

// Review page
$string['reviewrequest']      = 'Review e-ID Card Request';
$string['student_details']    = 'Student Details';
$string['previous_comment']   = 'Previous Comment';
$string['optional_comment']   = 'Comment (optional)';
$string['approve_comment_placeholder'] = 'Add a note for the student (optional)...';
$string['rejection_reason']            = 'Reason for Rejection';
$string['reject_comment_placeholder']  = 'Explain the reason for rejection...';
$string['reject_comment_required_hint'] = 'Comment is required and will be shared with the student';
$string['card_already_generated']      = 'e-ID card already generated:';
$string['generate_card_hint']          = 'The request is approved. Click below to generate the digital e-ID card for this student.';

// Pre-defined rejection reasons
$string['reject_reason_1'] = 'Poor photo quality — please upload a clearer image';
$string['reject_reason_2'] = 'Inappropriate or non-white background in photo';
$string['reject_reason_3'] = 'Face not clearly visible or not front-facing';
$string['reject_reason_4'] = 'Non-professional appearance (sunglasses, hat, etc.)';
$string['reject_reason_5'] = 'Incorrect or mismatched student information';
$string['reject_reason_6'] = 'Photo Mismatch';
$string['reject_reason_other'] = 'Other (please specify below)';

// Photo guidelines
$string['photo_guidelines_title'] = 'Photo Requirements';
$string['photo_guide_1'] = 'Professional passport-size photo on a white or light background';
$string['photo_guide_2'] = 'Face clearly visible and front-facing, no sunglasses or hats';
$string['photo_guide_3'] = 'File format: JPG or PNG only, maximum 2 MB';
$string['photo_guide_4'] = 'Minimum dimensions: 300 × 400 pixels (portrait)';
$string['photo_guide_5'] = 'Recent photograph taken within the last 6 months';

// Photo review checklist
$string['photo_checklist_title'] = 'Photo Compliance Checklist';
$string['checklist_professional'] = 'Professional appearance';
$string['checklist_clear_face']   = 'Face clearly visible';
$string['checklist_background']   = 'Appropriate background';
$string['checklist_dimensions']   = 'Correct dimensions / quality';

// Student dashboard messages
$string['request_rejected_title'] = 'Your request was rejected';
$string['request_rejected_hint']  = 'You can submit a new request with the corrected photo/details.';
$string['current_photo']          = 'Current photo';
$string['no_photo_uploaded']      = 'No photo uploaded';

// Audit log actions
$string['logcreate']      = 'Request created';
$string['logapprove']     = 'Request approved';
$string['logreject']      = 'Request rejected';
$string['logupdate']      = 'Request updated';
$string['logreplace']     = 'Request replaced';
$string['logunderreview'] = 'Request moved to under review';
$string['loggenerate']    = 'e-ID card generated';
$string['logsubmit']      = 'Request submitted';

// Email notifications
$string['email_submitted_subject'] = 'Your Student e-ID Card request has been received';
$string['email_submitted_body']    = 'Dear {$a}, your e-ID card request has been successfully submitted and is pending verification.';
$string['email_approved_subject']  = 'Your Student e-ID Card request has been approved';
$string['email_approved_body']     = 'Dear {$a}, great news! Your e-ID card request has been approved. Your e-ID card is being generated and will be available for download shortly.';
$string['email_rejected_subject']  = 'Your Student e-ID Card request requires attention';
$string['email_rejected_body']     = 'Dear {$a->name}, your e-ID card request has been rejected. Reason: {$a->comment}. Please log in to the portal and submit a new request with the corrections.';
$string['email_generated_subject'] = 'Your Student e-ID Card is ready for download';
$string['email_generated_body']    = 'Dear {$a}, your Student e-ID Card has been generated and is ready to download from the Student e-ID Card portal.';

// Address fields
$string['city_label']     = 'City';
$string['state_label']    = 'State';

// Update info
$string['updateinfo']      = 'Update Info';
$string['updateinfo_note'] = 'If you have recently updated your details (such as email ID or mobile number) on JOSH or AMS, click "Update Info" to sync them here and redownload your ID card. No action is needed if your details are already correct.';
$string['infoupdated']     = 'Student information updated from your profile';

// Download tracking
$string['downloadcount']  = 'Downloaded {$a} times';
$string['lastdownloaded'] = 'Last downloaded';
$string['neverdownloaded'] = 'Not downloaded yet';

// Admin settings — section headings
$string['setting_hdr_branding']       = 'Card Branding';
$string['setting_hdr_validity']       = 'Card Validity';
$string['setting_hdr_uploads']        = 'Photo Upload Limits';
$string['setting_hdr_notifications']  = 'Email Notifications';

// Admin settings — fields
$string['setting_universityname']      = 'University / Institution Name';
$string['setting_universityname_desc'] = 'Printed on the card logo area. Defaults to the site name if left blank.';
$string['setting_universityaddress']   = 'Student Information Centre Address';
$string['setting_universityaddress_desc'] = 'Full address printed on the back of the e-ID card.';
$string['setting_universitywebsite']   = 'University Website';
$string['setting_universitywebsite_desc'] = 'Website URL shown on the back of the card (without https://). e.g. www.onlinejain.com';
$string['setting_universitytag']       = 'Brand Hashtag / Tagline';
$string['setting_universitytag_desc']  = 'Short tagline or hashtag shown on the back of the card. e.g. #ItAllAddsUp';
$string['setting_signatorytitle']      = 'Signatory Title';
$string['setting_signatorytitle_desc'] = 'Title shown under the signature line on the front of the card. e.g. Deputy Director';
$string['setting_validityyears']       = 'Card validity (years)';
$string['setting_validityyears_desc']  = 'Number of years the e-ID card is valid. Shown in the card footer.';
$string['setting_emailnotifications']  = 'Enable email notifications';
$string['setting_emailnotifications_desc'] = 'Send email notifications to students when their request status changes.';
$string['setting_maxphotosize']        = 'Maximum photo file size (bytes)';
$string['setting_maxphotosize_desc']   = 'Maximum allowed file size for student photo uploads. Default is 2097152 (2 MB).';

// Privacy metadata
$string['privacy:metadata']                  = 'The Student e-ID plugin stores student e-ID request data, verification status, uploaded files, and audit logs.';
$string['privacy:metadata:requests']         = 'Student e-ID card requests';
$string['privacy:metadata:requests:userid']  = 'The ID of the student who submitted the request';
$string['privacy:metadata:requests:usn']     = 'The University Seat Number at time of request';
$string['privacy:metadata:requests:status']  = 'The current status of the request';
$string['privacy:metadata:requests:photo_path'] = 'Filename of the uploaded student photo';
$string['privacy:metadata:requests:blood_group'] = 'Blood group provided by the student';
$string['privacy:metadata:requests:review_comment'] = 'Comment left by the verifier';
$string['privacy:metadata:requests:timecreated'] = 'Time the request was created';
$string['privacy:metadata:cards']                = 'Generated student e-ID cards';
$string['privacy:metadata:cards:userid']         = 'The ID of the student the card belongs to';
$string['privacy:metadata:cards:idcard_number']  = 'Unique e-ID card number';
$string['privacy:metadata:cards:file_path']      = 'Filename of the generated e-ID card file';
$string['privacy:metadata:cards:timegenerated']  = 'Time the card was generated';
$string['privacy:metadata:logs']                 = 'Audit log of all actions';
$string['privacy:metadata:logs:userid']          = 'User who performed the action';
$string['privacy:metadata:logs:action']          = 'Action type';
$string['privacy:metadata:logs:context']         = 'Additional context for the action';
$string['privacy:metadata:logs:timecreated']     = 'Time the action was performed';
