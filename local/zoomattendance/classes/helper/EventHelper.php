<?php
namespace local_zoomattendance\helper;

defined('MOODLE_INTERNAL') || die();

class EventHelper {

    /**
     * Map Zoom meeting → Moodle event
     * Conditions:
     *  - Same date (based on session start_time)
     *  - Topic match
     *  - description contains 'zoom'
     */
    public static function map_zoom_to_event($meetingid, $topic, $starttime) {

        global $DB;

        // Basic validation
        if (empty($meetingid) || empty($topic) || empty($starttime)) {
            return false;
        }

        // Prevent duplicate mapping
        if ($DB->record_exists('local_zoom_event_map', [
            'meeting_id' => $meetingid
        ])) {
            return true;
        }

        // Normalize topic
        $topic = trim(mb_strtolower($topic));

        /**
         * Use session date
         */
        $startofday = strtotime(date('Y-m-d 00:00:00', $starttime));
        $endofday   = strtotime(date('Y-m-d 23:59:59', $starttime));

        /**
         * UPDATED QUERY
         * - filter by date
         * - filter by description LIKE '%zoom%'
         */
        $sql = "SELECT id, name, description
                FROM {event}
                WHERE timestart BETWEEN :start AND :end
                  AND " . $DB->sql_like('description', ':zoom', false);

        $events = $DB->get_records_sql($sql, [
            'start' => $startofday,
            'end'   => $endofday,
            'zoom'  => '%zoom%'
        ]);

        if (empty($events)) {
            return false;
        }

        foreach ($events as $event) {

            $eventname = trim(mb_strtolower($event->name));

            // Topic match
            if (
                $eventname === $topic ||
                strpos($eventname, $topic) !== false ||
                strpos($topic, $eventname) !== false
            ) {

                $rec = new \stdClass();
                $rec->meeting_id  = $meetingid;
                $rec->event_id    = $event->id;
                $rec->timecreated = time();

                $DB->insert_record('local_zoom_event_map', $rec);

                return true;
            }
        }

        return false;
    }
}
