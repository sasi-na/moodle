<?php
namespace block_livesessionstats;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

class external extends \external_api {

    public static function get_sessions_parameters() {
        return new \external_function_parameters([
            'courseid' => new \external_value(PARAM_INT, 'Course ID')
        ]);
    }

    public static function get_sessions($courseid) {
        global $DB, $USER;

        self::validate_parameters(self::get_sessions_parameters(), [
            'courseid' => $courseid
        ]);

        require_login($courseid);

        $useremail = $DB->get_field('user', 'email', ['id' => $USER->id]);
        $now = time();

        // Note: a recordset is used (not get_records_sql) because several
        // sessions can share the same event name; keying the result array by
        // the first column would silently collapse those duplicates so the
        // list would show fewer rows than the block's "conducted" count.
        // The collation-safe meeting_id join mirrors the block, and status
        // comes from the persisted 90% attended flag so it matches the
        // block's attended count.
        $sql = "
            SELECT
                e.id AS eventid,
                e.name,
                e.timestart,
                zm.meeting_id,
                COALESCE(SUM(s.total_duration), 0) as duration,
                COALESCE(MAX(s.session_duration), 0) as sessionduration,
                COALESCE(MAX(s.attended), 0) as attended,
                COUNT(s.id) as reccount
            FROM {local_zoom_event_map} zm
            JOIN {event} e ON e.id = zm.event_id
            LEFT JOIN {local_zoom_attendance_summary} s
                ON CONVERT(s.meeting_id USING utf8mb4) COLLATE utf8mb4_unicode_ci =
                   CONVERT(zm.meeting_id USING utf8mb4) COLLATE utf8mb4_unicode_ci
                AND s.user_email = ?
            WHERE e.courseid = ?
              AND e.timestart <= ?
              AND zm.skipped = 0
            GROUP BY e.id, e.name, e.timestart, zm.meeting_id
            ORDER BY e.timestart DESC
        ";

        // Map normalised meeting id -> recording page cmid, from the
        // "zoom_recording_<meetingid>" tags on page activities in this course.
        // So we can offer a "Watch recording" link for missed sessions.
        $recordingmap = [];
        $likepat = $DB->sql_like_escape('zoom_recording_') . '%';
        $tagrs = $DB->get_recordset_sql("
            SELECT ti.itemid AS cmid, t.rawname
              FROM {tag_instance} ti
              JOIN {tag} t ON t.id = ti.tagid
              JOIN {course_modules} cm ON cm.id = ti.itemid
             WHERE ti.itemtype = 'course_modules'
               AND ti.component = 'core'
               AND cm.course = :courseid
               AND " . $DB->sql_like('t.rawname', ':pat', false), [
            'courseid' => $courseid,
            'pat' => $likepat
        ]);
        foreach ($tagrs as $t) {
            if (preg_match('/^zoom_recording_(.+)$/i', trim($t->rawname), $m)) {
                $key = \core_text::strtolower(preg_replace('/\s+/', '', $m[1]));
                if ($key !== '' && !isset($recordingmap[$key])) {
                    $recordingmap[$key] = (int)$t->cmid;
                }
            }
        }
        $tagrs->close();

        // This student's recorded-session summary status per meeting
        // (0 = pending, 1 = approved, 2 = rejected), keyed by normalised id.
        $summarystatus = [];
        $sumrs = $DB->get_recordset_sql("
            SELECT zoommeetingid, MAX(status) AS status
              FROM {local_zoom_recording_summary}
             WHERE userid = :userid AND courseid = :courseid
          GROUP BY zoommeetingid
        ", ['userid' => $USER->id, 'courseid' => $courseid]);
        foreach ($sumrs as $sr) {
            $key = \core_text::strtolower(preg_replace('/\s+/', '', (string)$sr->zoommeetingid));
            if ($key !== '') {
                $summarystatus[$key] = (int)$sr->status;
            }
        }
        $sumrs->close();

        $records = $DB->get_recordset_sql($sql, [$useremail, $courseid, $now]);

        $sessions = [];

        foreach ($records as $r) {

            $attendedsecs = (int)$r->duration;
            $sessionsecs  = (int)$r->sessionduration;

            $percentage = $sessionsecs > 0
                ? round(($attendedsecs / $sessionsecs) * 100, 2)
                : 0;

            $key       = \core_text::strtolower(preg_replace('/\s+/', '', (string)$r->meeting_id));
            $livecredit = ((int)$r->attended === 1);
            $sumstat    = $summarystatus[$key] ?? null;

            // Combined attendance status with its source.
            if ($livecredit) {
                $status = 'Attended (Live)';
            } else if ($sumstat === 1) {
                $status = 'Attended (Summary)';
            } else if ($sumstat === 0) {
                $status = 'Pending';
            } else if ($sumstat === 2) {
                $status = 'Rejected';
            } else {
                $status = 'Not Attended';
            }

            // Watch recording: only when not credited and not awaiting review,
            // i.e. Rejected or Absent (and a tagged recording page exists).
            $recordingurl = '';
            if (($sumstat === 2 || $sumstat === null) && !$livecredit && isset($recordingmap[$key])) {
                $recordingurl = (new \moodle_url('/mod/page/view.php', [
                    'id' => $recordingmap[$key],
                    'forceview' => 1
                ]))->out(false);
            }

            $sessions[] = [
                'name' => format_string($r->name),
                'date' => date('Y-m-d H:i', $r->timestart),
                'sessionduration' => round($sessionsecs / 60, 2),
                'attendedduration' => round($attendedsecs / 60, 2),
                'percentage' => $percentage,
                // 0 when the student has no attendance record for the session
                // (truly absent); the UI shows '-' rather than zeros.
                'hasrecord' => ((int)$r->reccount > 0) ? 1 : 0,
                'status' => $status,
                'recordingurl' => $recordingurl
            ];
        }

        $records->close();

        return ['sessions' => $sessions];
    }

    public static function get_sessions_returns() {
        return new \external_single_structure([
            'sessions' => new \external_multiple_structure(
                new \external_single_structure([
                    'name' => new \external_value(PARAM_TEXT, 'Session name'),
                    'date' => new \external_value(PARAM_TEXT, 'Date'),
                    'sessionduration' => new \external_value(PARAM_FLOAT, 'Session duration (mins)'),
                    'attendedduration' => new \external_value(PARAM_FLOAT, 'Attended duration (mins)'),
                    'percentage' => new \external_value(PARAM_FLOAT, 'Attendance percentage'),
                    'hasrecord' => new \external_value(PARAM_INT, 'Whether an attendance record exists'),
                    'status' => new \external_value(PARAM_TEXT, 'Status'),
                    'recordingurl' => new \external_value(PARAM_RAW, 'Recording page URL for missed sessions, or empty')
                ])
            )
        ]);
    }
}
