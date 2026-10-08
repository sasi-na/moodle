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
 * Upgrade steps for local_helpdesk.
 *
 * @package    local_helpdesk
 * @copyright  2026 onwards
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_helpdesk_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026082001) {
        // A pre-release build briefly granted this by archetype, which put it on every
        // teacher role. Answering forwarded requests is an appointment to the support
        // rota, so take it back off the archetype roles and leave any deliberate
        // site-level assignment alone.
        $syscontext = context_system::instance();
        foreach (['teacher', 'editingteacher'] as $archetype) {
            foreach (get_archetype_roles($archetype) as $role) {
                unassign_capability('local/helpdesk:respondforwarded', $role->id, $syscontext->id);
            }
        }

        upgrade_plugin_savepoint(true, 2026082001, 'local', 'helpdesk');
    }

    if ($oldversion < 2026082002) {
        // Queue management is granted by the manager archetype, so a role cloned from
        // manager to be read-only silently gains write access to tickets. Moodle gives
        // a plugin no way to tell those clones apart, so this names the convention this
        // fleet uses. A site with its own read-only role has to strip it by hand.
        $syscontext = context_system::instance();
        if ($role = $DB->get_record('role', ['shortname' => 'adminreadonly'])) {
            unassign_capability('local/helpdesk:manage', $role->id, $syscontext->id);
            unassign_capability('local/helpdesk:managemacros', $role->id, $syscontext->id);
        }

        upgrade_plugin_savepoint(true, 2026082002, 'local', 'helpdesk');
    }

    if ($oldversion < 2026083101) {
        // Tickets carry the course they are about, so forwarding can offer the TA or
        // faculty member actually attached to it rather than every specialist on site.
        $table = new xmldb_table('local_helpdesk_ticket');
        $field = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'channel');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $index = new xmldb_index('courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026083101, 'local', 'helpdesk');
    }

    return true;
}
