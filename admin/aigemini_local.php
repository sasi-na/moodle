<?php
// Local helper: point Moodle's core OpenAI AI provider (Summarise / generate text) at Google Gemini's
// OpenAI-compatible endpoint, using the Gemini key already configured for GeniAI. Old values are kept in *_prod_backup. CLI only.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
$c = 'aiprovider_openai';
$key = trim((string) get_config('geniaicontroller_gemini', 'apikey'));
if (strlen($key) < 10) { exit("no Gemini key found\n"); }
foreach (['apikey', 'orgid', 'action_summarise_text_endpoint', 'action_summarise_text_model', 'action_generate_text_endpoint', 'action_generate_text_model'] as $n) {
    if (get_config($c, "{$n}_prod_backup") === false) { set_config("{$n}_prod_backup", (string) get_config($c, $n), $c); }
}
$ep = 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions';
set_config('apikey', $key, $c);
set_config('orgid', '', $c);
foreach (['summarise_text', 'generate_text'] as $a) {
    set_config("action_{$a}_endpoint", $ep, $c);
    set_config("action_{$a}_model", 'gemini-3.5-flash-lite', $c);
}
purge_all_caches();
// Test a real summary through Moodle's AI manager (same path the Summarise button uses).
\core\session\manager::set_user(get_admin());
$ctx = context_course::instance(1045);
$action = new \core_ai\aiactions\summarise_text(contextid: $ctx->id, userid: 2,
    prompttext: 'Capital budgeting is the process a business uses to evaluate potential major projects or investments. Methods include payback period, net present value (NPV) and internal rate of return (IRR). NPV discounts future cash flows to today using the cost of capital; a positive NPV means the project adds value.');
$manager = \core\di::get(\core_ai\manager::class);
$res = $manager->process_action($action);
echo 'success: ' . ($res->get_success() ? 'yes' : 'NO') . "\n";
echo $res->get_success() ? "summary: " . substr($res->get_response_data()['generatedcontent'] ?? '', 0, 400) . "\n" : 'error: ' . $res->get_errorcode() . ' ' . $res->get_errormessage() . "\n";
