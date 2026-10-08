<?php
require_once('../../../config.php');
require_once($CFG->dirroot . '/local/studentid/classes/manager.php');

require_login();

$syscontext = context_system::instance();
require_capability('local/studentid:verify', $syscontext);

$requestid = required_param('id', PARAM_INT);
$request   = \local_studentid\manager::get_request($requestid);

if (!$request) {
    throw new moodle_exception('invalidrecord', 'error');
}

$PAGE->set_url('/local/studentid/verifier/review.php', ['id' => $requestid]);
$PAGE->set_context($syscontext);
$PAGE->set_title(get_string('reviewrequest', 'local_studentid'));
$PAGE->set_heading($SITE->fullname);
$PAGE->set_pagelayout('standard');

$PAGE->requires->js_call_amd('local_studentid/verifier_dashboard', 'initReview');
$PAGE->requires->js_call_amd('local_studentid/confirm', 'init');

// ---- Handle POST actions ----
$action = optional_param('action', '', PARAM_ALPHANUMEXT);

if ($action && confirm_sesskey()) {
    $comment = optional_param('comment', '', PARAM_TEXT);

    try {
        switch ($action) {
            case 'under_review':
                \local_studentid\manager::set_under_review($requestid, $USER->id);
                $msg   = get_string('status_set_under_review', 'local_studentid');
                $level = \core\output\notification::NOTIFY_INFO;
                break;

            case 'approve':
                \local_studentid\manager::approve_request($requestid, $comment, $USER->id);
                $msg   = get_string('requestapproved', 'local_studentid');
                $level = \core\output\notification::NOTIFY_SUCCESS;
                break;

            case 'reject':
                if (empty(trim($comment))) {
                    throw new \moodle_exception('errorcommentrequired', 'local_studentid');
                }
                \local_studentid\manager::reject_request($requestid, $comment, $USER->id);
                $msg   = get_string('requestrejected', 'local_studentid');
                $level = \core\output\notification::NOTIFY_WARNING;
                break;

            case 'generate':
                require_capability('local/studentid:generate', $syscontext);
                $cardid = \local_studentid\manager::generate_id_card($requestid, $USER->id);
                $msg   = get_string('idcard_generated', 'local_studentid');
                $level = \core\output\notification::NOTIFY_SUCCESS;
                break;

            default:
                $msg   = '';
                $level = '';
        }

        if ($msg) {
            redirect(
                new moodle_url('/local/studentid/verifier/review.php', ['id' => $requestid]),
                $msg,
                null,
                $level
            );
        }
    } catch (\moodle_exception $e) {
        \core\notification::error($e->getMessage());
    }
}

// Re-fetch after any action.
$request     = \local_studentid\manager::get_request($requestid);
$studentdata = \local_studentid\manager::get_student_data($request->userid);
$studentdata['blood_group'] = $request->blood_group ?? '';
// Use student-overridden city/state/country from the request if set.
if (!empty($request->city))    $studentdata['city']    = $request->city;
if (!empty($request->state))   $studentdata['state']   = $request->state;
if (!empty($request->country)) $studentdata['country'] = $request->country;
$card = \local_studentid\manager::get_card_for_request($requestid);

$photourl = '';
if (!empty($request->photo_path)) {
    $photourl = \local_studentid\manager::get_photo_url($requestid, $request->photo_path)->out(false);
}

$aadhaarfronturl   = '';
$aadhaarbackurl    = '';
$aadhaarfrontispdf = false;
$aadhaarbackispdf  = false;
if (!empty($request->aadhaar_front_path)) {
    $aadhaarfronturl   = \local_studentid\manager::get_aadhaar_url($requestid, 'aadhaar_front', $request->aadhaar_front_path)->out(false);
    $aadhaarfrontispdf = strtolower(pathinfo($request->aadhaar_front_path, PATHINFO_EXTENSION)) === 'pdf';
}
if (!empty($request->aadhaar_back_path)) {
    $aadhaarbackurl   = \local_studentid\manager::get_aadhaar_url($requestid, 'aadhaar_back', $request->aadhaar_back_path)->out(false);
    $aadhaarbackispdf = strtolower(pathinfo($request->aadhaar_back_path, PATHINFO_EXTENSION)) === 'pdf';
}

$abcidurl  = '';
$abcidname = '';
$abcidispdf = false;
if (!empty($request->abc_id_path)) {
    $abcidurl   = \local_studentid\manager::get_abc_id_url($requestid, $request->abc_id_path)->out(false);
    $abcidname  = $request->abc_id_path;
    // ABC ID now accepts images, but older requests may hold a PDF.
    $abcidispdf = strtolower(pathinfo($request->abc_id_path, PATHINFO_EXTENSION)) === 'pdf';
}

$tenthmarksurl   = '';
$tenthmarksname  = '';
$tenthmarksispdf = false;
if (!empty($request->tenth_marks_path)) {
    $tenthmarksurl   = \local_studentid\manager::get_tenth_marks_url($requestid, $request->tenth_marks_path)->out(false);
    $tenthmarksname  = $request->tenth_marks_path;
    $tenthmarksispdf = strtolower(pathinfo($request->tenth_marks_path, PATHINFO_EXTENSION)) === 'pdf';
}

$olevelurl   = '';
$olevelname  = '';
$olevelispdf = false;
if (!empty($request->olevel_path)) {
    $olevelurl   = \local_studentid\manager::get_olevel_url($requestid, $request->olevel_path)->out(false);
    $olevelname  = $request->olevel_path;
    $olevelispdf = strtolower(pathinfo($request->olevel_path, PATHINFO_EXTENSION)) === 'pdf';
}

