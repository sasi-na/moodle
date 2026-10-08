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
 * Put each course's continuous assessments into a grade category that scores the best two of three.
 *
 * Creates a "Continuous Assessment" grade category per course, set to Mean of grades with "drop the
 * lowest 1" and "aggregate only non-empty grades" off, its maximum being whatever the course's CAs are
 * each marked out of. Core then computes (best two) / 2 automatically and shows it in the grader
 * report, the learner's gradebook and every grade export, with no marks entered by hand.
 *
 * Dropping the lowest rather than keeping the highest two is deliberate - the two are the same
 * arithmetic for three CAs, but this site forces grade_keephigh to 0. See the note where the category
 * is created.
 *
 * Courses whose CA data cannot carry the rule are reported and skipped rather than guessed at:
 * mixed CA maxima, duplicate CA quizzes, fewer than three CAs, or a non-zero CA minimum.
 *
 * Dry run by default. Every executed course is journaled to the backup file first, so --rollback can
 * put it back exactly as it was.
 *
 * Usage (on an app pod):
 *   php local/cagrades/cli/apply_ca_category.php --categories=27,2,17,174,239
 *   php local/cagrades/cli/apply_ca_category.php --categories=27 --execute --backup=/path/ca.json
 *   php local/cagrades/cli/apply_ca_category.php --rollback=/path/ca.json --execute
 *
 * @package   local_cagrades
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/lib.php');

use local_cagrades\ca_items;

/** @var string The name this rollout books its one-writer lock under. */
const LOCAL_CAGRADES_RUN = 'apply_ca_category';

[$options, $unrecognized] = cli_get_params([
    'categories' => '',
    'courses' => '',
    'exclude-courses' => '',
    'execute' => false,
    'backup' => '',
    'rollback' => '',
    'resume-after' => 0,
    'limit' => 0,
    'force-unlock' => false,
    'help' => false,
], ['h' => 'help']);

if ($unrecognized) {
    cli_error('Unrecognised options: ' . implode(' ', $unrecognized));
}

if ($options['help']) {
    cli_writeln("Group each course's continuous assessments into a best-two-of-three grade category.\n");
    cli_writeln('Options:');
    cli_writeln('  --categories=<ids>       Course category ids to sweep, including subcategories.');
    cli_writeln('  --courses=<ids>          Course ids to act on, instead of --categories.');
    cli_writeln('  --exclude-courses=<ids>  Course ids to leave alone.');
    cli_writeln('  --execute                Apply the changes (otherwise dry run).');
    cli_writeln('  --backup=<file>          Where to journal the previous state (required with --execute).');
    cli_writeln('  --rollback=<file>        Undo a previous run from its backup file.');
    cli_writeln('  --resume-after=<id>      Skip courses up to and including this course id.');
    cli_writeln('  --limit=<n>              Stop after n courses, to run the sweep in batches.');
    cli_writeln('  --force-unlock           Clear a lock left behind by a killed run. Be sure');
    cli_writeln('                           no run is actually active before using it.');
    cli_writeln('  -h, --help               This help.');
    exit(0);
}

$execute = !empty($options['execute']);

// Recover from a killed run that could not release its lock. See local_cagrades\run_lock.
if (!empty($options['force-unlock'])) {
    local_cagrades_force_unlock(LOCAL_CAGRADES_RUN, $execute);
    exit(0);
}

// Only ever one writer. The journal is read once at startup, held in memory and rewritten whole, so two
// overlapping runs each discard the other's entries - which cost the sibling lab rollout five courses'
// rollback records on 2026-07-31. $lock must stay in scope for the life of the process.
$lock = null;
if ($execute) {
    $lock = \local_cagrades\run_lock::acquire(LOCAL_CAGRADES_RUN);
    if (!$lock) {
        $holder = \local_cagrades\run_lock::describe(LOCAL_CAGRADES_RUN);
        $detail = '';
        if ($holder) {
            $detail = "\nHeld since before " . userdate($holder->expires - \local_cagrades\run_lock::MAXLIFETIME) .
                ', expires ' . userdate($holder->expires) .
                ($holder->expired ? ' (already expired - it will be taken over)' : '');
        }
        cli_error("Another apply_ca_category run is already in progress.{$detail}\n" .
            "The journal cannot safely be written by two runs at once.\n" .
            'If that run was killed and cannot release its lock, clear it with --force-unlock --execute.');
    }
    \local_cagrades\run_lock::release_on_shutdown($lock);
}

