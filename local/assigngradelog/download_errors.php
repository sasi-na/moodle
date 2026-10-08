<?php
require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);

$upload = $DB->get_record('local_assigngradelog_upload', ['id' => $id], '*', MUST_EXIST);

[$course, $cm] = get_course_and_cm_from_cmid($upload->cmid, 'assign');
require_login($course, false, $cm);

$context = context_module::instance($upload->cmid);
require_capability('mod/assign:grade', $context);

$errors = !empty($upload->errordetail)
    ? (json_decode($upload->errordetail, true) ?? [])
    : [];

$filename = clean_filename(
    'error_log_' . $upload->id . '_' . date('Ymd', $upload->timeuploaded)
);

// Release the session lock before streaming the file.
\core\session\manager::write_close();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility.
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, [
    get_string('errorlog_row',    'local_assigngradelog'),
    get_string('errorlog_name',   'local_assigngradelog'),
    get_string('errorlog_email',  'local_assigngradelog'),
    get_string('errorlog_reason', 'local_assigngradelog'),
]);

$knownreasons = [
    'gradeunchanged', 'gradenotprovided', 'staledate', 'locked', 'outofrange',
];

foreach ($errors as $i => $err) {
    $rawreason = $err['reason'] ?? '';
    $reason = in_array($rawreason, $knownreasons)
        ? get_string('reason_' . $rawreason, 'local_assigngradelog')
        : $rawreason; // Fall back to raw value if key not recognised.

    fputcsv($out, [
        $i + 1,
        $err['fullname'] ?? '',
        $err['email']    ?? '',
        $reason,
    ]);
}

fclose($out);
exit;
