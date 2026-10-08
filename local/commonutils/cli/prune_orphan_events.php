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
 * Delete calendar events whose course no longer exists.
 *
 * WHY THIS EXISTS. Deleting a course does not always take its {event} rows with
 * it, and the leftovers are invisible -- the calendar cannot render an event
 * whose course is gone, so nobody notices until an upgrade step touches the
 * table. mod_feedback's 2024100701 step does exactly that: it loads every
 * feedback open/close event and calls calendar_event->delete(), which resolves
 * the course context first and throws dml_missing_record_exception on the first
 * orphan. The upgrade then aborts, leaving the site on new code with an
 * un-upgraded database.
 *
 * That is what happened adopting learn-usdc-prod on 2026-09-01: 5,464 of 6,064
 * event rows referenced 488 deleted courses, and admin/cli/upgrade.php failed
 * five times in CI before giving up.
 *
 * This is NOT a workaround for that upgrade step -- deleting these rows is
 * precisely what the step is trying to do. It just gets there without asking
 * for a context that cannot exist.
 *
 * Run --dry-run first. It prints what would go and touches nothing.
 *
 *   php local/commonutils/cli/prune_orphan_events.php --dry-run
 *   php local/commonutils/cli/prune_orphan_events.php --backup=/tmp/events.json
 *   php local/commonutils/cli/prune_orphan_events.php --execute --backup=/tmp/events.json
 *
 * @package   local_commonutils
 * @copyright 2026 onlinejain.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognised) = cli_get_params([
    'help' => false,
    'dry-run' => false,
    'execute' => false,
    'backup' => '',
    'batch' => 1000,
], ['h' => 'help']);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognised)));
}

if ($options['help'] || (!$options['dry-run'] && !$options['execute'])) {
    cli_writeln("Delete calendar events whose course no longer exists.

Options:
  --dry-run           Report what would be deleted. Default when neither flag is given.
  --execute           Actually delete. Refuses to run without --backup.
  --backup=PATH       Write the full rows to PATH as JSON before deleting.
                      Required with --execute; optional with --dry-run.
  --batch=N           Rows per DELETE (default 1000). Keeps Galera write-sets small.
  -h, --help          This help.
");
    exit(0);
}

/** The orphan test, used identically for counting, dumping and deleting. */
const ORPHAN_SQL = "SELECT e.%s
                      FROM {event} e
                     WHERE e.courseid <> 0
                       AND NOT EXISTS (SELECT 1 FROM {course} c WHERE c.id = e.courseid)
                  ORDER BY e.id";

$total = $DB->count_records('event');
$orphans = $DB->count_records_sql(
    "SELECT COUNT(*) FROM {event} e
      WHERE e.courseid <> 0
        AND NOT EXISTS (SELECT 1 FROM {course} c WHERE c.id = e.courseid)");

cli_writeln(sprintf("{event} rows: %d total, %d orphaned (%.1f%%)",
    $total, $orphans, $total ? $orphans / $total * 100 : 0));

if (!$orphans) {
    cli_writeln("Nothing to do.");
    exit(0);
}

$bymod = $DB->get_records_sql(
    "SELECT COALESCE(NULLIF(e.modulename, ''), '(none)') AS modulename, COUNT(*) AS n
       FROM {event} e
      WHERE e.courseid <> 0
        AND NOT EXISTS (SELECT 1 FROM {course} c WHERE c.id = e.courseid)
   GROUP BY COALESCE(NULLIF(e.modulename, ''), '(none)')
   ORDER BY n DESC");
foreach ($bymod as $r) {
    cli_writeln(sprintf("  %-14s %6d", $r->modulename, $r->n));
}
$courses = $DB->count_records_sql(
    "SELECT COUNT(DISTINCT e.courseid) FROM {event} e
      WHERE e.courseid <> 0
        AND NOT EXISTS (SELECT 1 FROM {course} c WHERE c.id = e.courseid)");
cli_writeln(sprintf("across %d deleted courses", $courses));

// The backup is written before anything is deleted, and --execute refuses to
// proceed without one. These rows are unreachable, not worthless: they are the
// only remaining record of what those deleted courses had scheduled.
if ($options['backup'] !== '') {
    $rows = $DB->get_records_sql(sprintf(ORPHAN_SQL, '*'));
    $json = json_encode(array_values(array_map(fn($r) => (array) $r, $rows)), JSON_PRETTY_PRINT);
    if ($json === false || file_put_contents($options['backup'], $json) === false) {
        cli_error("Could not write the backup to {$options['backup']} -- refusing to continue.");
    }
    // Read it back rather than trusting the write: a truncated backup is worse
    // than no backup, because it looks like one.
    $check = json_decode((string) file_get_contents($options['backup']), true);
    if (!is_array($check) || count($check) !== count($rows)) {
        cli_error("Backup at {$options['backup']} did not read back intact -- refusing to continue.");
    }
    cli_writeln(sprintf("Backed up %d rows to %s (verified by re-reading it).",
        count($check), $options['backup']));
}

if (!$options['execute']) {
    cli_writeln("\nDry run -- nothing deleted. Re-run with --execute --backup=PATH.");
    exit(0);
}

if ($options['backup'] === '') {
    cli_error("--execute requires --backup=PATH.");
}

$batch = max(1, (int) $options['batch']);
$deleted = 0;
while (true) {
    // Re-selected each round against the live orphan test rather than working
    // through one list captured up front: if a course were restored midway, its
    // events must stop qualifying and survive.
    $ids = $DB->get_fieldset_sql(sprintf(ORPHAN_SQL, 'id'), null, 0, $batch);
    if (!$ids) {
        break;
    }
    list($insql, $params) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'id');
    $DB->delete_records_select('event', "id $insql", $params);
    $deleted += count($ids);
    cli_writeln(sprintf("  deleted %d / %d", $deleted, $orphans));
    usleep(20000);
}

$left = $DB->count_records_sql(
    "SELECT COUNT(*) FROM {event} e
      WHERE e.courseid <> 0
        AND NOT EXISTS (SELECT 1 FROM {course} c WHERE c.id = e.courseid)");
cli_writeln(sprintf("\nDone. {event}: %d -> %d. Orphans remaining: %d.",
    $total, $DB->count_records('event'), $left));
exit($left === 0 ? 0 : 1);
