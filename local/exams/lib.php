<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Adds the exam portal node to the sidebar drawer.
 *
 * Gated on the `shownav` setting: while testing, the feature ships dark and is
 * reached by direct URL only, so this returns immediately when shownav is OFF.
 */
function local_exams_extend_navigation(global_navigation $nav) {
    global $USER;

    if (!isloggedin() || isguestuser() || !\local_exams\manager::shownav()) {
        return;
    }

    $isfaculty = \local_exams\manager::is_faculty($USER->id);
    $url = new moodle_url('/local/exams/index.php');

    $nav->add(
        get_string($isfaculty ? 'facultydashboard' : 'mydashboard', 'local_exams'),
        $url,
        navigation_node::TYPE_CUSTOM,
        null,
        $isfaculty ? 'local_exams_faculty' : 'local_exams_student',
        new pix_icon('i/report', '')
    );
}

/**
 * Injects the exam portal link into the primary nav bar via the navinject AMD
 * module (theme_baz / RemUI ignores plugin nav nodes in the top bar).
 *
 * Gated on `shownav` — returns '' (nothing injected) while the feature is dark.
 */
function local_exams_render_navbar_output() {
    global $PAGE, $USER;

    if (!isloggedin() || isguestuser() || !\local_exams\manager::shownav()) {
        return '';
    }

    $isfaculty = \local_exams\manager::is_faculty($USER->id);
    $PAGE->requires->js_call_amd('local_exams/navinject', 'init', [
        $isfaculty ? 'faculty' : 'student',
        (new moodle_url('/local/exams/index.php'))->out(false),
        get_string($isfaculty ? 'facultydashboard' : 'mydashboard', 'local_exams'),
    ]);
    return '';
}

/**
 * Adds an "Exam runner" opt-in checkbox to the quiz settings form.
 *
 * @param moodleform_mod $formwrapper
 * @param MoodleQuickForm $mform
 */
function local_exams_coursemodule_standard_elements($formwrapper, $mform) {
    global $DB;

    $current = $formwrapper->get_current();
    if (($current->modulename ?? '') !== 'quiz') {
        return;
    }

    $mform->addElement('header', 'localexamshdr', get_string('pluginname', 'local_exams'));
    $mform->addElement('advcheckbox', 'localexams_runner', get_string('runner_enable', 'local_exams'));
    $mform->addHelpButton('localexams_runner', 'runner_enable', 'local_exams');
    $mform->setExpanded('localexamshdr', false);

    $default = 0;
    if (!empty($current->instance)) {
        $default = (int) $DB->get_field('local_exams_runner_quiz', 'enabled',
            ['quizid' => $current->instance]);
    }
    $mform->setDefault('localexams_runner', $default);
}

/**
 * Persists the "Exam runner" opt-in when a quiz is saved.
 *
 * @param stdClass $data
 * @param stdClass $course
 * @return stdClass
 */
function local_exams_coursemodule_edit_post_actions($data, $course) {
    global $DB;

    if (($data->modulename ?? '') !== 'quiz' || !isset($data->localexams_runner)) {
        return $data;
    }

    $quizid = (int) $data->instance;
    $enabled = (int) $data->localexams_runner;

    $existing = $DB->get_record('local_exams_runner_quiz', ['quizid' => $quizid]);
    if ($existing) {
        $existing->enabled = $enabled;
        $existing->timemodified = time();
        $DB->update_record('local_exams_runner_quiz', $existing);
    } else {
        $DB->insert_record('local_exams_runner_quiz', (object) [
            'quizid'       => $quizid,
            'enabled'      => $enabled,
            'timemodified' => time(),
        ]);
    }

    return $data;
}

/**
 * Pluginfile handler — serves generated PDF result sheets, owner-checked.
 */
function local_exams_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB, $USER;

    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }
    if ($filearea !== 'resultsheet') {
        return false;
    }

    require_login();

    $syscontext = context_system::instance();
    $itemid   = (int) array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    // Owner or analytics-capable staff only.
    if (!has_capability('local/exams:viewanalytics', $syscontext)) {
        $sheet = $DB->get_record('local_exams_resultsheets', ['id' => $itemid]);
        if (!$sheet || $sheet->userid != $USER->id) {
            return false;
        }
    }

    $fs = get_file_storage();
    $file = $fs->get_file($syscontext->id, 'local_exams', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}
