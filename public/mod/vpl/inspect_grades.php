<?php
require_once('../../config.php');
require_once($CFG->libdir . '/gradelib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/mod/vpl/inspect_grades.php'));
$PAGE->set_title('Inspect Grades');
$PAGE->set_heading('Inspect Grades');

echo $OUTPUT->header();

$courseid = optional_param('id', 235, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid]);
if (!$course) {
    echo $OUTPUT->notification("Course $courseid not found. Listing all courses:", 'warning');
    $courses = $DB->get_records('course', null, '', 'id, fullname');
    echo '<ul>';
    foreach ($courses as $c) {
        echo "<li><a href='?id=$c->id'>$c->fullname (ID: $c->id)</a></li>";
    }
    echo '</ul>';
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->heading("Inspecting Gradebook for Course: $course->fullname ($course->id)");

$grade_items = grade_item::fetch_all(['courseid' => $courseid]);
if (!$grade_items) {
    echo $OUTPUT->notification("No grade items found.", 'info');
    echo $OUTPUT->footer();
    exit;
}

// Build hierarchy
$categories = grade_category::fetch_all(['courseid' => $courseid]);
$cat_map = []; // Map ID to category object
echo "<h3>Categories</h3>";
echo "<ul>";
foreach ($categories as $cat) {
    $cat_map[$cat->id] = $cat;
    $parent_info = $cat->parent ? " (Parent: $cat->parent)" : " (Root)";
    echo "<li><strong>{$cat->fullname}</strong> (ID: {$cat->id}, Aggregation: {$cat->aggregation})$parent_info</li>";
}
echo "</ul>";

echo "<h3>Grade Items</h3>";
echo "<ul>";
foreach ($grade_items as $item) {
    $cat_name = isset($cat_map[$item->categoryid]) ? $cat_map[$item->categoryid]->fullname : 'Root/None';
    echo "<li>";
    echo "<strong>{$item->itemname}</strong> (Type: {$item->itemtype}, Module: {$item->itemmodule}, ID: {$item->id})";
    echo "<br><em>Category: $cat_name</em>";
    echo "<br><em>Calculation: " . s($item->calculation) . "</em>";
    echo "</li>";
}
echo "</ul>";

echo $OUTPUT->footer();
