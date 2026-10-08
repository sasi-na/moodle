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
 * Put the Virtual Labs marking scheme into the gradebook.
 *
 * Each assessment section of a lab course offers two questions and only the higher one should count.
 * This builds the grade category tree that makes core do that arithmetic - "Highest grade" categories
 * for the best-of-two pairs, plain sums above them - and a calculated course total carrying the
 * 35-to-40 moderation floor. See {@see local_cagrades\lab_scheme} for the scheme itself.
 *
 * Courses whose sections do not match a known shape are reported and skipped, never guessed at.
 *
 * Dry run by default. Every course is journaled before it is touched, so --rollback can put it back.
 *
 * Usage (on an app pod):
 *   php local/cagrades/cli/apply_lab_scheme.php --labs
 *   php local/cagrades/cli/apply_lab_scheme.php --courses=1233 --execute --backup=/path/labs.json
 *   php local/cagrades/cli/apply_lab_scheme.php --rollback=/path/labs.json --execute
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

use local_cagrades\lab_scheme;

/** @var string The name this rollout books its one-writer lock under. */
const LOCAL_CAGRADES_LAB_RUN = 'apply_lab_scheme';

[$options, $unrecognized] = cli_get_params([
    'labs' => false,
    'courses' => '',
    'exclude-courses' => '',
    'execute' => false,
    'backup' => '',
    'rollback' => '',
    'resume-after' => 0,
    'limit' => 0,
    'show-total' => false,
    'reveal-totals' => false,
    'hide-totals' => false,
    'refresh-totals' => false,
    'force-unlock' => false,
    'help' => false,
], ['h' => 'help']);

if ($unrecognized) {
    cli_error('Unrecognised options: ' . implode(' ', $unrecognized));
}

if ($options['help']) {
    cli_writeln("Put the Virtual Labs marking scheme into the gradebook.\n");
    cli_writeln('Options:');
    cli_writeln('  --labs                   Every lab course: any course holding VPL activities,');
    cli_writeln('                           plus the Virtual Accounting Labs.');
    cli_writeln('  --courses=<ids>          Act on these course ids instead.');
    cli_writeln('  --exclude-courses=<ids>  Course ids to leave alone.');
    cli_writeln('  --execute                Apply the changes (otherwise dry run).');
    cli_writeln('  --backup=<file>          Where to journal the previous state (required with --execute).');
    cli_writeln('  --rollback=<file>        Undo a previous run from its backup file.');
    cli_writeln('  --resume-after=<id>      Skip courses up to and including this course id.');
    cli_writeln('  --limit=<n>              Stop after n courses, to run the sweep in batches.');
    cli_writeln('  --show-total             Leave the course total visible to learners. By default it');
    cli_writeln('                           is hidden, because the moderation floor would otherwise');
    cli_writeln('                           show a partially-marked learner as having passed.');
    cli_writeln('  --reveal-totals          Nothing else: reveal the course total on courses that');
    cli_writeln('                           already carry the scheme, for when marking is complete.');
    cli_writeln('  --hide-totals            The reverse: hide it again.');
    cli_writeln('  --refresh-totals         Re-apply the moderation formula and whole-mark display to');
    cli_writeln('                           courses that already carry the scheme, then re-verify.');
    cli_writeln('  --force-unlock           Clear a lock left behind by a killed run. Be sure');
    cli_writeln('                           no run is actually active before using it.');
    cli_writeln('  -h, --help               This help.');
    exit(0);
}

$execute = !empty($options['execute']);

// Recover from a killed run that could not release its lock. See local_cagrades\run_lock.
if (!empty($options['force-unlock'])) {
    local_cagrades_lab_force_unlock(LOCAL_CAGRADES_LAB_RUN, $execute);
    exit(0);
}

// Only ever one writer. Two runs sharing a backup file each hold their own copy of the journal in
// memory and rewrite the whole thing, so the second to write silently discards the first's entries -
// which is exactly what happened on 2026-07-31, costing five courses their rollback record while their
// grades were fine. $lock must stay in scope for the life of the process.
$lock = null;
if ($execute) {
    $lock = \local_cagrades\run_lock::acquire(LOCAL_CAGRADES_LAB_RUN);
    if (!$lock) {
        $holder = \local_cagrades\run_lock::describe(LOCAL_CAGRADES_LAB_RUN);
        $detail = $holder
            ? "\nExpires " . userdate($holder->expires) .
              ($holder->expired ? ' (already expired - it will be taken over)' : '')
            : '';
        cli_error("Another apply_lab_scheme run is already in progress.{$detail}\n" .
            "The journal cannot safely be written by two runs at once.\n" .
            'If that run was killed and cannot release its lock, clear it with --force-unlock --execute.');
    }
    \local_cagrades\run_lock::release_on_shutdown($lock);
}

