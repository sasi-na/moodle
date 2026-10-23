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
 * Export grade comparison to CSV
 *
 * @package mod_vpl
 * @copyright 2025
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../../config.php');
require_once($CFG->dirroot.'/mod/vpl/locallib.php');
require_once($CFG->dirroot.'/mod/vpl/vpl.class.php');

global $CFG, $USER, $DB;

require_login();

$courseid = required_param('id', PARAM_INT);
$vplids = required_param('vplids', PARAM_TEXT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);

$context = context_course::instance($courseid);
require_capability('mod/vpl:grade', $context);

$selectedvplids = array_map('intval', explode(',', $vplids));

if (count($selectedvplids) < 2) {
    die('At least 2 VPL activities must be selected');
}

// Get VPL activities
$vpls = [];
foreach ($selectedvplids as $vplid) {
    $vpls[$vplid] = $DB->get_record('vpl', ['id' => $vplid], '*', MUST_EXIST);
}

// Get students
$students = get_enrolled_users($context, 'mod/vpl:submit', 0, 'u.id, u.firstname, u.lastname, u.email, u.idnumber', 'u.lastname, u.firstname');

// Set headers for CSV download
$filename = 'vpl_grade_comparison_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Write BOM for Excel UTF-8 support
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Build header row
$headers = ['Student ID', 'Student Name', 'Email'];

foreach ($selectedvplids as $vplid) {
    $headers[] = $vpls[$vplid]->name;
}

$headers[] = 'Highest Grade';
$headers[] = 'From Activity';

fputcsv($output, $headers);

// Build data rows
foreach ($students as $student) {
    $row = [
        $student->id,
        fullname($student),
        $student->email,
    ];
    
    $highestgrade = null;
    $highestvpl = '';
    
    foreach ($selectedvplids as $vplid) {
        // Get course module for this VPL
        $cm = get_coursemodule_from_instance('vpl', $vplid, $courseid);
        if (!$cm) {
            $row[] = 'Error';
            continue;
        }
        
        $vplobj = new mod_vpl($cm->id);
        $lastsub = $vplobj->last_user_submission($student->id);
        
        if ($lastsub && !is_null($lastsub->grade)) {
            $grade = floatval($lastsub->grade);
            $row[] = $grade . ' / ' . $vpls[$vplid]->grade;
            
            if (is_null($highestgrade) || $grade > $highestgrade) {
                $highestgrade = $grade;
                $highestvpl = $vpls[$vplid]->name;
            }
        } else {
            $row[] = 'No submission';
        }
    }
    
    $row[] = !is_null($highestgrade) ? $highestgrade : '-';
    $row[] = $highestvpl;
    
    fputcsv($output, $row);
}

fclose($output);
exit;
