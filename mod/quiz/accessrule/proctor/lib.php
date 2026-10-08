<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Serves proctoring snapshot files, gated by the review capability.
 *
 * @package   quizaccess_proctor
 */
function quizaccess_proctor_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_MODULE || $filearea !== 'snapshot') {
        return false;
    }

    require_login($course, false, $cm);
    require_capability('quizaccess/proctor:viewreport', $context);

    $itemid = (int) array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $file = get_file_storage()->get_file($context->id, 'quizaccess_proctor', 'snapshot', $itemid, $filepath, $filename);
    if (!$file) {
        return false;
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
}
