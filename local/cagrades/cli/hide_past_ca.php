<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Hide previous-semester continuous assessment marks from learners.
 *
 * Learners should see only their current semester's CA marks. Current means the highest semester
 * number the learner is enrolled in - see {@see local_cagrades\past_ca} for why there is no other
 * signal to use, and why the flag has to be per learner rather than per course.
 *
 * This is the initial sweep. Afterwards the scheduled task local_cagrades\task\hide_past_ca keeps it
 * true, including repairing the drift caused by the gradebook's own show/hide control, which
 * cascades over every learner's flag on an item.
 *
 * It only ever hides. Nothing here reveals a mark, and the current semester is never touched, so the
 * per-item release the teaching team drives keeps working exactly as it does today.
 *
 * Dry run by default. Every row is journaled before it is touched, so --rollback can put it back.
 *
 * Usage (on an app pod):
 *   php local/cagrades/cli/hide_past_ca.php
 *   php local/cagrades/cli/hide_past_ca.php --user=26913
 *   php local/cagrades/cli/hide_past_ca.php --execute --backup=/path/pastca.json
 *   php local/cagrades/cli/hide_past_ca.php --rollback=/path/pastca.json --execute
 *
 * @package   local_cagrades
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/gradelib.php');

use local_cagrades\past_ca;
use local_cagrades\run_lock;

/** @var int[] Learners whose per-semester counts are printed before and after, as a spot check. */
const LOCAL_CAGRADES_SAMPLE_LEARNERS = [35363, 26913, 32461];

[$options, $unrecognized] = cli_get_params([
    'execute' => false,
    'backup' => '',
    'rollback' => '',
    'limit' => 0,
    'user' => 0,
    'force-unlock' => false,
    'help' => false,
], ['h' => 'help']);

if ($unrecognized) {
    cli_error('Unrecognised options: ' . implode(' ', $unrecognized));
}

if ($options['help']) {
    cli_writeln("Hide previous-semester continuous assessment marks from learners.\n");
    cli_writeln('Options:');
    cli_writeln('  --execute            Apply the changes (otherwise dry run).');
    cli_writeln('  --backup=<file>      Where to journal the changed rows (required with --execute).');
    cli_writeln('  --rollback=<file>    Reveal again exactly the rows a previous run hid.');
    cli_writeln('  --limit=<n>          Stop after n rows, to run the sweep in batches.');
    cli_writeln('  --user=<id>          Act on one learner only, for spot checks.');
    cli_writeln('  --force-unlock       Clear a lock left behind by a killed run. Be sure no run');
    cli_writeln('                       is actually active before using it.');
    cli_writeln('  -h, --help           This help.');
    exit(0);
}

$execute = !empty($options['execute']);
$userid = (int) $options['user'] ?: null;
$limit = (int) $options['limit'];

if (!empty($options['force-unlock'])) {
    $held = run_lock::describe(past_ca::RUN_NAME);
    if (!$held) {
        cli_writeln('No lock is held for ' . past_ca::RUN_NAME . '.');
        exit(0);
    }
    cli_writeln(sprintf('Lock held by %s, expires %s%s.', $held->owner,
        userdate($held->expires), $held->expired ? ' (EXPIRED)' : ''));
    if (!$execute) {
        cli_writeln('Dry run - re-run with --execute to clear it.');
        exit(0);
    }
    cli_writeln(run_lock::force_release(past_ca::RUN_NAME) ? 'Lock cleared.' : 'Nothing to clear.');
    exit(0);
}

// Only ever one writer: this rewrites its journal whole, and so does the scheduled task's lock
// holder. $lock must stay in scope for the life of the process.
$lock = null;
if ($execute) {
    $lock = run_lock::acquire(past_ca::RUN_NAME);
    if ($lock === null) {
        $held = run_lock::describe(past_ca::RUN_NAME);
        $detail = $held
            ? sprintf(' Held by %s, expires %s%s.', $held->owner, userdate($held->expires),
                $held->expired ? ' (EXPIRED - use --force-unlock)' : '')
            : '';
        cli_error('Another ' . past_ca::RUN_NAME . " run holds the lock.{$detail}");
    }
    run_lock::release_on_shutdown($lock);
}

// ---------------------------------------------------------------- rollback

if ($options['rollback'] !== '') {
    $path = (string) $options['rollback'];
    if (!file_exists($path)) {
        cli_error("Cannot read the backup file {$path}.");
    }
    $journal = json_decode(trim((string) file_get_contents($path)), true);
    if (!is_array($journal) || !isset($journal['hidden']) || !is_array($journal['hidden'])) {
        cli_error("The backup file {$path} is not a readable hide_past_ca journal.");
    }

    $ids = array_map('intval', $journal['hidden']);
    cli_writeln(sprintf('%s revealing %d row(s) recorded in %s.',
        $execute ? 'EXECUTING:' : 'DRY RUN:', count($ids), $path));

    if (!$execute) {
        cli_writeln('Dry run - nothing changed. Re-run with --execute to apply.');
        exit(0);
    }

    $revealed = 0;
    foreach (array_chunk($ids, past_ca::BATCH_SIZE) as $chunk) {
        [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'gg');
        $revealed += $DB->count_records_select('grade_grades', "id {$insql} AND hidden <> 0", $params);
        $DB->execute("UPDATE {grade_grades} SET hidden = 0 WHERE id {$insql}", $params);
    }
    cli_writeln("Revealed {$revealed} row(s).");
    exit(0);
}

// ---------------------------------------------------------------- sweep

if ($execute && $options['backup'] === '') {
    cli_error('--backup=<file> is required with --execute: without it the run cannot be undone.');
}