if ($options['rollback'] !== '') {
    local_cagrades_rollback((string) $options['rollback'], $execute);
    exit(0);
}

// ---------------------------------------------------------------------------------------------
// Work out which courses to look at.
// ---------------------------------------------------------------------------------------------

$courseids = local_cagrades_resolve_courses($options);
if (!$courseids) {
    cli_error('No courses selected. Pass --categories or --courses.');
}

if ($execute && $options['backup'] === '') {
    cli_error('--execute needs --backup=<file> so the change can be undone.');
}

// The sweep is meant to be run in batches, which means several runs sharing one backup file. Carry
// the existing journal forward rather than overwriting it, or the earlier batches would quietly
// become unrollbackable.
$journal = [];
if ($execute) {
    $journal = local_cagrades_read_backup((string) $options['backup']);
    if ($journal) {
        cli_writeln('Backup file already holds ' . count($journal) . ' course(s); appending to it.');
    }
}
$journalled = array_column($journal, 'courseid');

cli_writeln(($execute ? 'EXECUTING' : 'DRY RUN') . ' over ' . count($courseids) . ' course(s) holding CA items.');
cli_writeln('');

$buckets = [
    'applied' => [],
    'already' => [],
    'mixedmax' => [],
    'duplicates' => [],
    'incomplete' => [],
    'nonzeromin' => [],
    'failed' => [],
];
$done = 0;

foreach ($courseids as $courseid) {
    $course = get_course($courseid);
    $label = $course->shortname . " (id {$courseid})";

    $bynumber = ca_items::for_course($courseid);

    // Refuse anything the rule cannot be defended on, and say which course and why.
    if (count($bynumber) < ca_items::CA_COUNT) {
        $buckets['incomplete'][] = $label . ' - only ' . count($bynumber) . ' CA(s)';
        continue;
    }
    if (ca_items::has_duplicates($bynumber)) {
        $buckets['duplicates'][] = $label . ' - ' . local_cagrades_describe($bynumber);
        continue;
    }
    $maxima = ca_items::distinct_maxima($bynumber);
    if (count($maxima) > 1) {
        $buckets['mixedmax'][] = $label . ' - maxima ' . implode(', ', $maxima);
        continue;
    }
    $outof = (float) reset($maxima);
    if ($outof <= 0) {
        $buckets['incomplete'][] = $label . ' - CA maximum is zero';
        continue;
    }
    $nonzeromin = false;
    foreach ($bynumber as $items) {
        foreach ($items as $item) {
            if ((float) $item->grademin != 0.0) {
                $nonzeromin = true;
            }
        }
    }
    if ($nonzeromin) {
        // ca_items::final_mark() normalises against the maximum alone; a non-zero minimum would
        // make the report and the gradebook disagree, so leave the course for a human.
        $buckets['nonzeromin'][] = $label;
        continue;
    }

    if (ca_items::get_category($courseid) || in_array($courseid, $journalled, true)) {
        $buckets['already'][] = $label;
        continue;
    }

    if (!$execute) {
        $buckets['applied'][] = $label . ' - would create category out of ' . local_cagrades_num($outof);
        $done++;
        if ($options['limit'] && $done >= (int) $options['limit']) {
            break;
        }
        continue;
    }

    try {
        // The journal is written as the change happens, not after it: a course that fails halfway
        // through - category created, only some items moved - still has to be rollbackable.
        $entry = local_cagrades_apply($courseid, $bynumber, $outof, $journal,
            (string) $options['backup']);

        $mismatches = local_cagrades_reconcile($courseid, $bynumber, $outof);
        if ($mismatches) {
            cli_error("Reconciliation FAILED for {$label}: " . count($mismatches) . ' learner(s) disagree, ' .
                'first: ' . reset($mismatches) . "\nBackup written to " . $options['backup'] .
                ' - roll back before continuing.');
        }

        $buckets['applied'][] = $label . ' - category ' . $entry['categoryid'] . ' out of ' .
            local_cagrades_num($outof) . ', course total ' . local_cagrades_num($entry['coursemaxbefore']) .
            ' -> ' . local_cagrades_num($entry['coursemaxafter']);
        cli_writeln('  done: ' . end($buckets['applied']));
    } catch (Throwable $e) {
        $buckets['failed'][] = $label . ' - ' . $e->getMessage();
        cli_writeln('  FAILED: ' . end($buckets['failed']));
    }

    $done++;
    if ($options['limit'] && $done >= (int) $options['limit']) {
        break;
    }
}

