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
 * Take non-credit activities out of the learner-facing gradebook.
 *
 * Two cleanups over grade_items, run together because they touch the same courses and the
 * same course totals:
 *
 * 1. SCORM package items are hidden from learners. They carry a completion-style score the
 *    learner is not assessed on, and showing it invites the reading that it is a mark.
 *
 * 2. Self-Assessment items that still carry weight are set to weight zero, as the sweep of
 *    2026-08-27 did. That sweep matched item names literally, so misspellings kept their
 *    weight - "Self Assessmemt" in course 983 is the one learners reported. Matching here
 *    is normalised, the way {@see local_cagrades\ca_items} matches CA names.
 *
 * Neither step alters a mark. Hiding is item-level and deliberately does not cascade, so the
 * per-learner flags local_cagrades\past_ca maintains are left exactly as they are.
 *
 * COLLAPSE GUARD: some courses carry no continuous assessment at all - their gradebook is
 * Self-Assessment quizzes and Reading-on-Screen lessons and nothing else. Zeroing there leaves
 * the course total made up entirely of reading-completion scores, which states something even
 * less true than today. Those courses are skipped and listed; the guard asks whether a real
 * assessment survives, not merely whether some weighted item does. This is the judgement the
 * 2026-08-27 sweep made when it set 38 courses aside, and it reproduces all 38.
 *
 * Dry run by default. Every field is journaled before it is touched, so --rollback can put it
 * back exactly.
 *
 * Usage (on an app pod):
 *   php local/cagrades/cli/tidy_learner_gradebook.php
 *   php local/cagrades/cli/tidy_learner_gradebook.php --only=scorm
 *   php local/cagrades/cli/tidy_learner_gradebook.php --execute --backup=/path/tidy.json
 *   php local/cagrades/cli/tidy_learner_gradebook.php --rollback=/path/tidy.json --execute
 *
 * @package   local_cagrades
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/gradelib.php');

use local_cagrades\ca_items;

/** @var string Journal format, so a rollback cannot be fed the wrong file. */
const LOCAL_CAGRADES_TIDY_JOURNAL = 'tidy_learner_gradebook';

[$options, $unrecognized] = cli_get_params([
    'execute' => false,
    'backup' => '',
    'rollback' => '',
    'only' => 'all',
    'course' => 0,
    'regrade-pending' => false,
    'help' => false,
], ['h' => 'help']);

if ($unrecognized) {
    cli_error('Unrecognised options: ' . implode(' ', $unrecognized));
}

if ($options['help']) {
    cli_writeln("Take non-credit activities out of the learner-facing gradebook.\n");
    cli_writeln('Options:');
    cli_writeln('  --execute            Apply the changes (otherwise dry run).');
    cli_writeln('  --backup=<file>      Where to journal the old values (required with --execute).');
    cli_writeln('  --rollback=<file>    Restore exactly the fields a previous run changed.');
    cli_writeln('  --only=scorm|sa|all  Which cleanup to run. Default all.');
    cli_writeln('  --course=<id>        Act on one course only, for spot checks.');
    cli_writeln('  --regrade-pending    Only recalculate courses flagged needsupdate, to finish');
    cli_writeln('                       a run that was interrupted. Changes nothing else.');
    cli_writeln('  -h, --help           This help.');
    exit(0);
}

$execute = !empty($options['execute']);
$only = (string) $options['only'];
$courseid = (int) $options['course'] ?: null;

// Finish the recalculation for courses an interrupted run left behind. Changes nothing else.
if (!empty($options['regrade-pending'])) {
    $pending = $DB->get_fieldset_sql(
        'SELECT DISTINCT courseid FROM {grade_items} WHERE needsupdate = 1 ORDER BY courseid');
    cli_writeln(sprintf('%s regrading %d course(s) flagged needsupdate.',
        $execute ? 'EXECUTING:' : 'DRY RUN:', count($pending)));
    foreach ($pending as $cid) {
        cli_writeln('  c' . $cid . ($execute ? '' : ' (dry run)'));
        if ($execute) {
            local_cagrades_tidy_regrade((int) $cid);
        }
    }
    cli_writeln($execute ? 'Done.' : 'Dry run - nothing changed. Re-run with --execute.');
    exit(0);
}

