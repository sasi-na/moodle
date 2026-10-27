<?php
// Local check: is the theme_ednxt Ask JO footer hook registered, and does it build for a student?
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
$hm = \core\di::get(\core\hook\manager::class);
$cbs = $hm->get_callbacks_for_hook(\core\hook\output\before_footer_html_generation::class);
echo "callbacks: " . json_encode($cbs) . "\n";
echo "askjo class: " . (class_exists('\\theme_baz\\local\\askjo') ? 'yes' : 'no') . "\n";
$u = $DB->get_record('user', ['email' => 'anupamaacharya21@gmail.com']);
\core\session\manager::set_user($u);
$jo = \theme_baz\local\askjo::build();
echo "build: " . ($jo ? 'ok blockid ' . $jo['blockid'] : 'NULL') . "\n";
\theme_baz\local\askjo::$built = false;
$catid = theme_baz_interestzone_categoryid();
echo "iz category: $catid\n";
echo "openai_chat instances: " . json_encode($DB->get_records('block_instances', ['blockname' => 'openai_chat'], '', 'id,pagetypepattern,parentcontextid,showinsubcontexts')) . "\n";
$PAGE->set_url('/course/index.php', ['categoryid' => $catid]);
$PAGE->set_context(context_coursecat::instance($catid));
$PAGE->set_pagelayout('coursecategory');
$PAGE->set_pagetype('course-index-category');
try {
    $out = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);
    $GLOBALS['OUTPUT'] = $out;
    ob_start();
    echo $out->header();
    echo $out->footer();
    $html = ob_get_clean();
    echo "rendered " . strlen($html) . " bytes; theme=" . $PAGE->theme->name . "; layout=" . $PAGE->pagelayout . "\n";
    echo "nt-jo-panel: " . (strpos($html, 'nt-jo-panel') !== false ? 'YES' : 'no') . "; askjo-panel: " . (preg_match('/id="[a-z]+-askjo-panel"/', $html) ? 'YES' : 'no') . "; built=" . (\theme_baz\local\askjo::$built ? 1 : 0) . "\n";
    echo "pos panel=" . strpos($html, "nt-jo-panel") . " body_end=" . strrpos($html, "</body>") . " amd_init=" . (strpos($html, "block_openai_chat/lib") !== false ? "yes" : "no") . "\n";
    file_put_contents($CFG->dirroot . "/theme/baz/newtheme/backup/preview-iz.html", $html);
    echo "hook skip: " . \theme_ednxt\local\hooks::$skip . "\n"; echo "interestzone markup: " . (strpos($html, 'iz-') !== false ? 'yes' : 'no') . "\n";
} catch (\Throwable $e) {
    @ob_end_clean();
    echo get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n";
}
