<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/local/studentid/classes/manager.php');

require_login();

$syscontext = context_system::instance();

if (isguestuser()) {
    redirect(get_login_url());
}
if (has_capability('local/studentid:verify', $syscontext)) {
    redirect(new moodle_url('/local/studentid/verifier/index.php'));
}

$requestid = optional_param('id', 0, PARAM_INT);

$PAGE->set_url('/local/studentid/request.php', $requestid ? ['id' => $requestid] : []);
$PAGE->set_context($syscontext);
$PAGE->set_title(get_string('newrequest', 'local_studentid'));
$PAGE->set_heading($SITE->fullname);
$PAGE->set_pagelayout('standard');
$PAGE->requires->js_call_amd('local_studentid/request_form', 'init');

$studentdata   = \local_studentid\manager::get_student_data($USER->id);

// Indian States and Union Territories are kept in separate lists so the
// dropdown can group them under their own <optgroup>s. "Dadra and Nagar Haveli
// and Daman and Diu" is offered as two distinct UTs per requirement.
$indianstates = [
    'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh',
    'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand',
    'Karnataka', 'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur',
    'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab',
    'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura',
    'Uttar Pradesh', 'Uttarakhand', 'West Bengal',
];
$unionterritories = [
    'Andaman and Nicobar Islands', 'Chandigarh',
    'Dadra and Nagar Haveli', 'Daman and Diu',
    'Delhi', 'Jammu and Kashmir', 'Ladakh', 'Lakshadweep', 'Puducherry',
];
$validstates = array_merge($indianstates, $unionterritories);

// Country list (localised names). India drives the state/city dropdowns; any
// other country uses free-text state and city.
$allcountries  = get_string_manager()->get_list_of_countries();
$validcountries = array_values($allcountries);
$statescountry = $allcountries['IN'] ?? 'India';

// ── Handle POST ─────────────────────────────────────────────────────────────
// Must run BEFORE the GET-only redirect logic below, because the form posts
// to request.php (no id param) and the else-branch would redirect away first.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $formdata = new stdClass();
    $formdata->blood_group = optional_param('blood_group', '', PARAM_TEXT);
    $formdata->country = trim(clean_param(optional_param('country', '', PARAM_TEXT), PARAM_TEXT));
    $formdata->city  = trim(clean_param(optional_param('city', '', PARAM_TEXT), PARAM_TEXT));
    $formdata->state = trim(clean_param(optional_param('state', '', PARAM_TEXT), PARAM_TEXT));
    $formdata->requestid = optional_param('requestid', 0, PARAM_INT);

    // Validate all fields and collect errors; form re-renders with inline notifications.
    $posterrors = false;
    $validbloodgroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    if (!in_array($formdata->blood_group, $validbloodgroups)) {
        \core\notification::error(get_string('errornobloodgroup', 'local_studentid'));
        $posterrors = true;
    }
    if (empty($formdata->country) || !in_array($formdata->country, $validcountries, true)) {
        \core\notification::error('Please select your country.');
        $posterrors = true;
    }
    // State is required. For India it must be a recognised state/UT; for any
    // other country it is free text.
    if (empty($formdata->state)) {
        \core\notification::error('Please select or enter your state.');
        $posterrors = true;
    } else if ($formdata->country === $statescountry && !in_array($formdata->state, $validstates, true)) {
        \core\notification::error('Please select a valid state.');
        $posterrors = true;
    }
    if (empty($formdata->city)) {
        \core\notification::error('Please select or enter your city.');
        $posterrors = true;
    }

    if (!$posterrors) {
    try {
        $rid = \local_studentid\manager::save_request($USER->id, $formdata);

        // Save each file independently; collect errors without aborting others.
        $saveerrors = [];
        foreach (['photo', 'aadhaar_front', 'aadhaar_back', 'abc_id'] as $field) {
            if (!isset($_FILES[$field])) {
                continue;
            }
            $uerr = (int) $_FILES[$field]['error'];
            if ($uerr === UPLOAD_ERR_OK) {
                try {
                    \local_studentid\manager::save_file_from_upload($rid, $field, $_FILES[$field], $USER->id);
                } catch (\Exception $fe) {
                    $saveerrors[$field] = $fe->getMessage();
                }
            }
            // UPLOAD_ERR_NO_FILE = nothing selected — silent skip.
        }

        if (!empty($saveerrors)) {
            // Show each file error and re-render the form so the student can fix it.
            foreach ($saveerrors as $msg) {
                \core\notification::error($msg);
            }
            // Fall through to re-render request form with errors visible.
        } else {
            redirect(new moodle_url('/local/studentid/index.php'),
                $formdata->requestid
                    ? get_string('requestupdated', 'local_studentid')
                    : get_string('requestsubmitted', 'local_studentid'),
                null, \core\output\notification::NOTIFY_SUCCESS);
        }

    } catch (\moodle_exception $e) {
        \core\notification::error($e->getMessage());
    } catch (\Exception $e) {
        \core\notification::error($e->getMessage());
    }
    } // end if (!$posterrors)
}

