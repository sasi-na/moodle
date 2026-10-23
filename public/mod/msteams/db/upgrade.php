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
 * @package    mod_msteams
 * @copyright  2020 Zentrum für Lernmanagement (http://www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

function xmldb_msteams_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026040300) {
        $table = new xmldb_table('msteams');

        $field = new xmldb_field('meetingid', XMLDB_TYPE_TEXT, null, null, null, null, null, 'externalurl');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('startdatetime', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'meetingid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('enddatetime', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'startdatetime');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026040300, 'msteams');
    }

    if ($oldversion < 2026041600) {
        $table = new xmldb_table('msteams');

        $field = new xmldb_field('requirelobby', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'enddatetime');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026041600, 'msteams');
    }

    if ($oldversion < 2026041700) {
        $table = new xmldb_table('msteams');

        $field = new xmldb_field('presenteruserid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'requirelobby');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('coorganizeruserid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'presenteruserid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026041700, 'msteams');
    }

    return true;
}
