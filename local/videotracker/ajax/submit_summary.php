<?php

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');

require_once(
    $CFG->dirroot .
    '/local/videotracker/lib.php'
);

require_login();

global $DB, $USER;

header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 1);

/*
|--------------------------------------------------------------------------
| Decode JSON safely
|--------------------------------------------------------------------------
*/

$rawbody = file_get_contents(
    'php://input'
);

$data = json_decode(
    $rawbody,
    true
);

/*
|--------------------------------------------------------------------------
| Invalid JSON
|--------------------------------------------------------------------------
*/

if (
    empty($data)
    || !is_array($data)
) {

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
    empty($data['sesskey'])
    || !confirm_sesskey(
        $data['sesskey']
    )
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
    'videoid',
    'cmid',
    'courseid',
    'summary'
];

foreach ($requiredfields as $field) {

    if (
        !array_key_exists(
            $field,
            $data
        )
    ) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' =>
                'Missing required field: ' .
                $field
        ]);

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Clean values
|--------------------------------------------------------------------------
*/

$sessionuuid = clean_param(
    $data['sessionuuid'],
    PARAM_TEXT
);

$videoid = clean_param(
    $data['videoid'],
    PARAM_TEXT
);

$cmid = clean_param(
    $data['cmid'],
    PARAM_INT
);

$courseid = clean_param(
    $data['courseid'],
    PARAM_INT
);

/*
|--------------------------------------------------------------------------
| Zoom meeting ID
|--------------------------------------------------------------------------
|
| Kept as TEXT because Zoom IDs
| may exceed integer range.
|--------------------------------------------------------------------------
*/

$zoommeetingid = '';

if (
    isset($data['zoommeetingid'])
) {

    $zoommeetingid = clean_param(
        $data['zoommeetingid'],
        PARAM_TEXT
    );
}

/*
|--------------------------------------------------------------------------
| Clean summary
|--------------------------------------------------------------------------
*/

$summary = trim(
    clean_param(
        $data['summary'],
        PARAM_RAW
    )
);

/*
|--------------------------------------------------------------------------
| Validate summary
|--------------------------------------------------------------------------
*/

if (empty($summary)) {

    echo json_encode([
        'success' => false,
        'message' =>
            'Summary cannot be empty.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Word count
|--------------------------------------------------------------------------
*/

// Count words the SAME way the client counter does: whitespace-separated
// tokens. PHP's str_word_count() ignores pure-number tokens (years, citation
// numbers like "2023" or "[1]"), so a citation-heavy summary the student sees
// as 151 words could be counted below 150 here and wrongly rejected.
$plain = trim(strip_tags($summary));

$wordcount = ($plain === '')
    ? 0
    : count(preg_split('/\s+/u', $plain));

/*
|--------------------------------------------------------------------------
| Minimum word validation
|--------------------------------------------------------------------------
*/

if ($wordcount < 150) {

    echo json_encode([
        'success' => false,
        'message' =>
            'Minimum 150 words required.'
    ]);

    exit;
}

if (
    strlen($summary) > 20000
) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' =>
            'Summary too large.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Duplicate / resubmit handling
|
| A summary may only be resubmitted once, and only after it was rejected.
| status: 0 = pending, 1 = approved, 2 = rejected.
|--------------------------------------------------------------------------
*/

$existing = $DB->get_record(
    'local_zoom_recording_summary',
    [
        'userid' => $USER->id,
        'cmid' => $cmid,
        'videoid' => $videoid
    ],
    'id, status, attempts',
    IGNORE_MISSING
);

$isresubmit = false;

if ($existing) {

    // Pending or approved summaries cannot be changed.
    if ((int)$existing->status !== 2) {

        echo json_encode([
            'success' => false,
            'message' =>
                'Summary already submitted.'
        ]);

        exit;
    }

    // Rejected, but the single resubmit opportunity is already used.
    if ((int)$existing->attempts >= 2) {

        echo json_encode([
            'success' => false,
            'message' =>
                'You have already used your resubmission attempt.'
        ]);

        exit;
    }

    $isresubmit = true;
}

/*
|--------------------------------------------------------------------------
| Validate tracking session
|--------------------------------------------------------------------------
*/

$session = $DB->get_record(
    'local_videotracker_session',
    [
        'sessionuuid' => $sessionuuid,
        'userid' => $USER->id
    ]
);

if (!$session) {

    echo json_encode([
        'success' => false,
        'message' => 'Session not found.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Validate session ownership
|--------------------------------------------------------------------------
*/

if (
    (int)$session->cmid !== $cmid
    || $session->videoid !== $videoid
) {

    echo json_encode([
        'success' => false,
        'message' =>
            'Session validation failed.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Validate completion
|--------------------------------------------------------------------------
|
| Backend validation mandatory.
|--------------------------------------------------------------------------
*/

$minimumwatchpercentage =
    local_videotracker_minimum_watch_percentage();

if (
    (int)$session->completed !== 1
    || (
        (float)$session->percentage <
        $minimumwatchpercentage
    )
) {

    echo json_encode([
        'success' => false,
        'message' =>
            'Minimum watch percentage not met.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Build summary record
|
| status 0 = Pending, 1 = Approved, 2 = Rejected. New and resubmitted
| summaries both await faculty review.
|--------------------------------------------------------------------------
*/

$record = new stdClass();

$record->userid = $USER->id;

$record->courseid = $courseid;

$record->cmid = $cmid;

$record->videoid = $videoid;

$record->zoommeetingid =
    $zoommeetingid;

$record->sessionid = $session->id;

$record->summary = $summary;

$record->wordcount = $wordcount;

$record->status = 0;

$record->reviewedby = 0;

$record->reviewedat = 0;

$record->feedback = '';

$record->submittedat = time();

/*
|--------------------------------------------------------------------------
| Save summary
|
| Resubmit updates the rejected record in place (the single allowed
| resubmit); otherwise insert a fresh submission.
|--------------------------------------------------------------------------
*/

if ($isresubmit) {

    $record->id = $existing->id;

    $record->attempts = (int)$existing->attempts + 1;

    $DB->update_record(
        'local_zoom_recording_summary',
        $record
    );

} else {

    $record->attempts = 1;

    $DB->insert_record(
        'local_zoom_recording_summary',
        $record
    );
}

/*
|--------------------------------------------------------------------------
| Success response
|--------------------------------------------------------------------------
*/

echo json_encode([
    'success' => true
]);

exit;