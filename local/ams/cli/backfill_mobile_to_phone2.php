<?php
/**
 * One-time backfill: copy each user's custom `mobile` profile field value into the
 * core "Mobile phone" field (user.phone2, shown under the Optional section), so all
 * existing students match the new AMS create-user behaviour.
 *
 * Dry-run by default — prints what WOULD change. Pass --execute to apply.
 * Non-destructive: the custom `mobile` field is left untouched.
 *
 * Usage (on an app pod):
 *   php local/ams/cli/backfill_mobile_to_phone2.php            # dry run
 *   php local/ams/cli/backfill_mobile_to_phone2.php --execute  # apply
 *   php local/ams/cli/backfill_mobile_to_phone2.php --execute --overwrite
 *
 * By default only users whose phone2 is empty are filled; --overwrite also
 * replaces a non-empty phone2 with the custom mobile value.
 *
 * @package   local_ams
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params(
    ['execute' => false, 'overwrite' => false, 'help' => false],
    ['h' => 'help']
);

if ($options['help']) {
    cli_writeln("Backfill core phone2 (\"Mobile phone\") from the custom `mobile` profile field.\n");
    cli_writeln("Options:");
    cli_writeln("  --execute     Apply the changes (otherwise dry run).");
    cli_writeln("  --overwrite   Also replace a non-empty phone2 (default: only fill empty).");
    cli_writeln("  -h, --help    This help.");
    exit(0);
}

global $DB;

$fieldid = $DB->get_field('user_info_field', 'id', ['shortname' => 'mobile']);
if (!$fieldid) {
    cli_error("No custom profile field with shortname 'mobile' was found — nothing to backfill.");
}

$overwrite = !empty($options['overwrite']);
$phonecond = $overwrite ? '' : " AND (u.phone2 IS NULL OR u.phone2 = '')";

$sql = "SELECT u.id AS userid, u.phone2 AS oldphone2, uid.data AS mobile
          FROM {user} u
          JOIN {user_info_data} uid ON uid.userid = u.id
          JOIN {user_info_field} uif ON uif.id = uid.fieldid
         WHERE uif.shortname = :sn
           AND uid.data <> ''
           AND u.deleted = 0
           $phonecond
      ORDER BY u.id ASC";
$rs = $DB->get_recordset_sql($sql, ['sn' => 'mobile']);

$rows = [];
$samples = [];
foreach ($rs as $r) {
    $rows[$r->userid] = $r->mobile;
    if (count($samples) < 10) {
        $samples[] = $r;
    }
}
$rs->close();

$count = count($rows);
cli_writeln("Custom 'mobile' field id: {$fieldid}");
cli_writeln("Mode: " . ($overwrite ? "OVERWRITE (replace non-empty phone2 too)" : "fill only empty phone2"));
cli_writeln("Users to backfill: {$count}");
cli_writeln("Sample (up to 10):");
foreach ($samples as $s) {
    cli_writeln(sprintf("  user %-8d phone2 '%s' -> '%s'", $s->userid, (string) $s->oldphone2, $s->mobile));
}

if ($count === 0) {
    cli_writeln("\nNothing to do.");
    exit(0);
}

if (empty($options['execute'])) {
    cli_writeln("\nDRY RUN — no changes made. Save this output as your before-state record,");
    cli_writeln("then re-run with --execute to apply.");
    exit(0);
}

$now = time();
$done = 0;
foreach ($rows as $userid => $mobile) {
    $DB->update_record('user', (object) [
        'id' => $userid,
        'phone2' => $mobile,
        'timemodified' => $now,
    ]);
    $done++;
}

cli_writeln("\nUpdated phone2 for {$done} users.");
cli_writeln("The custom 'mobile' field was left intact (non-destructive).");
