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
 * Creates the programme-coordinator role and assigns it from the mapping below.
 *
 * Idempotent: re-running reports "already" for anything already in place, so it
 * is safe to run again after editing MAP. Nothing is ever removed -- revoking
 * access is done through the normal role UI, deliberately, so this script can
 * never take entitlements away by accident.
 *
 * Usage:
 *   php local/batchvitals/cli/setup_coordinators.php --dry-run
 *   php local/batchvitals/cli/setup_coordinators.php --execute
 *
 * @package   local_batchvitals
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

/**
 * Coordinator email => programme category ids.
 *
 * Resolved against jain-prod on 31 Aug 2026. Category 32 (BBA July 2024) belongs
 * to satish.k, per the decision on the duplicate row -- bharath.raj keeps MBA
 * July 2024 only.
 */
const MAP = [
    'mayamanishankar@onlinejain.com'    => [7],          // MCA Jan 2025.
    'manthana.hs@onlinejain.com'        => [20],         // MCA July 2025.
    'smitha.s@onlinejain.com'           => [312],        // MCA July 2026.
    'kavitha.rg@onlinejain.com'         => [181],        // BCA Jan 2026.
    'saraswathy.r@onlinejain.com'       => [317],        // BCA July 2026.
    'bharath.raj@onlinejain.com'        => [30],         // MBA July 2024.
    'chethana.k@onlinejain.com'         => [18, 21],     // MBA + BBA July 2025.
    'pooja.s@onlinejain.com'            => [3, 8],       // MBA + BBA Jan 2025.
    'mohan.yc@onlinejain.com'           => [175, 179],   // MBA + BBA Jan 2026.
    'satish.k@onlinejain.com'           => [79, 32],     // BBA Jan 2024 + July 2024.
    'shruthi.s@onlinejain.com'          => [80, 77],     // B.Com + M.Com Jan 2024.
    'meghashilpa.r@onlinejain.com'      => [33, 34],     // B.Com + M.Com July 2024.
    'gurucharan@onlinejain.com'         => [9, 15],      // B.Com + M.Com Jan 2025.
    'swaroop.srikrishna@onlinejain.com' => [22, 19],     // B.Com + M.Com July 2025.
    'soumya.sirsali@onlinejain.com'     => [180, 177],   // B.Com + M.Com Jan 2026.
    'mahima.agrawal@onlinejain.com'     => [316, 313],   // B.Com + M.Com July 2026.
    'arunima@onlinejain.com'            => [178],        // MA Jan 2026.
    'shiva.chaudhary@onlinejain.com'    => [23],         // MA July 2025.
    'niharika.saikia@onlinejain.com'    => [31],         // MA July 2024.
    'jainologyfaculty@onlinejain.com'   => [314],        // MA July 2026.
];

list($options, $unrecognised) = cli_get_params(
    ['help' => false, 'dry-run' => false, 'execute' => false],
    ['h' => 'help']
);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognised)));
}
if ($options['help'] || (!$options['dry-run'] && !$options['execute'])) {
    cli_writeln("Creates the programme-coordinator role and assigns it.\n");
    cli_writeln("  --dry-run   Report what would change, touch nothing.");
    cli_writeln("  --execute   Apply the changes.");
    exit(0);
}
$dry = !$options['execute'];
cli_writeln($dry ? "== DRY RUN ==\n" : "== APPLYING ==\n");

// Shared with the settings-page overview so the two can never drift.
$roleshortname = \local_batchvitals\access::ROLE_SHORTNAME;

// ---------- the role ----------
$roleid = $DB->get_field('role', 'id', ['shortname' => $roleshortname]);
if ($roleid) {
    cli_writeln("role '{$roleshortname}' already exists (id={$roleid})");
} else if ($dry) {
    cli_writeln("would create role '{$roleshortname}' assignable at category context");
    $roleid = 0;
} else {
    // No archetype on purpose: an archetype would import a pile of unrelated
    // capabilities, and this role should allow exactly one thing.
    $roleid = create_role(get_string('rolename', 'local_batchvitals'), $roleshortname,
        get_string('roledescription', 'local_batchvitals'));
    set_role_contextlevels($roleid, [CONTEXT_COURSECAT]);
    assign_capability('local/batchvitals:viewprogramme', CAP_ALLOW,
        $roleid, context_system::instance()->id, true);
    cli_writeln("created role '{$roleshortname}' (id={$roleid}) with only "
        . "local/batchvitals:viewprogramme allowed");
}

// ---------- the assignments ----------
$made = $already = $skipped = 0;
foreach (MAP as $email => $catids) {
    $user = $DB->get_record_select('user', 'LOWER(email) = :e AND deleted = 0',
        ['e' => strtolower(trim($email))], 'id, firstname, lastname, suspended');
    if (!$user) {
        cli_problem("  NO ACCOUNT  $email -- skipped");
        $skipped += count($catids);
        continue;
    }
    if ($user->suspended) {
        cli_problem("  SUSPENDED   $email (id={$user->id}) -- skipped");
        $skipped += count($catids);
        continue;
    }

    foreach ($catids as $catid) {
        $cat = $DB->get_record('course_categories', ['id' => $catid], 'id, name');
        if (!$cat) {
            cli_problem("  NO CATEGORY id=$catid for $email -- skipped");
            $skipped++;
            continue;
        }
        $context = context_coursecat::instance($catid);
        $label = sprintf('%-34s cat=%-5d %s', $email, $catid, trim($cat->name));

        if ($roleid && $DB->record_exists('role_assignments',
                ['roleid' => $roleid, 'contextid' => $context->id, 'userid' => $user->id])) {
            cli_writeln("  already     $label");
            $already++;
            continue;
        }
        if ($dry) {
            cli_writeln("  would add   $label");
            $made++;
            continue;
        }
        role_assign($roleid, $user->id, $context->id);
        cli_writeln("  assigned    $label");
        $made++;
    }
}

cli_writeln(sprintf("\n%s: %d assignment(s), %d already in place, %d skipped.",
    $dry ? 'would make' : 'made', $made, $already, $skipped));
if ($dry) {
    cli_writeln("Nothing was changed. Re-run with --execute to apply.");
}
