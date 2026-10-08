<?php
/**
 * Reviewer report — download all (non-draft) ID card requests matching the
 * chosen filters as CSV / Excel / ODS. Verifier capability required.
 */
require_once('../../../config.php');
require_once($CFG->dirroot . '/local/studentid/classes/manager.php');

require_login();

$syscontext = context_system::instance();
require_capability('local/studentid:verify', $syscontext);

$status     = optional_param('status', 'all', PARAM_ALPHANUMEXT);
$dataformat = optional_param('dataformat', 'csv', PARAM_ALPHA); // csv | excel | ods
$subfrom    = optional_param('subfrom', '', PARAM_TEXT);        // YYYY-MM-DD
$subto      = optional_param('subto', '', PARAM_TEXT);
$verfrom    = optional_param('verfrom', '', PARAM_TEXT);
$verto      = optional_param('verto', '', PARAM_TEXT);

// Convert YYYY-MM-DD inputs to start-/end-of-day unix timestamps.
$daystart = function(string $d): int {
    $t = strtotime($d . ' 00:00:00');
    return $t ?: 0;
};
$dayend = function(string $d): int {
    $t = strtotime($d . ' 23:59:59');
    return $t ?: 0;
};

$filters = [
    'status'  => $status,
    'subfrom' => $subfrom ? $daystart($subfrom) : 0,
    'subto'   => $subto   ? $dayend($subto)     : 0,
    'verfrom' => $verfrom ? $daystart($verfrom) : 0,
    'verto'   => $verto   ? $dayend($verto)     : 0,
];

$records = \local_studentid\manager::get_requests_for_report($filters);

// Column key => header label, in display order.
$columns = [
    'name'           => get_string('report_col_name', 'local_studentid'),
    'usn'            => get_string('report_col_usn', 'local_studentid'),
    'contact'        => get_string('report_col_contact', 'local_studentid'),
    'batch'          => get_string('report_col_batch', 'local_studentid'),
    'verificationid' => get_string('report_col_verificationid', 'local_studentid'),
    'status'         => get_string('report_col_status', 'local_studentid'),
    'submitted'      => get_string('report_col_submitted', 'local_studentid'),
    'verified'       => get_string('report_col_verified', 'local_studentid'),
    'comments'       => get_string('report_col_comments', 'local_studentid'),
];

$datefmt = '%Y-%m-%d %H:%M';
$rows = [];
foreach ($records as $r) {
    // Batch and contact live on the student profile (contact = phone2-first,
    // apostrophe-stripped) — resolved through the shared accessor.
    $sd = \local_studentid\manager::get_student_data($r->userid);
    $rows[] = [
        'name'           => $r->fullname,
        'usn'            => $r->usn,
        'contact'        => $sd['contact'],
        'batch'          => $sd['batch'],
        'verificationid' => $r->idcard_number ?? '',
        'status'         => \local_studentid\manager::get_status_label($r->status),
        'submitted'      => $r->timesubmitted ? userdate($r->timesubmitted, $datefmt) : '',
        'verified'       => $r->timereviewed ? userdate($r->timereviewed, $datefmt) : '',
        'comments'       => $r->review_comment ?? '',
    ];
}

$filename = 'studentid-report-' . userdate(time(), '%Y%m%d-%H%M');

\core\dataformat::download_data($filename, $dataformat, $columns, $rows);
