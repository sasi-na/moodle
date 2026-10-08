<?php
require_once('../../config.php');
require_once($CFG->libdir . '/gradelib.php');
// require_once($CFG->libdir . '/coursecatlib.php'); // Deprecated/Removed in modern Moodle


require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/mod/vpl/setup_grading.php'));
$PAGE->set_title('Setup Grading Structure');
$PAGE->set_heading('Setup Grading Structure');

echo $OUTPUT->header();

$courseid = optional_param('id', 235, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid]);
if (!$course) {
    echo $OUTPUT->notification("Course not found.", 'error');
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->heading("Setup Grading for: $course->fullname");

// 1. Fetch Course Sections and Identify Targets
$sections = $DB->get_records('course_sections', ['course' => $courseid], 'section ASC');
$section_groups = [];

// Group sections by number and part
foreach ($sections as $section) {
    $name = $section->name ? $section->name : "Section $section->section";
    
    // Match "Section X Part A" or "Section X Part B"
    if (preg_match('/Section\s*(\d+)\s*Part\s*([A-Za-z])/i', $name, $matches)) {
        $sec_num = $matches[1];
        $sec_part = strtoupper($matches[2]);
        if (!isset($section_groups[$sec_num])) {
            $section_groups[$sec_num] = ['parts' => []];
        }
        $section_groups[$sec_num]['parts'][$sec_part] = $section;
    }
}

echo "<h3>Detected Course Sections</h3>";
if (empty($section_groups)) {
    echo "<p style='color:red'>No sections matching 'Section X Part Y' found. Formatting examples: 'Section 1 Part A', 'Section 2 Part B'.</p>";
    echo $OUTPUT->footer();
    exit;
}

foreach ($section_groups as $num => $group) {
    echo "<h4>Section $num:</h4><ul>";
    foreach ($group['parts'] as $part => $section) {
        $name = $section->name ? $section->name : "Section $section->section";
        echo "<li>Part $part: $name (ID: $section->id)</li>";
    }
    echo "</ul>";
}

// 2. Identify Grade Items for each part
function get_grade_items_for_section($section) {
    global $DB;
    $grade_items = [];
    if (empty($section->sequence)) return $grade_items;
    
    $cm_ids = explode(',', $section->sequence);
    foreach ($cm_ids as $cm_id) {
        if (empty($cm_id)) continue;
        $cm = $DB->get_record('course_modules', ['id' => $cm_id]);
        if (!$cm) continue;
        $module = $DB->get_record('modules', ['id' => $cm->module]);
        if (!$module) continue;
        
        // Find corresponding grade item
        $gi = grade_item::fetch([
            'courseid' => $section->course,
            'itemtype' => 'mod',
            'itemmodule' => $module->name,
            'iteminstance' => $cm->instance
        ]);
        
        if ($gi) {
            $grade_items[] = $gi;
        }
    }
    return $grade_items;
}

if (!$confirm) {
    echo $OUTPUT->notification("Review the detected sections above. If correct, click Proceed to configure the Gradebook structure.", 'info');
    echo "<form method='post'><input type='hidden' name='confirm' value='1'><input type='submit' value='Proceed' class='btn btn-primary'></form>";
    echo $OUTPUT->footer();
    exit;
}

// START MODIFICATION
echo "<h3>Applying Gradebook Changes...</h3>";

// 3. Create Root "Core Final Assessment" Category if not exists
$course_category = grade_category::fetch_course_category($courseid);
$core_final_cat = $DB->get_record('grade_categories', ['courseid' => $courseid, 'fullname' => 'Core Final Assessment']);

if (!$core_final_cat) {
    $core_final_cat = new grade_category();
    $core_final_cat->courseid = $courseid;
    $core_final_cat->fullname = "Core Final Assessment";
    $core_final_cat->parent = $course_category->id;
    $core_final_cat->aggregation = GRADE_AGGREGATE_MAX; // Best of all sections
    $core_final_cat->insert();
    echo "<p>Created Root Category: <strong>Core Final Assessment (Best of All Sections)</strong></p>";
} else {
    $core_final_cat = grade_category::fetch(['id' => $core_final_cat->id]);
    echo "<p>Using existing Root Category: <strong>Core Final Assessment</strong></p>";
}

// 4. Process each section group
foreach ($section_groups as $num => $group) {
    // A. Section X Total Category (Max)
    $sec_total_name = "Section $num Total";
    $sec_total_cat = $DB->get_record('grade_categories', ['courseid' => $courseid, 'fullname' => $sec_total_name]);
    
    if (!$sec_total_cat) {
        $sec_total_cat = new grade_category();
        $sec_total_cat->courseid = $courseid;
        $sec_total_cat->fullname = $sec_total_name;
        $sec_total_cat->parent = $core_final_cat->id;
        $sec_total_cat->aggregation = GRADE_AGGREGATE_MAX; // Best of Part A and B
        $sec_total_cat->insert();
        echo "<p>Created Category: <strong>$sec_total_name (Best of Part A and B)</strong></p>";
    } else {
        $sec_total_cat = grade_category::fetch(['id' => $sec_total_cat->id]);
        echo "<p>Using existing Category: <strong>$sec_total_name</strong></p>";
    }
    
    // B. Create Part Categories and move items
    foreach ($group['parts'] as $part_letter => $section) {
        $part_cat_name = "Section $num Part $part_letter";
        $part_cat = $DB->get_record('grade_categories', ['courseid' => $courseid, 'fullname' => $part_cat_name]);
        
        if (!$part_cat) {
            $part_cat = new grade_category();
            $part_cat->courseid = $courseid;
            $part_cat->fullname = $part_cat_name;
            $part_cat->parent = $sec_total_cat->id;
            $part_cat->aggregation = GRADE_AGGREGATE_SUM; // Sum of CA + Practice
            $part_cat->insert();
            echo "<p>Created Category: <strong>$part_cat_name (Sum of Labs)</strong></p>";
        } else {
            $part_cat = grade_category::fetch(['id' => $part_cat->id]);
            echo "<p>Using existing Category: <strong>$part_cat_name</strong></p>";
        }
        
        // Move items
        $items = get_grade_items_for_section($section);
        foreach ($items as $gi) {
            if ($gi->categoryid != $part_cat->id) {
                $gi->set_parent($part_cat->id);
                echo "&nbsp;&nbsp;&nbsp;Moved '{$gi->itemname}' to $part_cat_name.<br>";
            }
        }
    }
}

// Re-grade
grade_regrade_final_grades($courseid);
echo $OUTPUT->notification("Gradebook structure updated and grades recalculated successfully.", 'success');
echo "<p><a href='/grade/report/grader/index.php?id=$courseid' class='btn btn-secondary'>View Grader Report</a></p>";

echo $OUTPUT->footer();
