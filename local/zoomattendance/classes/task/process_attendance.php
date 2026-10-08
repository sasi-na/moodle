<?php

namespace local_zoomattendance\task;

defined('MOODLE_INTERNAL') || die();

class process_attendance extends \core\task\scheduled_task {

    public function get_name() {
        return 'Zoom Attendance Processor';
    }

    public function execute() {
        // This plugin ships in a shared image. Do nothing on sites that have no
        // Zoom credentials -- see local_zoomattendance_is_configured().
        require_once(__DIR__ . '/../../lib.php');
        if (!local_zoomattendance_is_configured()) {
            mtrace('local_zoomattendance: Zoom not configured on this site, skipping process_attendance.');
            return;
        }

        global $DB;

        mtrace("Zoom Attendance Task Started");

        // LOCK (prevents parallel execution)
        $lockfactory = \core\lock\lock_config::get_lock_factory('zoomattendance');
        $lock = $lockfactory->get_lock('process_attendance', 0);

        if (!$lock) {
            mtrace("Another cron is already running. Skipping.");
            return;
        }

        try {

            $records = $DB->get_records('local_zoom_attendance_queue', [
                'processed' => 0
            ]);

            foreach ($records as $rec) {

                // Wait for Zoom finalization
                if (time() - $rec->timecreated < 180) {
                    continue;
                }

                // Retry limit safety
                if (!empty($rec->retrycount) && $rec->retrycount >= 5) {
                    mtrace("Skipping {$rec->meeting_id} (max retries reached)");
                    continue;
                }

                $uuid = trim($rec->uuid);

                // Fetch participants
                $participants = $this->fetch_all_participants($uuid);

                if (empty($participants)) {

                    $rec->retrycount = ($rec->retrycount ?? 0) + 1;

                    mtrace("Retry {$rec->retrycount} for meeting {$rec->meeting_id}");

                    if ($rec->retrycount >= 5) {
                        $rec->processed = 1;
                    }

                    $DB->update_record('local_zoom_attendance_queue', $rec);
                    continue;
                }

                $totals = [];

                foreach ($participants as $p) {

                    $email = strtolower(trim($p['user_email'] ?? ''));
                    $name  = $p['name'] ?? '';

                    // RAW LOG
                    $log = new \stdClass();
                    $log->meeting_id  = $rec->meeting_id;
                    $log->uuid        = $rec->uuid;
                    $log->topic       = $rec->topic ?? '';
                    $log->user_email  = $email;
                    $log->fullname    = $name;
                    $log->join_time   = $p['join_time'] ?? null;
                    $log->leave_time  = $p['leave_time'] ?? null;
                    $log->duration    = (int)($p['duration'] ?? 0);
                    $log->timecreated = time();

                    $DB->insert_record('local_zoom_attendance_logs', $log);

                    // AGGREGATION
                    $key = $email ?: $name;

                    if (!isset($totals[$key])) {
                        $totals[$key] = [
                            'email' => $email,
                            'name'  => $name,
                            'duration' => 0
                        ];
                    }

                    $totals[$key]['duration'] += (int)($p['duration'] ?? 0);
                }

                // SUMMARY INSERT
                foreach ($totals as $t) {

                    $sum = new \stdClass();
                    $sum->meeting_id = $rec->meeting_id;
                    $sum->uuid       = $rec->uuid;
                    $sum->topic      = $rec->topic ?? '';

                    $sum->user_email = $t['email'];
                    $sum->fullname   = $t['name'];
                    $sum->total_duration = $t['duration'];

                    $sum->start_time = $rec->start_time ?? 0;
                    $sum->end_time   = $rec->end_time ?? 0;
                    $sum->session_duration = $rec->session_duration ?? 0;

                    // Persisted attendance status (total vs session duration, >=50%).
                    $sessduration = (int)($rec->session_duration ?? 0);
                    $watched      = (int)$t['duration'];
                    $sum->attended = ($sessduration > 0)
                        ? ($watched >= 0.5 * $sessduration ? 1 : 0)
                        : ($watched > 0 ? 1 : 0);

                    $sum->timecreated = time();

                    $DB->insert_record('local_zoom_attendance_summary', $sum);
                }

                // MAP ZOOM → MOODLE EVENT
                if (!empty($rec->start_time) && !empty($rec->topic)) {

                    // IMPORTANT: no need for helper namespace here
                    if (class_exists('\\local_zoomattendance\\helper\\EventHelper')) {

                        \local_zoomattendance\helper\EventHelper::map_zoom_to_event(
                            $rec->meeting_id,
                            $rec->topic,
                            $rec->start_time
                        );
                    }
                }

                // MARK COMPLETE
                $rec->processed = 1;
                $rec->status = 'processed';
                $rec->processed_time = time();

                $DB->update_record('local_zoom_attendance_queue', $rec);

                mtrace("Processed meeting {$rec->meeting_id}");
            }

        } catch (\Throwable $e) {
            mtrace("CRON ERROR: " . $e->getMessage());
        }

        // RELEASE LOCK
        if (!empty($lock)) {
            $lock->release();
        }

        mtrace("Zoom Attendance Task Completed");
    }

    /**
     * FETCH PARTICIPANTS
     */
    private function fetch_all_participants($uuid) {

        $encoded_uuid = rawurlencode(rawurlencode(trim($uuid)));

        $res = \local_zoomattendance\helper\zoom_api::get_paginated(
            "/past_meetings/{$encoded_uuid}/participants", 'participants');

        if ($res['status'] !== 200) {
            mtrace("Participant fetch failed for {$uuid} (HTTP {$res['status']})");
            return [];
        }

        return $res['items'];
    }
}