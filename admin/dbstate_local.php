<?php
// Local read-only helper: what is in the database right now. CLI only.
define('CLI_SCRIPT', true);
define('NO_UPGRADE_CHECK', true);
require(__DIR__ . '/../config.php');
echo "db: {$CFG->dbname} prefix {$CFG->prefix}\n";
foreach (['local_geniai', 'theme_baz', 'local_coursetutor', 'local_aidock', 'local_dttutorgemini', 'local_customcode', 'local_ams', 'local_welcome', 'local_adminer'] as $c) {
    echo "$c: " . var_export(get_config($c, 'version'), true) . "\n";
}
echo "active users: " . $DB->count_records('user', ['deleted' => 0]) . "\n";
echo "courses: " . $DB->count_records('course') . "\n";
echo "gemini key set: " . (get_config('geniaicontroller_gemini', 'apikey') ? 'yes' : 'no') . "\n";
echo "AI generated questions: " . $DB->count_records_sql("SELECT COUNT(1) FROM {tag_instance} ti JOIN {tag} t ON t.id = ti.tagid WHERE t.name = 'ai generated'") . "\n";
echo "last upgrade_log:\n";
foreach ($DB->get_records('upgrade_log', null, 'id DESC', 'id,timemodified,plugin,version,info', 0, 6) as $l) {
    echo date('Y-m-d H:i', $l->timemodified) . " {$l->plugin} {$l->version} {$l->info}\n";
}
echo "databases: " . implode(', ', $DB->get_fieldset_sql('SHOW DATABASES')) . "\n";
