<?php
// Local helper: simulate "Save selected to question bank" with nested q[] POST data, then delete the test questions. CLI only.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/questionlib.php');
\core\session\manager::set_user(get_admin());
$maxid = (int) $DB->get_field_sql('SELECT MAX(id) FROM {question}');
$_GET['courseid'] = $_REQUEST['courseid'] = 1045;
$_POST['action'] = $_REQUEST['action'] = $_GET['action'] = 'save';
$_POST['sesskey'] = $_REQUEST['sesskey'] = $_GET['sesskey'] = sesskey();
$_POST['q'] = [
  ['type' => 'truefalse', 'difficulty' => 'easy', 'name' => 'ZZTEST TF', 'text' => 'ZZTEST The sky is blue.', 'explanation' => 'x', 'answer' => 'true', 'keep' => '1'],
  ['type' => 'multichoice', 'difficulty' => 'medium', 'name' => 'ZZTEST MC', 'text' => 'ZZTEST 2+2?', 'explanation' => 'x', 'options' => ['3', '4', '5'], 'correct' => '1', 'keep' => '1'],
  ['type' => 'multiselect', 'difficulty' => 'hard', 'name' => 'ZZTEST MS', 'text' => 'ZZTEST Even numbers?', 'explanation' => 'x', 'options' => ['2', '3', '4'], 'correctm' => ['0', '2'], 'keep' => '1'],
];
ob_start();
$code = file_get_contents($CFG->dirroot . '/local/coursetutor/qgen.php');
$code = str_replace("require(__DIR__ . '/../../config.php');", '', $code);
$code = preg_replace('/^<\?php/', '', $code);
$code = str_replace('__DIR__', "'" . addslashes($CFG->dirroot . '/local/coursetutor') . "'", $code);
$err = '';
try { eval($code); } catch (Throwable $e) { $err = get_class($e) . ': ' . $e->getMessage(); }
$html = ob_get_clean();
echo "result: " . ($err ?: 'no exception') . "\n";
if (preg_match_all('/alert-danger[^>]*>(.*?)<\/div>/s', $html, $m)) { foreach ($m[1] as $x) echo 'notice: ' . trim(strip_tags($x)) . "\n"; }
$new = $DB->get_records_select('question', 'id > ?', [$maxid], 'id', 'id,name,qtype');
echo 'questions created: ' . count($new) . "\n";
foreach ($new as $q) { echo "  {$q->id} {$q->qtype} {$q->name}\n"; }
foreach ($new as $q) { question_delete_question($q->id); }
echo 'test questions deleted: ' . ($DB->count_records_select('question', 'id > ?', [$maxid]) == 0 ? 'yes' : 'NO') . "\n";