$backuppath = (string) $options['backup'];
if ($execute && file_exists($backuppath)) {
    // Refuse to overwrite anything that is not one of our journals.
    $existing = json_decode(trim((string) file_get_contents($backuppath)), true);
    if (!is_array($existing) || !isset($existing['hidden'])) {
        cli_error("The backup file {$backuppath} is not a readable hide_past_ca journal - "
            . 'refusing to overwrite it.');
    }
}

$map = past_ca::semester_map();
cli_writeln(sprintf('%d semester categor%s recognised.', count($map), count($map) === 1 ? 'y' : 'ies'));

// The spot-check learners, before. Their current semester's numbers must not move.
$before = [];
foreach (LOCAL_CAGRADES_SAMPLE_LEARNERS as $sampleid) {
    $before[$sampleid] = past_ca::learner_summary($sampleid);
}

$total = past_ca::count_candidates($userid);
cli_writeln(sprintf('%s hiding %d previous-semester CA mark(s)%s.',
    $execute ? 'EXECUTING:' : 'DRY RUN:', $total,
    $userid ? " for user {$userid}" : ''));

if ($limit > 0 && $limit < $total) {
    cli_writeln("  --limit={$limit}: " . ($total - $limit) . ' row(s) will be left for a later run.');
}

if (!$execute) {
    local_cagrades_past_ca_report_samples($before, $userid);
    cli_writeln("\nDry run - nothing changed. Re-run with --execute --backup=<file> to apply.");
    exit(0);
}

$journal = ['started' => time(), 'hidden' => []];
$hidden = 0;
$target = $limit > 0 ? min($limit, $total) : $total;

while ($hidden < $target) {
    $batch = min(past_ca::BATCH_SIZE, $target - $hidden);
    $ids = past_ca::candidate_ids($batch, $userid);
    if (!$ids) {
        break;
    }

    // Journal BEFORE the write, and rewrite it whole after every batch, so a killed run is still
    // fully undoable. Every id here was hidden = 0 - that is in the selection - so the rollback is
    // simply "set these back to 0".
    $journal['hidden'] = array_merge($journal['hidden'], $ids);
    if (file_put_contents($backuppath,
            json_encode($journal, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
        cli_error("Could not write the backup file {$backuppath}.");
    }

    $changed = past_ca::hide($ids);
    $hidden += $changed;
    cli_writeln(sprintf('  hid %d row(s) - %d of %d', $changed, $hidden, $target));
}

cli_writeln("\nHid {$hidden} row(s). Journal: {$backuppath}");

// ---------------------------------------------------------------- verify

cli_writeln("\nVerification:");

$remaining = past_ca::count_candidates($userid);
cli_writeln("  rows still matching the rule : {$remaining}"
    . ($limit > 0 ? ' (expected, --limit was set)' : ' (expected 0)'));

$failures = 0;
foreach (LOCAL_CAGRADES_SAMPLE_LEARNERS as $sampleid) {
    $after = past_ca::learner_summary($sampleid);
    $maxsem = past_ca::max_semester($sampleid);
    if ($maxsem === null) {
        continue;
    }

    // The hard assertion the client asked for: the current semester must be untouched, and nothing
    // anywhere may have become MORE visible.
    foreach ($after as $semnum => $counts) {
        $was = $before[$sampleid][$semnum] ?? ['graded' => 0, 'hidden' => 0];
        if ($semnum === $maxsem && $counts['hidden'] !== $was['hidden']) {
            cli_writeln(sprintf('  FAIL user %d semester %d is the CURRENT semester and its hidden '
                . 'count moved %d -> %d', $sampleid, $semnum, $was['hidden'], $counts['hidden']));
            $failures++;
        }
        if ($counts['hidden'] < $was['hidden']) {
            cli_writeln(sprintf('  FAIL user %d semester %d became more visible (%d -> %d hidden)',
                $sampleid, $semnum, $was['hidden'], $counts['hidden']));
            $failures++;
        }
    }
}

local_cagrades_past_ca_report_samples($before, $userid, true);

if ($failures > 0) {
    cli_error("\n{$failures} verification failure(s). Roll back with "
        . "--rollback={$backuppath} --execute and investigate before re-running.");
}
cli_writeln('  current semesters untouched, nothing revealed.');

/**
 * Print each sample learner's graded/hidden CA counts per semester.
 *
 * @param array $before summaries captured before the run
 * @param int|null $userid the single learner the run was restricted to, if any
 * @param bool $withafter re-read the current state and show it beside the before
 * @return void
 */
function local_cagrades_past_ca_report_samples(array $before, ?int $userid, bool $withafter = false): void {
    cli_writeln("\nPer-semester graded CA counts (hidden/graded), '*' marks the current semester:");
    foreach ($before as $sampleid => $summary) {
        if ($userid !== null && $userid !== $sampleid) {
            continue;
        }
        $maxsem = past_ca::max_semester($sampleid);
        $after = $withafter ? past_ca::learner_summary($sampleid) : null;
        $cells = [];
        foreach ($summary as $semnum => $counts) {
            $cell = sprintf('sem%d%s %d/%d', $semnum, $semnum === $maxsem ? '*' : '',
                $counts['hidden'], $counts['graded']);
            if ($after !== null) {
                $now = $after[$semnum] ?? ['graded' => 0, 'hidden' => 0];
                $cell .= sprintf(' -> %d/%d', $now['hidden'], $now['graded']);
            }
            $cells[] = $cell;
        }
        cli_writeln(sprintf('  user %-7d %s', $sampleid, $cells ? implode('   ', $cells) : '(no CA)'));
    }
}
