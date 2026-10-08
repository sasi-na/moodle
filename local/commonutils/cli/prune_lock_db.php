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
 * Prunes released rows from lock_db.
 *
 * WHY THIS EXISTS. db_record_lock_factory keeps one row per resourcekey forever:
 * get_lock() inserts a row if none exists, and release_lock() only NULLs owner and
 * expires -- it never deletes. Core's task_lock_cleanup_task calls
 * manager::cleanup_metadata(), which releases locks for abnormally stopped tasks
 * but likewise removes no rows. So the table grows without bound. On jain it
 * reached 1.6 million rows, 1.42 million of them cron_adhoc_<taskid> keys for
 * tasks that finished long ago and whose ids are never reissued.
 *
 * WHAT IS SAFE. A row with owner IS NULL holds no lock; get_lock() recreates it on
 * demand, so deleting it costs one INSERT next time that resource is locked. A row
 * with a non-null owner may be held and is never touched. The owner IS NULL test is
 * repeated inside the DELETE, not just the SELECT, so a lock claimed in the gap
 * between the two is skipped rather than deleted out from under its holder.
 *
 * GALERA. Deletes are batched into small id lists. One 1.6-million-row DELETE
 * would be a single enormous write-set and this cluster does not tolerate those.
 *
 * Usage:
 *   php local/commonutils/cli/prune_lock_db.php --dry-run
 *   php local/commonutils/cli/prune_lock_db.php --execute
 *   php local/commonutils/cli/prune_lock_db.php --execute --max=200000
 *
 * @package   local_commonutils
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

/** Ids per DELETE. Small enough to keep each Galera write-set modest. */
const BATCH = 2000;

/** Pause between batches, microseconds, so replication keeps up. */
const PAUSE = 20000;

list($options, $unrecognised) = cli_get_params(
    ['help' => false, 'dry-run' => false, 'execute' => false, 'max' => 0, 'pattern' => ''],
    ['h' => 'help']
);
if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognised)));
}
if ($options['help'] || (!$options['dry-run'] && !$options['execute'])) {
    cli_writeln("Prunes released (owner IS NULL) rows from lock_db.\n");
    cli_writeln("  --dry-run        Report what would go, touch nothing.");
    cli_writeln("  --execute        Delete.");
    cli_writeln("  --max=N          Stop after roughly N rows. 0 (default) means all.");
    cli_writeln("  --pattern=STR    Only keys starting with STR, e.g. --pattern=cron_adhoc_");
    exit(0);
}
$dry = !$options['execute'];
$max = (int) $options['max'];
$pattern = (string) $options['pattern'];

$now = time();
$where = 'owner IS NULL';
$params = [];
if ($pattern !== '') {
    $where .= ' AND ' . $DB->sql_like('resourcekey', ':pat', false);
    $params['pat'] = $DB->sql_like_escape($pattern) . '%';
}

$total = $DB->count_records('lock_db');
$free = $DB->count_records_select('lock_db', $where, $params);
$held = $DB->count_records_select('lock_db', 'owner IS NOT NULL AND expires >= :n', ['n' => $now]);

cli_writeln($dry ? "== DRY RUN ==\n" : "== DELETING ==\n");
cli_writeln(sprintf("lock_db rows:        %s", number_format($total)));
cli_writeln(sprintf("held right now:      %s  (never touched)", number_format($held)));
cli_writeln(sprintf("candidates:          %s%s", number_format($free),
    $pattern !== '' ? " matching '{$pattern}*'" : ''));
if ($max > 0) {
    cli_writeln(sprintf("capped at:           %s", number_format($max)));
}
cli_writeln('');

if ($dry) {
    cli_writeln("Nothing changed. Re-run with --execute to apply.");
    exit(0);
}

$deleted = 0;
$rounds = 0;
$started = microtime(true);

while (true) {
    $take = BATCH;
    if ($max > 0 && ($max - $deleted) < $take) {
        $take = $max - $deleted;
    }
    if ($take <= 0) {
        break;
    }

    // get_fieldset_select/get_fieldset_sql take no limit arguments -- passing them
    // is silently ignored and you get the whole table back, then a million-placeholder
    // IN clause. get_records_sql is the one that bounds the result.
    $batch = $DB->get_records_sql(
        "SELECT id FROM {lock_db} WHERE $where ORDER BY id", $params, 0, $take);
    if (!$batch) {
        break;
    }
    $ids = array_keys($batch);

    list($insql, $inparams) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'id');
    // owner IS NULL repeated here on purpose: a row claimed since the SELECT
    // above must survive, or its holder would lose its lock silently.
    $DB->delete_records_select('lock_db', "id $insql AND owner IS NULL", $inparams);

    $deleted += count($ids);
    $rounds++;
    if ($rounds % 25 === 0) {
        cli_writeln(sprintf("  %s rows in %d batches (%.0f rows/s)", number_format($deleted),
            $rounds, $deleted / max(microtime(true) - $started, 0.01)));
    }
    usleep(PAUSE);
}

$remaining = $DB->count_records('lock_db');
$heldafter = $DB->count_records_select('lock_db', 'owner IS NOT NULL AND expires >= :n', ['n' => time()]);
cli_writeln(sprintf("\ndeleted %s row(s) in %d batches, %.1fs",
    number_format($deleted), $rounds, microtime(true) - $started));
cli_writeln(sprintf("lock_db now:         %s", number_format($remaining)));
cli_writeln(sprintf("held right now:      %s%s", number_format($heldafter),
    $heldafter >= $held ? ' (intact)' : ' ** a held lock disappeared, investigate **'));
