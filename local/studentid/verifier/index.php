<?php
require_once('../../../config.php');
require_once($CFG->dirroot . '/local/studentid/classes/manager.php');

require_login();

$syscontext = context_system::instance();
require_capability('local/studentid:verify', $syscontext);

$PAGE->set_url('/local/studentid/verifier/index.php');
$PAGE->set_context($syscontext);
$PAGE->set_title(get_string('verifierdashboard', 'local_studentid'));
$PAGE->set_heading($SITE->fullname);
$PAGE->set_pagelayout('standard');

$PAGE->requires->js_call_amd('local_studentid/verifier_dashboard', 'init');
$PAGE->requires->js_call_amd('local_studentid/confirm', 'init');

// Filter / pagination params.
$status  = optional_param('status', 'all', PARAM_ALPHANUMEXT);
$search  = optional_param('search', '', PARAM_TEXT);
$sort    = optional_param('sort', 'timecreated', PARAM_ALPHA);
$dir     = optional_param('dir', 'DESC', PARAM_ALPHA);
$page    = optional_param('page', 0, PARAM_INT);
$perpage = 20;

$filters = [
    'status' => $status,
    'search' => $search,
];

$stats    = \local_studentid\manager::get_dashboard_stats();
$requests = \local_studentid\manager::get_all_requests($filters, $sort, $dir, $page, $perpage);
$total    = \local_studentid\manager::count_requests($filters);

// Batch-load cards for all listed requests (avoids an N+1 query).
$cardsbyrequest = \local_studentid\manager::get_cards_for_requests(
    array_map(static fn($r) => (int) $r->id, $requests)
);

// Build rows for template.
$rows = [];
foreach ($requests as $req) {
    $card = $cardsbyrequest[$req->id] ?? null;
    $daysqueued = (int) ((time() - $req->timecreated) / 86400);

    $rows[] = [
        'id'           => $req->id,
        'fullname'     => $req->fullname,
        'usn'          => $req->usn,
        'status'       => $req->status,
        'statuslabel'  => \local_studentid\manager::get_status_label($req->status),
        'statusclass'  => \local_studentid\manager::get_status_badge_class($req->status),
        'timecreated'  => userdate($req->timecreated),
        'timesubmitted' => !empty($req->timesubmitted) ? userdate($req->timesubmitted) : '-',
        'timemodified' => !empty($req->timemodified) ? userdate($req->timemodified) : '-',
        'days_queued'  => $daysqueued,
        'review_url'   => (new moodle_url('/local/studentid/verifier/review.php', ['id' => $req->id]))->out(false),
        'has_card'     => (bool) $card,
        'card_url'     => $card ? (new moodle_url('/local/studentid/download.php', ['cardid' => $card->id]))->out(false) : '',
        'can_generate' => !$card && $req->status === \local_studentid\manager::STATUS_APPROVED
                          && has_capability('local/studentid:generate', $syscontext),
        'generate_url' => (new moodle_url('/local/studentid/verifier/index.php', ['action' => 'generate', 'reqid' => $req->id, 'sesskey' => sesskey()]))->out(false),
    ];
}

// Handle generate action.
$action = optional_param('action', '', PARAM_ALPHANUMEXT);
if ($action === 'generate') {
    require_sesskey();
    require_capability('local/studentid:generate', $syscontext);
    $reqid = required_param('reqid', PARAM_INT);
    try {
        \local_studentid\manager::generate_id_card($reqid, $USER->id);
        redirect(
            new moodle_url('/local/studentid/verifier/index.php'),
            get_string('idcard_generated', 'local_studentid'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (\moodle_exception $e) {
        redirect(
            new moodle_url('/local/studentid/verifier/index.php'),
            $e->getMessage(),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

// Pagination.
$pagingurl = new moodle_url('/local/studentid/verifier/index.php', ['status' => $status, 'search' => $search, 'sort' => $sort, 'dir' => $dir]);
$pagingbar  = $OUTPUT->paging_bar($total, $page, $perpage, $pagingurl);

// Status filter tabs.
$statuses = [
    'all'          => get_string('all', 'moodle'),
    'submitted'    => get_string('statussubmitted', 'local_studentid'),
    'under_review' => get_string('statusunderreview', 'local_studentid'),
    'approved'     => get_string('statusapproved', 'local_studentid'),
    'rejected'     => get_string('statusrejected', 'local_studentid'),
];

$statustabs = [];
$reportstatusoptions = [];
foreach ($statuses as $key => $label) {
    $statustabs[] = [
        'key'    => $key,
        'label'  => $label,
        'active' => $key === $status,
        'url'    => (new moodle_url('/local/studentid/verifier/index.php', ['status' => $key, 'search' => $search]))->out(false),
    ];
    // Status dropdown for the report — defaults to the currently viewed tab.
    $reportstatusoptions[] = [
        'value'    => $key,
        'label'    => $label,
        'selected' => $key === $status,
    ];
}

// Clickable sort headers: build a toggling URL + direction flag per column.
$sorts = [];
foreach (['usn', 'status', 'timesubmitted', 'timemodified', 'timecreated'] as $col) {
    $isactive = ($sort === $col);
    $newdir   = ($isactive && strtoupper($dir) === 'ASC') ? 'DESC' : 'ASC';
    $sorts[$col] = [
        'url'    => (new moodle_url('/local/studentid/verifier/index.php',
            ['status' => $status, 'search' => $search, 'sort' => $col, 'dir' => $newdir]))->out(false),
        'active' => $isactive,
        'asc'    => $isactive && strtoupper($dir) === 'ASC',
        'desc'   => $isactive && strtoupper($dir) === 'DESC',
    ];
}

$templatedata = [
    'sorts'           => $sorts,
    'stats'           => [
        'total_pending'        => $stats['total_pending'],
        'under_review'         => $stats['under_review'],
        'approved_today'       => $stats['approved_today'],
        'rejected_today'       => $stats['rejected_today'],
        'total_approved'       => $stats['total_approved'],
        'total_rejected'       => $stats['total_rejected'],
        'ids_downloaded_total' => $stats['ids_downloaded_total'],
        'ids_downloaded_today' => $stats['ids_downloaded_today'],
    ],
    'rows'            => $rows,
    'has_rows'        => !empty($rows),
    'total'           => $total,
    'pagingbar'       => $pagingbar,
    'statustabs'      => $statustabs,
    'search'          => $search,
    'current_status'  => $status,
    'search_url'      => (new moodle_url('/local/studentid/verifier/index.php'))->out(false),
    'report_url'      => (new moodle_url('/local/studentid/verifier/report.php'))->out(false),
    'report_status_options' => $reportstatusoptions,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_studentid/verifier_dashboard', $templatedata);
echo $OUTPUT->footer();
