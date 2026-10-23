<?php
/**
 * One-time CLI cleanup script — deletes all student ID card data.
 * Run from server: php local/studentid/cli/cleanup.php
 */
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognised) = cli_get_params(
    ['confirm' => false, 'help' => false],
    ['h' => 'help']
);

if ($options['help']) {
    cli_writeln("Delete all local_studentid requests, cards, logs and file storage.\n");
    cli_writeln("Usage: php local/studentid/cli/cleanup.php --confirm");
    exit(0);
}

if (!$options['confirm']) {
    cli_writeln("This will permanently delete ALL student ID card data (requests, cards, logs, files).");
    cli_writeln("Run with --confirm to proceed.");
    exit(1);
}

// Delete Moodle file storage for the plugin.
$syscontext = context_system::instance();
$fs = get_file_storage();

foreach (['photo', 'aadhaar_front', 'aadhaar_back', 'abc_id', 'idcard'] as $area) {
    $fs->delete_area_files($syscontext->id, 'local_studentid', $area);
    cli_writeln("Deleted files: $area");
}

// Delete DB records.
$DB->delete_records('local_studentid_cards');
cli_writeln("Deleted all records from local_studentid_cards.");

$DB->delete_records('local_studentid_requests');
cli_writeln("Deleted all records from local_studentid_requests.");

$DB->delete_records('local_studentid_logs');
cli_writeln("Deleted all records from local_studentid_logs.");

cli_writeln("Done. All student ID card data has been removed.");
