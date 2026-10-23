<?php
// This file is part of VPL for Moodle - http://vpl.dis.ulpgc.es/
//
// VPL for Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// VPL for Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with VPL for Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * VPL Grade Tools - Entry point for grade comparison tools
 *
 * @package mod_vpl
 * @copyright 2025
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../../config.php');
require_once($CFG->dirroot.'/mod/vpl/locallib.php');

global $CFG, $USER, $DB, $OUTPUT, $PAGE;

require_login();

$PAGE->set_url(new moodle_url('/mod/vpl/views/gradetools.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('VPL Grade Tools');
$PAGE->set_heading('VPL Grade Tools');

echo $OUTPUT->header();
echo $OUTPUT->heading('VPL Grade Comparison Tools');

echo '<div style="max-width: 800px; margin: 0 auto;">';
echo '<p>Select a course to compare VPL activity grades:</p>';

// Get all courses where user can grade VPL activities
$courses = enrol_get_users_courses($USER->id, true, ['id', 'fullname', 'shortname']);

if (empty($courses)) {
    echo $OUTPUT->notification('You are not enrolled in any courses.', 'info');
} else {
    echo '<div style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">';
    echo '<h3>Your Courses:</h3>';
    echo '<ul style="list-style: none; padding: 0;">';
    
    foreach ($courses as $course) {
        // Check if course has VPL activities
        $vplcount = $DB->count_records('vpl', ['course' => $course->id]);
        
        if ($vplcount > 0) {
            $context = context_course::instance($course->id);
            
            // Check if user has grading capability
            if (has_capability('mod/vpl:grade', $context)) {
                echo '<li style="margin-bottom: 15px; padding: 10px; background: white; border: 1px solid #ddd; border-radius: 3px;">';
                echo '<div style="font-weight: bold; margin-bottom: 5px;">'.htmlspecialchars($course->fullname).'</div>';
                echo '<div style="color: #666; font-size: 0.9em; margin-bottom: 8px;">VPL Activities: '.$vplcount.'</div>';
                echo '<a href="comparegrades.php?id='.$course->id.'" class="btn btn-primary btn-sm">';
                echo 'Compare Grades in this Course';
                echo '</a>';
                echo '</li>';
            }
        }
    }
    
    echo '</ul>';
    echo '</div>';
}

echo '</div>';

echo '<div style="max-width: 800px; margin: 30px auto; padding: 15px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 5px;">';
echo '<h4>How to use:</h4>';
echo '<ol>';
echo '<li>Click on "Compare Grades in this Course" for the course you want to analyze</li>';
echo '<li>Select 2 or more VPL activities using the checkboxes</li>';
echo '<li>Click "Compare Grades" to see a side-by-side comparison</li>';
echo '<li>The system will automatically highlight the highest grade for each student</li>';
echo '<li>You can export the results to CSV for further analysis</li>';
echo '</ol>';
echo '</div>';

echo $OUTPUT->footer();
