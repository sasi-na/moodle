<?php

require_once '../../config.php';
require_once 'lib.php';
require_once 'classes/add_filter_form.php';

require_login();

$title = get_string('pluginname', 'local_systemreports');
$context = context_system::instance();
$PAGE->set_url("$CFG->wwwroot/local/systemreports/datalist.php");
$PAGE->set_context($context);
$PAGE->set_title($title);
$PAGE->set_heading($title);
//$PAGE->set_pagelayout('standard');
echo $OUTPUT->header();
$PAGE->requires->js_call_amd('local_systemreports/addform', 'addmoreform');

$page = optional_param('page', 'course_progress', PARAM_TEXT);

echo $OUTPUT->render_from_template('local_systemreports/nav', []);

if ($page == 'course_progress') {
    $form = new filter_form('', ['page' => $page]);
    // Display form
    $form->display();
    echo $OUTPUT->render_from_template('local_systemreports/course_progress', []);
}

if ($page == 'time_spent_report') {
    $form = new course_filter_form('', ['page' => $page]);
    // Display form
    $form->display();
    echo $OUTPUT->render_from_template('local_systemreports/time_spent_report', []);
}

if ($page == 'course_time_spent') {
    $form = new course_filter_form('', ['page' => $page]);
    // Display form
    $form->display();
    echo $OUTPUT->render_from_template('local_systemreports/course_time_spent', []);
}

echo $OUTPUT->footer();

