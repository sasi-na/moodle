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
 * Zoom Online block
 *
 * @package    block_zoomonline
 * @copyright  2024 Ciaran Mac Donncha
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Handles the upgrade process for the block_zoomonline plugin.
 *
 * @param int $oldversion The version of the plugin before the upgrade.
 * @return bool True on successful upgrade.
 */
function xmldb_block_zoomonline_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager(); // Database manager.

    if ($oldversion < 2025021301) {
        // Define table block_zoomonline_links.
        $table = new xmldb_table('block_zoomonline_links');

        // Add table if it does not exist.
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('zoom_meeting_id', XMLDB_TYPE_CHAR, '45', null, null, null, null);
            $table->add_field('lecturer_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('course_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('visible', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('crscode', XMLDB_TYPE_CHAR, '90', null, null, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('zoom_meeting_idx', XMLDB_INDEX_UNIQUE, ['zoom_meeting_id']);

            $dbman->create_table($table);
        } else {
            // Add missing fields if table already exists.
            $fields = [
                    new xmldb_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null),
                    new xmldb_field('zoom_meeting_id', XMLDB_TYPE_CHAR, '45', null, null, null, null),
                    new xmldb_field('lecturer_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null),
                    new xmldb_field('course_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null),
                    new xmldb_field('groupid', XMLDB_TYPE_INTEGER, '10', null, null, null, null),
                    new xmldb_field('visible', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null),
                    new xmldb_field('crscode', XMLDB_TYPE_CHAR, '90', null, null, null, null),
                    new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null), // Added default value '0'

            ];
            foreach ($fields as $field) {
                if (!$dbman->field_exists($table, $field->getName())) {
                    $dbman->add_field($table, $field);
                }
            }
        }

        // Define table block_zoomonline_att_check.
        $table = new xmldb_table('block_zoomonline_att_check');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('zoom_meeting_id', XMLDB_TYPE_CHAR, '45', null, null, null, null);
            $table->add_field('start_date', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            // Add a unique index to prevent duplicate attendance records for the same meeting and date.
            $table->add_index('zoom_meeting_startdate_idx', XMLDB_INDEX_UNIQUE, ['zoom_meeting_id', 'start_date']);

            $dbman->create_table($table);
        } else {
            // Add missing fields if table already exists.

            $fields = [
                    new xmldb_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null),
                    new xmldb_field('zoom_meeting_id', XMLDB_TYPE_CHAR, '45', null, XMLDB_NOTNULL, null, null),
                    new xmldb_field('start_date', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null),
                    new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null),
            ];

            foreach ($fields as $field) {
                if (!$dbman->field_exists($table, $field->getName())) {
                    $dbman->add_field($table, $field);
                }
            }

            // Ensure the primary key exists for the 'id' field.
            $primarykey = new xmldb_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            try {
                $dbman->add_key($table, $primarykey);
            } catch (ddl_exception $e) {
                // Ignore the error if the key already exists
                debugging('Primary key for the table already exists or could not be added: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }

            // Ensure the unique index exists for 'zoom_meeting_id' and 'start_date'.
            $index = new xmldb_index('zoom_meeting_startdate_idx', XMLDB_INDEX_UNIQUE, ['zoom_meeting_id', 'start_date']);
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }

        }

        // Define table block_zoomonline_token.
        $table = new xmldb_table('block_zoomonline_token');

        if (!$dbman->table_exists($table)) {
            // Create the table with all required fields.
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('access_token', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('current', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            // Add the primary key.
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            // Add a unique index for the current token if necessary.
            $table->add_index('current_token_idx', XMLDB_INDEX_UNIQUE, ['current']);

            // Create the table.
            $dbman->create_table($table);
        } else {
            // Add missing fields if the table already exists.
            $fields = [
                    new xmldb_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null),
                    new xmldb_field('access_token', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null),
                    new xmldb_field('current', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0'),
            ];

            foreach ($fields as $field) {
                if (!$dbman->field_exists($table, $field->getName())) {
                    $dbman->add_field($table, $field);
                }
            }

            // Ensure the primary key exists.
            $primarykey = new xmldb_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            try {
                $dbman->add_key($table, $primarykey);
            } catch (ddl_exception $e) {
                // Ignore the error if the key already exists
                debugging('Primary key already exists or could not be added: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
            // Ensure the unique index exists for the 'current' field.
            $index = new xmldb_index('current_token_idx', XMLDB_INDEX_UNIQUE, ['current']);
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }

            // Remove the timemodified field if it exists.
            $timemodified = new xmldb_field('timemodified');
            if ($dbman->field_exists($table, $timemodified)) {
                $dbman->drop_field($table, $timemodified);
            }
        }

        // Upgrade savepoint - single call after all changes.
        upgrade_block_savepoint(true, 2025021301, 'zoomonline');
    }

    return true;
}