// ---------------------------------------------------------------------------------------------
// Report.
// ---------------------------------------------------------------------------------------------

$headings = [
    'applied' => $execute ? 'Changed' : 'Would change',
    'already' => 'Already had the category (left alone)',
    'mixedmax' => 'SKIPPED - CA maxima differ within the course',
    'duplicates' => 'SKIPPED - duplicate CA quiz present',
    'incomplete' => 'SKIPPED - fewer than three CAs',
    'nonzeromin' => 'SKIPPED - a CA has a non-zero minimum grade',
    'failed' => 'FAILED',
];

cli_writeln('');
foreach ($headings as $key => $heading) {
    $list = $buckets[$key];
    cli_writeln($heading . ': ' . count($list));
    if ($key === 'applied' || $key === 'already') {
        continue;
    }
    foreach ($list as $line) {
        cli_writeln('  - ' . $line);
    }
}

if ($execute) {
    cli_writeln('');
    cli_writeln('Backup: ' . $options['backup']);
    if ($courseids) {
        cli_writeln('Last course id looked at: ' . $courseid . ' (pass --resume-after=' . $courseid . ' to continue).');
    }
} else {
    cli_writeln('');
    cli_writeln('Dry run - nothing changed. Re-run with --execute --backup=<file> to apply.');
}

exit(empty($buckets['failed']) ? 0 : 1);

// ---------------------------------------------------------------------------------------------
// Helpers.
// ---------------------------------------------------------------------------------------------

/**
 * Clear a lock left behind by a run that was killed before it could release one.
 *
 * Reports what it finds either way, so a lock genuinely held by a working run is visible rather than
 * silently stolen. It cannot tell a dead owner from a live one - that judgement is the operator's.
 *
 * @param string $name the run's lock name
 * @param bool $execute
 * @return void
 */
function local_cagrades_force_unlock(string $name, bool $execute): void {
    if (!\local_cagrades\run_lock::is_row_based()) {
        cli_writeln('This site does not use database-row locks, so a lock cannot outlive its owner.');
        cli_writeln('There is nothing to force.');
        return;
    }

    $holder = \local_cagrades\run_lock::describe($name);
    if (!$holder) {
        cli_writeln("No run is holding the {$name} lock - nothing to clear.");
        return;
    }

    cli_writeln("The {$name} lock is held.");
    cli_writeln('  expires: ' . userdate($holder->expires) .
        ($holder->expired ? ' (already expired - the next run would take it over anyway)' : ''));
    cli_writeln('  owner:   ' . $holder->owner);
    cli_writeln('');
    cli_writeln('Make sure no run is actually active before clearing this. A live run whose lock is');
    cli_writeln('taken away can be joined by a second one, and the two will overwrite each other\'s journal.');

    if (!$execute) {
        cli_writeln('');
        cli_writeln('Dry run - nothing changed. Re-run with --force-unlock --execute to clear it.');
        return;
    }

    cli_writeln('');
    cli_writeln(\local_cagrades\run_lock::force_release($name)
        ? 'Lock cleared.' : 'Nothing to clear - it was released while we looked.');
}

/**
 * The courses to act on: those holding CA items within the requested scope.
 *
 * @param array $options CLI options
 * @return int[] course ids, ascending
 */
function local_cagrades_resolve_courses(array $options): array {
    global $DB;

    $explicit = local_cagrades_ids((string) $options['courses']);
    $exclude = local_cagrades_ids((string) $options['exclude-courses']);
    $categories = local_cagrades_ids((string) $options['categories']);

    $params = [];
    $where = ['1 = 1'];

    if ($explicit) {
        [$insql, $inparams] = $DB->get_in_or_equal($explicit, SQL_PARAMS_NAMED, 'cid');
        $where[] = "c.id {$insql}";
        $params += $inparams;
    } else if ($categories) {
        // Include subcategories: a batch category holds programmes, which hold semesters.
        $paths = [];
        foreach ($categories as $i => $catid) {
            $paths[] = "cc.path = :pe{$i} OR " . $DB->sql_like('cc.path', ":pl{$i}");
            $params["pe{$i}"] = '/' . $catid;
            $params["pl{$i}"] = '%/' . $catid . '/%';
        }
        $where[] = '(' . implode(' OR ', $paths) . ')';
    } else {
        return [];
    }

    if ($exclude) {
        [$notsql, $notparams] = $DB->get_in_or_equal($exclude, SQL_PARAMS_NAMED, 'xid', false);
        $where[] = "c.id {$notsql}";
        $params += $notparams;
    }

    if ($options['resume-after']) {
        $where[] = 'c.id > :resume';
        $params['resume'] = (int) $options['resume-after'];
    }

    $sql = "SELECT DISTINCT c.id
              FROM {course} c
              JOIN {course_categories} cc ON cc.id = c.category
              JOIN {grade_items} gi ON gi.courseid = c.id
             WHERE " . implode(' AND ', $where) . "
               AND " . ca_items::where_sql('gi') . "
          ORDER BY c.id";

    return array_keys($DB->get_records_sql($sql, $params));
}