if (!in_array($only, ['all', 'scorm', 'sa'], true)) {
    cli_error('--only must be one of: scorm, sa, all.');
}

// ---------------------------------------------------------------- rollback

if ($options['rollback'] !== '') {
    $path = (string) $options['rollback'];
    if (!file_exists($path)) {
        cli_error("Cannot read the backup file {$path}.");
    }
    $journal = json_decode(trim((string) file_get_contents($path)), true);
    if (!is_array($journal) || ($journal['format'] ?? '') !== LOCAL_CAGRADES_TIDY_JOURNAL) {
        cli_error("The backup file {$path} is not a readable " . LOCAL_CAGRADES_TIDY_JOURNAL . ' journal.');
    }

    $items = $journal['items'] ?? [];
    cli_writeln(sprintf('%s restoring %d grade item(s) recorded in %s.',
        $execute ? 'EXECUTING:' : 'DRY RUN:', count($items), $path));

    if (!$execute) {
        cli_writeln('Dry run - nothing changed. Re-run with --execute to apply.');
        exit(0);
    }

    $courses = [];
    foreach ($items as $old) {
        $item = grade_item::fetch(['id' => (int) $old['id']]);
        if (!$item) {
            cli_writeln('  grade item ' . $old['id'] . ' is gone - skipped.');
            continue;
        }
        $item->hidden = $old['hidden'];
        $item->weightoverride = $old['weightoverride'];
        $item->aggregationcoef2 = $old['aggregationcoef2'];
        $item->update('local_cagrades');
        $courses[(int) $old['courseid']] = true;
    }
    foreach (array_keys($courses) as $cid) {
        local_cagrades_tidy_regrade($cid);
    }
    cli_writeln('Restored ' . count($items) . ' item(s) across ' . count($courses) . ' course(s).');
    exit(0);
}

// ---------------------------------------------------------------- plan

if ($execute && $options['backup'] === '') {
    cli_error('--backup=<file> is required with --execute: without it the run cannot be undone.');
}

$backuppath = (string) $options['backup'];
if ($execute && strpos($backuppath, '/tmp/') === 0) {
    // /tmp lives in the container, which a rollout can take away mid-run, and the journal
    // is the only way back. Write it where it will outlive the pod.
    cli_error('--backup must not be under /tmp: it would not survive the pod. '
        . 'Use a moodledata path, e.g. ' . $CFG->dataroot . '/tidy_learner_gradebook.json');
}
if ($execute && file_exists($backuppath)) {
    $existing = json_decode(trim((string) file_get_contents($backuppath)), true);
    if (!is_array($existing) || ($existing['format'] ?? '') !== LOCAL_CAGRADES_TIDY_JOURNAL) {
        cli_error("The backup file {$backuppath} is not a readable "
            . LOCAL_CAGRADES_TIDY_JOURNAL . ' journal - refusing to overwrite it.');
    }
}

$plan = [];
$skipped = [];

if ($only === 'all' || $only === 'scorm') {
    $plan = array_merge($plan, local_cagrades_tidy_scorm_plan($courseid));
}
if ($only === 'all' || $only === 'sa') {
    [$saplan, $skipped] = local_cagrades_tidy_selfassessment_plan($courseid);
    $plan = array_merge($plan, $saplan);
}

local_cagrades_tidy_report($plan, $skipped, $execute);

if (!$plan) {
    cli_writeln("\nNothing to do.");
    exit(0);
}

if (!$execute) {
    cli_writeln("\nDry run - nothing changed. Re-run with --execute --backup=<file> to apply.");
    exit(0);
}

// ---------------------------------------------------------------- apply

$journal = [
    'format' => LOCAL_CAGRADES_TIDY_JOURNAL,
    'started' => time(),
    'only' => $only,
    'items' => [],
];

