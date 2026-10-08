<?php

require_once(__DIR__ . '/../../../config.php');

require_login();

global $DB, $USER;

header('Content-Type: application/json');

/*
|--------------------------------------------------------------------------
| Read JSON body safely
|--------------------------------------------------------------------------
*/

$rawbody = file_get_contents('php://input');
$data = json_decode($rawbody, true);

if (!is_array($data)) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid request payload.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Sesskey validation (Moodle-safe)
|--------------------------------------------------------------------------
|
| IMPORTANT:
| confirm_sesskey() depends on active session cookie.
| If missing → will always fail.
|--------------------------------------------------------------------------
*/

if (
    empty($data['sesskey']) ||
    !confirm_sesskey($data['sesskey'])
) {

    http_response_code(403);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid session.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Required fields validation
|--------------------------------------------------------------------------
*/

$requiredfields = [
    'courseid',
    'cmid',
    'videoid',
    'sessionuuid',
    'videoduration'
];

foreach ($requiredfields as $field) {

    if (!isset($data[$field])) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Missing required field: ' . $field
        ]);

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Clean values
|--------------------------------------------------------------------------
*/

$courseid = (int) $data['courseid'];
$cmid = (int) $data['cmid'];

$videoid = clean_param($data['videoid'], PARAM_ALPHANUMEXT);
$sessionuuid = clean_param($data['sessionuuid'], PARAM_ALPHANUMEXT);

$videoduration = (int) $data['videoduration'];

/*
|--------------------------------------------------------------------------
| Validate duration
|--------------------------------------------------------------------------
*/

if ($videoduration <= 0) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid video duration.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Prevent duplicate session
|--------------------------------------------------------------------------
*/

if (
    $DB->record_exists(
        'local_videotracker_session',
        ['sessionuuid' => $sessionuuid]
    )
) {

    echo json_encode([
        'success' => true,
        'message' => 'Session already exists'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Insert session
|--------------------------------------------------------------------------
*/

$record = (object)[
    'userid' => $USER->id,
    'courseid' => $courseid,
    'cmid' => $cmid,
    'videoid' => $videoid,
    'sessionuuid' => $sessionuuid,
    'startedat' => time(),
    'endedat' => null,
    'videoduration' => $videoduration,
    'watchedseconds' => 0,
    'percentage' => 0,
    'completed' => 0
];

$DB->insert_record('local_videotracker_session', $record);

/*
|--------------------------------------------------------------------------
| Success response
|--------------------------------------------------------------------------
*/

echo json_encode([
    'success' => true
]);
exit;