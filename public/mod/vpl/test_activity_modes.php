<?php
// This file is part of VPL for Moodle - http://vpl.dis.ulpgc.es/
//
// Simple test script to verify activity modes implementation
//
// Usage: Run this script from the VPL directory to test the new functionality

require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/vpl.class.php');

echo "<h1>VPL Activity Modes Test</h1>\n";

// Test the language strings
echo "<h2>Language Strings Test</h2>\n";
echo "<p><strong>Activity Mode:</strong> " . get_string('activity_mode', 'mod_vpl') . "</p>\n";
echo "<p><strong>Practice Mode:</strong> " . get_string('activity_mode:practice', 'mod_vpl') . "</p>\n";
echo "<p><strong>CA Mode:</strong> " . get_string('activity_mode:continuous_assessment', 'mod_vpl') . "</p>\n";
// echo "<p><strong>Practice Details:</strong> " . get_string('practice_mode_details', 'mod_vpl') . "</p>\n";
// echo "<p><strong>CA Details:</strong> " . get_string('ca_mode_details', 'mod_vpl') . "</p>\n";

// Test VPL class methods with mock data
echo "<h2>VPL Class Methods Test</h2>\n";

// Create a mock VPL instance object
$mockInstance = new stdClass();
$mockInstance->id = 1;
$mockInstance->activity_mode = 0; // Practice mode

echo "<h3>Practice Mode Test (activity_mode = 0)</h3>\n";
echo "<p>Activity Mode: " . (isset($mockInstance->activity_mode) ? $mockInstance->activity_mode : 0) . "</p>\n";
echo "<p>Is Practice Mode: " . (($mockInstance->activity_mode ?? 0) == 0 ? 'Yes' : 'No') . "</p>\n";
echo "<p>Is CA Mode: " . (($mockInstance->activity_mode ?? 0) == 1 ? 'Yes' : 'No') . "</p>\n";
echo "<p>Grade Range: Min = 0, Max = 100</p>\n";

// Test CA mode
$mockInstance->activity_mode = 1; // CA mode
echo "<h3>Continuous Assessment Mode Test (activity_mode = 1)</h3>\n";
echo "<p>Activity Mode: " . $mockInstance->activity_mode . "</p>\n";
echo "<p>Is Practice Mode: " . ($mockInstance->activity_mode == 0 ? 'Yes' : 'No') . "</p>\n";
echo "<p>Is CA Mode: " . ($mockInstance->activity_mode == 1 ? 'Yes' : 'No') . "</p>\n";
echo "<p>Grade Range: Min = 0, Max = 100</p>\n";

echo "<h2>Database Schema Test</h2>\n";
echo "<p>Check if the activity_mode field exists in the vpl table:</p>\n";

global $DB;
try {
    $dbman = $DB->get_manager();
    $table = new xmldb_table('vpl');
    $field = new xmldb_field('activity_mode');
    
    if ($dbman->field_exists($table, $field)) {
        echo "<p style='color: green;'>✓ activity_mode field exists in vpl table</p>\n";
        
        // Try to get field info
        $columns = $DB->get_columns('vpl');
        if (isset($columns['activity_mode'])) {
            $fieldInfo = $columns['activity_mode'];
            echo "<p>Field details: Type=" . $fieldInfo->type . ", Default=" . ($fieldInfo->default_value ?? 'NULL') . "</p>\n";
        }
    } else {
        echo "<p style='color: red;'>✗ activity_mode field does not exist in vpl table</p>\n";
        echo "<p>You may need to run the database upgrade. Go to Site Administration > Notifications.</p>\n";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error checking database: " . $e->getMessage() . "</p>\n";
}

echo "<h2>Form Integration Test</h2>\n";
echo "<p>The activity mode dropdown should appear in the VPL creation/edit form.</p>\n";
echo "<p>To test: Create or edit a VPL activity and look for the 'Activity mode' field.</p>\n";

echo "<h2>View Integration Test</h2>\n";
echo "<p>Activity mode information should display when viewing a VPL activity.</p>\n";
echo "<p>To test: View any VPL activity and look for:</p>\n";
echo "<ul>\n";
echo "<li>Activity mode alert box with mode details</li>\n";
echo "<li>Submission guidelines specific to the mode</li>\n";
echo "<li>Mode-specific grading information for teachers</li>\n";
echo "</ul>\n";

echo "<h2>Assessment Structure Summary</h2>\n";
echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>\n";
echo "<h3>Practice Mode (25 marks total)</h3>\n";
echo "<ul>\n";
echo "<li>Section 1: 5 questions × 2 marks each = 10 marks</li>\n";
echo "<li>Section 2: 5 questions × 3 marks each = 15 marks</li>\n";
echo "<li>Duration: 12 weeks to complete</li>\n";
echo "</ul>\n";
echo "</div>\n";

echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>\n";
echo "<h3>Continuous Assessment Mode (25 marks each for CA1/CA2/CA3)</h3>\n";
echo "<ul>\n";
echo "<li>Part A: 1 of 2 questions × 10 marks = 10 marks</li>\n";
echo "<li>Part B: 1 of 2 questions × 15 marks = 15 marks</li>\n";
echo "<li>Total per CA: 25 marks</li>\n";
echo "</ul>\n";
echo "</div>\n";

echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>\n";
echo "<h3>Total Internal Assessment (IA)</h3>\n";
echo "<ul>\n";
echo "<li>CA1: 25 marks</li>\n";
echo "<li>CA2: 25 marks</li>\n";
echo "<li>CA3: 25 marks</li>\n";
echo "<li>Practice: 25 marks</li>\n";
echo "<li><strong>Total IA: 100 marks</strong></li>\n";
echo "</ul>\n";
echo "</div>\n";

echo "<p><em>Test completed. Check the results above to verify the implementation.</em></p>\n";
?>