// Grouped by course, and each course is regraded as soon as its own items are done.
// A run that dies part way - the pod it is exec'd into gets rolled, say - then leaves at
// most one course to finish rather than every course changed and none recalculated.
$bycourse = [];
foreach ($plan as $change) {
    $bycourse[$change['courseid']][] = $change;
}

$done = 0;
foreach ($bycourse as $cid => $changes) {
    foreach ($changes as $change) {
        $item = grade_item::fetch(['id' => $change['id']]);
        if (!$item) {
            cli_writeln('  grade item ' . $change['id'] . ' vanished between plan and apply - skipped.');
            continue;
        }

        // Journal before touching anything, so a half-finished run is still undoable.
        $journal['items'][] = [
            'id' => (int) $item->id,
            'courseid' => (int) $item->courseid,
            'itemname' => (string) $item->itemname,
            'hidden' => $item->hidden,
            'weightoverride' => $item->weightoverride,
            'aggregationcoef2' => $item->aggregationcoef2,
        ];
        file_put_contents($backuppath, json_encode($journal, JSON_PRETTY_PRINT));

        if ($change['action'] === 'hide') {
            // No cascade: the per-learner flags past_ca maintains are not ours to rewrite.
            $item->set_hidden(1, false);
        } else {
            $item->weightoverride = 1;
            $item->aggregationcoef2 = 0;
            $item->update('local_cagrades');
        }
        $done++;
    }
    local_cagrades_tidy_regrade($cid);
    cli_writeln(sprintf('  c%-6d %d item(s), regraded.', $cid, count($changes)));
}

$journal['finished'] = time();
file_put_contents($backuppath, json_encode($journal, JSON_PRETTY_PRINT));

cli_writeln("\nDone. {$done} item(s) changed across " . count($bycourse)
    . " course(s). Journal: {$backuppath}");

// ---------------------------------------------------------------- helpers

/**
 * Every SCORM grade item still visible to learners.
 *
 * @param int|null $courseid Restrict to one course.
 * @return array[] Plan entries.
 */
function local_cagrades_tidy_scorm_plan(?int $courseid): array {
    global $DB;

    $where = "itemmodule = 'scorm' AND hidden = 0";
    $params = [];
    if ($courseid) {
        $where .= ' AND courseid = :courseid';
        $params['courseid'] = $courseid;
    }

    $plan = [];
    foreach ($DB->get_records_select('grade_items', $where, $params, 'courseid') as $item) {
        $plan[] = [
            'id' => (int) $item->id,
            'courseid' => (int) $item->courseid,
            'action' => 'hide',
            'name' => (string) $item->itemname,
            'detail' => 'hidden 0 -> 1',
        ];
    }
    return $plan;
}

/**
 * Self-Assessment items that still carry weight, minus the courses that would collapse.
 *
 * @param int|null $courseid Restrict to one course.
 * @return array{0: array[], 1: array[]} [plan entries, skipped courses]
 */
function local_cagrades_tidy_selfassessment_plan(?int $courseid): array {
    global $DB;

    $where = "itemtype = 'mod' AND aggregationcoef2 > 0";
    $params = [];
    if ($courseid) {
        $where .= ' AND courseid = :courseid';
        $params['courseid'] = $courseid;
    }

    // Name matching is normalised rather than literal, which is the whole point: the items
    // still carrying weight are largely the ones whose spelling the last sweep did not predict.
    $candidates = [];
    foreach ($DB->get_records_select('grade_items', $where, $params, 'courseid') as $item) {
        if (local_cagrades_tidy_is_selfassessment((string) $item->itemname)) {
            $candidates[(int) $item->courseid][] = $item;
        }
    }

    $plan = [];
    $skipped = [];
    foreach ($candidates as $cid => $items) {
        if (!local_cagrades_tidy_assessment_survives($cid, array_map(fn($i) => (int) $i->id, $items))) {
            $skipped[] = [
                'courseid' => $cid,
                'name' => (string) $DB->get_field('course', 'fullname', ['id' => $cid]),
                'items' => count($items),
            ];
            continue;
        }
        foreach ($items as $item) {
            $plan[] = [
                'id' => (int) $item->id,
                'courseid' => $cid,
                'action' => 'weightzero',
                'name' => (string) $item->itemname,
                'detail' => sprintf('weight %.4f -> 0', (float) $item->aggregationcoef2),
            ];
        }
    }
    return [$plan, $skipped];
}

