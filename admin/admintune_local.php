<?php
// Local helper: refresh table statistics and set admin's My courses default view to "In progress". No data removed. CLI only.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
$t = microtime(true);
foreach (['user_enrolments','enrol','role_assignments','context','course','course_categories','user_lastaccess','user','user_preferences',
          'course_completions','course_modules','course_modules_completion','grade_grades','grade_items','logstore_standard_log','favourite','block_recent_activity'] as $tb) {
    if ($DB->get_manager()->table_exists($tb)) { $DB->execute("ANALYZE TABLE {{$tb}}"); }
}
printf("table statistics refreshed in %.1f s\n", microtime(true) - $t);
$old = get_user_preferences('block_myoverview_user_grouping_preference', null, 2);
set_user_preference('block_myoverview_user_grouping_preference', 'inprogress', 2);
echo "admin My courses default view: " . ($old ?: 'all (default)') . " -> inprogress\n";
