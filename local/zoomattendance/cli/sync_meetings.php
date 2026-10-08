<?php
/**
 * Manual run of the Zoom meeting registry sync + event link rewrite.
 *
 * Thin wrapper around the scheduled task so cron and manual runs always
 * execute identical logic.
 *
 * Usage: php local/zoomattendance/cli/sync_meetings.php
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');

$task = new \local_zoomattendance\task\sync_meetings();
$task->execute();
