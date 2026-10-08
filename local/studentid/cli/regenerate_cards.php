<?php
/**
 * Regenerate stored ID card PDFs in place (e.g. after a template / watermark
 * change). Each card keeps its existing card number, filename and download
 * counters — only the PDF bytes are re-rendered — and students are NOT
 * re-notified.
 *
 * Usage:
 *   php local/studentid/cli/regenerate_cards.php                 # dry run, all cards
 *   php local/studentid/cli/regenerate_cards.php --execute       # regenerate all cards
 *   php local/studentid/cli/regenerate_cards.php --cardid=12 --execute
 *   php local/studentid/cli/regenerate_cards.php --requestid=34 --execute
 */
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/local/studentid/classes/manager.php');

list($options, $unrecognised) = cli_get_params(
    [
        'execute'   => false,
        'cardid'    => 0,
        'requestid' => 0,
        'help'      => false,
    ],
    ['h' => 'help']
);

if ($options['help']) {
    cli_writeln("Regenerate stored student ID card PDFs in place (keeps card numbers).\n");
    cli_writeln("Usage: php local/studentid/cli/regenerate_cards.php [--cardid=N|--requestid=N] [--execute]\n");
    cli_writeln("Without --execute the script only lists the cards it would regenerate (dry run).");
    exit(0);
}

// Resolve the target set of cards.
if ($options['cardid']) {
    $cards = $DB->get_records('local_studentid_cards', ['id' => (int) $options['cardid']]);
} else if ($options['requestid']) {
    $cards = $DB->get_records('local_studentid_cards', ['requestid' => (int) $options['requestid']]);
} else {
    $cards = $DB->get_records('local_studentid_cards', [], 'id ASC');
}

$total = count($cards);
if ($total === 0) {
    cli_writeln("No matching cards found.");
    exit(0);
}

if (!$options['execute']) {
    cli_writeln("DRY RUN — would regenerate {$total} card(s). Re-run with --execute to apply.");
    foreach ($cards as $card) {
        cli_writeln("  card #{$card->id}  number={$card->idcard_number}  requestid={$card->requestid}");
    }
    exit(0);
}

$generatorid = (int) get_admin()->id;
cli_writeln("Regenerating {$total} card(s)...");

$ok = 0;
$failed = [];
foreach ($cards as $card) {
    try {
        $number = \local_studentid\manager::regenerate_card((int) $card->id, $generatorid);
        $ok++;
        cli_writeln("  [OK]   card #{$card->id}  {$number}");
    } catch (\Throwable $e) {
        $failed[] = $card->id;
        cli_writeln("  [FAIL] card #{$card->id}  requestid={$card->requestid}: " . $e->getMessage());
    }
}

cli_writeln("\nDone. Regenerated {$ok}/{$total}.");
if ($failed) {
    cli_writeln("Failed card ids: " . implode(', ', $failed));
    exit(1);
}
exit(0);