$canverify   = has_capability('local/studentid:verify', $syscontext);
$cangenerate = has_capability('local/studentid:generate', $syscontext);

$reviewable  = in_array($request->status, [
    \local_studentid\manager::STATUS_SUBMITTED,
    \local_studentid\manager::STATUS_UNDER_REVIEW,
]);

// Non-Indian students upload a Passport in the same two file slots an Indian
// student uses for their Aadhaar card; label the document block accordingly.
$isindia = empty($request->country) || strcasecmp($request->country, 'India') === 0;
$documentlabel = $isindia ? 'Aadhaar Card' : 'Passport / National ID';

$templatedata = [
    'requestid'       => $requestid,
    'fullname'        => $studentdata['fullname'],
    'email'           => $studentdata['email'],
    'usn'             => $studentdata['usn'],
    'program'         => $studentdata['program'],
    'batch'           => $studentdata['batch'],
    'dob'             => $studentdata['dob'],
    'blood_group'     => $studentdata['blood_group'],
    'contact'         => $studentdata['contact'],
    'city'            => $studentdata['city'],
    'state'           => $studentdata['state'],
    'country'         => $studentdata['country'],
    'photo_url'           => $photourl,
    'has_photo'           => !empty($photourl),
    'aadhaar_front_url'   => $aadhaarfronturl,
    'aadhaar_back_url'    => $aadhaarbackurl,
    'document_label'      => $documentlabel,
    'show_abc'            => $isindia,
    'has_aadhaar_front'   => !empty($aadhaarfronturl),
    'has_aadhaar_back'    => !empty($aadhaarbackurl),
    'aadhaar_front_ispdf' => $aadhaarfrontispdf,
    'aadhaar_back_ispdf'  => $aadhaarbackispdf,
    'aadhaar_front_name'  => $request->aadhaar_front_path ?? '',
    'aadhaar_back_name'   => $request->aadhaar_back_path ?? '',
    'abc_id_url'          => $abcidurl,
    'abc_id_name'         => $abcidname,
    'has_abc_id'          => !empty($abcidurl),
    'abc_id_ispdf'        => !empty($abcidurl) && $abcidispdf,
    'abc_id_isimg'        => !empty($abcidurl) && !$abcidispdf,
    'show_tenth'          => $isindia,
    'tenth_marks_url'     => $tenthmarksurl,
    'tenth_marks_name'    => $tenthmarksname,
    'has_tenth_marks'     => !empty($tenthmarksurl),
    'tenth_marks_ispdf'   => !empty($tenthmarksurl) && $tenthmarksispdf,
    'tenth_marks_isimg'   => !empty($tenthmarksurl) && !$tenthmarksispdf,
    'show_olevel'         => !$isindia,
    'olevel_url'          => $olevelurl,
    'olevel_name'         => $olevelname,
    'has_olevel'          => !empty($olevelurl),
    'olevel_ispdf'        => !empty($olevelurl) && $olevelispdf,
    'olevel_isimg'        => !empty($olevelurl) && !$olevelispdf,
    'status'          => $request->status,
    'statuslabel'     => \local_studentid\manager::get_status_label($request->status),
    'statusclass'     => \local_studentid\manager::get_status_badge_class($request->status),
    'review_comment'  => $request->review_comment ?? '',
    'timecreated'     => userdate($request->timecreated),
    'timesubmitted'   => $request->timesubmitted ? userdate($request->timesubmitted) : '-',
    'timemodified'    => $request->timemodified ? userdate($request->timemodified) : '-',
    'reviewable'      => $reviewable,
    'is_submitted'    => $request->status === \local_studentid\manager::STATUS_SUBMITTED,
    'is_under_review' => $request->status === \local_studentid\manager::STATUS_UNDER_REVIEW,
    'is_approved'     => $request->status === \local_studentid\manager::STATUS_APPROVED,
    'can_generate'    => $cangenerate && $request->status === \local_studentid\manager::STATUS_APPROVED && !$card,
    'has_card'        => (bool) $card,
    'card_url'        => $card ? (new moodle_url('/local/studentid/download.php', ['cardid' => $card->id]))->out(false) : '',
    'card_number'     => $card ? $card->idcard_number : '',
    'back_url'        => (new moodle_url('/local/studentid/verifier/index.php'))->out(false),
    'action_url'      => (new moodle_url('/local/studentid/verifier/review.php', ['id' => $requestid]))->out(false),
    'sesskey'         => sesskey(),
];

// Predefined rejection reasons for dropdown.
$templatedata['rejection_reasons'] = [
    ['value' => get_string('reject_reason_1', 'local_studentid'), 'label' => get_string('reject_reason_1', 'local_studentid')],
    ['value' => get_string('reject_reason_2', 'local_studentid'), 'label' => get_string('reject_reason_2', 'local_studentid')],
    ['value' => get_string('reject_reason_3', 'local_studentid'), 'label' => get_string('reject_reason_3', 'local_studentid')],
    ['value' => get_string('reject_reason_4', 'local_studentid'), 'label' => get_string('reject_reason_4', 'local_studentid')],
    ['value' => get_string('reject_reason_5', 'local_studentid'), 'label' => get_string('reject_reason_5', 'local_studentid')],
    ['value' => get_string('reject_reason_6', 'local_studentid'), 'label' => get_string('reject_reason_6', 'local_studentid')],
    ['value' => '',                                                 'label' => get_string('reject_reason_other', 'local_studentid')],
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_studentid/review_request', $templatedata);
echo $OUTPUT->footer();
