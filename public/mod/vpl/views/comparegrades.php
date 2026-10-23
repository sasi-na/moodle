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
 * Compare grades across multiple VPL activities
 *
 * @package mod_vpl
 * @copyright 2025
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../../config.php');
require_once($CFG->dirroot.'/mod/vpl/locallib.php');
require_once($CFG->dirroot.'/mod/vpl/vpl.class.php');
require_once($CFG->libdir.'/tablelib.php');

global $CFG, $USER, $DB, $OUTPUT, $PAGE;

require_login();

$courseid = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

// Get selected VPL IDs (comma-separated)
$vplids = optional_param('vplids', '', PARAM_TEXT);

$PAGE->set_url(new moodle_url('/mod/vpl/views/comparegrades.php', ['id' => $courseid]));
require_login($course);
$context = context_course::instance($courseid);
$PAGE->set_context($context);
$PAGE->set_title(get_string('comparegrades', 'vpl'));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('comparegrades', 'vpl'));

// Get all VPL activities in the course
$vpls = $DB->get_records('vpl', ['course' => $courseid], 'name ASC');

if (empty($vpls)) {
    echo $OUTPUT->notification('No VPL activities found in this course.', 'info');
    echo $OUTPUT->footer();
    exit;
}

// Display VPL selection form
echo '<form method="get" action="comparegrades.php" style="margin: 20px 0;">';
echo '<input type="hidden" name="id" value="'.$courseid.'" />';
echo '<div style="background: #f9f9f9; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">';
echo '<label style="font-weight: bold;">Select VPL Activities to Compare:</label><br>';
echo '<div style="margin-top: 10px;">';

// Show checkboxes for each VPL
$selectedvpls = !empty($vplids) ? explode(',', $vplids) : [];
foreach ($vpls as $vpl) {
    $checked = in_array($vpl->id, $selectedvpls) ? 'checked' : '';
    echo '<label style="display: inline-block; margin-right: 20px; margin-bottom: 10px;">';
    echo '<input type="checkbox" name="vpl[]" value="'.$vpl->id.'" '.$checked.'> ';
    echo htmlspecialchars($vpl->name);
    echo '</label>';
}

echo '</div>';
echo '<button type="submit" class="btn btn-primary" style="margin-top: 10px;">Compare Grades</button>';
echo '</div>';
echo '</form>';

// If VPLs are selected, show comparison
if (!empty($_GET['vpl']) && is_array($_GET['vpl'])) {
    $selectedvplids = array_map('intval', $_GET['vpl']);
    
    if (count($selectedvplids) < 2) {
        echo $OUTPUT->notification('Please select at least 2 VPL activities to compare.', 'warning');
    } else {
        // Get all students enrolled in the course
        $students = get_enrolled_users($context, 'mod/vpl:submit', 0, 'u.id, u.firstname, u.lastname, u.email', 'u.lastname, u.firstname');
        
        if (empty($students)) {
            echo $OUTPUT->notification('No students found in this course.', 'info');
        } else {
            // Build comparison data
            $comparisondata = [];
            
            foreach ($students as $student) {
                $studentdata = [
                    'id' => $student->id,
                    'name' => fullname($student),
                    'email' => $student->email,
                    'grades' => [],
                    'highest_grade' => null,
                    'highest_vpl' => '',
                ];
                
                foreach ($selectedvplids as $vplid) {
                    // Get course module for this VPL
                    $cm = get_coursemodule_from_instance('vpl', $vplid, $courseid);
                    if (!$cm) {
                        continue;
                    }
                    
                    $vplobj = new mod_vpl($cm->id);
                    $lastsub = $vplobj->last_user_submission($student->id);
                    
                    if ($lastsub && !is_null($lastsub->grade)) {
                        $grade = floatval($lastsub->grade);
                        $studentdata['grades'][$vplid] = $grade;
                        
                        if (is_null($studentdata['highest_grade']) || $grade > $studentdata['highest_grade']) {
                            $studentdata['highest_grade'] = $grade;
                            $studentdata['highest_vpl'] = $vplobj->get_instance()->name;
                        }
                    } else {
                        $studentdata['grades'][$vplid] = null;
                    }
                }
                
                $comparisondata[] = $studentdata;
            }
            
            // Display results table
            echo '<h3 style="margin-top: 30px;">Grade Comparison Results</h3>';
            echo '<table class="generaltable" style="width: 100%; margin-top: 20px;">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>Student Name</th>';
            echo '<th>Email</th>';
            
            // VPL column headers
            foreach ($selectedvplids as $vplid) {
                $vpl = $vpls[$vplid];
                echo '<th>'.htmlspecialchars($vpl->name).'</th>';
            }
            
            echo '<th style="background: #d4edda; font-weight: bold;">Highest Grade</th>';
            echo '<th style="background: #d4edda; font-weight: bold;">From Activity</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            // Data rows
            foreach ($comparisondata as $data) {
                echo '<tr>';
                echo '<td>'.htmlspecialchars($data['name']).'</td>';
                echo '<td>'.htmlspecialchars($data['email']).'</td>';
                
                // Show grade for each VPL
                foreach ($selectedvplids as $vplid) {
                    $grade = $data['grades'][$vplid];
                    $maxgrade = $vpls[$vplid]->grade;
                    
                    if (!is_null($grade)) {
                        $gradetext = $grade . ' / ' . $maxgrade;
                        // Highlight if this is the highest grade
                        if ($grade == $data['highest_grade']) {
                            echo '<td style="background: #d4edda; font-weight: bold;">'.$gradetext.'</td>';
                        } else {
                            echo '<td>'.$gradetext.'</td>';
                        }
                    } else {
                        echo '<td style="color: #999;">No submission</td>';
                    }
                }
                
                // Highest grade columns
                if (!is_null($data['highest_grade'])) {
                    echo '<td style="background: #d4edda; font-weight: bold;">'.$data['highest_grade'].'</td>';
                    echo '<td style="background: #d4edda;">'.htmlspecialchars($data['highest_vpl']).'</td>';
                } else {
                    echo '<td style="color: #999;">-</td>';
                    echo '<td style="color: #999;">-</td>';
                }
                
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
            
            // Export to CSV option
            echo '<div style="margin-top: 20px;">';
            echo '<a href="comparegrades_export.php?id='.$courseid.'&vplids='.implode(',', $selectedvplids).'" class="btn btn-secondary">';
            echo 'Export to CSV';
            echo '</a>';
            echo '</div>';
        }
    }
}

echo $OUTPUT->footer();
