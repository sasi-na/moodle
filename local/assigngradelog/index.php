<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

$cmid = required_param('cmid', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'assign');

require_login($course, false, $cm);

$context = context_module::instance($cmid);
require_capability('mod/assign:grade', $context);

$assignment = new assign($context, $cm, $course);

$PAGE->set_url('/local/assigngradelog/index.php', ['cmid' => $cmid]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('uploadhistory', 'local_assigngradelog'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');
$PAGE->navbar->add(
    format_string($assignment->get_instance()->name),
    new moodle_url('/mod/assign/view.php', ['id' => $cmid])
);
$PAGE->navbar->add(get_string('uploadhistory', 'local_assigngradelog'));

$uploads = $DB->get_records(
    'local_assigngradelog_upload',
    ['assignmentid' => $assignment->get_instance()->id],
    'timeuploaded DESC'
);

// Batch-fetch all uploaders in one query to avoid N+1.
$uploaderids = array_unique(array_column($uploads, 'uploaderid'));
$uploaders = [];
if (!empty($uploaderids)) {
    [$insql, $inparams] = $DB->get_in_or_equal($uploaderids);
    $uploaders = $DB->get_records_sql(
        "SELECT id, firstname, lastname, email FROM {user} WHERE id $insql",
        $inparams
    );
}

// Aggregate summary stats.
$totaluploads   = count($uploads);
$totalgrades    = array_sum(array_column($uploads, 'updated'));
$totalfeedback  = array_sum(array_column($uploads, 'feedbackupdated'));
$lastupload     = !empty($uploads) ? reset($uploads)->timeuploaded : null;

echo $OUTPUT->header();

// ── Inline styles for this page ──────────────────────────────────────────────
echo html_writer::tag('style', '
.agl-stat-card          { border-left: 4px solid; border-radius: .5rem; }
.agl-stat-card.uploads  { border-left-color: #6c757d; }
.agl-stat-card.grades   { border-left-color: #198754; }
.agl-stat-card.feedback { border-left-color: #0d6efd; }
.agl-stat-card.lastrun  { border-left-color: #ffc107; }
.agl-stat-value         { font-size: 1.9rem; font-weight: 700; line-height: 1.15; }
.agl-stat-label         { font-size: .78rem; text-transform: uppercase;
                           letter-spacing: .05em; color: #6c757d; }
#agl-table th           { background: #f8f9fa; font-size: .8rem;
                           text-transform: uppercase; letter-spacing: .04em;
                           white-space: nowrap; }
#agl-table td           { vertical-align: middle; }
.agl-num-good           { color: #198754; font-weight: 600; }
.agl-num-warn           { color: #e07800; font-weight: 600; }
.agl-num-neutral        { color: #6c757d; }
');

// ── Heading + back link ───────────────────────────────────────────────────────
echo $OUTPUT->heading(
    format_string($assignment->get_instance()->name) . ': ' .
    get_string('uploadhistory', 'local_assigngradelog'),
    3
);

$gradingurl = new moodle_url('/mod/assign/view.php', ['id' => $cmid, 'action' => 'grading']);
echo html_writer::div(
    html_writer::link($gradingurl,
        html_writer::tag('i', '', ['class' => 'fa fa-arrow-left mr-1']) .
        get_string('viewgrading', 'assign'),
        ['class' => 'btn btn-sm btn-outline-secondary mb-3']
    )
);

if (empty($uploads)) {
    echo $OUTPUT->notification(get_string('nouploadsfound', 'local_assigngradelog'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// ── Summary stat cards ────────────────────────────────────────────────────────
$lastuploadstr = $lastupload
    ? userdate($lastupload, get_string('strftimedatetime', 'langconfig'))
    : '—';

echo html_writer::start_div('row mb-4 g-3');

$cards = [
    ['uploads',  $totaluploads,   get_string('col_totaluploads',    'local_assigngradelog')],
    ['grades',   $totalgrades,    get_string('col_totalgradesupd',  'local_assigngradelog')],
    ['feedback', $totalfeedback,  get_string('col_totalfeedbackupd','local_assigngradelog')],
    ['lastrun',  $lastuploadstr,  get_string('col_lastupload',      'local_assigngradelog')],
];

foreach ($cards as [$type, $value, $label]) {
    echo html_writer::start_div('col-6 col-md-3');
    echo html_writer::start_div("card agl-stat-card {$type} h-100");
    echo html_writer::start_div('card-body py-3 px-3');
    echo html_writer::div($value,  'agl-stat-value');
    echo html_writer::div($label,  'agl-stat-label mt-1');
    echo html_writer::end_div(); // card-body
    echo html_writer::end_div(); // card
    echo html_writer::end_div(); // col
}

echo html_writer::end_div(); // row

// ── Uploads table ─────────────────────────────────────────────────────────────
$table = new html_table();
$table->id = 'agl-table';
$table->attributes['class'] = 'generaltable table table-hover table-bordered table-sm w-100';
$table->head = [
    '#',
    get_string('col_uploadedby',      'local_assigngradelog'),
    get_string('col_datetime',        'local_assigngradelog'),
    get_string('col_filename',        'local_assigngradelog'),
    get_string('col_totalrows',       'local_assigngradelog'),
    get_string('col_updated',         'local_assigngradelog'),
    get_string('col_feedbackupdated', 'local_assigngradelog'),
    get_string('col_skipped',         'local_assigngradelog'),
    get_string('col_status',          'local_assigngradelog'),
    get_string('col_errors',          'local_assigngradelog'),
];
$table->colclasses = [
    'text-center', '', '', '', 'text-center',
    'text-center', 'text-center', 'text-center', 'text-center', '',
];

$rownum = 1;
foreach ($uploads as $upload) {
    $uploader     = $uploaders[$upload->uploaderid] ?? null;
    $uploadername = $uploader ? fullname($uploader) : '—';

    $datetime = userdate($upload->timeuploaded, get_string('strftimedatetime', 'langconfig'));
    $filename = s($upload->filename ?: '—');

    // Status badge (Bootstrap 5).
    $badgeclass = $upload->status === 'complete' ? 'badge bg-success' : 'badge bg-danger';
    $statusbadge = html_writer::span(
        get_string('status_' . $upload->status, 'local_assigngradelog'),
        $badgeclass
    );

    // Updated cell.
    $updclass    = $upload->updated > 0 ? 'agl-num-good' : 'agl-num-neutral';
    $updatedcell = new html_table_cell(
        html_writer::span($upload->updated, $updclass)
    );
    $updatedcell->attributes['class'] = 'text-center';

    // Feedback updated cell.
    $fbclass  = $upload->feedbackupdated > 0 ? 'agl-num-good' : 'agl-num-neutral';
    $fbcell   = new html_table_cell(
        html_writer::span($upload->feedbackupdated, $fbclass)
    );
    $fbcell->attributes['class'] = 'text-center';

    // Skipped cell.
    $skipclass   = $upload->skipped > 0 ? 'agl-num-warn' : 'agl-num-neutral';
    $skippedcell = new html_table_cell(
        html_writer::span($upload->skipped, $skipclass)
    );
    $skippedcell->attributes['class'] = 'text-center';

    // Error log download link.
    $errorlink = html_writer::span('—', 'text-muted');
    $errdata   = !empty($upload->errordetail)
        ? (json_decode($upload->errordetail, true) ?? [])
        : [];
    if (!empty($errdata)) {
        $errcount = count($errdata);
        $errurl   = new moodle_url('/local/assigngradelog/download_errors.php', ['id' => $upload->id]);
        $errorlink = html_writer::link(
            $errurl,
            html_writer::tag('i', '', ['class' => 'fa fa-download mr-1']) .
            get_string('downloaderrorlog', 'local_assigngradelog') . " ({$errcount})",
            ['class' => 'btn btn-sm btn-outline-danger']
        );
    }

    $row = new html_table_row([
        $rownum++,
        $uploadername,
        $datetime,
        $filename,
        $upload->totalrows,
        $updatedcell,
        $fbcell,
        $skippedcell,
        $statusbadge,
        $errorlink,
    ]);

    $table->data[] = $row;
}

echo html_writer::table($table);
echo $OUTPUT->footer();
