<?php
// Local helper: switches the Tutor AI course flag on for every course (only a local visibility flag).
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../config.php');
global $DB;
$courses = $DB->get_fieldset_select('course', 'id', 'id > 1');
$on = 0; $new = 0; $now = time();
$cols = $DB->get_columns('local_dttutor_course_config');
foreach ($courses as $cid) {
    $rec = $DB->get_record('local_dttutor_course_config', ['courseid' => $cid]);
    if ($rec) { if (!$rec->indexing_enabled) { $rec->indexing_enabled = 1; if (isset($cols['timemodified'])) { $rec->timemodified = $now; } $DB->update_record('local_dttutor_course_config', $rec); $on++; } }
    else { $r = (object)['courseid' => $cid, 'indexing_enabled' => 1, 'usermodified' => 2]; if (isset($cols['timecreated'])) { $r->timecreated = $now; } if (isset($cols['timemodified'])) { $r->timemodified = $now; } $DB->insert_record('local_dttutor_course_config', $r); $new++; }
}
cache_helper::purge_all();
echo "courses: " . count($courses) . ", switched on: $on, new records: $new\n";
