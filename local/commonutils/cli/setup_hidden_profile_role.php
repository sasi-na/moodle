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
 * Creates the "Learner record viewer" role and assigns it at system level.
 *
 * WHY THIS EXISTS
 *
 * Report Builder decides per VIEWER whether a custom profile field column is
 * available: reportbuilder/classes/local/helpers/user_profile_fields.php calls
 * ->set_is_available($profilefield->is_visible()), and an unavailable column is
 * dropped silently. The report still renders, just narrower, with no error and no
 * gap to notice.
 *
 * So report 10 ("LMS Student Information") showed 31 columns to the site admin and
 * 17 to everybody else in its audience. The 14 missing ones are profile fields set
 * to "Not visible", which profile_field_base::is_visible() gates on
 * moodle/user:viewalldetails at system context. Five of the six people in that
 * audience held no such capability anywhere.
 *
 * WHY A ROLE AND NOT A FIELD VISIBILITY CHANGE
 *
 * The field that prompted this is Comments, and it holds internal staff annotation
 * about learners: fee arrears, refunds, "L2 Not verified", batch transfers, ticket
 * numbers. 4,842 accounts carry real content. Setting it to "Visible to everyone"
 * would have shown every learner their own and other learners' fee and verification
 * status - and at least two of the audience members hold the student role. A role
 * confines the exposure to named people instead of widening it to the whole site.
 *
 * WHAT IT COSTS, STATED PLAINLY
 *
 * moodle/user:viewalldetails is not per-field. Granting it reveals every profile
 * field marked "Not visible" for every user on the site - mobile, birth date,
 * parents' names, provisional enrolment number, nationality - not only Comments and
 * not only inside this report. Moodle has no narrower lever; that is the trade
 * being made, not an oversight.
 *
 * Idempotent. Re-running reports "already" for anything in place. Nothing is ever
 * removed or revoked here - withdrawing access is done through the role UI on
 * purpose, so this script cannot take it away.
 *
 * Usage:
 *   php local/commonutils/cli/setup_hidden_profile_role.php --dry-run
 *   php local/commonutils/cli/setup_hidden_profile_role.php --execute
 *   php local/commonutils/cli/setup_hidden_profile_role.php --users=37007,33520 --execute
 *
 * @package   local_commonutils
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/accesslib.php');

/** @var string Shortname of the role this script owns. */
const ROLE_SHORTNAME = 'learnerrecordviewer';

/** @var string Human name. */
const ROLE_NAME = 'Learner record viewer';

/** @var string What it is for, recorded on the role itself so the UI explains itself. */
const ROLE_DESC = 'Sees profile fields marked "Not visible" - including the Comments annotations '
    . 'used by learner support - in reports and on user profiles. Granted to the staff who run the '
    . 'learner record reports. Deliberately has no archetype, so no existing role inherits it and '
    . 'a core upgrade cannot widen it.';

/**
 * @var string[] The one capability this role carries.
 *
 * Not moodle/site:viewuseridentity: that governs the identity fields listed in
 * $CFG->showuseridentity (here just email and phone2) and fields set to "Visible to
 * user, teachers and admins". It does not reach a field marked "Not visible", which
 * is what Comments is.
 */
const ROLE_CAPS = [
    'moodle/user:viewalldetails',
];

/**
 * @var int[] Who gets it by default: the non-admin members of report 10's audience.
 *
 * The site admin in that audience already has the capability and is not listed.
 */
const DEFAULT_USERS = [33520, 33521, 33615, 37007, 56743];

[$options, $unrecognised] = cli_get_params([
    'execute' => false,
    'dry-run' => false,
    'users' => '',
    'help' => false,
], ['h' => 'help']);

if ($unrecognised) {
    cli_error('Unrecognised options: ' . implode(' ', $unrecognised));
}

