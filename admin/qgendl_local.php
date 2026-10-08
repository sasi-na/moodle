<?php
// Local helper: create a temporary AI set, test Word/PDF/XML download and the delete action. CLI only.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/questionlib.php');
get_string_manager()->reset_caches();
\core\session\manager::set_user(get_admin());
$maxid = (int) $DB->get_field_sql('SELECT MAX(id) FROM {question}');
\local_coursetutor\qgen::save_to_bank(1045, [
  ['type' => 'truefalse', 'difficulty' => 'easy', 'name' => 'ZZTEST TF', 'text' => 'ZZTEST The sky is blue.', 'explanation' => 'Because.', 'answer' => true],
  ['type' => 'multiselect', 'difficulty' => 'medium', 'name' => 'ZZTEST MS', 'text' => 'ZZTEST Even numbers?', 'explanation' => 'x', 'options' => ['2', '3', '4'], 'correct' => [0, 2]],
  ['type' => 'shortanswer', 'difficulty' => 'hard', 'name' => 'ZZTEST SA', 'text' => 'ZZTEST Capital of France?', 'explanation' => 'x', 'answers' => ['Paris']],
  ['type' => 'matching', 'difficulty' => 'hard', 'name' => 'ZZTEST MA', 'text' => 'ZZTEST Match.', 'explanation' => 'x', 'pairs' => [['left' => 'NPV', 'right' => 'Net present value'], ['left' => 'IRR', 'right' => 'Internal rate of return'], ['left' => 'PI', 'right' => 'Profitability index']]],
  ['type' => 'essay', 'difficulty' => 'medium', 'name' => 'ZZTEST ES', 'text' => 'ZZTEST Explain NPV.', 'explanation' => 'x', 'model_answer' => 'NPV is ...'],
]);
$ids = array_keys($DB->get_records_select('question', 'id > ?', [$maxid], 'id', 'id'));
echo 'temp questions: ' . count($ids) . "\n";
function qg_eval($file, $get, $post = []) {
    global $CFG, $DB, $USER, $SESSION, $PAGE, $OUTPUT, $COURSE, $SITE;
    $PAGE = new moodle_page(); $OUTPUT = new bootstrap_renderer();
    $_GET = $get; $_POST = $post; $_REQUEST = $get + $post;
    $code = file_get_contents($CFG->dirroot . '/local/coursetutor/' . $file);
    $code = str_replace("require(__DIR__ . '/../../config.php');", '', $code);
    $code = preg_replace('/^<\?php/', '', $code);
    $code = str_replace('__DIR__', "'" . addslashes($CFG->dirroot . '/local/coursetutor') . "'", $code);
    $code = str_replace('send_file(', 'return qg_out(', $code);
    $code = str_replace("\$pdf->Output(\$filename . '.pdf', 'D');\n    die;", "return qg_out(\$pdf->Output('', 'S'), \$filename . '.pdf');", $code);
    ob_start();
    try { $r = eval($code); } catch (Throwable $e) { $r = 'ERROR: ' . $e->getMessage() . ' line ' . $e->getLine(); }
    $html = ob_get_clean();
    return [$r, $html];
}
function qg_out($content, $name) { return [$name, $content]; }
$base = ['courseid' => 1045, 'ids' => implode(',', $ids), 'set' => 2];
foreach (['doc' => 1, 'pdf' => 1, 'xml' => 1] as $f => $_) {
    [$r] = qg_eval('qgendownload.php', $base + ['format' => $f, 'key' => 1]);
    if (!is_array($r)) { echo "$f: " . $r . "\n"; continue; }
    [$name, $c] = $r;
    echo "$f: $name, " . strlen($c) . " bytes";
    if ($f === 'pdf') echo ', starts ' . substr($c, 0, 5);
    if ($f === 'xml') echo ', <question> count ' . substr_count($c, '<question type="');
    echo "\n";
    if ($f === 'doc') echo preg_replace('/\n\s*\n+/', "\n", trim(html_entity_decode(strip_tags(str_replace(['<br>', '</p>', '<br clear="all" style="page-break-before:always">'], ["\n", "\n", "\n---page---\n"], $c))))) . "\n";
}
[$r] = qg_eval('qgendownload.php', $base + ['format' => 'doc', 'key' => 0]);
echo 'questions-only has answer key: ' . (is_array($r) && strpos($r[1], 'Answer key') !== false ? 'YES (wrong)' : 'no (ok)') . "\n";
// Page shows the new controls.
[, $html] = qg_eval('qgen.php', ['courseid' => 1045]);
echo 'page has Download: ' . (strpos($html, 'qgendownload.php') !== false ? 'yes' : 'NO') . ', Delete set: ' . (strpos($html, 'Delete this set') !== false ? 'yes' : 'NO') . "\n";
// Delete one question, then the rest of the set.
[$dr, $dh] = qg_eval('qgen.php', ['courseid' => 1045], ['action' => 'delete', 'ids' => (string) $ids[0], 'sesskey' => sesskey()]);
echo 'delete eval: ' . (is_string($dr) ? $dr : 'ok') . ' | ' . substr(trim(strip_tags($dh)), 0, 300) . "\n";
echo 'after single delete: ' . $DB->count_records_select('question', 'id > ?', [$maxid]) . " left\n";
qg_eval('qgen.php', ['courseid' => 1045], ['action' => 'delete', 'ids' => implode(',', array_slice($ids, 1)), 'sesskey' => sesskey()]);
echo 'after set delete: ' . $DB->count_records_select('question', 'id > ?', [$maxid]) . " left\n";
foreach ($DB->get_records_select('question', 'id > ?', [$maxid], 'id', 'id') as $q) { question_delete_question($q->id); }
echo 'real questions untouched: ' . $DB->count_records_sql("SELECT COUNT(1) FROM {question} WHERE id <= ? AND name LIKE '[%'", [$maxid]) . "\n";
