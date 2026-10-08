<?php
// Release task claims left behind by cron pods that died mid-task, so adhoc
// processing cannot be wedged by a dead worker. Idempotent and silent when there
// is nothing to do.
//
// WHY THIS EXISTS
//
// A task is claimed by writing timestarted/pid/hostname onto its task_adhoc or
// task_scheduled row and taking a lock. If the pod is killed between those two
// points and the task finishing, nothing clears the row: the claim is counted
// against $CFG->task_adhoc_concurrency_limit forever after.
//
// That limit is 1 on this site, so ONE dead claim stops all adhoc processing. On
// 2026-09-01 a cron pod died holding \core\task\send_login_notifications and the
// queue froze for 18 hours behind it -- 5,606 tasks, including 1,549 forum
// notifications, plus every async backup, which is what made restorefile.php sit
// on "process pending" indefinitely.
//
// CORE ALREADY TRIES TO DO THIS, AND CANNOT HERE. \core\task\manager::cleanup_metadata()
// walks the running tasks and clears any whose lock it can acquire, on the sound
// reasoning that an acquirable lock means the holder is gone. But this site sets
//
//     $CFG->lock_factory = '\core\lock\db_record_lock_factory';
//
// because PXC (Galera) blocks GET_LOCK() in ENFORCING mode. A db_record lock is a
// row in lock_db carrying a 24-hour expiry, and it stays held for that full 24
// hours whether or not the owner still exists -- so cleanup_metadata() is locked
// out of exactly the rows it needs to fix. Worse, \core\task\task_lock_cleanup_task
// runs once a day (00:16), so the real worst case is expiry PLUS up to a day: the
// 2026-09-01 freeze would not have cleared until 2 Sept 00:16, ~29 hours in.
//
// So this cannot be lock-based, and it cannot be a Moodle scheduled task either --
// a scheduled task inherits the same lock problem. It is age-based and runs from
// the adhoc CronJob, before the task runner.
//
// TWO THINGS HAVE TO BE CLEARED, and the second is easy to miss. The claim row is
// what get_next_adhoc_task() counts against the concurrency limit when choosing a
// task, so a stale row makes even `adhoc_task.php --ignorelimits` report "0 adhoc
// tasks found". The runner-slot lock is what run_adhoc_tasks() checks before it
// gets that far, and is what produces "Concurrency limit reached". Clearing only
// one of them leaves the queue stuck.
//
// THE AGE THRESHOLD IS THE ONLY THING PROTECTING A LIVE TASK. A claim younger than
// the threshold is never touched, so pick it comfortably above the longest task
// that legitimately runs here. $CFG->task_adhoc_max_runtime bounds the runner
// loop, not one task, and a large course backup can exceed it, so the default is
// hours rather than minutes.

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params([
    'help' => false,
    'minage' => 6 * HOURSECS,
    'dry-run' => false,
], ['h' => 'help']);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'core_admin', implode("\n  ", $unrecognised)));
}