/**
 * Is this item name a Self-Assessment, however it happens to be spelled?
 *
 * @param string $itemname
 * @return bool
 */
function local_cagrades_tidy_is_selfassessment(string $itemname): bool {
    $normalised = strtolower(preg_replace('/[^a-z0-9]/i', '', $itemname));

    // "selfassessment" with the vowels and doubled letters people actually get wrong, e.g.
    // selfassessmemt, selfassesment, selfasssessment. Anything may follow (unit numbers,
    // "questions"), but the name has to start there, so "Reflective self assessment note"
    // is not swept by accident.
    return (bool) preg_match('/^self[a-z]*ass+e+s+[a-z]*/', $normalised);
}

/**
 * Whether a real assessment would still carry weight once these items go to zero.
 *
 * "Real" means continuous assessment: a numbered CA leaf, or the Continuous Assessment
 * category total that aggregates them. Reading-on-Screen lessons do not count - a course
 * total built only from those is not a grade, which is why the courses without any CA are
 * left alone rather than swept.
 *
 * @param int $courseid
 * @param int[] $zeroing Item ids about to lose their weight.
 * @return bool
 */
function local_cagrades_tidy_assessment_survives(int $courseid, array $zeroing): bool {
    global $DB;

    $where = ca_items::where_with_category_sql('gi');
    $sql = "SELECT gi.id
              FROM {grade_items} gi
             WHERE gi.courseid = :courseid AND gi.aggregationcoef2 > 0 AND {$where}";
    foreach ($DB->get_records_sql($sql, ['courseid' => $courseid]) as $item) {
        if (!in_array((int) $item->id, $zeroing, true)) {
            return true;
        }
    }
    return false;
}

/**
 * Recalculate a course's totals after its item weights changed.
 *
 * @param int $courseid
 * @return void
 */
function local_cagrades_tidy_regrade(int $courseid): void {
    $status = grade_regrade_final_grades($courseid);
    if (is_array($status)) {
        cli_writeln("  course {$courseid}: regrade reported errors: " . implode('; ', $status));
    }
}

/**
 * Print the plan, grouped by course, with the effect on each course total.
 *
 * @param array[] $plan
 * @param array[] $skipped
 * @param bool $execute
 * @return void
 */
function local_cagrades_tidy_report(array $plan, array $skipped, bool $execute): void {
    global $DB;

    $bycourse = [];
    foreach ($plan as $change) {
        $bycourse[$change['courseid']][] = $change;
    }

    cli_writeln(sprintf('%s %d grade item(s) across %d course(s).',
        $execute ? 'EXECUTING:' : 'DRY RUN:', count($plan), count($bycourse)));

    foreach ($bycourse as $cid => $changes) {
        $name = (string) $DB->get_field('course', 'fullname', ['id' => $cid]);
        $max = (float) $DB->get_field_select('grade_items', 'grademax',
            'courseid = ? AND itemtype = ?', [$cid, 'course']);
        cli_writeln(sprintf("\n  c%-6d %s (course total out of %s)", $cid, $name, $max));
        foreach ($changes as $change) {
            cli_writeln(sprintf('    #%-7d %-11s %-24s %s',
                $change['id'], $change['action'], $change['detail'], $change['name']));
        }
    }

    if ($skipped) {
        cli_writeln("\n  SKIPPED - these courses carry no continuous assessment, so zeroing would leave the
  course total made up of reading-completion scores alone:");
        foreach ($skipped as $skip) {
            cli_writeln(sprintf('    c%-6d %-55s (%d item(s) left alone)',
                $skip['courseid'], substr($skip['name'], 0, 55), $skip['items']));
        }
    }
}
