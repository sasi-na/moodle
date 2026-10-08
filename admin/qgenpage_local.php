<?php
// Local read-only helper: render qgen.php as admin and check it, plus the course menu entry. CLI only.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
\core\session\manager::set_user(get_admin());
$_GET['courseid'] = 1045; $_REQUEST['courseid'] = 1045;
ob_start();
$code = file_get_contents($CFG->dirroot . '/local/coursetutor/qgen.php');
$code = str_replace("require(__DIR__ . '/../../config.php');", '', $code);
$code = preg_replace('/^<\?php/', '', $code);
$code = str_replace('__DIR__', "'" . addslashes($CFG->dirroot . '/local/coursetutor') . "'", $code);
try { eval($code); } catch (Throwable $e) { echo 'ERROR: ' . $e->getMessage() . ' line ' . $e->getLine(); }
$html = ob_get_clean();
echo 'page length: ' . strlen($html) . "\n";
echo 'has title "AI Question Generator": ' . (strpos($html, 'AI Question Generator') !== false ? 'yes' : 'NO') . "\n";
echo 'True / False option: ' . (strpos($html, '>True / False<') !== false ? 'yes' : 'NO') . "\n";
if (preg_match('/ERROR: .*/', $html, $m)) { echo $m[0] . "\n"; }
$PAGE2 = new moodle_page(); $PAGE2->set_course(get_course(1045)); $PAGE2->set_url('/course/view.php', ['id' => 1045]);
$node = $PAGE2->settingsnav->find('local_coursetutor_qgen', navigation_node::TYPE_SETTING);
echo 'in course menu: ' . ($node ? 'yes (' . $node->get_content() . ')' : 'NO') . "\n";