if ($options['rollback'] !== '') {
    local_cagrades_lab_rollback((string) $options['rollback'], $execute);
    exit(0);
}

$courseids = local_cagrades_lab_courses($options);
if (!$courseids) {
    cli_error('No courses selected. Pass --labs or --courses.');
}

if (!empty($options['refresh-totals'])) {
    local_cagrades_lab_refresh_totals($courseids, $execute);
    exit(0);
}

if (!empty($options['reveal-totals']) || !empty($options['hide-totals'])) {
    if (!empty($options['reveal-totals']) && !empty($options['hide-totals'])) {
        cli_error('Pass one of --reveal-totals or --hide-totals, not both.');
    }
    local_cagrades_lab_set_total_visibility($courseids, !empty($options['hide-totals']), $execute);
    exit(0);
}

// The course total carries the moderation floor, which lifts a learner to the pass mark. Applied
// while marking is still in progress that makes a partially-marked learner read as having passed, so
// the total stays hidden from learners until someone says marking is done.
$hidetotal = empty($options['show-total']);

if ($execute && $options['backup'] === '') {
    cli_error('--execute needs --backup=<file> so the change can be undone.');
}

// Several runs may share one backup file when the sweep is batched; carry the journal forward rather
// than overwriting it, or the earlier batches become unrollbackable.
$journal = [];
if ($execute) {
    $journal = local_cagrades_lab_read_backup((string) $options['backup']);
    if ($journal) {
        cli_writeln('Backup file already holds ' . count($journal) . ' course(s); appending to it.');
    }
}
$journalled = array_column($journal, 'courseid');

cli_writeln(($execute ? 'EXECUTING' : 'DRY RUN') . ' over ' . count($courseids) . ' lab course(s).');
cli_writeln('');

$buckets = ['applied' => [], 'already' => [], 'refused' => [], 'failed' => []];
$done = 0;
$courseid = null;

