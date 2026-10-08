<?php
// Local helper: test the AI Question Generator end to end on course 1045, then remove the test questions again. CLI only.
define('CLI_SCRIPT', true);
define('IGNORE_COMPONENT_CACHE', false);
require(__DIR__ . '/../config.php');
// Rebuild Moodle's class list so the new qgen class is found (small cache file; Moodle recreates it).
@unlink($CFG->cachedir . '/core_component.php');
core_component::reset();
require_once($CFG->libdir . '/questionlib.php');
// Refresh only the language strings and the plugin callback list (no full cache purge, so the site stays fast).
get_string_manager()->reset_caches();
cache_helper::purge_by_definition('core', 'plugin_functions');
\core\session\manager::set_user(get_admin());
$rows = \local_coursetutor\qgen::clean_rows(['truefalse', 'multichoice', 'shortanswer'], ['easy', 'medium', 'hard'], [3, 3, 4]);
$t = microtime(true);
$qs = \local_coursetutor\qgen::generate(1045, 'Unit 4: Capital Budgeting (NPV, IRR, payback period)', false, $rows);
printf("generated %d questions in %.1f s\n", count($qs), microtime(true) - $t);
$c = [];
foreach ($qs as $q) { $k = "{$q['difficulty']}/{$q['type']}"; $c[$k] = ($c[$k] ?? 0) + 1; }
echo 'mix: ' . json_encode($c) . "\n";
foreach (array_slice($qs, 0, 10) as $i => $q) {
    $ans = $q['type'] === 'truefalse' ? ($q['answer'] ? 'TRUE' : 'FALSE') : ($q['type'] === 'shortanswer' ? implode(' / ', $q['answers'])
        : ($q['options'][$q['correct'][0]] ?? ''));
    echo ($i + 1) . ". [{$q['difficulty']}/{$q['type']}] " . mb_substr($q['text'], 0, 110) . "  => $ans\n";
}
// Save one of each type, check they are real Moodle questions, then delete them (test only).
$pick = [];
foreach ($qs as $q) { $pick[$q['type']] = $pick[$q['type']] ?? $q; }
[$n, $cat] = \local_coursetutor\qgen::save_to_bank(1045, array_values($pick));
echo "saved $n to category '$cat'\n";
$ids = $DB->get_fieldset_sql("SELECT q.id FROM {question} q WHERE q.name LIKE '[%' AND q.timecreated > ? ORDER BY q.id DESC", [time() - 120]);
foreach ($ids as $id) { $qq = $DB->get_record('question', ['id' => $id], 'id, qtype, name'); echo "  ok: {$qq->qtype} {$qq->name}\n"; question_delete_question($id); }
echo "test questions removed again\n";
