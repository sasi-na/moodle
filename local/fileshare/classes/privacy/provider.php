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
 * Privacy provider for local_fileshare.
 *
 * @package   local_fileshare
 * @copyright 2026 onlinejain.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_fileshare\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * A share row names two people: whoever created the link, and whose file it points at.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Describe what is stored.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_fileshare', [
            'filename' => 'privacy:metadata:local_fileshare:filename',
            'createdby' => 'privacy:metadata:local_fileshare:createdby',
            'studentid' => 'privacy:metadata:local_fileshare:studentid',
            'recipient' => 'privacy:metadata:local_fileshare:recipient',
            'timecreated' => 'privacy:metadata:local_fileshare:timecreated',
            'downloads' => 'privacy:metadata:local_fileshare:downloads',
            'lastaccess' => 'privacy:metadata:local_fileshare:lastaccess',
        ], 'privacy:metadata:local_fileshare');

        return $collection;
    }

    /**
     * Contexts holding data for this user, in either role.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            "SELECT DISTINCT contextid FROM {local_fileshare} WHERE createdby = :c OR studentid = :s",
            ['c' => $userid, 's' => $userid]);
        return $contextlist;
    }

    /**
     * Users with data in a context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $userlist->add_from_sql('createdby', 'SELECT createdby FROM {local_fileshare} WHERE contextid = ?',
            [$context->id]);
        $userlist->add_from_sql('studentid', 'SELECT studentid FROM {local_fileshare} WHERE contextid = ?',
            [$context->id]);
    }

    /**
     * Export.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $shares = $DB->get_records_select('local_fileshare',
                'contextid = :ctx AND (createdby = :c OR studentid = :s)',
                ['ctx' => $context->id, 'c' => $userid, 's' => $userid]);
            if (!$shares) {
                continue;
            }
            $rows = [];
            foreach ($shares as $share) {
                $rows[] = (object) [
                    'filename' => $share->filename,
                    'role' => ((int) $share->createdby === $userid) ? 'creator' : 'subject',
                    'recipient' => $share->recipient,
                    'created' => \core_privacy\local\request\transform::datetime($share->timecreated),
                    'expires' => \core_privacy\local\request\transform::datetime($share->validuntil),
                    'downloads' => $share->downloads,
                    'lastaccess' => $share->lastaccess
                        ? \core_privacy\local\request\transform::datetime($share->lastaccess) : null,
                    'revoked' => \core_privacy\local\request\transform::yesno($share->revoked),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_fileshare')], (object) ['shares' => $rows]);
        }
    }

    /**
     * Delete everything in a context. Keys go first so no link outlives its row.
     *
     * @param \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        self::delete_keys_for_select('contextid = :ctx', ['ctx' => $context->id]);
        $DB->delete_records('local_fileshare', ['contextid' => $context->id]);
    }

    /**
     * Delete for one user, in either role.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $select = 'contextid = :ctx AND (createdby = :c OR studentid = :s)';
            $params = ['ctx' => $context->id, 'c' => $userid, 's' => $userid];
            self::delete_keys_for_select($select, $params);
            $DB->delete_records_select('local_fileshare', $select, $params);
        }
    }

    /**
     * Delete for a set of users in one context.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }
        // Two separate placeholder sets: reusing one named set in both halves of the OR would
        // leave the parameter count out of step with the placeholders.
        [$csql, $cparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'cb');
        [$ssql, $sparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'st');
        $select = "contextid = :ctx AND (createdby $csql OR studentid $ssql)";
        $params = $cparams + $sparams + ['ctx' => $context->id];
        self::delete_keys_for_select($select, $params);
        $DB->delete_records_select('local_fileshare', $select, $params);
    }

    /**
     * Delete the user keys behind the share rows a select matches.
     *
     * @param string $select
     * @param array $params
     * @return void
     */
    protected static function delete_keys_for_select(string $select, array $params): void {
        global $DB;

        $keyids = $DB->get_fieldset_select('local_fileshare', 'keyid', "$select AND keyid > 0", $params);
        if (!$keyids) {
            return;
        }
        foreach (array_chunk($keyids, 500) as $chunk) {
            [$insql, $inparams] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED);
            $inparams['script'] = \local_fileshare\manager::SCRIPT;
            $DB->delete_records_select('user_private_key', "id $insql AND script = :script", $inparams);
        }
    }
}