// ── GET: load / validate the request being edited ────────────────────────────
$existingrequest = null;
if ($requestid) {
    $existingrequest = \local_studentid\manager::get_request($requestid);
    if (!$existingrequest || $existingrequest->userid != $USER->id) {
        throw new moodle_exception('errorinvalidaccess', 'local_studentid');
    }
    if ($existingrequest->status !== \local_studentid\manager::STATUS_DRAFT) {
        redirect(new moodle_url('/local/studentid/index.php'));
    }
} else {
    $active = \local_studentid\manager::get_active_request($USER->id);
    if ($active) {
        if ($active->status === \local_studentid\manager::STATUS_DRAFT) {
            redirect(new moodle_url('/local/studentid/request.php', ['id' => $active->id]));
        } elseif (!in_array($active->status, [\local_studentid\manager::STATUS_REJECTED, \local_studentid\manager::STATUS_REPLACED])) {
            redirect(new moodle_url('/local/studentid/index.php'),
                get_string('erroractiveexists', 'local_studentid'), null,
                \core\output\notification::NOTIFY_WARNING);
        }
    }
}

// ── Build existing-file URLs for the form ────────────────────────────────────
$photourl       = '';
$aadhaarfronturl = '';
$aadhaarbackurl  = '';
$aadhaarfrontispdf = false;
$aadhaarbackispdf  = false;
$abcidurl          = '';
$abcidname         = '';

if ($existingrequest) {
    if (!empty($existingrequest->photo_path)) {
        $photourl = \local_studentid\manager::get_photo_url($existingrequest->id, $existingrequest->photo_path)->out(false);
    }
    if (!empty($existingrequest->aadhaar_front_path)) {
        $aadhaarfronturl   = \local_studentid\manager::get_aadhaar_url($existingrequest->id, 'aadhaar_front', $existingrequest->aadhaar_front_path)->out(false);
        $aadhaarfrontispdf = strtolower(pathinfo($existingrequest->aadhaar_front_path, PATHINFO_EXTENSION)) === 'pdf';
    }
    if (!empty($existingrequest->aadhaar_back_path)) {
        $aadhaarbackurl   = \local_studentid\manager::get_aadhaar_url($existingrequest->id, 'aadhaar_back', $existingrequest->aadhaar_back_path)->out(false);
        $aadhaarbackispdf = strtolower(pathinfo($existingrequest->aadhaar_back_path, PATHINFO_EXTENSION)) === 'pdf';
    }
    if (!empty($existingrequest->abc_id_path)) {
        $abcidurl  = \local_studentid\manager::get_abc_id_url($existingrequest->id, $existingrequest->abc_id_path)->out(false);
        $abcidname = $existingrequest->abc_id_path;
    }
}

