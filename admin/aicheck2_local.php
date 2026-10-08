<?php
// Local read-only helper: last core AI actions and placement settings. CLI only.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
foreach ($DB->get_records('ai_action_register', null, 'id DESC', 'id, actionname, actionid, provider, userid, contextid, success, errorcode, errormessage, timecreated', 0, 6) as $r) {
    echo userdate($r->timecreated, '%d %b %H:%M:%S') . " {$r->actionname} user={$r->userid} ctx={$r->contextid} ok={$r->success} err={$r->errorcode} " . substr((string)$r->errormessage, 0, 160) . "\n";
    if ($r->success && $r->actionname === 'summarise_text') {
        $resp = $DB->get_record('ai_action_summarise_text', ['id' => $r->actionid]);
        echo '   prompt chars: ' . strlen((string)($resp->prompt ?? '')) . ' | prompt start: ' . substr(preg_replace('/\s+/', ' ', (string)($resp->prompt ?? '')), 0, 160) . "\n";
        echo '   response: ' . substr(preg_replace('/\s+/', ' ', (string)($resp->responseid ?? '') . ' ' . ($resp->generatedcontent ?? '')), 0, 200) . "\n";
    }
}
echo 'courseassist enabled: ' . get_config('aiplacement_courseassist', 'enabled') . "\n";
echo 'ai policy accepted by admin: ' . ($DB->record_exists('ai_policy_register', ['userid' => 2]) ? 'yes' : 'NO') . "\n";
