<?php
namespace local_zoomattendance\helper;

defined('MOODLE_INTERNAL') || die();

class report_helper {

    /**
     * Generate CSV and send email for a single meeting
     */
    public static function send_meeting_report($meetingid, $records, $topic = '') {

        global $CFG;

        // Safe temp file path
        $filename = "zoom_attendance_{$meetingid}_" . date('Ymd_His') . ".csv";
        $filepath = $CFG->tempdir . '/' . $filename;

        // Create CSV file
        $fp = fopen($filepath, 'w');

        if (!$fp) {
            return; // fail silently (safe for cron)
        }

        // Present requires at least this percentage of the session.
        $presentthreshold = 50;

        // CSV header
        fputcsv($fp, [
            'Meeting ID',
            'Topic',
            'Email',
            'Name',
            'Session Start',
            'Session End',
            'Session Duration (mins)',
            'User Duration (mins)',
            'Attendance %',
            'Status'
        ]);

        // Write rows safely
        foreach ($records as $r) {

            $start = !empty($r->start_time)
                ? date('Y-m-d H:i:s', $r->start_time)
                : '';

            $end = !empty($r->end_time)
                ? date('Y-m-d H:i:s', $r->end_time)
                : '';

            $sessionseconds = (int)($r->session_duration ?? 0);
            $userseconds    = (int)($r->total_duration ?? 0);

            $attendance = ($sessionseconds > 0)
                ? round(($userseconds / $sessionseconds) * 100, 2)
                : 0;

            // Status from the persisted attendance decision (>=50%), with an
            // inline fallback when the stored flag is unavailable.
            if (isset($r->attended)) {
                $status = ((int)$r->attended === 1) ? 'Present' : 'Absent';
            } else if ($sessionseconds > 0) {
                $status = ($attendance >= $presentthreshold) ? 'Present' : 'Absent';
            } else {
                $status = '-';
            }

            fputcsv($fp, [
                $meetingid,
                $topic,
                $r->user_email ?? '',
                $r->fullname ?? '',
                $start,
                $end,
                round($sessionseconds / 60, 2),
                round($userseconds / 60, 2),
                $attendance . '%',
                $status
            ]);
        }

        fclose($fp);

        //Send email
        self::send_email($filepath, $filename, $meetingid, $topic);

        // Cleanup
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }

    /**
     * Send email with attachment
     */
    private static function send_email($file, $filename, $meetingid, $topic) {

        global $CFG;

        // Recipients are configured via $CFG->zoomattendance_email (comma-
        // separated). With no recipients configured there is nobody to mail,
        // so skip sending rather than falling back to a baked-in address.
        $emails = (string)($CFG->zoomattendance_email ?? '');
        $emails = array_filter(array_map('trim', explode(',', $emails)));
        if (empty($emails)) {
            return;
        }

        $subject = "Zoom Attendance Report - {$meetingid}";

        $message  = "Zoom Attendance Report\n\n";
        $message .= "Meeting ID: {$meetingid}\n";
        $message .= "Topic: {$topic}\n";
        $message .= "Generated: " . date('Y-m-d H:i:s') . "\n";

        $content = file_get_contents($file);

        if ($content === false) {
            return;
        }

        $attachment = chunk_split(base64_encode($content));

        $boundary = md5(time());

        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $body .= $message . "\r\n";

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/csv; name=\"{$filename}\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n";
        $body .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n\r\n";
        $body .= $attachment . "\r\n";
        $body .= "--{$boundary}--";

        /**
         * Send individually (safer than CC for delivery tracking)
         */
        foreach ($emails as $email) {

            $headers  = "From: {$CFG->noreplyaddress}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

            mail($email, $subject, $body, $headers);
        }
    }
}