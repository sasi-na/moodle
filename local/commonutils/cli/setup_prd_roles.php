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
 * Creates the Teaching Assistant and Mentor/Faculty roles the role PRDs require,
 * and closes the capability gaps found in the PRD audit.
 *
 * Idempotent. Re-running reports "already" for anything in place and changes
 * nothing else. Nothing is ever removed or revoked here — withdrawing access is
 * done through the role UI on purpose, so this script cannot take it away.
 *
 * Usage:
 *   php local/commonutils/cli/setup_prd_roles.php --dry-run
 *   php local/commonutils/cli/setup_prd_roles.php --execute
 *
 * @package   local_commonutils
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/accesslib.php');

/**
 * The roles the PRDs name, and how each is built.
 *
 * Mentor/Faculty maps onto editingteacher cleanly: the PRD gives it content
 * authoring, calendar, assignments, grading including bulk CSV, attendance and
 * course reports, which is exactly that archetype.
 *
 * Teaching Assistant does not map onto any stock archetype. The PRD gives it
 * content edit and course reports but explicitly NO grading and NO user
 * management, so it starts from editingteacher and those capabilities are
 * withdrawn. That reading is recorded here because it is an interpretation:
 * "Grading & Attendance: No access" is taken to mean cannot award or change a
 * grade, while still seeing submissions and scores — otherwise the Assignment
 * and Quiz reports the same PRD grants would be empty.
 */
const ROLES = [
    'faculty' => [
        'name'      => 'Mentor / Faculty',
        'desc'      => 'Owns course delivery for assigned courses: content authoring, calendar and '
                     . 'live sessions, assignments and assessments, grading including bulk CSV, '
                     . 'attendance, and learner progress. Answers support tickets forwarded by the '
                     . 'Program Manager, shown to the student under the Program Manager identity.',
        'archetype' => 'editingteacher',
        'levels'    => [CONTEXT_COURSECAT, CONTEXT_COURSE],
        'allow'     => ['local/helpdesk:respondforwarded'],
        'prevent'   => [],
    ],
    'ta' => [
        'name'      => 'Teaching Assistant',
        'desc'      => 'Supports Faculty on assigned courses: reference access to course content, '
                     . 'course-scoped completion, assignment and quiz reports, and replies to '
                     . 'content queries forwarded by the Program Manager. Cannot award grades, '
                     . 'manage attendance, or enrol users.',
        'archetype' => 'editingteacher',
        'levels'    => [CONTEXT_COURSECAT, CONTEXT_COURSE],
        'allow'     => ['local/helpdesk:respondforwarded'],
        'prevent'   => [
            // No grading: may read submissions and scores, may not change them.
            'mod/assign:grade',
            'mod/quiz:grade',
            'moodle/grade:edit',
            'moodle/grade:manage',
            // No user management.
            'enrol/manual:enrol',
            'enrol/manual:unenrol',
            'moodle/role:assign',
            'moodle/course:enrolconfig',
        ],
    ],
];

/** Capability gaps the audit found: capability => role shortnames to grant it to. */
const GRANTS = [
    // Nobody held this, so the legal-hold feature was unreachable.
    'local/helpdesk:setlegalhold' => ['programhead', 'manager'],
];

list($options, $unrecognised) = cli_get_params(
    ['help' => false, 'dry-run' => false, 'execute' => false],
    ['h' => 'help']
);
if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognised)));
}
if ($options['help'] || (!$options['dry-run'] && !$options['execute'])) {
    cli_writeln("Creates the PRD roles (Teaching Assistant, Mentor/Faculty) and grants the");
    cli_writeln("capabilities the PRD audit found unheld.\n");
    cli_writeln("  --dry-run   Report what would change, touch nothing.");
    cli_writeln("  --execute   Apply the changes.");
    exit(0);
}
$dry = !$options['execute'];
cli_writeln($dry ? "== DRY RUN ==\n" : "== APPLYING ==\n");

