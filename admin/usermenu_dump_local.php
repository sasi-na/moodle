<?php
// Local helper: dump the theme user menu HTML (normal + "log in as") for styling checks. CLI only.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
$admin = get_admin();
\core\session\manager::set_user($admin);
$PAGE = new moodle_page(); $PAGE->set_url('/my/'); $PAGE->set_context(context_system::instance());
$OUTPUT = $PAGE->get_renderer('core');
$normal = $OUTPUT->user_menu();
$student = $DB->get_record('user', ['username' => 'anupamaacharya21@gmail.com']);
\core\session\manager::loginas($student->id, context_system::instance(), false);
$PAGE = new moodle_page(); $PAGE->set_url('/my/'); $PAGE->set_context(context_system::instance());
$OUTPUT = $PAGE->get_renderer('core');
$as = $OUTPUT->user_menu();
file_put_contents(__DIR__ . '/../theme/baz/newtheme/backup/usermenu.json', json_encode(['normal' => $normal, 'as' => $as]));
echo strlen($normal) . ' / ' . strlen($as) . "\n";
