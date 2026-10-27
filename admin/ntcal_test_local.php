<?php
// Local helper: render the student calendar (month) + the holiday map into a preview page for checking. CLI only.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
require_once($CFG->dirroot . '/calendar/lib.php');
require_once($CFG->libdir . '/blocklib.php'); require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
$u = $DB->get_record('user', ['username' => 'anupamaacharya21@gmail.com']);
\core\session\manager::set_user($u);
$PAGE = new moodle_page(); $PAGE->set_url('/calendar/view.php'); $PAGE->set_context(context_system::instance()); $PAGE->set_pagelayout('standard'); $PAGE->set_pagetype('calendar-view');
$OUTPUT = $PAGE->get_renderer('core');
$ctx = (new \theme_baz\output\student_calendar())->export_for_template($OUTPUT);
$ctx['output'] = $OUTPUT; $ctx['bodyattributes'] = 'id="page-calendar-view" class="nt-shell"'; $ctx['config'] = ['wwwroot' => $CFG->wwwroot];
$html = $OUTPUT->render_from_template('theme_baz/student-calendar', $ctx);
$map = [];
foreach (\theme_baz\output\nt_student_dashboard::nt_holidays() as $date => $h) {
    [$y, $m, $d] = array_map('intval', explode('-', $date));
    $map[(string) make_timestamp($y, $m, $d)] = ['n' => $h['name'], 't' => $h['type']];
}
$inject = '<link rel="stylesheet" href="' . $CFG->wwwroot . '/theme/baz/newtheme/shell.css?' . time() . '"><script>window.EDNXT_HOLIDAYS=' . json_encode($map) . ';</script><script src="' . $CFG->wwwroot . '/theme/baz/newtheme/shell.js?' . time() . '"></script>';
$html = str_replace('</body>', $inject . '</body>', $html);
file_put_contents($CFG->dirroot . '/theme/baz/newtheme/backup/preview-cal.html', $html);
echo strlen($html) . " bytes, " . count($map) . " holidays\n";
