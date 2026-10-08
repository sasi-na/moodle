<?php

require_once '../../config.php';

require_login();
global $DB;
$maxquizattemptid = 0;
$sql = "SELECT * FROM {local_timetrack} ORDER BY qid DESC LIMIT 1";
$timequizattemptid = $DB->get_record_sql($sql);
if ($timequizattemptid && $timequizattemptid->qid > 0) {
    $maxquizattemptid = $timequizattemptid->qid;
}

$attempts = "SELECT a.*, cm.id as cmid, cm.course FROM {quiz_attempts} a
                        JOIN {course_modules} cm ON a.quiz = cm.instance
                        WHERE cm.module = 18 AND a.id > $maxquizattemptid AND a.timefinish > 0 ORDER BY a.id LIMIT 0, 1000";

$records = $DB->get_records_sql($attempts);

foreach ($records as $value) {
    $obj = new \stdClass();

    $obj->userid = $value->userid;
    $obj->cmid = $value->cmid;
    $obj->courseid = $value->course;
    $obj->timespent = $value->timefinish - $value->timestart;
    $obj->timecreated = $value->timefinish;
    $obj->qid = $value->id;

    $DB->insert_record('local_timetrack', $obj);
    echo $value->id."<br>";
}