foreach ($courseids as $courseid) {
    $course = get_course($courseid);
    $label = $course->shortname . " (id {$courseid})";

    $analysis = lab_scheme::analyse_course($courseid);
    if ($analysis->problems) {
        $buckets['refused'][] = $label . "\n        - " . implode("\n        - ", $analysis->problems);
        continue;
    }

    if (local_cagrades_lab_already_done($courseid) || in_array($courseid, $journalled, true)) {
        $buckets['already'][] = $label;
        continue;
    }

    if (!$execute) {
        $buckets['applied'][] = $label . ' - ' . local_cagrades_lab_summarise($analysis) .
            ($hidetotal ? '; total hidden from learners' : '; total left visible');
        $done++;
        if ($options['limit'] && $done >= (int) $options['limit']) {
            break;
        }
        continue;
    }

    try {
        $entry = local_cagrades_lab_apply($courseid, $analysis, $journal, (string) $options['backup'],
            $hidetotal);

        $checked = 0;
        $mismatches = local_cagrades_lab_reconcile($courseid, $analysis, $checked);
        if ($mismatches) {
            cli_error("Reconciliation FAILED for {$label}: " . count($mismatches) .
                ' learner(s) disagree, first: ' . reset($mismatches) .
                "\nBackup written to " . $options['backup'] . ' - roll back before continuing.');
        }

        $buckets['applied'][] = $label . ' - course total ' .
            lab_scheme::num($entry['coursemaxbefore']) . ' -> ' .
            lab_scheme::num($entry['coursemaxafter']) . ', ' . $checked . ' learner(s) verified';
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

cli_writeln('');
foreach ([
    'applied' => $execute ? 'Changed' : 'Would change',
    'already' => 'Already carried the scheme (left alone)',
    'refused' => 'REFUSED - the sections do not match the scheme',
    'failed' => 'FAILED',
] as $key => $heading) {
    cli_writeln($heading . ': ' . count($buckets[$key]));
    if ($key === 'applied' && $execute) {
        continue;
    }
    foreach ($buckets[$key] as $line) {
        cli_writeln('  - ' . $line);
    }
}

if ($execute) {
    cli_writeln('');
    cli_writeln('Backup: ' . $options['backup']);
    cli_writeln('Last course id looked at: ' . $courseid .
        ' (pass --resume-after=' . $courseid . ' to continue).');
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
function local_cagrades_lab_force_unlock(string $name, bool $execute): void {
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
 * The lab courses to act on.
 *
 * A lab course is any course holding a VPL activity, plus the Virtual Accounting Labs, which are
 * built out of assignments instead.
 *
 * @param array $options CLI options
 * @return int[] course ids, ascending
 */
function local_cagrades_lab_courses(array $options): array {
    global $DB;

    $explicit = local_cagrades_lab_ids((string) $options['courses']);
    $exclude = local_cagrades_lab_ids((string) $options['exclude-courses']);

    $params = [];
    $where = [];

    if ($explicit) {
        [$insql, $inparams] = $DB->get_in_or_equal($explicit, SQL_PARAMS_NAMED, 'cid');
        $where[] = "c.id {$insql}";
        $params += $inparams;
    } else if (!empty($options['labs'])) {
        $where[] = "(EXISTS (SELECT 1 FROM {course_modules} cm
                              JOIN {modules} m ON m.id = cm.module
                             WHERE cm.course = c.id AND m.name = 'vpl'
                               AND cm.deletioninprogress = 0)
                     OR " . $DB->sql_like($DB->sql_compare_text('c.fullname'), ':acc', false) . ')';
        $params['acc'] = '%accounting lab%';
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

    return array_keys($DB->get_records_sql(
        'SELECT c.id FROM {course} c WHERE ' . implode(' AND ', $where) . ' ORDER BY c.id', $params));
}

/**
 * Parse a comma-separated list of ids.
 *
 * @param string $raw
 * @return int[]
 */
function local_cagrades_lab_ids(string $raw): array {
    $ids = [];
    foreach (preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) as $part) {
        if ((int) $part > 0) {
            $ids[(int) $part] = (int) $part;
        }
    }
    return array_values($ids);
}

/**
 * Whether a course already carries the scheme.
 *
 * @param int $courseid
 * @return bool
 */
function local_cagrades_lab_already_done(int $courseid): bool {
    global $DB;

    return $DB->record_exists_select('grade_items',
        'courseid = :courseid AND idnumber = :idnumber',
        ['courseid' => $courseid, 'idnumber' => lab_scheme::IDNUMBERS['ca1']]);
}

/**
 * A one-line description of what would be built.
 *
 * @param stdClass $analysis
 * @return string
 */
function local_cagrades_lab_summarise(stdClass $analysis): string {
    $parts = [];
    foreach ($analysis->components as $key => $component) {
        if ($component->kind === 'sum') {
            $parts[] = $key . ' sum(' . count($component->items) . ' q)';
        } else {
            $bits = [];
            foreach ($component->parts as $part) {
                $bits[] = 'best of ' . count($part->items) . '/' . lab_scheme::num((float) $part->outof);
            }
            $parts[] = $key . ' ' . implode(' + ', $bits);
        }
    }

    return $analysis->flavour . ': ' . implode(', ', $parts) .
        ($analysis->excluded ? '; ' . count($analysis->excluded) . ' item(s) excluded' : '');
}

/**
 * Build the category tree for one course and set the calculated total.
 *
 * The journal is appended and flushed at each step that cannot be undone by inspection alone, so an
 * exception partway through still leaves a backup describing what did happen.
 *
 * @param int $courseid
 * @param stdClass $analysis from lab_scheme::analyse_course()
 * @param array $journal by reference
 * @param string $backuppath
 * @param bool $hidetotal hide the course total from learners
 * @return array the journal entry
 */
function local_cagrades_lab_apply(int $courseid, stdClass $analysis, array &$journal,
        string $backuppath, bool $hidetotal = true): array {
    global $DB;

    $coursemaxbefore = local_cagrades_lab_course_maximum($courseid);
    $courseitem = grade_item::fetch(['courseid' => $courseid, 'itemtype' => 'course']);

    // Everything that has to be put back on a rollback, captured before anything moves.
    $moved = [];
    foreach ($analysis->components as $component) {
        foreach (local_cagrades_lab_component_items($component) as $item) {
            $moved[] = ['itemid' => (int) $item->id, 'categoryid' => (int) $item->categoryid];
        }
    }
    $reweighted = [];
    foreach ($analysis->excluded as $item) {
        $full = $DB->get_record('grade_items', ['id' => $item->id],
            'id, weightoverride, aggregationcoef2');
        $reweighted[] = [
            'itemid' => (int) $full->id,
            'weightoverride' => (int) $full->weightoverride,
            'aggregationcoef2' => (float) $full->aggregationcoef2,
        ];
    }

    $entry = [
        'courseid' => $courseid,
        'flavour' => $analysis->flavour,
        'categoryids' => [],
        'moved' => $moved,
        'reweighted' => $reweighted,
        'coursecalculation' => $courseitem ? (string) $courseitem->calculation : '',
        'coursehidden' => $courseitem ? (int) $courseitem->hidden : 0,
        'coursemaxbefore' => $coursemaxbefore,
        'coursemaxafter' => null,
        'timeapplied' => time(),
    ];
    $index = count($journal);
    $journal[$index] = $entry;
    local_cagrades_lab_write_backup($backuppath, $journal);

    // Component categories, and the best-of-two sub-categories beneath them.
    foreach ($analysis->components as $key => $component) {
        $category = local_cagrades_lab_make_category($courseid, local_cagrades_lab_name($key, $component),
            GRADE_AGGREGATE_SUM, (float) $component->outof, null,
            lab_scheme::IDNUMBERS[$key] ?? null);
        $entry['categoryids'][] = (int) $category->id;
        $journal[$index] = $entry;
        local_cagrades_lab_write_backup($backuppath, $journal);

        if ($component->kind === 'sum') {
            // Every question counts, so they hang straight off the component category.
            foreach ($component->items as $item) {
                local_cagrades_lab_move($item, (int) $category->id);
            }
            continue;
        }

        foreach ($component->parts as $part) {
            $sub = local_cagrades_lab_make_category($courseid,
                get_string('labsection', 'local_cagrades', $part->label),
                GRADE_AGGREGATE_MAX, (float) $part->outof, (int) $category->id);
            $entry['categoryids'][] = (int) $sub->id;
            $journal[$index] = $entry;
            local_cagrades_lab_write_backup($backuppath, $journal);

            foreach ($part->items as $item) {
                local_cagrades_lab_move($item, (int) $sub->id);
            }
        }
    }

    // Anything outside the scheme - hands-on practice, demo questions, live sessions - keeps its
    // marks but must not reach the total.
    foreach ($analysis->excluded as $item) {
        $gradeitem = grade_item::fetch(['id' => $item->id]);
        if ($gradeitem) {
            $gradeitem->weightoverride = 1;
            $gradeitem->aggregationcoef2 = 0;
            $gradeitem->update('local_cagrades');
        }
    }

    // The course total carries the moderation floor.
    $courseitem = grade_item::fetch(['courseid' => $courseid, 'itemtype' => 'course']);
    local_cagrades_lab_set_total($courseitem);

    if ($hidetotal) {
        // Not cascading: only the total is hidden, so the CA and practice totals keep whatever
        // visibility the course already gave them.
        $courseitem->set_hidden(1, false);
    }

    $status = grade_regrade_final_grades($courseid);
    if (is_array($status)) {
        throw new moodle_exception('error', 'core', '', null,
            'regrade reported errors: ' . implode('; ', $status));
    }

    $entry['coursemaxafter'] = local_cagrades_lab_course_maximum($courseid);
    $journal[$index] = $entry;
    local_cagrades_lab_write_backup($backuppath, $journal);

    return $entry;
}

/**
 * Point a course total at the scheme: range, whole marks, and the moderation formula.
 *
 * Whole marks because faculty enter fractional ones (12.86, 6.67) and a result of "37.53" means
 * nothing to an exam board. The formula rounds the value; decimals = 0 stops the gradebook adding the
 * ".00" back on when it displays it.
 *
 * @param grade_item $courseitem
 * @return void
 */
function local_cagrades_lab_set_total(grade_item $courseitem): void {
    $courseitem->grademin = 0;
    $courseitem->grademax = lab_scheme::TOTAL;
    $courseitem->gradetype = GRADE_TYPE_VALUE;
    $courseitem->decimals = 0;
    $courseitem->update('local_cagrades');
    $courseitem->set_calculation(lab_scheme::total_formula());
}

/**
 * Every grade item a component covers.
 *
 * @param stdClass $component
 * @return stdClass[]
 */
function local_cagrades_lab_component_items(stdClass $component): array {
    if ($component->kind === 'sum') {
        return $component->items;
    }
    $items = [];
    foreach ($component->parts as $part) {
        $items = array_merge($items, $part->items);
    }
    return $items;
}

/**
 * The gradebook name for a component category.
 *
 * @param string $key component key, e.g. "ca2"
 * @param stdClass $component
 * @return string
 */
function local_cagrades_lab_name(string $key, stdClass $component): string {
    if ($key === 'practice') {
        return get_string('labpractice', 'local_cagrades');
    }
    return get_string('labca', 'local_cagrades', (int) substr($key, 2));
}

/**
 * Create a grade category and pin its range.
 *
 * The maximum is set by hand either way, but it means two different things. For the "Highest grade"
 * sub-categories it is authoritative: grade_category::auto_update_max() returns early for every
 * non-Natural aggregation, so nothing recomputes it. For the Natural component categories core does
 * recompute it from the children - and arrives at the same 25, since a CA's two sub-categories are
 * 10 and 15 and the practice questions add to 25. Setting it up front just means the category reads
 * correctly in the gradebook before the questions have been moved in.
 *
 * @param int $courseid
 * @param string $name
 * @param int $aggregation
 * @param float $outof
 * @param int|null $parentid
 * @param string|null $idnumber
 * @return grade_category
 */
function local_cagrades_lab_make_category(int $courseid, string $name, int $aggregation, float $outof,
        ?int $parentid = null, ?string $idnumber = null): grade_category {
    $category = new grade_category(['courseid' => $courseid], false);
    $category->fullname = $name;
    $category->aggregation = $aggregation;
    $category->droplow = 0;
    $category->keephigh = 0;
    // Off, so an unattempted question counts as zero: max(0, x) is still x, and a learner who
    // answered only one of a pair still scores it.
    $category->aggregateonlygraded = 0;
    $category->aggregateoutcomes = 0;
    if ($parentid !== null) {
        $category->parent = $parentid;
    }

    $categoryid = $category->insert('local_cagrades');
    if (!$categoryid) {
        throw new moodle_exception('error', 'core', '', null, "could not create category \"{$name}\"");
    }

    $category = grade_category::fetch(['id' => $categoryid]);
    $item = $category->load_grade_item();
    $item->gradetype = GRADE_TYPE_VALUE;
    $item->grademin = 0;
    $item->grademax = $outof;
    if ($idnumber !== null) {
        $item->idnumber = $idnumber;
    }
    $item->update('local_cagrades');

    return $category;
}

/**
 * Move a grade item into a category.
 *
 * @param stdClass $item
 * @param int $categoryid
 * @return void
 */
function local_cagrades_lab_move(stdClass $item, int $categoryid): void {
    $gradeitem = grade_item::fetch(['id' => $item->id]);
    if (!$gradeitem) {
        throw new moodle_exception('error', 'core', '', null, "grade item {$item->id} vanished");
    }
    if (!$gradeitem->set_parent($categoryid)) {
        throw new moodle_exception('error', 'core', '', null,
            "could not move grade item {$item->id} into category {$categoryid}");
    }
}

/**
 * The course total's maximum grade.
 *
 * @param int $courseid
 * @return float
 */
function local_cagrades_lab_course_maximum(int $courseid): float {
    global $DB;

    return (float) $DB->get_field('grade_items', 'grademax',
        ['courseid' => $courseid, 'itemtype' => 'course']);
}

/**
 * Check every learner's stored course total against the scheme.
 *
 * The whole point is that nobody works these out by hand any more, so it is worth proving that what
 * core stored is what the scheme says - including the moderation floor.
 *
 * @param int $courseid
 * @param stdClass $analysis
 * @param int $checked set to how many learners were compared
 * @return string[] descriptions of the disagreements, empty when all agree
 */
function local_cagrades_lab_reconcile(int $courseid, stdClass $analysis, int &$checked = 0): array {
    global $DB;

    $itemids = [];
    foreach ($analysis->components as $component) {
        foreach (local_cagrades_lab_component_items($component) as $item) {
            $itemids[] = (int) $item->id;
        }
    }
    if (!$itemids) {
        return ['the course holds no scheme questions'];
    }

    [$insql, $params] = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED, 'gi');
    $grades = [];
    $rs = $DB->get_recordset_select('grade_grades', "itemid {$insql}", $params, '',
        'id, itemid, userid, finalgrade');
    foreach ($rs as $row) {
        $grades[(int) $row->userid][(int) $row->itemid] =
            $row->finalgrade === null ? null : (float) $row->finalgrade;
    }
    $rs->close();

    $courseitemid = $DB->get_field('grade_items', 'id',
        ['courseid' => $courseid, 'itemtype' => 'course']);
    $stored = $DB->get_records_menu('grade_grades', ['itemid' => $courseitemid], '',
        'userid, finalgrade');

    $mismatches = [];
    $checked = 0;
    foreach ($grades as $userid => $usergrades) {
        $expected = lab_scheme::final_score($analysis->components, $usergrades);
        $actual = array_key_exists($userid, $stored) && $stored[$userid] !== null
            ? (float) $stored[$userid] : null;
        $checked++;

        if ($expected === null && $actual === null) {
            continue;
        }
        if ($expected !== null && $actual !== null && abs($expected - $actual) < 0.00001) {
            continue;
        }

        $mismatches[] = "user {$userid}: scheme says " . var_export($expected, true) .
            ', gradebook says ' . var_export($actual, true);
        if (count($mismatches) >= 10) {
            break;
        }
    }

    return $mismatches;
}

/**
 * Re-apply the course total's formula and display to courses that already carry the scheme.
 *
 * For when the scheme itself changes - the rounding rule, say - so already-converted courses can be
 * brought into line without dismantling and rebuilding their whole category tree. Re-verifies every
 * learner afterwards and stops on the first disagreement, exactly as applying does.
 *
 * @param int[] $courseids
 * @param bool $execute
 * @return void
 */
function local_cagrades_lab_refresh_totals(array $courseids, bool $execute): void {
    cli_writeln(($execute ? 'EXECUTING' : 'DRY RUN') . ' refresh over ' . count($courseids) .
        ' course(s).');
    cli_writeln('  formula: ' . lab_scheme::total_formula());
    cli_writeln('');

    $refreshed = 0;
    $skipped = 0;
    foreach ($courseids as $courseid) {
        $course = get_course($courseid);
        if (!local_cagrades_lab_already_done($courseid)) {
            $skipped++;
            continue;
        }
        $analysis = lab_scheme::analyse_course($courseid);
        if ($analysis->problems) {
            cli_writeln("  {$course->shortname} (id {$courseid}) - SKIPPED, no longer matches: " .
                implode('; ', $analysis->problems));
            $skipped++;
            continue;
        }

        cli_writeln("  {$course->shortname} (id {$courseid}) - refresh");
        if (!$execute) {
            continue;
        }

        $courseitem = grade_item::fetch(['courseid' => $courseid, 'itemtype' => 'course']);
        local_cagrades_lab_set_total($courseitem);
        $status = grade_regrade_final_grades($courseid);
        if (is_array($status)) {
            cli_error("regrade reported errors on {$course->shortname}: " . implode('; ', $status));
        }

        $checked = 0;
        $mismatches = local_cagrades_lab_reconcile($courseid, $analysis, $checked);
        if ($mismatches) {
            cli_error("Reconciliation FAILED for {$course->shortname}: " . count($mismatches) .
                ' learner(s) disagree, first: ' . reset($mismatches));
        }
        cli_writeln("      {$checked} learner(s) verified");
        $refreshed++;
    }

    cli_writeln('');
    cli_writeln('Refreshed: ' . ($execute ? $refreshed : 'none (dry run)') .
        "; skipped: {$skipped}");
}

/**
 * Show or hide the course total on courses that already carry the scheme.
 *
 * Revealing is for when marking is finished and the final score - moderation floor included - is meant
 * to be the published result. Hiding is the reverse, and is what applying the scheme does by default.
 *
 * Only touches courses the scheme has actually been applied to, so revealing cannot accidentally
 * publish a total that is still a raw sum of every question attempted.
 *
 * @param int[] $courseids
 * @param bool $hide true to hide, false to reveal
 * @param bool $execute
 * @return void
 */
function local_cagrades_lab_set_total_visibility(array $courseids, bool $hide, bool $execute): void {
    $verb = $hide ? 'hide' : 'reveal';
    $state = $hide ? 'hidden' : 'visible';
    cli_writeln(($execute ? 'EXECUTING' : 'DRY RUN') . " {$verb} over " . count($courseids) .
        ' course(s).');

    $changed = 0;
    $already = 0;
    $skipped = 0;
    foreach ($courseids as $courseid) {
        $course = get_course($courseid);
        $courseitem = local_cagrades_lab_already_done($courseid)
            ? grade_item::fetch(['courseid' => $courseid, 'itemtype' => 'course'])
            : null;
        if (!$courseitem) {
            $skipped++;
            continue;
        }
        if ((bool) $courseitem->hidden === $hide) {
            $already++;
            continue;
        }

        cli_writeln("  {$course->shortname} (id {$courseid}) - {$verb} the course total");
        if ($execute) {
            // Not cascading, so the CA and practice totals keep their own visibility.
            $courseitem->set_hidden($hide ? 1 : 0, false);
            $changed++;
        }
    }

    cli_writeln('');
    cli_writeln("Now {$state}: " . ($execute ? $changed : 'none (dry run)') .
        "; already {$state}: {$already}; skipped (scheme not applied): {$skipped}");
    if (!$execute) {
        cli_writeln('Dry run - nothing changed. Re-run with --execute to apply.');
    }
}

/**
 * Write the journal, pretty-printed so it can be read and diffed.
 *
 * @param string $path
 * @param array $journal
 * @return void
 */
function local_cagrades_lab_write_backup(string $path, array $journal): void {
    $json = json_encode($journal, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (file_put_contents($path, $json) === false) {
        cli_error("Could not write the backup file {$path}.");
    }
}

/**
 * Read an existing journal, or an empty one.
 *
 * @param string $path
 * @param bool $required
 * @return array
 */
function local_cagrades_lab_read_backup(string $path, bool $required = false): array {
    if (!file_exists($path)) {
        if ($required) {
            cli_error("Cannot read the backup file {$path}.");
        }
        return [];
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
 * Undo a previous run: put the questions back, restore weights and the total, delete the categories.
 *
 * @param string $path
 * @param bool $execute
 * @return void
 */
function local_cagrades_lab_rollback(string $path, bool $execute): void {
    $journal = local_cagrades_lab_read_backup($path, true);
    if (!$journal) {
        cli_error("The backup file {$path} holds no courses - nothing to roll back.");
    }

    cli_writeln(($execute ? 'EXECUTING' : 'DRY RUN') . ' rollback of ' . count($journal) . ' course(s).');

    foreach ($journal as $entry) {
        $courseid = (int) $entry['courseid'];
        cli_writeln("  course {$courseid}: " . count($entry['moved']) . ' question(s) back, ' .
            count($entry['reweighted']) . ' weight(s) restored, ' .
            count($entry['categoryids']) . ' category(ies) deleted');
        if (!$execute) {
            continue;
        }

        // The course total first, so the tree can be dismantled without it recalculating.
        $courseitem = grade_item::fetch(['courseid' => $courseid, 'itemtype' => 'course']);
        if ($courseitem) {
            $courseitem->set_calculation((string) $entry['coursecalculation']);
            $courseitem->set_hidden((int) ($entry['coursehidden'] ?? 0), false);
        }

        foreach ($entry['moved'] as $move) {
            $gradeitem = grade_item::fetch(['id' => (int) $move['itemid']]);
            if ($gradeitem) {
                $gradeitem->set_parent((int) $move['categoryid']);
            }
        }
        foreach ($entry['reweighted'] as $weight) {
            $gradeitem = grade_item::fetch(['id' => (int) $weight['itemid']]);
            if ($gradeitem) {
                $gradeitem->weightoverride = (int) $weight['weightoverride'];
                $gradeitem->aggregationcoef2 = (float) $weight['aggregationcoef2'];
                $gradeitem->update('local_cagrades');
            }
        }
        // Deepest first, so a parent is never deleted before its children have left.
        $categoryids = $entry['categoryids'];
        rsort($categoryids);
        foreach ($categoryids as $categoryid) {
            $category = grade_category::fetch(['id' => (int) $categoryid]);
            if ($category) {
                $category->delete('local_cagrades');
            }
        }

        $status = grade_regrade_final_grades($courseid);
        if (is_array($status)) {
            cli_writeln('    regrade reported errors: ' . implode('; ', $status));
        }
    }

    cli_writeln($execute ? 'Rollback done.' : 'Dry run - nothing changed.');
}