/**
 * Parse a comma-separated list of ids.
 *
 * @param string $raw
 * @return int[]
 */
function local_cagrades_ids(string $raw): array {
    $ids = [];
    foreach (preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) as $part) {
        if ((int) $part > 0) {
            $ids[(int) $part] = (int) $part;
        }
    }
    return array_values($ids);
}

/**
 * A one-line description of a course's CA items, for the skip report.
 *
 * @param array[] $bynumber output of ca_items::for_course()
 * @return string
 */
function local_cagrades_describe(array $bynumber): string {
    $parts = [];
    foreach ($bynumber as $canum => $items) {
        foreach ($items as $item) {
            $parts[] = "CA{$canum} \"{$item->itemname}\" /" . local_cagrades_num((float) $item->grademax);
        }
    }
    return implode(', ', $parts);
}

/**
 * Trim trailing zeros off a grade for printing.
 *
 * @param float $number
 * @return string
 */
function local_cagrades_num(float $number): string {
    return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
}

/**
 * Create the category for one course and move its CAs into it.
 *
 * The journal is appended to and flushed to disk at each step that cannot be undone by inspection
 * alone - as soon as the category exists, and again once the items have moved - so an exception
 * partway through still leaves a backup describing everything that did happen.
 *
 * @param int $courseid
 * @param array[] $bynumber output of ca_items::for_course()
 * @param float $outof what the CAs are each marked out of
 * @param array $journal journal to append this course's entry to, by reference
 * @param string $backuppath where to flush the journal
 * @return array journal entry describing what changed
 */
function local_cagrades_apply(int $courseid, array $bynumber, float $outof,
        array &$journal, string $backuppath): array {
    $coursemaxbefore = local_cagrades_course_maximum($courseid);

    // Where each CA item sits now, captured before anything moves.
    $moved = [];
    foreach ($bynumber as $items) {
        foreach ($items as $item) {
            $moved[] = ['itemid' => (int) $item->id, 'categoryid' => (int) $item->categoryid];
        }
    }

    $category = new grade_category(['courseid' => $courseid], false);
    $category->fullname = ca_items::CATEGORY_NAME;
    $category->aggregation = GRADE_AGGREGATE_MEAN;
    // Drop the lowest, not keep the highest. For exactly three CAs the two are arithmetically the same
    // thing, but this site FORCES grade_keephigh to 0 (grade_keephigh_flag has bit 1 set), and
    // grade_category::apply_forced_settings() - called from insert() and update() - would silently
    // reset keephigh and leave the category averaging all three CAs. grade_droplow is not forced.
    $category->droplow = ca_items::CA_COUNT - ca_items::CA_KEEP;
    $category->keephigh = 0;
    // Off, so that an unsat CA counts as zero and the divisor is always two.
    $category->aggregateonlygraded = 0;
    $category->aggregateoutcomes = 0;
    $category->hidden = 0;

    $categoryid = $category->insert('local_cagrades');
    if (!$categoryid) {
        throw new moodle_exception('error', 'core', '', null, 'could not create the grade category');
    }

    // The category now exists and must be recorded before anything else can fail.
    $entry = [
        'courseid' => $courseid,
        'categoryid' => (int) $categoryid,
        'outof' => $outof,
        'moved' => $moved,
        'coursemaxbefore' => $coursemaxbefore,
        'coursemaxafter' => null,
        'timeapplied' => time(),
    ];
    $index = count($journal);
    $journal[$index] = $entry;
    local_cagrades_write_backup($backuppath, $journal);

    // Never trust that a category kept the settings it was given: the site can force any of them, and
    // a forced setting is applied silently. Getting this wrong produces plausible-looking marks, so it
    // is worth failing on the spot rather than finding out from the reconciliation.
    local_cagrades_assert_category_settings($categoryid);

    // The category's own grade item is what carries "out of 30". Safe to set by hand precisely
    // because the aggregation is not Natural: grade_category::auto_update_max() returns early for
    // every other aggregation, so nothing recomputes it from the children.
    local_cagrades_set_category_maximum($categoryid, $outof);

    foreach ($bynumber as $items) {
        foreach ($items as $item) {
            $gradeitem = grade_item::fetch(['id' => $item->id]);
            if (!$gradeitem) {
                throw new moodle_exception('error', 'core', '', null, "grade item {$item->id} vanished");
            }
            if (!$gradeitem->set_parent($categoryid)) {
                throw new moodle_exception('error', 'core', '', null,
                    "could not move grade item {$item->id} into the category");
            }
        }
    }

    // Re-assert the maximum: moving items in reset the category item's aggregation fields.
    local_cagrades_set_category_maximum($categoryid, $outof);

    // Returns true, or an array of error strings keyed by grade item id.
    $status = grade_regrade_final_grades($courseid);
    if (is_array($status)) {
        throw new moodle_exception('error', 'core', '', null,
            'regrade reported errors: ' . implode('; ', $status));
    }

    $entry['coursemaxafter'] = local_cagrades_course_maximum($courseid);
    $journal[$index] = $entry;
    local_cagrades_write_backup($backuppath, $journal);

    return $entry;
}

