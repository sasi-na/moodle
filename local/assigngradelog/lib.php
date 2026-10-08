<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Inject a "Grade upload history" link into the assignment's settings navigation.
 * Visible only to users who can grade the assignment.
 */
function local_assigngradelog_extend_settings_navigation(
    \settings_navigation $settingsnav,
    \context $context
): void {
    global $PAGE, $CFG;

    // Shared image: only surface this where the tenant uses it.
    // Opt in with $CFG->assigngradelog = true; in the tenant's config.php.
    if (empty($CFG->assigngradelog)) {
        return;
    }

    if (!$PAGE->cm || $PAGE->cm->modname !== 'assign') {
        return;
    }

    if (!has_capability('mod/assign:grade', $context)) {
        return;
    }

    $assignnode = $settingsnav->find('modulesettings', \navigation_node::TYPE_SETTING);
    if (!$assignnode) {
        return;
    }

    $url = new \moodle_url('/local/assigngradelog/index.php', ['cmid' => $PAGE->cm->id]);
    $assignnode->add(
        get_string('uploadhistory', 'local_assigngradelog'),
        $url,
        \navigation_node::TYPE_SETTING,
        null,
        'assigngradelog',
        new \pix_icon('i/report', '')
    );
}
