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
 * Upgrade script for the Edwiser Video Activity module.
 *
 * @package    mod_edwiservideoactivity
 * @copyright  2024 Edwiser
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade function for the Edwiser Video Activity module.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool Success/Failure.
 */
function xmldb_edwiservideoactivity_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    // Add progress tracking table and completion percentage field for version 2025110500.
    if ($oldversion < 2025110500) {
        // Define table edwiservideoactivity_progress to be created.
        $table = new xmldb_table('edwiservideoactivity_progress');

        // Adding fields to table edwiservideoactivity_progress.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('cm_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('user_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('currenttime', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('duration', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('percent', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('videoname', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('sourcetype', XMLDB_TYPE_INTEGER, '3', null, null, null, null);
        $table->add_field('provider', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table edwiservideoactivity_progress.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table edwiservideoactivity_progress.
        $table->add_index('cm_user', XMLDB_INDEX_UNIQUE, ['cm_id', 'user_id']);
        $table->add_index('user_id', XMLDB_INDEX_NOTUNIQUE, ['user_id']);
        $table->add_index('cm_id', XMLDB_INDEX_NOTUNIQUE, ['cm_id']);
        $table->add_index('provider', XMLDB_INDEX_NOTUNIQUE, ['provider']);

        // Conditionally launch create table for edwiservideoactivity_progress.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table edwiservideoactivity to be modified.
        $table = new xmldb_table('edwiservideoactivity');

        // Define field completionpercent to be added to edwiservideoactivity.
        $field = new xmldb_field('completionpercent', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, false, 0, 'sourcepath');

        // Conditionally launch add field completionpercent.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Edwiservideoactivity savepoint reached.
        upgrade_mod_savepoint(true, 2025110500, 'edwiservideoactivity');
    }

    return true;
}
