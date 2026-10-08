<?php

namespace local_zoomattendance\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Auto-approve recorded-session summaries left pending too long.
 *
 * FRD: if a summary remains in 'Pending' status for more than 96 hours, it is
 * automatically approved so students are not penalised for slow review.
 *
 * @package   local_zoomattendance
 */
class auto_approve_summaries extends \core\task\scheduled_task {

    /** Pending summaries older than this (in seconds) are auto-approved. */
    const PENDING_MAX_AGE = 96 * HOURSECS;

    public function get_name() {
        return get_string('autoapprovetask', 'local_zoomattendance');
    }

    public function execute() {
        // This plugin ships in a shared image. Do nothing on sites that have no
        // Zoom credentials -- see local_zoomattendance_is_configured().
        require_once(__DIR__ . '/../../lib.php');
        if (!local_zoomattendance_is_configured()) {
            mtrace('local_zoomattendance: Zoom not configured on this site, skipping auto_approve_summaries.');
            return;
        }

        global $DB;

        mtrace("Auto-approve pending summaries: started");

        $now       = time();
        $threshold = $now - self::PENDING_MAX_AGE;

        // status: 0 = pending, 1 = approved, 2 = rejected.
        $select = "status = :status AND submittedat < :threshold";
        $params = ['status' => 0, 'threshold' => $threshold];

        $count = $DB->count_records_select(
            'local_zoom_recording_summary',
            $select,
            $params
        );

        if ($count == 0) {
            mtrace("Auto-approve pending summaries: nothing to approve");
            return;
        }

        // Bulk approve. reviewedby = 0 marks a system (non-faculty) approval.
        $DB->execute(
            "UPDATE {local_zoom_recording_summary}
                SET status = 1,
                    reviewedby = 0,
                    reviewedat = :now
              WHERE status = :status
                AND submittedat < :threshold",
            ['now' => $now, 'status' => 0, 'threshold' => $threshold]
        );

        mtrace("Auto-approve pending summaries: approved {$count} summaries");
    }
}
