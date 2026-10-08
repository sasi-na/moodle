<?php
/**
 * Purge the adhoc task backlog carried in from a production import.
 *
 * IIITD's AWS box ran with main cron disabled, so adhoc tasks accumulated for
 * months without ever running. The dump brings all of them across. The moment
 * cron is enabled on the new platform, Moodle works through the queue and acts
 * on every one -- including tens of thousands of "new device sign-in" security
 * alerts dated months earlier.
 *
 * That is not hypothetical: on 2026-09-08 this exact backlog sent 7,991
 * notifications to 456 real students in under three hours.
 *
 * WHAT IS DELETED, AND WHY
 *
 *   send_login_notifications   stale security alerts for sign-ins that happened
 *                              months ago. Acting on them now is pure noise and
 *                              alarming noise at that.
 *   convert_submission         pre-renders submission PDFs. Dropping them costs
 *                              nothing: the PDF is generated on demand when a
 *                              marker opens the submission.
 *   content_notification_task  "new content available" mails for content that is
 *                              no longer new.
 *   send_schedule              a scheduled report delivery, long past its date.
 *
 * WHAT IS KEPT, DELIBERATELY
 *
 *   course_delete_modules      modules sit at deletioninprogress=1 until this
 *                              runs. Purging it strands them permanently --
 *                              they vanish from the course but never finish
 *                              deleting. These MUST run.
 *   quiz_statistics recalculate, refresh_mod_calendar_events,
 *   build_installed_themes     cheap, idempotent, and produce no user-visible
 *                              messages.
 *
 * Usage:
 *   php purge-adhoc-backlog.php              report only
 *   php purge-adhoc-backlog.php --apply      delete
 *
 * @package   local_iiitdcutover
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
$root = getenv('MOODLE_ROOT') ?: '/var/www/html';
require($root . '/config.php');
require_once($CFG->libdir . '/clilib.php');

[$options] = cli_get_params(['apply' => false, 'help' => false], ['h' => 'help']);

if ($options['help']) {
    cli_writeln("Purge the imported adhoc task backlog.\n  --apply   actually delete (default is a report)\n");
    exit(0);
}

// Matched on a LIKE so a class rename or a namespace change does not silently
// stop matching and leave the backlog in place.
$purge = [
    '%send_login_notification%'   => 'stale sign-in security alerts',
    '%convert_submission%'        => 'submission PDF pre-render (regenerates on demand)',
    '%content_notification_task%' => 'stale "new content" notifications',
    '%send_schedule%'             => 'stale scheduled report delivery',
];

$total = $DB->count_records('task_adhoc');
cli_writeln("adhoc tasks queued: $total\n");

$deleted = 0;
foreach ($purge as $pattern => $why) {
    $count = $DB->count_records_select('task_adhoc', $DB->sql_like('classname', '?'), [$pattern]);
    if (!$count) {
        continue;
    }
    cli_writeln(sprintf('  %-9s %-30s %6d   %s',
        $options['apply'] ? 'DELETE' : 'would', trim($pattern, '%'), $count, $why));
    if ($options['apply']) {
        $DB->delete_records_select('task_adhoc', $DB->sql_like('classname', '?'), [$pattern]);
    }
    $deleted += $count;
}

cli_writeln('');
foreach ($DB->get_records_sql(
        'SELECT classname, COUNT(*) c FROM {task_adhoc} GROUP BY classname ORDER BY c DESC') as $row) {
    $kept = true;
    foreach (array_keys($purge) as $pattern) {
        if (fnmatch(str_replace('%', '*', $pattern), $row->classname)) {
            $kept = false;
        }
    }
    if ($kept || $options['apply']) {
        cli_writeln(sprintf('  KEEP      %-40s %6d', $row->classname, $row->c));
    }
}

cli_writeln('');
if ($options['apply']) {
    cli_writeln('deleted ' . $deleted . ', remaining ' . $DB->count_records('task_adhoc'));
} else {
    cli_writeln('would delete ' . $deleted . ', would leave ' . ($total - $deleted));
    cli_writeln('re-run with --apply to do it.');
}
