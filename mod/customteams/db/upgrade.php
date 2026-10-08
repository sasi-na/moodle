<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade steps for mod_customteams.
 *
 * Jain installs this at 2026092200, so there is nothing to run yet. The
 * function exists so that the next schema change has a home and so that a site
 * restored from the older Sharda build (which had no organizer column and no
 * account map) upgrades cleanly.
 */
function xmldb_customteams_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026092200) {

        $table = new xmldb_table('customteams');
        $field = new xmldb_field('organizer', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'meetingid');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $index = new xmldb_index('organizer_timestart', XMLDB_INDEX_NOTUNIQUE, ['organizer', 'timestart']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $index = new xmldb_index('course', XMLDB_INDEX_NOTUNIQUE, ['course']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_mod_savepoint(true, 2026092200, 'customteams');
    }

    return true;
}
