<!-- <?php
/**
 * CLI script to add Jan 2025 Results block to all Jan 2025 students' dashboards
 */

// define('CLI_SCRIPT', true);

// require(__DIR__ . '/../../../config.php');
// require_once($CFG->libdir . '/clilib.php');
// require_once($CFG->dirroot . '/my/lib.php');

// // Get the field ID for "Cohort/Batch"
// $field = $DB->get_record('user_info_field', ['shortname' => 'cohort_batch']);
// if (!$field) {
//     $field = $DB->get_record('user_info_field', ['name' => 'Cohort/Batch']);
// }

// if (!$field) {
//     cli_error("Cohort/Batch field not found!");
// }

// echo "Found Cohort/Batch field (ID: {$field->id})\n";

// // Get all users with Jan, 2025 batch
// $sql = "SELECT u.id, u.username, u.firstname, u.lastname, uid.data
//         FROM {user} u
//         JOIN {user_info_data} uid ON uid.userid = u.id
//         WHERE uid.fieldid = :fieldid
//         AND u.deleted = 0
//         AND u.suspended = 0";

// $users = $DB->get_records_sql($sql, ['fieldid' => $field->id]);

// echo "Found " . count($users) . " users with cohort/batch data\n\n";

// $patterns = ['jan, 2025', 'jan 2025', 'jan,2025', 'january 2025', 'january, 2025'];
// $jan2025users = [];

// foreach ($users as $user) {
//     $value = preg_replace('/\s+/', ' ', trim($user->data));
//     $value_lower = strtolower($value);
    
//     foreach ($patterns as $pattern) {
//         if (strpos($value_lower, $pattern) !== false) {
//             $jan2025users[] = $user;
//             echo "✓ Found Jan 2025 student: {$user->firstname} {$user->lastname} (ID: {$user->id}) - Batch: {$user->data}\n";
//             break;
//         }
//     }
// }

// echo "\nTotal Jan 2025 students: " . count($jan2025users) . "\n\n";

// if (empty($jan2025users)) {
//     cli_error("No Jan 2025 students found!");
// }

// // Get the block ID
// $block = $DB->get_record('block', ['name' => 'jan2025results']);
// if (!$block) {
//     cli_error("Jan2025results block not found!");
// }

// echo "Found jan2025results block (ID: {$block->id})\n\n";

// $added = 0;
// $already_exists = 0;

// foreach ($jan2025users as $user) {
//     // Get user's my page context
//     $usercontext = context_user::instance($user->id);
    
//     // Check if block already exists on user's dashboard
//     $existing = $DB->get_records('block_instances', [
//         'blockname' => 'jan2025results',
//         'parentcontextid' => $usercontext->id
//     ]);
    
//     if (!empty($existing)) {
//         echo "⊘ Block already exists for {$user->firstname} {$user->lastname}\n";
//         $already_exists++;
//         continue;
//     }
    
//     try {
//         // Create block instance for this user's dashboard
//         $page = new moodle_page();
//         $page->set_context($usercontext);
//         $page->set_pagetype('my-index');
//         $page->set_subpage($user->id);
        
//         // Add block to the content region (Moodle 5.0 uses 'content' region)
//         $page->blocks->add_block('jan2025results', 'content', 0, false, 'my-index', $user->id);
        
//         echo "✓ Added block to dashboard for {$user->firstname} {$user->lastname}\n";
//         $added++;
//     } catch (Exception $e) {
//         echo "✗ Error adding block for {$user->firstname} {$user->lastname}: {$e->getMessage()}\n";
//     }
// }

// echo "\n=================================\n";
// echo "Summary:\n";
// echo "  Added: $added\n";
// echo "  Already exists: $already_exists\n";
// echo "  Total processed: " . count($jan2025users) . "\n";
// echo "=================================\n"; -->
