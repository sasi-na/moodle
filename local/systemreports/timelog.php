<?php

require_once '../../config.php';

require_login();

global $DB, $USER;

if (!is_siteadmin()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {
        $data = json_decode(file_get_contents('php://input'), true);

        if (isset($data['timeSpent'])) {
            $timeSpent = (int) $data['timeSpent']; // Time spent in milliseconds
            $cmid = (int) $data['cmid']; // Time spent in milliseconds
            // Here you could save the data to a database or a log file
            $object = new stdClass();
            $object->cmid = $cmid;
            $object->timespent = round($timeSpent / 1000);
            $object->userid = $USER->id;
            $object->timecreated = time();

            $DB->insert_record('local_timetrack', $object);

            // For demonstration, we'll just write it to a log file
//        $logFile = 'time_spent_log.txt';
//        $logEntry = date('Y-m-d H:i:s') . " - Time spent for cmid - ".$cmid." is : " . round($timeSpent /1000) . " ms\n";
//        file_put_contents($logFile, $logEntry, FILE_APPEND);
        }
    }
}