$bloodgroups  = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
$selectedblood = $existingrequest->blood_group ?? '';
$isupdate      = $existingrequest ? true : false;
$formrid       = $existingrequest ? $existingrequest->id : 0;

// City and state: prefer stored override, fall back to profile.
// Guard $existingrequest !== null to avoid property-on-null in PHP 8.
$cityvalue  = ($existingrequest !== null && !empty($existingrequest->city))
    ? $existingrequest->city
    : $studentdata['city'];
$statevalue = ($existingrequest !== null && !empty($existingrequest->state))
    ? $existingrequest->state
    : ($studentdata['state'] ?? '');
$countryvalue = ($existingrequest !== null && !empty($existingrequest->country))
    ? $existingrequest->country
    : ($studentdata['country'] ?? '');

// ── Render ───────────────────────────────────────────────────────────────────
// ── Build the template context ───────────────────────────────────────────────
$infofields = [];
foreach ([
    ['Full Name',     $studentdata['fullname']],
    ['USN',           $studentdata['usn']],
    ['Program',       $studentdata['program']],
    ['Batch',         $studentdata['batch']],
    ['Date of Birth', $studentdata['dob']],
    ['Contact',       $studentdata['contact']],
] as $f) {
    $infofields[] = ['label' => $f[0], 'value' => $f[1] ?: ''];
}

// Country options (mandatory dropdown). Selected = request override or profile.
$countryoptions = array_map(function($name) use ($countryvalue) {
    return ['value' => $name, 'selected' => ($countryvalue === $name)];
}, $validcountries);

$buildstateopts = function(array $list) use ($statevalue) {
    return array_map(function($s) use ($statevalue) {
        return ['value' => $s, 'selected' => ($statevalue === $s)];
    }, $list);
};
$stategroups = [
    ['grouplabel' => 'States', 'options' => $buildstateopts($indianstates)],
    ['grouplabel' => 'Union Territories', 'options' => $buildstateopts($unionterritories)],
];

$bloodoptions = array_map(function($bg) use ($selectedblood) {
    return ['value' => $bg, 'selected' => ($selectedblood === $bg)];
}, $bloodgroups);

$aadhaarcards = [];
foreach ([
    ['aadhaar_front', 'Front Side', $aadhaarfronturl, $aadhaarfrontispdf, $existingrequest->aadhaar_front_path ?? ''],
    ['aadhaar_back',  'Back Side',  $aadhaarbackurl,  $aadhaarbackispdf,  $existingrequest->aadhaar_back_path  ?? ''],
] as [$field, $label, $url, $ispdf, $filename]) {
    $has = !empty($url);
    $aadhaarcards[] = [
        'field'        => $field,
        'label'        => $label,
        'url'          => $url,
        'filename'     => $filename,
        'has'          => $has,
        'is_pdf'       => $has && $ispdf,
        'is_img'       => $has && !$ispdf,
        'hasfileattr'  => $has ? '1' : '0',
    ];
}

$templatedata = [
    'form_url'      => (new moodle_url('/local/studentid/request.php'))->out(false),
    'cancel_url'    => (new moodle_url('/local/studentid/index.php'))->out(false),
    'sesskey'       => sesskey(),
    'requestid'     => $formrid,
    'infofields'    => $infofields,
    'country_options' => $countryoptions,
    'states_country'  => $statescountry,
    'saved_country'   => $countryvalue,
    'saved_state'     => $statevalue,
    'saved_city'    => $cityvalue,
    'state_groups'  => $stategroups,
    'blood_options' => $bloodoptions,
    'has_photo'     => !empty($photourl),
    'photo_url'     => $photourl,
    'aadhaar_cards' => $aadhaarcards,
    'abc_has'         => !empty($abcidurl),
    'abc_name'        => $abcidname,
    'abc_hasfileattr' => !empty($abcidurl) ? '1' : '0',
    'submit_label'  => get_string('saverequest', 'local_studentid'),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_studentid/request_form', $templatedata);
echo $OUTPUT->footer();
