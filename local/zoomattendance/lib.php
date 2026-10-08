<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Drop the cached Zoom OAuth token.
 *
 * Wired to the credential settings so rotating them takes effect immediately
 * instead of after the cached token expires (up to an hour later).
 */
function local_zoomattendance_purge_token() {
    \local_zoomattendance\helper\zoom_api::purge_token();
}

/**
 * Add link in course navigation (Admin/Teacher only)
 */
/**
 * Is Zoom actually configured on THIS site?
 *
 * local_zoomattendance ships in an image shared by every tenant, so its code is
 * present on sites that have no Zoom integration at all. Without this check the
 * scheduled tasks would run every 15-30 minutes on those sites, fail against a
 * Zoom API they have no credentials for, and fill cron logs with errors.
 *
 * Gating here rather than with 'disabled' => 1 in db/tasks.php is deliberate:
 * Moodle resets non-customised task definitions from that file on every plugin
 * upgrade, and IIITD's rows are customised=0 -- so a disabled-by-default
 * schedule would silently switch IIITD's own attendance processing off at the
 * next upgrade. A runtime check cannot drift that way.
 *
 * @return bool true when this site has Zoom credentials.
 */
function local_zoomattendance_is_configured(): bool {
    foreach (['zoom_account_id', 'zoom_client_id', 'zoom_client_secret'] as $key) {
        if (trim((string)get_config('local_zoomattendance', $key)) === '') {
            return false;
        }
    }
    return true;
}

function local_zoomattendance_extend_navigation_course($navigation, $course, $context) {

    // Nothing to link to on a site without Zoom configured.
    if (!local_zoomattendance_is_configured()) {
        return;
    }

    // Teacher/admin: live attendance + summary review report.
    if (has_capability('moodle/course:update', $context)) {

        $url = new moodle_url('/local/zoomattendance/view.php', [
            'courseid' => $course->id
        ]);

        $navigation->add(
            get_string('livesessionattendance', 'local_zoomattendance'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'livesessionattendance',
            new pix_icon('i/report', '')
        );
    }

    // Faculty: schedule a Zoom meeting + calendar event in one step.
    if (has_capability('local/zoomattendance:schedulemeeting', $context)) {

        $navigation->add(
            get_string('schedulemeeting', 'local_zoomattendance'),
            new moodle_url('/local/zoomattendance/schedule.php', ['courseid' => $course->id]),
            navigation_node::TYPE_SETTING,
            null,
            'schedulezoommeeting',
            new pix_icon('i/calendar', '')
        );
    }
}
