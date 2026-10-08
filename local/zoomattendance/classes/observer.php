<?php
namespace local_zoomattendance;

defined('MOODLE_INTERNAL') || die();

use local_zoomattendance\helper\zoom_api;
use local_zoomattendance\helper\meeting_creator;

/**
 * Keeps scheduler-created Zoom meetings in sync with their calendar events.
 *
 * Only events linked through the in-LMS scheduler (local_zoom_event_join.managed
 * = 1) are touched — meetings created manually and merely mapped by the cron are
 * never updated or deleted on Zoom from here. Every handler fails soft: a Zoom
 * API hiccup must never break the calendar edit/delete the user just performed.
 */
class observer {

    /**
     * Fetch the managed event-join row for an event, or null if the event
     * isn't a scheduler-managed meeting.
     */
    private static function managed_record(int $eventid): ?\stdClass {
        global $DB;
        $rec = $DB->get_record('local_zoom_event_join', ['event_id' => $eventid]);
        if (!$rec || (int)$rec->managed !== 1 || empty($rec->meeting_id)) {
            return null;
        }
        return $rec;
    }

    /**
     * Calendar event edited: push the new title / time / duration to Zoom.
     */
    public static function event_updated(\core\event\calendar_event_updated $event): void {
        global $DB;

        try {
            $eventid = (int)$event->objectid;
            $rec = self::managed_record($eventid);
            if (!$rec) {
                return;
            }

            $cal = $DB->get_record('event', ['id' => $eventid], 'id, name, timestart, timeduration');
            if (!$cal) {
                return;
            }

            [$starttimestr, $tz] = meeting_creator::start_time_for_zoom((int)$cal->timestart);

            $body = [
                'topic' => $cal->name,
                'type' => 2,
                'start_time' => $starttimestr,
                'timezone' => $tz,
            ];
            // Only send a duration when the event actually has one.
            if ((int)$cal->timeduration > 0) {
                $body['duration'] = max(1, (int)round($cal->timeduration / 60));
            }

            $res = zoom_api::patch('/meetings/' . $rec->meeting_id, $body);

            // 204 = updated. 404 = meeting already gone on Zoom; nothing to do.
            if (!in_array((int)($res['status'] ?? 0), [204, 404], true)) {
                debugging('local_zoomattendance: failed to update Zoom meeting '
                    . $rec->meeting_id . ' (HTTP ' . ($res['status'] ?? 0) . ')', DEBUG_NORMAL);
                return;
            }

            // Keep the registry's stored title/start in step with the event.
            $DB->set_field('local_zoom_meeting_registry', 'topic', substr($cal->name, 0, 255),
                ['meeting_id' => $rec->meeting_id]);
            $DB->set_field('local_zoom_meeting_registry', 'start_time', (int)$cal->timestart,
                ['meeting_id' => $rec->meeting_id]);
        } catch (\Throwable $e) {
            debugging('local_zoomattendance: event_updated observer error: ' . $e->getMessage(),
                DEBUG_NORMAL);
        }
    }

    /**
     * Calendar event deleted: delete the Zoom meeting and drop our mappings.
     */
    public static function event_deleted(\core\event\calendar_event_deleted $event): void {
        global $DB;

        try {
            $eventid = (int)$event->objectid;
            // The event row is already gone; rely on our own mapping row.
            $rec = self::managed_record($eventid);
            if (!$rec) {
                return;
            }

            $meetingid = $rec->meeting_id;

            $res = zoom_api::delete('/meetings/' . $meetingid);
            $status = (int)($res['status'] ?? 0);

            // 204 = deleted, 404 = already gone. Anything else: the meeting may
            // still exist on Zoom — log so it can be cleaned up manually. We
            // still drop our rows below since the calendar event no longer exists.
            if (!in_array($status, [204, 404], true)) {
                debugging('local_zoomattendance: failed to delete Zoom meeting ' . $meetingid
                    . ' (HTTP ' . $status . '); orphaned on Zoom, remove manually', DEBUG_NORMAL);
            }

            $DB->delete_records('local_zoom_event_join', ['event_id' => $eventid]);
            $DB->delete_records('local_zoom_event_map', ['meeting_id' => $meetingid]);
            $DB->delete_records('local_zoom_meeting_registry', ['meeting_id' => $meetingid]);
            $DB->delete_records('local_zoom_join_links', ['meeting_id' => $meetingid]);
        } catch (\Throwable $e) {
            debugging('local_zoomattendance: event_deleted observer error: ' . $e->getMessage(),
                DEBUG_NORMAL);
        }
    }
}
