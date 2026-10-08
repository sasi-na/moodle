<?php
defined('MOODLE_INTERNAL') || die();

function local_nodownload_extend_navigation(global_navigation $nav) {
    global $PAGE, $DB;

    // Ensure activity page
    if (empty($PAGE->cm) || empty($PAGE->cm->id)) {
        return;
    }

    $cmid = $PAGE->cm->id;

    $exists = $DB->record_exists_sql("
        SELECT 1
        FROM {tag} t
        JOIN {tag_instance} ti ON ti.tagid = t.id
        WHERE ti.component = 'core'
          AND ti.itemtype = 'course_modules'
          AND ti.itemid = ?
          AND t.name = 'no_download'
    ", [$cmid]);

    if ($exists) {
        $PAGE->requires->js_init_code("
            document.addEventListener('DOMContentLoaded', function() {
                document.body.classList.add('no-download');
            });
        ");
    }
}