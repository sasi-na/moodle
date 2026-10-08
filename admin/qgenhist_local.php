<?php
// Local helper: render qgen.php history section (adds 3 temporary questions if none exist, then deletes them). CLI only.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/questionlib.php');
get_string_manager()->reset_caches();
\core\session\manager::set_user(get_admin());
$maxid = (int) $DB->get_field_sql('SELECT MAX(id) FROM {question}');
$temp = in_array('apply', $argv ?? [], true);
if ($temp) {
    \local_coursetutor\qgen::save_to_bank(1045, [
      ['type' => 'truefalse', 'difficulty' => 'easy', 'name' => 'ZZTEST TF', 'text' => 'ZZTEST The sky is blue.', 'explanation' => 'Because.', 'answer' => true],
      ['type' => 'multichoice', 'difficulty' => 'medium', 'name' => 'ZZTEST MC', 'text' => 'ZZTEST 2+2?', 'explanation' => 'x', 'options' => ['3', '4', '5'], 'correct' => [1]],
      ['type' => 'shortanswer', 'difficulty' => 'hard', 'name' => 'ZZTEST SA', 'text' => 'ZZTEST Capital of France?', 'explanation' => 'x', 'answers' => ['Paris']],
    ]);
}
$_GET['courseid'] = $_REQUEST['courseid'] = 1045;
ob_start();
$code = file_get_contents($CFG->dirroot . '/local/coursetutor/qgen.php');
$code = str_replace("require(__DIR__ . '/../../config.php');", '', $code);
$code = preg_replace('/^<\?php/', '', $code);
$code = str_replace('__DIR__', "'" . addslashes($CFG->dirroot . '/local/coursetutor') . "'", $code);
$err = '';
try { eval($code); } catch (Throwable $e) { $err = 'ERROR: ' . $e->getMessage() . ' line ' . $e->getLine(); }
$html = ob_get_clean();
echo ($err ?: 'no exception') . "\n";
$p = strpos($html, 'id="qgen-history"');
echo $p === false ? "history section: NO\n" : "history section:\n" . preg_replace('/\n\s*\n+/', "\n", trim(html_entity_decode(strip_tags(preg_replace('/<script.*?<\/script>/s', '', substr($html, $p, 6000)))))) . "\n";
if ($temp) {
    $new = $DB->get_records_select('question', 'id > ?', [$maxid], 'id', 'id');
    foreach ($new as $q) { question_delete_question($q->id); }
    echo 'temp questions deleted: ' . ($DB->count_records_select('question', 'id > ?', [$maxid]) == 0 ? 'yes' : 'NO') . "\n";
}
