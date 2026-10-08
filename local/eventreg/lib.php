<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Whether the current user may view form responses and download the report.
 *
 * Forms live in the system context, but Teacher / Non-editing teacher roles are
 * assigned at course level, so a system-context has_capability() misses them.
 * We therefore also accept the capability held in ANY course.
 *
 * @return bool
 */
function local_eventreg_can_view_reports() {
    if (has_capability('local/eventreg:viewreports', context_system::instance())) {
        return true;
    }
    return (bool) get_user_capability_course(
        'local/eventreg:viewreports', null, false, '', '', 1);
}

/**
 * Serves a file attached to a learner's answer.
 *
 * Attachments live in the system context under the "answer" area, itemid = the
 * answer record id. Readable by the learner who uploaded the file and by anyone
 * allowed to read that form's responses — nobody else.
 *
 * @param stdClass $course Unused (system context).
 * @param stdClass $cm Unused (system context).
 * @param context $context
 * @param string $filearea
 * @param array $args itemid, then the file path parts and the filename.
 * @param bool $forcedownload
 * @param array $options
 * @return bool False when the file cannot be served.
 */
function local_eventreg_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB, $USER;

    if ($context->contextlevel != CONTEXT_SYSTEM
            || $filearea !== \local_eventreg\form_builder::FILE_AREA
            || empty($args)) {
        return false;
    }

    require_login();

    $answerid = (int) array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $sql = "SELECT s.userid
              FROM {local_eventreg_answers} a
              JOIN {local_eventreg_submissions} s ON s.id = a.submissionid
             WHERE a.id = :aid";
    $ownerid = (int) $DB->get_field_sql($sql, ['aid' => $answerid]);
    if (!$ownerid) {
        return false;
    }
    if ($ownerid !== (int) $USER->id && !local_eventreg_can_view_reports()) {
        return false;
    }

    $file = get_file_storage()->get_file($context->id, 'local_eventreg', $filearea,
        $answerid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Adds an "Event registration" node to the primary navigation for learners who
 * have at least one open form available to them.
 *
 * @param global_navigation $navigation
 */
function local_eventreg_extend_navigation(global_navigation $navigation) {
    global $USER;

    if (!isloggedin() || isguestuser()) {
        return;
    }
    if (!\local_eventreg\manager::open_forms_for_user((int) $USER->id)) {
        return;
    }

    $navigation->add(
        get_string('availableforms', 'local_eventreg'),
        new moodle_url('/local/eventreg/index.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_eventreg_index',
        new pix_icon('i/enrolusers', '')
    );
}
