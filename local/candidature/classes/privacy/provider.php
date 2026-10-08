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
 * Privacy provider for local_candidature.
 *
 * @package   local_candidature
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_candidature\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * A candidature is personal data: it records a decision about a named person.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_candidature', [
            'userid'        => 'privacy:metadata:local_candidature:userid',
            'status'        => 'privacy:metadata:local_candidature:status',
            'reason'        => 'privacy:metadata:local_candidature:reason',
            'submittedtime' => 'privacy:metadata:local_candidature:submittedtime',
        ], 'privacy:metadata:local_candidature');
        return $collection;
    }

    /**
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        $list->add_from_sql('SELECT id FROM {context} WHERE contextlevel = :lvl',
            ['lvl' => CONTEXT_SYSTEM]);
        return $list;
    }

    /**
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_candidature}', []);
    }

    /**
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $rec = $DB->get_record('local_candidature', ['userid' => $contextlist->get_user()->id]);
        if (!$rec) {
            return;
        }
        writer::with_context(\context_system::instance())->export_data(
            [get_string('pluginname', 'local_candidature')],
            (object) [
                'status'    => $rec->status,
                'submitted' => transform::datetime($rec->submittedtime),
                'decided'   => $rec->decidedtime ? transform::datetime($rec->decidedtime) : null,
                'reason'    => $rec->reason,
            ]);
    }

    /**
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context instanceof \context_system) {
            $DB->delete_records_select('local_candidature_event',
                'candidatureid IN (SELECT id FROM {local_candidature})');
            $DB->delete_records('local_candidature');
        }
    }

    /**
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        self::purge([$contextlist->get_user()->id]);
    }

    /**
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        self::purge($userlist->get_userids());
    }

    /**
     * Remove candidatures and their audit trail for the given users.
     *
     * @param array $userids
     */
    protected static function purge(array $userids): void {
        global $DB;

        if (!$userids) {
            return;
        }
        list($insql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
        $ids = $DB->get_fieldset_select('local_candidature', 'id', "userid $insql", $params);
        if ($ids) {
            list($esql, $eparams) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'c');
            $DB->delete_records_select('local_candidature_event', "candidatureid $esql", $eparams);
        }
        $DB->delete_records_select('local_candidature', "userid $insql", $params);
    }
}
