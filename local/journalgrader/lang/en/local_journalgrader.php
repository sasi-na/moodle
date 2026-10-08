<?php

defined('MOODLE_INTERNAL') || die();

/*
 * Plugin name.
 */

$string['pluginname'] =
    'Journal Grader';

/*
 * Settings page.
 */

$string['journalanswerkey'] =
    'Upload Answer Key JSON';

$string['journalanswerkey_desc'] =
    'Upload the JSON answer key used for automatic grading.';

/*
 * Scheduled task.
 */

$string['task_process_queue'] =
    'Process journal grading queue';

/*
 * Queue / grading messages.
 */

$string['gradingqueued'] =
    'Submission queued for automatic grading';

$string['gradingcompleted'] =
    'Automatic grading completed';

$string['gradingfailed'] =
    'Automatic grading failed';

/*
 * Supported formats.
 */

$string['type_journal'] =
    'Journal';

$string['type_ledger'] =
    'Ledger';

$string['type_profit_and_loss'] =
    'Profit and Loss';

$string['type_balance_sheet'] =
    'Balance Sheet';
/*
 * Error messages.
 */

$string['error_missing_answerkey'] =
    'Answer key file is missing';

$string['error_invalid_csv'] =
    'Invalid CSV file uploaded';

$string['error_invalid_answerkey'] =
    'Invalid answer key JSON';

$string['error_unsupported_type'] =
    'Unsupported grading type';

$string['error_processing_failed'] =
    'Submission processing failed';

$string['error_no_submission_found'] =
    'No submission found for grading';

/*
 * Feedback messages.
 */

$string['feedback_matched'] =
    'Matched {$a->matched} out of {$a->total} entries';