<?php

require_once(__DIR__ . '/../../../config.php');

require_login();

global $DB, $USER;

header('Content-Type: application/json');

/*
|--------------------------------------------------------------------------
| Decode JSON safely
|--------------------------------------------------------------------------
*/

$rawbody = file_get_contents('php://input');
$data = json_decode($rawbody, true);

/*
|--------------------------------------------------------------------------
| Validate JSON
|--------------------------------------------------------------------------
*/

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
| Sesskey validation
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
    'sessionuuid',
    'watchedseconds',
    'percentage',
    'completed'
];

foreach ($requiredfields as $field) {

    if (!array_key_exists($field, $data)) {

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
| Clean input
|--------------------------------------------------------------------------
*/

$sessionuuid = clean_param($data['sessionuuid'], PARAM_ALPHANUMEXT);

// Only the watched duration is taken from the client; percentage and
// completion are derived server-side below and never trusted from input.
$watchedseconds = max(0, (int)$data['watchedseconds']);

/*
|--------------------------------------------------------------------------
| Fetch session (SAFE mode)
|--------------------------------------------------------------------------
*/

$record = $DB->get_record(
    'local_videotracker_session',
    [
        'sessionuuid' => $sessionuuid,
        'userid' => $USER->id
    ]
);

if (!$record) {

    http_response_code(404);

    echo json_encode([
        'success' => false,
        'message' => 'Session not found.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Cap watched duration
|--------------------------------------------------------------------------
|
| Two ceilings:
|  1. The video can't be watched beyond its own length.
|  2. Anti-scrub: claimed watch time can't exceed plausibly-elapsed real
|     time since the session started. The 2.5x factor allows up to ~2x
|     playback plus buffering jitter; +5s grace covers the first update.
|--------------------------------------------------------------------------
*/

$videoduration = (int)$record->videoduration;

if ($videoduration > 0 && $watchedseconds > $videoduration) {
    $watchedseconds = $videoduration;
}

$elapsed = max(0, time() - (int)$record->startedat);
$maxbyrealtime = (int)ceil($elapsed * 2.5) + 5;

if ($watchedseconds > $maxbyrealtime) {
    $watchedseconds = $maxbyrealtime;
}

/*
|--------------------------------------------------------------------------
| Preserve the maximum watched duration, then derive the rest
|--------------------------------------------------------------------------
*/

$record->watchedseconds = max(
    (int)$record->watchedseconds,
    $watchedseconds
);

// Server-authoritative percentage; the client's value is ignored.
$record->percentage = $videoduration > 0
    ? min(100, round(($record->watchedseconds / $videoduration) * 100, 2))
    : 0;

/*
|--------------------------------------------------------------------------
| Completion (derived, sticky)
|--------------------------------------------------------------------------
*/

require_once($CFG->dirroot . '/local/videotracker/lib.php');

if ($record->percentage >= local_videotracker_minimum_watch_percentage()) {
    $record->completed = 1;
}

/*
|--------------------------------------------------------------------------
| End timestamp
|--------------------------------------------------------------------------
*/

$record->endedat = time();

/*
|--------------------------------------------------------------------------
| Update DB
|--------------------------------------------------------------------------
*/

$DB->update_record('local_videotracker_session', $record);

/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

echo json_encode([
    'success' => true,
    'watchedseconds' => $record->watchedseconds,
    'percentage' => $record->percentage,
    'completed' => $record->completed
]);

exit;