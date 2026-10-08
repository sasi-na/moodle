<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/local/studentid/classes/manager.php');

require_login();

$syscontext = context_system::instance();

// Any authenticated non-guest user can access the student ID portal.
// Verifiers are redirected away by after_require_login, but handle it
// here too in case someone navigates directly.
if (isguestuser()) {
    redirect(get_login_url());
}
if (has_capability('local/studentid:verify', $syscontext)) {
    redirect(new moodle_url('/local/studentid/verifier/index.php'));
}
// The e-ID Card portal is restricted to the eligible batches (see
// local_studentid/eligiblebatches).
if (!\local_studentid\manager::is_eligible_batch($USER->id)) {
    throw new moodle_exception('noteligible', 'local_studentid', '',
        implode(', ', \local_studentid\manager::get_eligible_batches()));
}

$PAGE->set_url('/local/studentid/index.php');
$PAGE->set_context($syscontext);
$PAGE->set_title(get_string('pluginname', 'local_studentid'));
$PAGE->set_heading($SITE->fullname);
$PAGE->set_pagelayout('standard');
$PAGE->requires->js_call_amd('local_studentid/confirm', 'init');

$request = \local_studentid\manager::get_active_request($USER->id);
$card    = null;

if ($request && $request->status === \local_studentid\manager::STATUS_APPROVED) {
    $card = \local_studentid\manager::get_card_for_request($request->id);
}

$statuslabel = $request ? \local_studentid\manager::get_status_label($request->status) : '';
$statusbadge = $request ? \local_studentid\manager::get_status_badge_class($request->status) : '';

$studentdata = \local_studentid\manager::get_student_data($USER->id);
$allrequests = \local_studentid\manager::get_user_requests($USER->id);

// Build history rows.
$history = [];
foreach ($allrequests as $r) {
    $history[] = [
        'id'          => $r->id,
        'status'      => $r->status,
        'statuslabel' => \local_studentid\manager::get_status_label($r->status),
        'statusclass' => \local_studentid\manager::get_status_badge_class($r->status),
        'timecreated' => userdate($r->timecreated),
        'timesubmitted' => $r->timesubmitted ? userdate($r->timesubmitted) : '-',
        'comment'     => $r->review_comment ?? '',
    ];
}

$can_new_request    = !$request || in_array($request->status, [
    \local_studentid\manager::STATUS_REJECTED,
    \local_studentid\manager::STATUS_REPLACED,
]);
$can_edit_draft     = $request && $request->status === \local_studentid\manager::STATUS_DRAFT;
$can_download       = $card !== null;
// Country/state/city are required for everyone. Academic documents differ by
// country: India needs ABC ID + Class 10 Marks Sheet; others need O Level.
$req_is_india = $request && strcasecmp((string) $request->country, 'India') === 0;
$can_submit_draft   = $can_edit_draft
    && !empty($request->photo_path)
    && !empty($request->aadhaar_front_path)
    && !empty($request->aadhaar_back_path)
    && !empty($request->country)
    && !empty($request->state)
    && !empty($request->city)
    && ($req_is_india
        ? (!empty($request->abc_id_path) && !empty($request->tenth_marks_path))
        : !empty($request->olevel_path));

// Build the linear status stepper (Draft → Submitted → Under Review →
// Approved → Card Ready). Hidden for rejected requests (shown as an alert).
$statusorder = [
    \local_studentid\manager::STATUS_DRAFT        => 0,
    \local_studentid\manager::STATUS_SUBMITTED    => 1,
    \local_studentid\manager::STATUS_UNDER_REVIEW => 2,
    \local_studentid\manager::STATUS_APPROVED     => 3,
];
$curstep = $request ? ($statusorder[$request->status] ?? -1) : -1;
if ($card) {
    $curstep = 4;
}
$steplabels = [
    get_string('statusdraft', 'local_studentid'),
    get_string('statussubmitted', 'local_studentid'),
    get_string('statusunderreview', 'local_studentid'),
    get_string('statusapproved', 'local_studentid'),
    get_string('step_card_ready', 'local_studentid'),
];
$stepicons = ['fa fa-pencil', 'fa fa-paper-plane', 'fa fa-search', 'fa fa-check', 'fa fa-id-card'];
$steps = [];
foreach ($steplabels as $i => $lbl) {
    $steps[] = [
        'label'  => $lbl,
        'icon'   => $stepicons[$i],
        'done'   => $i < $curstep,
        'active' => $i === $curstep,
    ];
}
$show_stepper = $request && $request->status !== \local_studentid\manager::STATUS_REJECTED;

$templatedata = [
    'has_request'       => (bool) $request,
    'steps'             => $steps,
    'show_stepper'      => $show_stepper,
    'status'            => $statuslabel,
    'statusbadge'       => $statusbadge,
    'request_id'        => $request->id ?? 0,
    'is_draft'          => $can_edit_draft,
    'is_rejected'       => $request && $request->status === \local_studentid\manager::STATUS_REJECTED,
    'reject_comment'    => ($request && $request->review_comment) ? $request->review_comment : '',
    'can_new_request'   => $can_new_request,
    'can_edit_draft'    => $can_edit_draft,
    'can_submit_draft'  => $can_submit_draft,
    'can_download'      => $can_download,
    'card_id'           => $card ? $card->id : 0,
    'card_number'       => $card ? $card->idcard_number : '',
    'download_url'      => $can_download ? (new moodle_url('/local/studentid/download.php', ['cardid' => $card->id]))->out(false) : '',
    'request_url'       => (new moodle_url('/local/studentid/request.php'))->out(false),
    'edit_url'          => $can_edit_draft ? (new moodle_url('/local/studentid/request.php', ['id' => $request->id]))->out(false) : '',
    'student_name'      => $studentdata['fullname'],
    'usn'               => $studentdata['usn'],
    'history'           => $history,
    'has_history'       => !empty($history),
    'sesskey'           => sesskey(),
];

// Populate download stats into templatedata.
if ($card) {
    $dlcount = (int) ($card->download_count ?? 0);
    $templatedata['download_count']      = $dlcount;
    $templatedata['download_count_text'] = get_string('downloadcount', 'local_studentid', $dlcount);
    $templatedata['last_downloaded']     = !empty($card->last_downloaded)
        ? userdate($card->last_downloaded)
        : get_string('neverdownloaded', 'local_studentid');
    $templatedata['has_download_stats']  = true;
} else {
    $templatedata['download_count']      = 0;
    $templatedata['download_count_text'] = '';
    $templatedata['last_downloaded']     = '';
    $templatedata['has_download_stats']  = false;
}
$templatedata['can_update_info'] = $can_download;

// Handle actions.
$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'update' && $request && $can_download) {
    require_sesskey();
    try {
        \local_studentid\manager::update_request_info($request->id, $USER->id);
        redirect(
            new moodle_url('/local/studentid/index.php'),
            get_string('infoupdated', 'local_studentid'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (\moodle_exception $e) {
        redirect(
            new moodle_url('/local/studentid/index.php'),
            $e->getMessage(),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

if ($action === 'submit' && $request && $request->status === \local_studentid\manager::STATUS_DRAFT) {
    require_sesskey();
    try {
        \local_studentid\manager::submit_request($request->id, $USER->id);
        redirect(
            new moodle_url('/local/studentid/index.php'),
            get_string('requestsubmitted', 'local_studentid'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (\moodle_exception $e) {
        redirect(
            new moodle_url('/local/studentid/index.php'),
            $e->getMessage(),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_studentid/student_dashboard', $templatedata);
echo $OUTPUT->footer();
