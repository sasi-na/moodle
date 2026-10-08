<?php
class block_livesessionstats extends block_base {

    public function init() {
        $this->title = get_string('livesessionstats', 'block_livesessionstats');
    }

    public function applicable_formats() {
        return ['course-view' => true];
    }

    public function get_content() {
        global $DB, $USER, $COURSE, $PAGE;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();

        // Hide for guests / not logged in
        if (!isloggedin() || isguestuser()) {
            $this->content->text = '';
            return $this->content;
        }

        $context = \context_course::instance($COURSE->id);

        if (!is_enrolled($context, $USER->id, '', true)) {
            $this->content->text = '';
            return $this->content;
        }

        $now = time();

        /**
         * =========================================
         * 1. TOTAL CLASSES
         * =========================================
         */
        $total = $DB->count_records_sql("
            SELECT COUNT(DISTINCT zm.meeting_id)
            FROM {local_zoom_event_map} zm
            JOIN {event} e ON e.id = zm.event_id
            WHERE e.courseid = ?
              AND e.timestart <= ?
              AND zm.skipped = 0
        ", [$COURSE->id, $now]);

        /**
         * =========================================
         * 2. ATTENDED (live >=50% OR an approved recorded-session summary)
         * =========================================
         *
         * Credited either by attending the live session (stored attended
         * flag) or by an approved summary for the recording.
         */
        $attended = $DB->count_records_sql("
            SELECT COUNT(DISTINCT zm.meeting_id)
            FROM {local_zoom_event_map} zm
            JOIN {event} e ON e.id = zm.event_id
            JOIN {user} u ON u.id = ?
            LEFT JOIN {local_zoom_attendance_summary} s
                ON CONVERT(s.meeting_id USING utf8mb4) COLLATE utf8mb4_unicode_ci =
                   CONVERT(zm.meeting_id USING utf8mb4) COLLATE utf8mb4_unicode_ci
                AND s.user_email = u.email
                AND s.attended = 1
            LEFT JOIN {local_zoom_recording_summary} rs
                ON CONVERT(REPLACE(rs.zoommeetingid, ' ', '') USING utf8mb4) COLLATE utf8mb4_unicode_ci =
                   CONVERT(REPLACE(zm.meeting_id, ' ', '') USING utf8mb4) COLLATE utf8mb4_unicode_ci
                AND rs.userid = u.id
                AND rs.status = 1
            WHERE e.courseid = ?
              AND e.timestart <= ?
              AND zm.skipped = 0
              AND (s.id IS NOT NULL OR rs.id IS NOT NULL)
        ", [$USER->id, $COURSE->id, $now]);

        /**
         * =========================================
         * 3. NOT ATTENDED
         * =========================================
         */
        $notattended = max(0, $total - $attended);

        // ── Attendance percentage & visual tier ──────────────────────
        $pct      = $total > 0 ? round(($attended / $total) * 100) : 0;
        $tier     = $pct >= 75 ? 'good' : ($pct >= 50 ? 'warn' : 'danger');
        $courseid = $COURSE->id;

        $PAGE->requires->css('/blocks/livesessionstats/styles.css');

        if ($total === 0) {
            $html = '<div class="lss-block"><div class="lss-empty">'
                  . '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/>'
                  . '<path d="M3 10h18M8 3v4M16 3v4"/></svg>'
                  . '<span>No live classes scheduled yet.</span>'
                  . '</div></div>';
        } else {
            // Ring geometry. r=34 in an 80x80 box leaves room for the 6px stroke
            // without clipping; the dash offset is the unfilled remainder.
            $circumference = 2 * M_PI * 34;
            $offset = $circumference * (1 - ($pct / 100));

            $html = '
            <div class="lss-block">
                <div class="lss-hero">
                    <svg class="lss-ring lss-ring--' . $tier . '" viewBox="0 0 80 80"
                         role="img" aria-label="Attendance ' . $pct . ' percent">
                        <circle class="lss-ring-track" cx="40" cy="40" r="34"/>
                        <circle class="lss-ring-fill" cx="40" cy="40" r="34"
                                style="stroke-dasharray:' . round($circumference, 2) . ';'
                                . 'stroke-dashoffset:' . round($offset, 2) . '"/>
                    </svg>
                    <div class="lss-hero-txt">
                        <span class="lss-hero-pct">' . $pct . '<i>%</i></span>
                        <span class="lss-hero-lbl">Attended</span>
                    </div>
                </div>

                <div class="lss-legend">
                    <div class="lss-leg lss-leg--total">
                        <span class="lss-leg-n">' . $total . '</span>
                        <span class="lss-leg-l">Total</span>
                    </div>
                    <div class="lss-leg lss-leg--attended">
                        <span class="lss-leg-n">' . $attended . '</span>
                        <span class="lss-leg-l">Attended</span>
                    </div>
                    <div class="lss-leg lss-leg--missed">
                        <span class="lss-leg-n">' . $notattended . '</span>
                        <span class="lss-leg-l">Missed</span>
                    </div>
                </div>

                <a href="#" class="lss-viewmore livesession-viewmore"
                   data-courseid="' . $courseid . '"
                   aria-label="View detailed attendance">
                    ' . get_string('viewmore', 'block_livesessionstats') . '
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
            </div>';
        }

        $this->content->text = $html;
        $this->content->footer = '';

        $PAGE->requires->js_call_amd('block_livesessionstats/viewmore', 'init');

        return $this->content;
    }
}