/**
 * Check that a freshly created category really carries the settings the rule needs.
 *
 * grade_category::apply_forced_settings() overwrites whatever the caller asked for with any site
 * setting whose _flag has bit 1 set, and says nothing about it. That is how a category asking for
 * "keep the highest 2" ended up averaging all three CAs.
 *
 * @param int $categoryid
 * @return void
 */
function local_cagrades_assert_category_settings(int $categoryid): void {
    $expected = [
        'aggregation' => GRADE_AGGREGATE_MEAN,
        'droplow' => ca_items::CA_COUNT - ca_items::CA_KEEP,
        'keephigh' => 0,
        'aggregateonlygraded' => 0,
    ];

    $category = grade_category::fetch(['id' => $categoryid]);
    $wrong = [];
    foreach ($expected as $field => $want) {
        $got = (int) $category->$field;
        if ($got !== (int) $want) {
            $wrong[] = "{$field}: asked for {$want}, got {$got}";
        }
    }

    if ($wrong) {
        throw new moodle_exception('error', 'core', '', null,
            'the site overrode the category settings (' . implode('; ', $wrong) .
            ') - check the forced grade settings, e.g. grade_keephigh_flag / grade_droplow_flag');
    }
}

/**
 * Set a category grade item's range to 0..$outof.
 *
 * @param int $categoryid
 * @param float $outof
 * @return void
 */
function local_cagrades_set_category_maximum(int $categoryid, float $outof): void {
    $category = grade_category::fetch(['id' => $categoryid]);
    $item = $category->load_grade_item();
    $item->gradetype = GRADE_TYPE_VALUE;
    $item->grademin = 0;
    $item->grademax = $outof;
    $item->update('local_cagrades');
}

/**
 * The course total's maximum grade, for showing the effect on it.
 *
 * @param int $courseid
 * @return float
 */
function local_cagrades_course_maximum(int $courseid): float {
    global $DB;

    return (float) $DB->get_field('grade_items', 'grademax',
        ['courseid' => $courseid, 'itemtype' => 'course']);
}

/**
 * Check the gradebook's category total against the rule, for every learner in the course.
 *
 * The point of the whole exercise is that nobody computes this by hand any more, so it is worth
 * proving that what core stored is what the rule says - and it also proves the report's own
 * PHP-computed column can never print something different from the learner's gradebook.
 *
 * @param int $courseid
 * @param array[] $bynumber output of ca_items::for_course()
 * @param float $outof
 * @return string[] descriptions of the disagreements, empty when all agree
 */
