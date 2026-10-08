<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade steps for local_dashboardpoll.
 *
 * @package   local_dashboardpoll
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function xmldb_local_dashboardpoll_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026062900) {
        // Per-poll multi-select support.
        $table = new xmldb_table('local_dashboardpoll');

        $field = new xmldb_field('multiselect', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'status');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('maxchoices', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'multiselect');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Relax the one-row-per-user uniqueness so multi-select polls can store
        // several rows per user. Uniqueness is now per (poll, user, option),
        // which still blocks counting the same option twice.
        $rtable = new xmldb_table('local_dashboardpoll_responses');

        $olduix = new xmldb_index('polluser_uix', XMLDB_INDEX_UNIQUE, ['pollid', 'userid']);
        if ($dbman->index_exists($rtable, $olduix)) {
            $dbman->drop_index($rtable, $olduix);
        }

        $newuix = new xmldb_index('polluseropt_uix', XMLDB_INDEX_UNIQUE, ['pollid', 'userid', 'optionid']);
        if (!$dbman->index_exists($rtable, $newuix)) {
            $dbman->add_index($rtable, $newuix);
        }

        $newidx = new xmldb_index('polluser_idx', XMLDB_INDEX_NOTUNIQUE, ['pollid', 'userid']);
        if (!$dbman->index_exists($rtable, $newidx)) {
            $dbman->add_index($rtable, $newidx);
        }

        upgrade_plugin_savepoint(true, 2026062900, 'local', 'dashboardpoll');
    }

    if ($oldversion < 2026072200) {
        // Per-poll "what students see after voting" control. Stored as a bitmask:
        // 0 = thank-you only, bit 1 (1) = percentages, bit 2 (2) = vote counts.
        $table = new xmldb_table('local_dashboardpoll');
        $field = new xmldb_field('resultsdisplay', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1', 'maxchoices');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Preserve today's behaviour for existing polls: percentages were always
        // shown; vote counts followed the global "showvotecounts" toggle.
        $existing = get_config('local_dashboardpoll', 'showvotecounts') ? 3 : 1;
        $DB->set_field('local_dashboardpoll', 'resultsdisplay', $existing, []);

        upgrade_plugin_savepoint(true, 2026072200, 'local', 'dashboardpoll');
    }

    return true;
}