$syscontext = context_system::instance();
$made = $already = 0;

foreach (ROLES as $shortname => $spec) {
    $roleid = $DB->get_field('role', 'id', ['shortname' => $shortname]);

    if ($roleid) {
        cli_writeln("role '{$shortname}' already exists (id={$roleid})");
        $already++;
    } else if ($dry) {
        cli_writeln("would create role '{$shortname}' ({$spec['name']}) from archetype "
            . "{$spec['archetype']}, assignable at category + course");
        $made++;
        $roleid = 0;
    } else {
        $roleid = create_role($spec['name'], $shortname, $spec['desc'], $spec['archetype']);
        set_role_contextlevels($roleid, $spec['levels']);
        // Seed the archetype's capabilities, then apply our deltas.
        reset_role_capabilities($roleid);
        cli_writeln("created role '{$shortname}' (id={$roleid}) from {$spec['archetype']}");
        $made++;
    }

    if (!$roleid) {
        continue;
    }

    foreach ($spec['allow'] as $cap) {
        $cur = $DB->get_field('role_capabilities', 'permission',
            ['roleid' => $roleid, 'capability' => $cap, 'contextid' => $syscontext->id]);
        if ((int) $cur === CAP_ALLOW) {
            cli_writeln("    allow    {$cap} — already");
            continue;
        }
        if ($dry) {
            cli_writeln("    would allow    {$cap}");
            continue;
        }
        assign_capability($cap, CAP_ALLOW, $roleid, $syscontext->id, true);
        cli_writeln("    allow    {$cap}");
    }

    foreach ($spec['prevent'] as $cap) {
        if (!$DB->record_exists('capabilities', ['name' => $cap])) {
            cli_writeln("    skip     {$cap} — capability not present on this site");
            continue;
        }
        $cur = $DB->get_field('role_capabilities', 'permission',
            ['roleid' => $roleid, 'capability' => $cap, 'contextid' => $syscontext->id]);
        if ($cur !== false && (int) $cur === CAP_PREVENT) {
            cli_writeln("    prevent  {$cap} — already");
            continue;
        }
        if ($dry) {
            cli_writeln("    would prevent  {$cap}");
            continue;
        }
        assign_capability($cap, CAP_PREVENT, $roleid, $syscontext->id, true);
        cli_writeln("    prevent  {$cap}");
    }
}

cli_writeln("");
foreach (GRANTS as $cap => $shortnames) {
    if (!$DB->record_exists('capabilities', ['name' => $cap])) {
        cli_problem("  capability {$cap} not present — skipped");
        continue;
    }
    foreach ($shortnames as $shortname) {
        $roleid = $DB->get_field('role', 'id', ['shortname' => $shortname]);
        if (!$roleid) {
            cli_writeln("  {$cap} -> {$shortname}: role absent, skipped");
            continue;
        }
        $cur = $DB->get_field('role_capabilities', 'permission',
            ['roleid' => $roleid, 'capability' => $cap, 'contextid' => $syscontext->id]);
        if ((int) $cur === CAP_ALLOW) {
            cli_writeln("  {$cap} -> {$shortname}: already");
            $already++;
            continue;
        }
        if ($dry) {
            cli_writeln("  {$cap} -> {$shortname}: would grant");
            $made++;
            continue;
        }
        assign_capability($cap, CAP_ALLOW, $roleid, $syscontext->id, true);
        cli_writeln("  {$cap} -> {$shortname}: granted");
        $made++;
    }
}

if (!$dry) {
    // Role definitions are cached aggressively; without this the change is not
    // visible until the next full purge. The _for_unit_testing variant throws
    // outside PHPUnit, so call the real one.
    accesslib_clear_all_caches(true);
}

cli_writeln(sprintf("\n%s: %d change(s), %d already in place.",
    $dry ? 'would make' : 'made', $made, $already));
if ($dry) {
    cli_writeln("Nothing was changed. Re-run with --execute to apply.");
}
