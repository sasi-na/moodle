<?php
namespace local_zoomattendance\helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Create a Zoom meeting and a matching course calendar event in one step,
 * then wire the two together for the direct-join / attendance flow.
 *
 * Driven by the faculty-facing schedule.php form. The Zoom meeting settings
 * mirror the account's existing class-meeting policy (registration on with
 * auto-approve, join-before-host, mute on entry, cloud recording, no waiting
 * room, video off) so auto-created meetings behave exactly like hand-made ones.
 */
class meeting_creator {

    /**
     * Meeting settings replicating the current class-meeting policy.
     */
    public static function policy_settings(string $hostemail): array {
        return [
            'host_video' => false,
            'participant_video' => false,
            'join_before_host' => true,
            'jbh_time' => 0,
            'mute_upon_entry' => true,
            // 0 = registration on, registrants auto-approved. Required so the
            // per-user registrant join flow (join_helper::register_user) works.
            'approval_type' => 0,
            'audio' => 'both',
            // Cloud recording keeps the recorded-session summary flow fed.
            'auto_recording' => 'cloud',
            'waiting_room' => false,
            'meeting_authentication' => false,
            'registrants_email_notification' => true,
            'registrants_confirmation_email' => true,
            'contact_name' => 'IIIT Dharwad',
            'contact_email' => $hostemail,
        ];
    }

    /**
     * Format a unix time as a Zoom start_time + timezone pair, using the
     * current user's timezone so Zoom shows the wall-clock time that was
     * picked. Falls back to Asia/Kolkata if the user's timezone is unusable.
     *
     * @return array [start_time string 'Y-m-dTH:i:s', timezone string]
     */
    public static function start_time_for_zoom(int $timestart): array {
        global $USER;

        $tz = \core_date::get_user_timezone($USER);
        $dt = new \DateTime('@' . $timestart);
        try {
            $dt->setTimezone(new \DateTimeZone($tz));
        } catch (\Exception $e) {
            $tz = 'Asia/Kolkata';
            $dt->setTimezone(new \DateTimeZone($tz));
        }
        return [$dt->format('Y-m-d\TH:i:s'), $tz];
    }

    /**
     * Create the meeting + event.
     *
     * @param int    $courseid       course the event belongs to
     * @param string $title          meeting topic / event title
     * @param int    $timestart      unix start time
     * @param int    $durationsecs   duration in seconds
     * @param string $descriptionhtml extra description HTML (may be empty)
     * @param string $hostemail      Zoom host the meeting is created under
     * @return int   the new calendar event id
     * @throws \moodle_exception if Zoom meeting creation fails (nothing created)
     */
    public static function create(int $courseid, string $title, int $timestart,
            int $durationsecs, string $descriptionhtml, string $hostemail): int {
        global $DB, $USER, $CFG;

        require_once($CFG->dirroot . '/calendar/lib.php');

        $minutes = max(1, (int)round($durationsecs / 60));

        // Send the start time in the user's timezone so Zoom shows the same
        // wall-clock time the faculty member picked.
        [$starttimestr, $zoomtz] = self::start_time_for_zoom($timestart);

        $res = zoom_api::post('/users/' . rawurlencode($hostemail) . '/meetings', [
            'topic' => $title,
            'type' => 2, // Scheduled meeting.
            'start_time' => $starttimestr,
            'timezone' => $zoomtz,
            'duration' => $minutes,
            'settings' => self::policy_settings($hostemail),
        ]);

        if (($res['status'] ?? 0) !== 201 || empty($res['data']['id'])) {
            throw new \moodle_exception('meetingcreatefailed', 'local_zoomattendance', '',
                null, 'Zoom API HTTP ' . ($res['status'] ?? 0));
        }

        $meetingid = (string)$res['data']['id'];
        $registrationurl = (string)($res['data']['registration_url'] ?? '');
        $joinurl = (string)($res['data']['join_url'] ?? '');
        // Prefer the registration URL (drives the per-user join flow); the plain
        // join URL is only a fallback if registration somehow wasn't enabled.
        $zoomurl = $registrationurl !== '' ? $registrationurl : $joinurl;

        $linkhtml = '<p><a href="' . s($zoomurl) . '">'
            . get_string('joinzoomlink', 'local_zoomattendance') . '</a></p>';
        $fulldesc = trim($descriptionhtml) . $linkhtml;

        $event = new \stdClass();
        $event->name = $title;
        $event->description = $fulldesc;
        $event->format = FORMAT_HTML;
        $event->courseid = $courseid;
        $event->groupid = 0;
        $event->userid = $USER->id;
        $event->modulename = '';
        $event->instance = 0;
        $event->eventtype = 'course';
        $event->timestart = $timestart;
        $event->timeduration = $durationsecs;
        $event->timesort = $timestart;
        $event->visible = 1;

        // checkcapability = false: the page already enforced our own
        // schedulemeeting capability, which non-editing teachers hold even
        // though they lack moodle/calendar:manageentries.
        $created = \calendar_event::create($event, false);
        $eventid = (int)$created->id;

        // Wire the meeting into the direct-join flow immediately.
        if ($registrationurl !== '') {
            join_helper::link_event_to_meeting($eventid, $registrationurl, $meetingid,
                $hostemail, $title, $timestart);
        }

        return $eventid;
    }
}
