<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname']       = 'Assignment grade upload log';
$string['uploadhistory']    = 'Grade upload history';
$string['nouploadsfound']   = 'No grade uploads have been recorded for this assignment yet.';

// Summary stat cards
$string['col_totaluploads']     = 'Total uploads';
$string['col_totalgradesupd']   = 'Grades updated';
$string['col_totalfeedbackupd'] = 'Feedback updated';
$string['col_lastupload']       = 'Last upload';

// Table headings
$string['col_index']           = '#';
$string['col_uploadedby']      = 'Uploaded by';
$string['col_datetime']        = 'Date & time';
$string['col_filename']        = 'File';
$string['col_totalrows']       = 'Total rows';
$string['col_updated']         = 'Grades updated';
$string['col_feedbackupdated'] = 'Feedback updated';
$string['col_skipped']         = 'Skipped';
$string['col_status']          = 'Status';
$string['col_errors']          = 'Error log';

// Statuses
$string['status_complete'] = 'Complete';
$string['status_failed']   = 'Failed';

// Error log
$string['downloaderrorlog']  = 'Download error log';
$string['errorlog_row']      = 'Row';
$string['errorlog_name']     = 'Full name';
$string['errorlog_email']    = 'Email';
$string['errorlog_reason']   = 'Reason';

// Skip reasons
$string['reason_gradeunchanged']    = 'Grade unchanged';
$string['reason_gradenotprovided']  = 'Grade not provided or invalid';
$string['reason_staledate']         = 'Grade modified more recently in system (stale upload)';
$string['reason_locked']            = 'Grading locked for this student';
$string['reason_outofrange']        = 'Grade value out of allowed range';
