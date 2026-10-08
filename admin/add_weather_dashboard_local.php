<?php
// Local helper: puts the Weather block on the default Dashboard (all users who have not customised theirs)
// and on the current admin's own Dashboard. Safe to run twice.
require_once(__DIR__ . '/../config.php');
require_once($CFG->dirroot . '/my/lib.php');
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) { die('Localhost only'); }
require_login(); require_capability('moodle/site:config', context_system::instance());
header('Content-Type: text/plain');
$region = 'drightblocks';
$add = function($contextid, $subpage) use ($DB, $region) {
    if ($DB->record_exists('block_instances', ['blockname' => 'weather', 'parentcontextid' => $contextid,
            'pagetypepattern' => 'my-index', 'subpagepattern' => $subpage])) {
        return 'already there';
    }
    $bi = (object)[
        'blockname' => 'weather', 'parentcontextid' => $contextid, 'showinsubcontexts' => 0,
        'requiredbytheme' => 0, 'pagetypepattern' => 'my-index', 'subpagepattern' => $subpage,
        'defaultregion' => $region, 'defaultweight' => -10,
        'configdata' => base64_encode(serialize((object)['city' => 'Bengaluru', 'title' => 'Weather'])),
        'timecreated' => time(), 'timemodified' => time(),
    ];
    $bi->id = $DB->insert_record('block_instances', $bi);
    context_block::instance($bi->id);
    return 'added';
};
$default = my_get_page(null, MY_PAGE_PRIVATE);
echo 'Default Dashboard (page ' . $default->id . '): ' . $add(context_system::instance()->id, $default->id) . "\n";
$mine = my_get_page($USER->id, MY_PAGE_PRIVATE);
if ($mine && $mine->userid == $USER->id) {
    echo 'Your Dashboard (page ' . $mine->id . '): ' . $add(context_user::instance($USER->id)->id, $mine->id) . "\n";
} else {
    echo "Your Dashboard uses the default page, so it shows the block too.\n";
}
$custom = $DB->count_records_select('my_pages', 'userid IS NOT NULL AND private = 1');
echo "Users with their own customised Dashboard (won't see it until reset): $custom\n";
purge_all_caches();