if ($options['help'] || (empty($options['execute']) && empty($options['dry-run']))) {
    cli_writeln('Create the "' . ROLE_NAME . '" role and assign it at system level.

Options:
  --dry-run        Report what would change, change nothing.
  --execute        Apply.
  --users=<ids>    Comma-separated user ids to assign instead of the default set.
  -h, --help       This help.
');
    exit(0);
}

$execute = !empty($options['execute']);
$userids = $options['users'] !== ''
    ? array_values(array_filter(array_map('intval', explode(',', (string) $options['users']))))
    : DEFAULT_USERS;

$syscontext = context_system::instance();
$changed = 0;

cli_writeln(($execute ? 'EXECUTING' : 'DRY RUN') . ': ' . ROLE_NAME . "\n");

// ---- the role ------------------------------------------------------------

$role = $DB->get_record('role', ['shortname' => ROLE_SHORTNAME]);
if ($role) {
    cli_writeln('  role already exists (id ' . $role->id . ')');
} else {
    cli_writeln('  role missing - would be created');
    if ($execute) {
        // No archetype on purpose: an archetype makes core copy the capability into
        // matching roles on upgrade, which is exactly the widening this must not do.
        $roleid = create_role(ROLE_NAME, ROLE_SHORTNAME, ROLE_DESC, '');
        $role = $DB->get_record('role', ['id' => $roleid], '*', MUST_EXIST);
        cli_writeln('    created (id ' . $role->id . ')');
        $changed++;
    }
}

// ---- where it may be assigned -------------------------------------------

if (!$role) {
    // Dry run with no role yet: the remaining steps still have a definite plan, and a
    // preview that stopped here would be asking for sign-off on work it had not shown.
    cli_writeln('  context levels: would allow CONTEXT_SYSTEM');
    foreach (ROLE_CAPS as $cap) {
        cli_writeln("  capability {$cap}: would allow");
    }
} else {
    // Cast: get_role_contextlevels() returns the values as strings straight from the
    // DB, so a strict in_array() against the CONTEXT_SYSTEM int never matches and the
    // block re-runs every time, reporting a change that did not happen.
    $levels = array_map('intval', get_role_contextlevels($role->id));
    if (!in_array(CONTEXT_SYSTEM, $levels, true)) {
        cli_writeln('  context levels: would allow CONTEXT_SYSTEM');
        if ($execute) {
            set_role_contextlevels($role->id, array_unique(array_merge($levels, [CONTEXT_SYSTEM])));
            cli_writeln('    set');
            $changed++;
        }
    } else {
        cli_writeln('  context levels: CONTEXT_SYSTEM already allowed');
    }

    foreach (ROLE_CAPS as $cap) {
        $current = $DB->get_record('role_capabilities', [
            'roleid' => $role->id,
            'capability' => $cap,
            'contextid' => $syscontext->id,
        ]);
        if ($current && (int) $current->permission === CAP_ALLOW) {
            cli_writeln("  capability {$cap}: already allowed");
            continue;
        }
        cli_writeln("  capability {$cap}: would allow");
        if ($execute) {
            assign_capability($cap, CAP_ALLOW, $role->id, $syscontext->id, true);
            cli_writeln('    allowed');
            $changed++;
        }
    }
}

// ---- the people ----------------------------------------------------------

cli_writeln("\n  assignments at system level:");
foreach ($userids as $userid) {
    $user = $DB->get_record('user', ['id' => $userid], 'id, username, deleted, suspended');
    if (!$user || $user->deleted) {
        cli_writeln("    id {$userid}: no such user - skipped");
        continue;
    }
    $already = $role && user_has_role_assignment($userid, $role->id, $syscontext->id);
    $label = sprintf('    %-34s', substr($user->username, 0, 34));
    if ($already) {
        cli_writeln($label . ' already assigned');
        continue;
    }
    cli_writeln($label . ' would be assigned' . ($user->suspended ? ' (account suspended)' : ''));
    if ($execute && $role) {
        role_assign($role->id, $userid, $syscontext->id);
        $changed++;
    }
}

// ---- verification --------------------------------------------------------

if ($execute && $role) {
    // No cache clearing here: role_assign() and assign_capability() invalidate what
    // they touch, and accesslib_clear_all_caches_for_unit_testing() throws outside
    // unit tests - which it did on the first run, after the writes had all succeeded.
    cli_writeln("\n  verification - can each user now see hidden profile fields?");
    $failures = 0;
    foreach ($userids as $userid) {
        if (!$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
            continue;
        }
        $ok = has_capability('moodle/user:viewalldetails', $syscontext, $userid);
        cli_writeln(sprintf('    id %-7d %s', $userid, $ok ? 'yes' : 'NO - investigate'));
        if (!$ok) {
            $failures++;
        }
    }
    if ($failures) {
        cli_error("\n{$failures} user(s) still lack the capability.");
    }
}

cli_writeln("\n" . ($execute
    ? "Done. {$changed} change(s) applied."
    : 'Dry run - nothing changed. Re-run with --execute to apply.'));
