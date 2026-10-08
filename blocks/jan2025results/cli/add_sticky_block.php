<!-- <?php
/**
 * CLI script to add Jan 2025 Results block as a sticky block on all user dashboards
 */

// define('CLI_SCRIPT', true);

// require(__DIR__ . '/../../../config.php');
// require_once($CFG->libdir . '/clilib.php');

// // Get the system context
// $systemcontext = context_system::instance();

// // Check if block already exists as a sticky block
// $existing = $DB->get_records('block_instances', [
//     'blockname' => 'jan2025results',
//     'parentcontextid' => $systemcontext->id,
//     'pagetypepattern' => 'my-index'
// ]);

// if (!empty($existing)) {
//     echo "Sticky block already exists for my-index page\n";
//     foreach ($existing as $block) {
//         echo "Block ID: {$block->id}, Region: {$block->defaultregion}, Weight: {$block->defaultweight}\n";
//     }
//     exit(0);
// }

// echo "Creating sticky block instance for all user dashboards...\n";

// try {
//     // Get the block ID
//     $block = $DB->get_record('block', ['name' => 'jan2025results'], '*', MUST_EXIST);
    
//     // Create block instance directly in the database
//     $blockinstance = new stdClass();
//     $blockinstance->blockname = 'jan2025results';
//     $blockinstance->parentcontextid = $systemcontext->id;
//     $blockinstance->showinsubcontexts = 1;
//     $blockinstance->pagetypepattern = 'my-index';
//     $blockinstance->subpagepattern = null;
//     $blockinstance->defaultregion = 'content';
//     $blockinstance->defaultweight = 0;
//     $blockinstance->configdata = '';
//     $blockinstance->timecreated = time();
//     $blockinstance->timemodified = time();
    
//     $blockinstance->id = $DB->insert_record('block_instances', $blockinstance);
    
//     // Create block position record
//     $blockposition = new stdClass();
//     $blockposition->blockinstanceid = $blockinstance->id;
//     $blockposition->contextid = $systemcontext->id;
//     $blockposition->pagetype = 'my-index';
//     $blockposition->subpage = '';
//     $blockposition->visible = 1;
//     $blockposition->region = 'content';
//     $blockposition->weight = 0;
    
//     $DB->insert_record('block_positions', $blockposition);
    
//     echo "✓ Successfully created sticky block instance (ID: {$blockinstance->id})!\n";
//     echo "The block will now appear on all user dashboards.\n";
//     echo "\nNote: The block will only show content for:\n";
//     echo "  - Admins (all results)\n";
//     echo "  - Teachers (student results only)\n";
//     echo "  - Students from Jan, 2025 batch (student results)\n";
// } catch (Exception $e) {
//     echo "✗ Error creating sticky block: {$e->getMessage()}\n";
//     exit(1);
// } -->
