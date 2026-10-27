<?php
// Local helper: export + render the "New theme" dashboards for the admin and one student (CLI only).
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
$CFG->debug = DEBUG_DEVELOPER; $CFG->debugdisplay = 1;
require_once($CFG->dirroot . '/calendar/lib.php'); require_once($CFG->libdir . '/blocklib.php'); require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
$admin = get_admin();
$student = $DB->get_record('user', ['username' => 'anupamaacharya21@gmail.com']) ?: $DB->get_record_select('user', "email LIKE 'anupama%'", null, '*', IGNORE_MULTIPLE);
foreach (['admin' => $admin, 'student' => $student] as $kind => $u) {
    if (!$u) { echo "$kind: no user\n"; continue; }
    \core\session\manager::set_user($u);
    $PAGE = new moodle_page(); $PAGE->set_url('/my/'); $PAGE->set_context(context_user::instance($u->id)); $PAGE->set_pagelayout('mydashboard'); $PAGE->set_pagetype('my-index');
    $OUTPUT = $PAGE->get_renderer('core');
    $t = microtime(true);
    try {
        $cls = $kind === 'admin' ? '\theme_baz\output\nt_admin_dashboard' : '\theme_baz\output\nt_student_dashboard';
        $ctx = (new $cls())->export_for_template($OUTPUT);
        $ms = round((microtime(true) - $t) * 1000);
        echo "== $kind ({$u->username}) export ok in {$ms} ms\n";
        $keys = array_filter(array_keys($ctx), fn($k) => strpos($k, 'nt') === 0);
        foreach ($keys as $k) { echo "  $k: " . substr(json_encode($ctx[$k]), 0, 300) . "\n"; }
        $ctx['ntcssurl'] = \theme_baz\local\newtheme::cssurl();
        $ctx['output'] = $OUTPUT; $ctx['bodyattributes'] = ''; $ctx['config'] = ['wwwroot' => $CFG->wwwroot];
        $html = $OUTPUT->render_from_template("theme_baz/nt-$kind-dashboard", $ctx);
        if (getenv("NTPREVIEW") || file_exists(__DIR__ . "/.ntpreview")) { file_put_contents($CFG->dirroot . "/theme/baz/newtheme/backup/preview-$kind.html", $html); }
        echo "  rendered " . strlen($html) . " bytes; [[ markers: " . substr_count($html, '[[') . "\n";
        if (preg_match_all('/\[\[[a-z_0-9]+\]\]/', $html, $m)) { echo "  missing: " . implode(' ', array_unique($m[0])) . "\n"; }
    } catch (\Throwable $e) {
        echo "== $kind FAILED: " . $e->getMessage() . " @ " . $e->getFile() . ':' . $e->getLine() . "\n";
    }
}
