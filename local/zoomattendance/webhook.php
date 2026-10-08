<?php
require('../../config.php');

use local_zoomattendance\helper\join_helper;

global $DB;

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

// Basic validation
if (!$data || empty($data['event'])) {
    http_response_code(400);
    exit;
}

// Webhook secret is admin-settings only — no hardcoded fallback, so an
// unconfigured install simply can't validate signatures (rather than trusting
// some other account's token). URL validation and signature checks below will
// just fail until webhook_secret is set.
$secret = (string)get_config('local_zoomattendance', 'webhook_secret');

// Zoom sends the event time in milliseconds; used to ignore stale,
// out-of-order webhook retries when updating the live status.
$eventts = (int)(($data['event_ts'] ?? 0) / 1000) ?: time();

/**
 * =========================================
 * 1. Zoom URL Validation
 * =========================================
 */
if ($data['event'] === 'endpoint.url_validation') {

    $plainToken = $data['payload']['plainToken'] ?? '';

    echo json_encode([
        'plainToken' => $plainToken,
        'encryptedToken' => hash_hmac('sha256', $plainToken, $secret)
    ]);

    http_response_code(200);
    exit;
}

/**
 * =========================================
 * 2. Verify the request really came from Zoom
 * =========================================
 *
 * Every non-validation event carries x-zm-signature, an HMAC over
 * "v0:{timestamp}:{body}" keyed with the same secret token. Without this
 * check anyone could POST forged meeting.started/ended events and control
 * the join gate or pollute the attendance queue.
 */
$signature = $_SERVER['HTTP_X_ZM_SIGNATURE'] ?? '';
$timestamp = $_SERVER['HTTP_X_ZM_REQUEST_TIMESTAMP'] ?? '';

$expected = 'v0=' . hash_hmac('sha256', "v0:{$timestamp}:{$raw}", $secret);
$validsig = ($signature !== '' && hash_equals($expected, $signature));

// Enforcement is opt-in (webhook_strict). This lets the endpoint be deployed
// without risking the existing attendance pipeline: signatures are verified
// and failures logged, but events are still processed until an admin confirms
// (via the log staying quiet during real classes) that Zoom's signatures
// validate, then enables strict mode to start rejecting forgeries.
if (!$validsig) {
    error_log('local_zoomattendance: webhook signature ' . ($signature === '' ? 'missing' : 'invalid')
        . ' for event ' . $data['event']);
    if (get_config('local_zoomattendance', 'webhook_strict')) {
        http_response_code(401);
        exit;
    }
}

/**
 * =========================================
 * 3. Meeting Started Event
 * =========================================
 */
if ($data['event'] === 'meeting.started') {

    $object = $data['payload']['object'] ?? [];

    $meetingid = (string)($object['id'] ?? '');

    if ($meetingid !== '') {
        try {
            join_helper::update_live_status(
                $meetingid,
                'started',
                $eventts,
                trim($object['topic'] ?? ''),
                strtotime($object['start_time'] ?? '') ?: 0
            );
        } catch (\Throwable $e) {
            debugging('local_zoomattendance: live-status update failed: ' . $e->getMessage(), DEBUG_NORMAL);
        }
    }

    http_response_code(200);
    exit;
}

/**
 * =========================================
 * 4. Meeting Ended Event
 * =========================================
 */
if ($data['event'] === 'meeting.ended') {

    $object = $data['payload']['object'] ?? [];

    $uuid       = trim($object['uuid'] ?? '');
    $meetingid  = (string)($object['id'] ?? '');
    $topic      = trim($object['topic'] ?? '');

    //Validate required fields
    if (empty($uuid) || empty($meetingid)) {
        http_response_code(200);
        exit;
    }

    /**
     * Insert into queue table (legacy critical path — done first and
     * unconditionally so the new live-status schema can never block it).
     */
    if (!$DB->record_exists('local_zoom_attendance_queue', ['uuid' => $uuid])) {

        $rec = new stdClass();
        $rec->meeting_id = $meetingid;
        $rec->uuid       = $uuid;
        $rec->topic      = $topic;

        // session duration values
        $rec->start_time = strtotime($object['start_time'] ?? '') ?: 0;
        $rec->end_time   = strtotime($object['end_time'] ?? '') ?: 0;

        if ($rec->start_time && $rec->end_time) {
            $rec->session_duration = $rec->end_time - $rec->start_time;
        }

        // processing flags
        $rec->processed = 0;
        $rec->retrycount = 0;

        // Email sent flag
        $rec->email_sent = 0;

        $rec->timecreated = time();

        $DB->insert_record('local_zoom_attendance_queue', $rec);
    }

    // Close the join gate. Isolated so a new-schema issue (e.g. during the
    // deploy/upgrade gap) can never drop attendance, and retries still update.
    try {
        join_helper::update_live_status($meetingid, 'ended', $eventts, $topic);
    } catch (\Throwable $e) {
        debugging('local_zoomattendance: live-status update failed: ' . $e->getMessage(), DEBUG_NORMAL);
    }
}

/**
 * Always respond OK to Zoom
 */
http_response_code(200);
exit;
