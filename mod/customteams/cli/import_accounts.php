<?php
/**
 * Import the shared Teams account map from CSV.
 *
 * The master sheet ("Teams ID's master file.xlsx") is exported to CSV with the
 * columns: batch, program, upn[, displayname]. A leading "SL No" column and a
 * "PW" column are ignored if present -- passwords are deliberately never stored,
 * because app-only Graph auth acts on the mailbox directly and never signs in as
 * the account.
 *
 * Usage:
 *   php mod/customteams/cli/import_accounts.php --file=accounts.csv --dry-run
 *   php mod/customteams/cli/import_accounts.php --file=accounts.csv --verify
 *   php mod/customteams/cli/import_accounts.php --file=accounts.csv --replace
 *
 * Re-runnable each intake: --replace deactivates rows absent from the file
 * rather than deleting them, so meetings already booked keep a resolvable owner.
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use mod_customteams\account_map;
use mod_customteams\graph_helper;

list($options, $unrecognised) = cli_get_params([
    'file'    => null,
    'dry-run' => false,
    'verify'  => false,
    'replace' => false,
    'help'    => false,
], ['f' => 'file', 'h' => 'help']);

if ($options['help'] || empty($options['file'])) {
    cli_writeln("Import the mod_customteams shared account map from CSV.

Options:
  -f, --file=PATH   CSV with columns batch, program, upn[, displayname]
      --dry-run     Parse, normalise and report. Writes nothing.
      --verify      Check every distinct UPN exists in the Azure tenant.
      --replace     Deactivate existing rows that are absent from the file.
  -h, --help        This text.
");
    exit(0);
}

$path = $options['file'];

if (!is_readable($path)) {
    cli_error('Cannot read file: ' . $path);
}

$handle = fopen($path, 'r');
if ($handle === false) {
    cli_error('Cannot open file: ' . $path);
}

$header = fgetcsv($handle);
if ($header === false) {
    cli_error('File is empty.');
}

// Locate columns by name so the sheet's column order can change without
// silently importing the wrong field.
$index = [];
foreach ($header as $i => $name) {
    $key = strtolower(trim($name));
    $key = preg_replace('/[^a-z]/', '', $key);
    $index[$key] = $i;
}

foreach (['batch', 'program', 'id'] as $required) {
    // The sheet calls the UPN column "ID"; accept either spelling.
    if ($required === 'id' && (isset($index['upn']) || isset($index['id']))) {
        continue;
    }
    if (!isset($index[$required])) {
        cli_error('Missing required column: ' . $required . ' (found: ' . implode(', ', array_keys($index)) . ')');
    }
}

$upncol = $index['upn'] ?? $index['id'];

$rows = [];
$problems = [];
$line = 1;

while (($row = fgetcsv($handle)) !== false) {
    $line++;

    if (count(array_filter($row, 'strlen')) === 0) {
        continue;
    }

    $rawbatch = trim($row[$index['batch']] ?? '');
    $program  = trim($row[$index['program']] ?? '');
    $upn      = strtolower(trim($row[$upncol] ?? ''));
    $display  = isset($index['displayname']) ? trim($row[$index['displayname']] ?? '') : '';

    if ($upn === '') {
        $problems[] = "line $line: no account id";
        continue;
    }

    if (!validate_email($upn)) {
        $problems[] = "line $line: '$upn' is not a valid address";
        continue;
    }

    $batch = account_map::normalise_batch($rawbatch);

    if ($batch === '') {
        $problems[] = "line $line: no batch for $upn";
        continue;
    }

    // Anything that did not fold to "Month YYYY" will never match a course
    // category, so flag it instead of importing an unusable row.
    if (!preg_match('/^(Jan|July) \d{4}$/', $batch)) {
        $problems[] = "line $line: batch '$rawbatch' did not normalise to a known form (got '$batch') -- $upn";
        continue;
    }

    if ($program === '') {
        $problems[] = "line $line: no programme for $upn";
        continue;
    }

    // Same account listed twice for the same pair: harmless in the sheet, a
    // duplicate option in the picker if imported. Collapse silently.
    $key = $batch . '|' . $program . '|' . $upn;
    if (isset($rows[$key])) {
        continue;
    }

    $rows[$key] = (object) [
        'batch'       => $batch,
        'program'     => $program,
        'upn'         => $upn,
        'displayname' => $display !== '' ? $display : null,
    ];

    if ($rawbatch !== $batch) {
        cli_writeln("  normalised batch '$rawbatch' -> '$batch' (line $line)");
    }
}

fclose($handle);

cli_separator();
cli_writeln('Parsed ' . count($rows) . ' unique batch/programme/account rows.');
cli_writeln('Distinct accounts: ' . count(array_unique(array_column($rows, 'upn'))));

if (!empty($problems)) {
    cli_writeln('');
    cli_problem('Rows that could not be imported (' . count($problems) . '):');
    foreach ($problems as $problem) {
        cli_problem('  ' . $problem);
    }
}

if ($options['verify']) {
    cli_separator();
    cli_writeln('Verifying accounts against the Azure tenant...');

    // Batched: 20 accounts per Graph call rather than one call each.
    $checked = graph_helper::check_accounts(array_column($rows, 'upn'));

    $bad = array_filter($checked, function($result) {
        return !$result->ok;
    });

    if (empty($bad)) {
        cli_writeln('  all ' . count($checked) . ' accounts exist and are enabled.');
    } else {
        cli_problem('  ' . count($bad) . ' of ' . count($checked) . ' account(s) need attention:');
        foreach ($bad as $upn => $result) {
            cli_problem('    ' . $upn . ': ' . $result->message);
        }
    }
}

if ($options['dry-run']) {
    cli_separator();
    cli_writeln('Dry run: nothing written.');
    exit(empty($problems) ? 0 : 1);
}

cli_separator();

// Share the upsert path with the admin setting, so the CLI and the settings
// page cannot drift on what "import" means.
$counts = account_map::sync($rows, $options['replace'] && !empty($rows));

cli_writeln("Created: {$counts['created']}");
cli_writeln("Updated: {$counts['updated']}");
if ($options['replace']) {
    cli_writeln("Deactivated: {$counts['deactivated']}");
}

exit(empty($problems) ? 0 : 1);
