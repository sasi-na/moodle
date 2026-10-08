<?php
// Local read-only helper: Moodle core AI providers/placements and recent action results. CLI only. Prints no secrets.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
$dbman = $DB->get_manager();
echo 'provider plugins: ' . implode(', ', array_keys(core_component::get_plugin_list('aiprovider'))) . "\n";
echo 'placement plugins: ' . implode(', ', array_keys(core_component::get_plugin_list('aiplacement'))) . "\n";
if ($dbman->table_exists('ai_providers')) {
    foreach ($DB->get_records('ai_providers') as $p) {
        $cfg = json_decode($p->config, true) ?: [];
        foreach ($cfg as $k => $v) { if (preg_match('/key|secret/i', $k)) { $cfg[$k] = $v ? '(set)' : '(empty)'; } }
        echo "instance {$p->id}: {$p->provider} name={$p->name} enabled={$p->enabled} config=" . json_encode($cfg) . "\n";
        $ac = json_decode($p->actionconfig, true) ?: [];
        foreach ($ac as $a => $c) { echo "    $a enabled=" . (int)($c['enabled'] ?? 0) . ' ' . json_encode($c['settings'] ?? []) . "\n"; }
    }
} else { echo "no ai_providers table (4.5 style config)\n"; foreach (['aiprovider_openai','aiprovider_azureai','aiprovider_datacurso'] as $c) { echo "$c: " . json_encode(array_map(fn($v) => is_string($v) && strlen($v) > 30 ? substr($v,0,6).'…' : $v, (array) get_config($c))) . "\n"; } }
foreach (['aiplacement_courseassist', 'aiplacement_editor'] as $c) { echo "$c enabled=" . json_encode(get_config($c, 'enabled')) . "\n"; }
$recent = $DB->get_records('ai_action_register', null, 'id DESC', 'id, actionname, provider, success, errorcode, errormessage, timecreated', 0, 5);
foreach ($recent as $r) { echo "action {$r->actionname} via {$r->provider} success={$r->success} err={$r->errorcode} " . substr((string)$r->errormessage, 0, 150) . ' ' . userdate($r->timecreated) . "\n"; }