function local_cagrades_reconcile(int $courseid, array $bynumber, float $outof): array {
    global $DB;

    $category = ca_items::get_category($courseid);
    if (!$category) {
        return ['the category is missing'];
    }
    $categoryitemid = $DB->get_field('grade_items', 'id',
        ['courseid' => $courseid, 'itemtype' => 'category', 'iteminstance' => $category->id]);
    if (!$categoryitemid) {
        return ['the category has no grade item'];
    }

    $itemids = [];
    $maxima = [];
    foreach ($bynumber as $canum => $items) {
        foreach ($items as $item) {
            $itemids[(int) $item->id] = $canum;
            $maxima[$canum] = (float) $item->grademax;
        }
    }

    [$insql, $params] = $DB->get_in_or_equal(array_keys($itemids), SQL_PARAMS_NAMED, 'gi');
    $grades = [];
    $rs = $DB->get_recordset_select('grade_grades', "itemid {$insql}", $params,
        '', 'id, itemid, userid, finalgrade');
    foreach ($rs as $row) {
        $canum = $itemids[(int) $row->itemid];
        $grades[(int) $row->userid][$canum] = $row->finalgrade === null ? null : (float) $row->finalgrade;
    }
    $rs->close();

    $stored = $DB->get_records_menu('grade_grades', ['itemid' => $categoryitemid], '', 'userid, finalgrade');

    $mismatches = [];
    foreach ($grades as $userid => $usergrades) {
        $expected = ca_items::final_mark($usergrades, $maxima, $outof);
        $actual = array_key_exists($userid, $stored) && $stored[$userid] !== null
            ? (float) $stored[$userid] : null;

        if ($expected === null && $actual === null) {
            continue;
        }
        // Both sides are floats binned to five decimals by the gradebook, so compare with a
        // tolerance rather than exactly.
        if ($expected !== null && $actual !== null && abs($expected - $actual) < 0.00001) {
            continue;
        }

        $mismatches[] = "user {$userid}: rule says " . var_export($expected, true) .
            ', gradebook says ' . var_export($actual, true);
        if (count($mismatches) >= 10) {
            break;
        }
    }

    return $mismatches;
}

/**
 * Write the journal, pretty-printed so it can be read and diffed.
 *
 * @param string $path
 * @param array $journal
 * @return void
 */
function local_cagrades_write_backup(string $path, array $journal): void {
    $json = json_encode($journal, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (file_put_contents($path, $json) === false) {
        cli_error("Could not write the backup file {$path}.");
    }
}

/**
 * Read an existing journal, or an empty one if the file is not there yet.
 *
 * Refuses to carry on with an unreadable file rather than overwriting it: whatever it holds is the
 * only record of how to undo an earlier batch.
 *
 * @param string $path
 * @param bool $required whether the file has to exist
 * @return array
 */
function local_cagrades_read_backup(string $path, bool $required = false): array {
    if (!file_exists($path)) {
        if ($required) {
            cli_error("Cannot read the backup file {$path}.");
        }
        return [];
    }
    if (!is_readable($path)) {
        cli_error("The backup file {$path} exists but cannot be read.");
    }
    $contents = trim((string) file_get_contents($path));
    if ($contents === '') {
        return [];
    }
    $journal = json_decode($contents, true);
    if (!is_array($journal)) {
        cli_error("The backup file {$path} is not readable JSON - refusing to overwrite it.");
    }

    return $journal;
}

/**
 * Undo a previous run: move the CAs back where they were and delete the categories.
 *
 * @param string $path backup file written by an earlier --execute run
 * @param bool $execute
 * @return void
 */
function local_cagrades_rollback(string $path, bool $execute): void {
    $journal = local_cagrades_read_backup($path, true);
    if (!$journal) {
        cli_error("The backup file {$path} holds no courses - nothing to roll back.");
    }

    cli_writeln(($execute ? 'EXECUTING' : 'DRY RUN') . ' rollback of ' . count($journal) . ' course(s).');

    foreach ($journal as $entry) {
        $courseid = (int) $entry['courseid'];
        $categoryid = (int) $entry['categoryid'];
        cli_writeln("  course {$courseid}: move " . count($entry['moved']) .
            " item(s) back, delete category {$categoryid}");
        if (!$execute) {
            continue;
        }

        foreach ($entry['moved'] as $move) {
            $gradeitem = grade_item::fetch(['id' => (int) $move['itemid']]);
            if ($gradeitem) {
                $gradeitem->set_parent((int) $move['categoryid']);
            }
        }
        $category = grade_category::fetch(['id' => $categoryid]);
        if ($category) {
            $category->delete('local_cagrades');
        }
        $status = grade_regrade_final_grades($courseid);
        if (is_array($status)) {
            cli_writeln('    regrade reported errors: ' . implode('; ', $status));
        }
    }

    cli_writeln($execute ? 'Rollback done.' : 'Dry run - nothing changed.');
}
