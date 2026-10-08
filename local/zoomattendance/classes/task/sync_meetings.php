<?php
namespace local_zoomattendance\task;

defined('MOODLE_INTERNAL') || die();

use local_zoomattendance\helper\join_helper;

class sync_meetings extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('syncmeetingstask', 'local_zoomattendance');
    }

    public function execute() {
        // This plugin ships in a shared image. Do nothing on sites that have no
        // Zoom credentials -- see local_zoomattendance_is_configured().
        require_once(__DIR__ . '/../../lib.php');
        if (!local_zoomattendance_is_configured()) {
            mtrace('local_zoomattendance: Zoom not configured on this site, skipping sync_meetings.');
            return;
        }

        mtrace('Zoom meeting sync started');

        $synced = join_helper::sync_registry();
        mtrace("Registry: {$synced} meetings added");

        $rewritten = join_helper::rewrite_future_events();
        mtrace("Events: {$rewritten} join links rewritten");

        $purged = join_helper::cleanup();
        mtrace("Cleanup: {$purged} stale join links removed");

        mtrace('Zoom meeting sync completed');
    }
}
