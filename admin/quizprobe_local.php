<?php
// Local read-only helper: what GeniAI's analyzer extracts for the "Continuous Assessment - 01 Quiz" in Computer Networks. CLI only.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
$quizzes = $DB->get_records_sql("SELECT q.id, q.name, q.course, c.fullname FROM {quiz} q JOIN {course} c ON c.id = q.course
    WHERE q.name LIKE ? AND c.fullname LIKE ? ", ['%Continuous Assessment - 01%', '%Computer Networks%'], 0, 3);
foreach ($quizzes as $q) {
    echo "quiz {$q->id} '{$q->name}' in course {$q->course} {$q->fullname}\n";
    $slots = $DB->get_records('quiz_slots', ['quizid' => $q->id]);
    echo '  slots: ' . count($slots) . "\n";
    $fixed = $DB->count_records_sql("SELECT COUNT(1) FROM {question_references} qr JOIN {quiz_slots} s ON s.id = qr.itemid
        WHERE s.quizid = ? AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'", [$q->id]);
    $random = $DB->get_records_sql("SELECT qsr.id, qsr.filtercondition FROM {question_set_references} qsr JOIN {quiz_slots} s ON s.id = qsr.itemid
        WHERE s.quizid = ? AND qsr.component = 'mod_quiz'", [$q->id]);
    echo "  fixed questions: $fixed, random slots: " . count($random) . "\n";
    foreach (array_slice($random, 0, 2) as $r) { echo '  random filter: ' . substr($r->filtercondition, 0, 200) . "\n"; }
    $cm = get_coursemodule_from_instance('quiz', $q->id);
    [$course, $cminfo] = get_course_and_cm_from_cmid($cm->id);
    $content = \local_geniai\extractor\extractor_manager::extract($cminfo, $course, 2);
    echo '  extracted questions: ' . count($content->questions ?? []) . "\n";
}
