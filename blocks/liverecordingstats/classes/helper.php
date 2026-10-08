<?php

namespace block_liverecordingstats;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper class.
 */
class helper {

    /**
     * Get student stats.
     *
     * @param int $courseid
     * @param int $userid
     * @return array
     */
    public static function get_stats(
        int $courseid,
        int $userid
    ): array {

        global $DB;

        /*
        |--------------------------------------------------------------------------
        | Total Zoom recordings
        |--------------------------------------------------------------------------
        */

        $total = $DB->count_records_sql(
            "
            SELECT COUNT(DISTINCT cm.id)

              FROM {course_modules} cm

              JOIN {modules} m
                ON m.id = cm.module

              JOIN {tag_instance} ti
                ON ti.itemid = cm.id

              JOIN {tag} t
                ON t.id = ti.tagid

             WHERE cm.course = :courseid

               AND m.name = 'page'

               AND ti.component = 'core'

               AND ti.itemtype = 'course_modules'

               AND t.rawname LIKE :tagname
            ",
            [
                'courseid' => $courseid,
                'tagname' => 'zoom_recording_%'
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Completed recordings
        |--------------------------------------------------------------------------
        */

        $completed = $DB->count_records_sql(
            "
            SELECT COUNT(DISTINCT lvs.cmid)

              FROM {local_videotracker_session} lvs

              JOIN {course_modules} cm
                ON cm.id = lvs.cmid

              JOIN {modules} m
                ON m.id = cm.module

              JOIN {tag_instance} ti
                ON ti.itemid = cm.id

              JOIN {tag} t
                ON t.id = ti.tagid

             WHERE lvs.userid = :userid

               AND lvs.courseid = :courseid

               AND lvs.completed = 1

               AND m.name = 'page'

               AND ti.component = 'core'

               AND ti.itemtype = 'course_modules'

               AND t.rawname LIKE :tagname
            ",
            [
                'userid' => $userid,
                'courseid' => $courseid,
                'tagname' => 'zoom_recording_%'
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Submitted summaries
        |--------------------------------------------------------------------------
        */

        $summaries = $DB->count_records_sql(
            "
            SELECT COUNT(DISTINCT lzrs.cmid)

              FROM {local_zoom_recording_summary} lzrs

              JOIN {course_modules} cm
                ON cm.id = lzrs.cmid

              JOIN {modules} m
                ON m.id = cm.module

              JOIN {tag_instance} ti
                ON ti.itemid = cm.id

              JOIN {tag} t
                ON t.id = ti.tagid

             WHERE lzrs.userid = :userid

               AND lzrs.courseid = :courseid

               AND m.name = 'page'

               AND ti.component = 'core'

               AND ti.itemtype = 'course_modules'

               AND t.rawname LIKE :tagname
            ",
            [
                'userid' => $userid,
                'courseid' => $courseid,
                'tagname' => 'zoom_recording_%'
            ]
        );

        return [

            'total' => (int)$total,

            'completed' => (int)$completed,

            'summaries' => (int)$summaries
        ];
    }
}
