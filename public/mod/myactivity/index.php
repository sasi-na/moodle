<?php
require('../../config.php');
 
$id = required_param('id', PARAM_INT); // Course ID
 
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_login($course);
 
$PAGE->set_url('/mod/myactivity/index.php', ['id' => $id]);
$PAGE->set_title(get_string('modulenameplural', 'mod_myactivity'));
$PAGE->set_heading($course->fullname);
 
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_myactivity'));
 
// Get all instances of this activity in the course
if (!$myactivities = get_all_instances_in_course('myactivity', $course)) {
    echo $OUTPUT->notification(get_string('nonewmodules', 'mod_myactivity'));
    echo $OUTPUT->footer();
    exit;
}
 
$table = new html_table();
$table->head  = [get_string('name'), get_string('intro')];
$table->data  = [];
 
foreach ($myactivities as $myactivity) {
    $link = html_writer::link(
        new moodle_url('/mod/myactivity/view.php', ['id' => $myactivity->coursemodule]),
        format_string($myactivity->name)
    );
    $table->data[] = [$link, format_text($myactivity->intro)];
}
 
echo html_writer::table($table);
echo $OUTPUT->footer();
