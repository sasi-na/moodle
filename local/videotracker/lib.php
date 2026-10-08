<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Get videotracker config.
 *
 * @param stdClass $cm
 * @param stdClass $course
 * @return array|null
 */
function local_videotracker_get_config(
    stdClass $cm,
    stdClass $course
) {

    global $USER, $DB, $CFG;

    /*
    |--------------------------------------------------------------------------
    | Tenant gate
    |--------------------------------------------------------------------------
    |
    | This plugin ships in an image shared by every tenant, and mod/page/view.php
    | calls this function on EVERY page-module view. Activation keys off the page
    | content containing a player.vimeo.com URL -- which is not a tenant
    | boundary: jo-moodle-prod has 43,782 such pages and learn-usdc-prod 25,222.
    | Ungated, this would impose IIITD's watch-percentage tracking and
    | progression gating on ~69,000 content pages across two live sites.
    |
    | Opt in with $CFG->videotracker = true; in the tenant's config.php.
    */

    if (empty($CFG->videotracker)) {
        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Basic validation
    |--------------------------------------------------------------------------
    */

    if (
        isguestuser()
        || empty($cm)
        || empty($course)
    ) {
        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Only Page module
    |--------------------------------------------------------------------------
    */

    if (
        empty($cm->modname)
        || $cm->modname !== 'page'
    ) {
        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Get page safely
    |--------------------------------------------------------------------------
    */

    $page = $DB->get_record(
        'page',
        [
            'id' => $cm->instance
        ],
        '*',
        IGNORE_MISSING
    );

    if (
        empty($page)
        || empty($page->content)
    ) {
        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Extract Vimeo video ID
    |--------------------------------------------------------------------------
    */

    if (
        !preg_match(
            '/player\.vimeo\.com\/video\/([0-9]+)/i',
            $page->content,
            $matches
        )
    ) {
        return null;
    }

    $videoid = (int)$matches[1];

    if ($videoid <= 0) {
        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Tags
    |--------------------------------------------------------------------------
    */

    $tags = core_tag_tag::get_item_tags(
        'core',
        'course_modules',
        $cm->id
    );

    $haszoomrecordingtag = false;

    $zoommeetingid = '';

    foreach ($tags as $tag) {

        if (empty($tag->rawname)) {
            continue;
        }

        $tagname = core_text::strtolower(
            trim($tag->rawname)
        );

        if (
            preg_match(
                '/^zoom_recording_(.+)$/',
                $tagname,
                $tagmatches
            )
        ) {

            $haszoomrecordingtag = true;

            // Strip whitespace so a tag copied as "830 1234 5678" still
            // matches the Zoom meeting id ("83012345678").
            $zoommeetingid = preg_replace(
                '/\s+/',
                '',
                $tagmatches[1]
            );

            break;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Attendance check
    |
    | A student counts as "attended" via the live session only if they were
    | present for at least the minimum percentage of the session.
    | Falling short means the live session is treated as missed, so the
    | recorded-summary path applies.
    |--------------------------------------------------------------------------
    */

    $attended = false;

    if (
        $haszoomrecordingtag
        && !empty($zoommeetingid)
        && !empty($USER->email)
    ) {

        $attended = local_videotracker_attended_live(
            $USER->email,
            $zoommeetingid
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Existing summary
    |--------------------------------------------------------------------------
    |
    | status: -1 = none, 0 = pending, 1 = approved, 2 = rejected.
    |--------------------------------------------------------------------------
    */

    $existingsummary = $DB->get_record(
        'local_zoom_recording_summary',
        [
            'userid' => (int)$USER->id,
            'cmid' => (int)$cm->id,
            'videoid' => (string)$videoid
        ],
        'id, status, feedback, attempts, summary',
        IGNORE_MISSING
    );

    $summarysubmitted = (bool)$existingsummary;

    $summarystatus = $existingsummary
        ? (int)$existingsummary->status
        : -1;

    $summaryfeedback = ($existingsummary && !empty($existingsummary->feedback))
        ? (string)$existingsummary->feedback
        : '';

    /*
    |--------------------------------------------------------------------------
    | Resubmit rule
    |
    | A rejected summary (status 2) may be edited and resubmitted exactly
    | once (attempts < 2). The previous text is returned for prefilling.
    |--------------------------------------------------------------------------
    */

    $canresubmit = $existingsummary
        && $summarystatus === 2
        && (int)$existingsummary->attempts < 2;

    $previoussummary = $canresubmit
        ? (string)$existingsummary->summary
        : '';

    /*
    |--------------------------------------------------------------------------
    | Final summary rule
    |
    | Prompt for a summary when none exists yet, or when a rejected one may
    | still be resubmitted.
    |--------------------------------------------------------------------------
    */

    $requiresummary =
        $haszoomrecordingtag
        && !$attended
        && (!$summarysubmitted || $canresubmit);

    /*
    |--------------------------------------------------------------------------
    | Final config
    |--------------------------------------------------------------------------
    */

    return [

        'wwwroot' => $CFG->wwwroot,

        'cmid' => (int)$cm->id,

        'courseid' => (int)$course->id,

        'videoid' => (string)$videoid,

        'zoommeetingid' => (string)$zoommeetingid,

        'requiresummary' => (bool)$requiresummary,

        'summarysubmitted' => (bool)$summarysubmitted,

        'summarystatus' => (int)$summarystatus,

        'summaryfeedback' => (string)$summaryfeedback,

        'canresubmit' => (bool)$canresubmit,

        'previoussummary' => (string)$previoussummary,

        'minimumwatchpercentage' => local_videotracker_minimum_watch_percentage()
    ];
}

/**
 * Minimum watch percentage required.
 *
 * @return int
 */
function local_videotracker_minimum_watch_percentage() {

    return 50;
}

/**
 * Minimum live-session attendance percentage required to be marked present.
 *
 * @return int
 */
function local_videotracker_minimum_live_percentage() {

    return 50;
}

/**
 * Whether a user attended a live Zoom session above the required threshold.
 *
 * Sums the user's attended duration across all logged summary rows for the
 * meeting and compares it to the session duration.
 *
 * @param string $email
 * @param string $meetingid
 * @return bool
 */
function local_videotracker_attended_live($email, $meetingid) {

    global $DB;

    $rows = $DB->get_records(
        'local_zoom_attendance_summary',
        [
            'user_email' => core_text::strtolower(trim($email)),
            'meeting_id' => $meetingid
        ],
        '',
        'id, total_duration, session_duration'
    );

    if (empty($rows)) {
        return false;
    }

    $attendedseconds = 0;
    $sessionduration = 0;

    foreach ($rows as $row) {
        $attendedseconds += (int)$row->total_duration;
        $sessionduration = max($sessionduration, (int)$row->session_duration);
    }

    /*
    |--------------------------------------------------------------------------
    | Degenerate data
    |
    | If the session duration was never captured we cannot enforce the
    | threshold; fall back to "attended" when any time was logged so genuine
    | attendees are not wrongly pushed onto the summary path.
    |--------------------------------------------------------------------------
    */
    if ($sessionduration <= 0) {
        return $attendedseconds > 0;
    }

    $percentage = ($attendedseconds / $sessionduration) * 100;

    return $percentage >= local_videotracker_minimum_live_percentage();
}