<?php
/**
 * Hide IIITD's blocks on tenants that do not use them.
 *
 * Moodle creates a {block} row with visible=1 for every block in the codebase at
 * upgrade time, so IIITD's four blocks appear in the "Add a block" picker on
 * every tenant sharing this image. They render nothing (no instances), but they
 * are offered to teachers, which is confusing on a site that has no use for
 * "Live Session Recording Stats".
 *
 * Unlike the code-level gates ($CFG->videotracker, $CFG->assigngradelog,
 * $CFG->emailbranding), block availability is DATABASE state, so it cannot be
 * gated in code -- it has to be set per site after the upgrade that installs it.
 *
 * Safe to re-run. Does nothing to a block that already has instances, so it can
 * never hide a block a tenant is actually using.
 *
 *   php deploy/tools/disable-iiitd-blocks.php          # report only
 *   php deploy/tools/disable-iiitd-blocks.php --apply  # make the change
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options] = cli_get_params(['apply' => false, 'help' => false], ['h' => 'help']);
if ($options['help']) {
    cli_writeln("Hide IIITD-specific blocks on tenants that do not use them.\n--apply  make the change (default: report only)");
    exit(0);
}

$blocks = ['examdashboard', 'liverecordingstats', 'livesessionstats', 'reflectivejournal'];
$changed = 0;

foreach ($blocks as $name) {
    $block = $DB->get_record('block', ['name' => $name]);
    if (!$block) {
        cli_writeln(sprintf('  %-22s not installed', $name));
        continue;
    }
    $instances = $DB->count_records('block_instances', ['blockname' => $name]);

    if ($instances > 0) {
        cli_writeln(sprintf('  %-22s IN USE (%d instances) -- leaving visible', $name, $instances));
        continue;
    }
    if (!$block->visible) {
        cli_writeln(sprintf('  %-22s already hidden', $name));
        continue;
    }
    if ($options['apply']) {
        $DB->set_field('block', 'visible', 0, ['id' => $block->id]);
        cli_writeln(sprintf('  %-22s HIDDEN', $name));
        $changed++;
    } else {
        cli_writeln(sprintf('  %-22s would hide (0 instances)', $name));
    }
}

if ($options['apply'] && $changed) {
    purge_all_caches();
    cli_writeln("purged caches after $changed change(s)");
}
cli_writeln($options['apply'] ? 'done' : 'dry run -- re-run with --apply to change anything');