if ($options['help']) {
    cli_writeln("Release task claims whose worker died, so adhoc processing is not wedged.

Options:
  --minage=<seconds>  Only claims older than this are released. Default 6 hours.
                      Must exceed the longest task that legitimately runs here.
  --dry-run           Report what would be released, change nothing.
  -h, --help          This help.
");
    exit(0);
}

// db_record_lock_factory's default lifetime, which is what \core\cron's
// get_lock($resourcekey, 0) calls leave on a runner-slot row. Used to work out when a
// slot was taken: expires - this = taken.
const LOCAL_USERAUDIT_CRON_LOCK_LIFETIME = 86400;

$minage = (int)$options['minage'];
$dryrun = !empty($options['dry-run']);

if ($minage < HOURSECS) {
    cli_error("--minage must be at least " . HOURSECS . " seconds; a shorter window risks killing a running task.");
}

$cutoff = time() - $minage;
$released = 0;

foreach (['adhoc', 'scheduled'] as $type) {
    $table = 'task_' . $type;
    $stale = $DB->get_records_select($table, 'timestarted IS NOT NULL AND timestarted < ?', [$cutoff], 'timestarted ASC');

    foreach ($stale as $record) {
        $age = format_time(time() - $record->timestarted);
        mtrace("Stale $type claim: {$record->classname} (id {$record->id}) held by "
            . ($record->hostname ?? 'unknown') . " pid " . ($record->pid ?? '?') . " for $age");

        if ($dryrun) {
            mtrace("  dry run: would release");
            continue;
        }

        // Clear the claim so the concurrency count drops and the task is runnable.
        $DB->update_record($table, (object)[
            'id' => $record->id,
            'timestarted' => null,
            'pid' => null,
            'hostname' => null,
        ]);

        // Free the lock row too. Leaving it held would keep core's own
        // cleanup_metadata() blocked and, for scheduled tasks, stop the task being
        // picked up again until the 24-hour expiry. Nulling owner/expires is how
        // db_record_lock_factory represents a free lock -- the row is reused by
        // resourcekey, so it must not be deleted.
        $key = ($type === 'adhoc') ? 'adhoc_' . $record->id : $record->classname;
        $DB->set_field_select('lock_db', 'owner', null, 'resourcekey = ?', ['cron_' . $key]);
        $DB->set_field_select('lock_db', 'expires', null, 'resourcekey = ?', ['cron_' . $key]);

        mtrace("  released");
        $released++;
    }
}

// Free stale runner-SLOT locks. These are the primary gate and are easy to miss:
// \core\cron::run_adhoc_tasks() does not count rows to enforce
// task_adhoc_concurrency_limit, it takes a lock named adhoc_task_runner_<n> for n
// in 0..limit-1 and bails with "Concurrency limit reached" if every slot is taken.
// A pod killed while holding slot 0 therefore stops all adhoc processing for the
// full 24-hour lock lifetime, no matter how many task rows have been cleared.
//
// A slot lock is only safe to free when no runner is alive to own it, and lock_db
// carries no owner->pid mapping to check. Two independent tests, and BOTH must pass:
//
//  1. The lock's own age, which is the direct question and is asked first. \core\cron
//     takes these locks through get_lock($key, 0), so they carry
//     db_record_lock_factory's default 86400s lifetime and `expires - 86400` is the
//     moment the slot was taken. A live runner's lock is minutes old; the lock left by
//     a killed pod is hours old.
//  2. No adhoc task claimed within --minage. A working runner claims a task almost
//     immediately, and its whole keep-alive window is 900s -- far shorter than the
//     3600s --minage floor -- so "a runner is alive but has claimed nothing for an
//     hour" is not a state that can exist.
//
// THIS TEST WAS PREVIOUSLY UNREACHABLE, which is worth recording because the symptom
// looked exactly like the script working. It read
//
//     $latestclaim = max(MAX(timestarted) task_adhoc, MAX(timestarted) task_scheduled);
//     if ($latestclaim && $latestclaim < $cutoff) {
//
// and both halves were wrong. task_scheduled is claimed every few minutes by the
// healthy 5-minute cron, so $latestclaim was always recent and the comparison always
// false -- and a scheduled-task claim says nothing about whether an ADHOC runner is
// alive. The `$latestclaim &&` guard then failed a second time in the one state that
// matters: once the stale claims have been cleared, MAX(timestarted) on task_adhoc is
// NULL, which is precisely when the orphaned slot should be freed.
//
// Result on 2026-09-08: a slot lock orphaned at 10:30 was still held six hours later
// with 964 tasks queued, and every adhoc run in between logged "Concurrency limit
// reached" while this script reported success. It had to be freed by hand.
$latestadhocclaim = (int)$DB->get_field_sql('SELECT MAX(timestarted) FROM {task_adhoc}');
$nothingclaiming = ($latestadhocclaim === 0) || ($latestadhocclaim < $cutoff);

$limit = (int)get_config('core', 'task_adhoc_concurrency_limit');
for ($slot = 0; $slot < max($limit, 1); $slot++) {
    $resourcekey = 'cron_adhoc_task_runner_' . $slot;
    $lockrow = $DB->get_record_select('lock_db', 'resourcekey = ? AND owner IS NOT NULL', [$resourcekey]);
    if (!$lockrow) {
        continue;
    }

    $taken = $lockrow->expires ? ((int)$lockrow->expires - LOCAL_USERAUDIT_CRON_LOCK_LIFETIME) : 0;
    $age = $taken ? format_time(time() - $taken) : 'unknown';

    if ($taken && $taken > $cutoff) {
        mtrace("Runner slot lock $resourcekey held for only $age - leaving it alone.");
        continue;
    }
    if (!$nothingclaiming) {
        mtrace("Runner slot lock $resourcekey is $age old, but an adhoc task was claimed at "
            . userdate($latestadhocclaim) . " - a runner may be alive, leaving it alone.");
        continue;
    }

    mtrace("Stale runner slot lock: $resourcekey (owner {$lockrow->owner}, held for $age, expires "
        . ($lockrow->expires ? userdate($lockrow->expires) : 'never') . ")");
    if ($dryrun) {
        mtrace("  dry run: would free");
        continue;
    }
    $DB->execute('UPDATE {lock_db} SET owner = NULL, expires = NULL WHERE id = ?', [$lockrow->id]);
    mtrace("  freed");
    $released++;
}

if (!$released && !$dryrun) {
    // Silent success is the normal case; say nothing worth grepping.
    exit(0);
}

mtrace($dryrun ? "Dry run complete." : "Released $released stale task claim(s).");
