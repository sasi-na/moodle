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

namespace block_zoomonline\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;
use context;

/**
 * Privacy provider implementation for block_zoomonline.
 *
 * @package    block_zoomonline
 * @category   privacy
 * @copyright  2024 Ciaran Mac Donncha
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    /**
     * Describe the personal data stored by the plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
                'block_zoomonline_links',
                [
                        'lecturer_id' => 'privacy:metadata:lecturer_id',
                        'zoom_meeting_id' => 'privacy:metadata:zoom_meeting_id',
                        'course_id' => 'privacy:metadata:course_id',
                        'groupid' => 'privacy:metadata:groupid',
                        'visible' => 'privacy:metadata:visible',
                ],
                'privacy:metadata:summary'
        );

        // Declare external data sent to Zoom
        $collection->add_external_location_link(
                'zoom.us',
                [
                        'email' => 'privacy:metadata:zoom_email',
                        'firstname' => 'privacy:metadata:zoom_firstname',
                        'lastname' => 'privacy:metadata:zoom_lastname',
                ],
                'privacy:metadata:zoom_summary'
        );

        return $collection;
    }

    /**
     * Export personal data for a specific user in a context.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $user = $contextlist->get_user();
        $contexts = $contextlist->get_contexts();

        foreach ($contexts as $context) {
            if ($context->contextlevel == CONTEXT_COURSE) {
                $records = $DB->get_records('block_zoomonline_links', ['lecturer_id' => $user->id]);

                foreach ($records as $record) {
                    $data = (object) [
                            'zoom_meeting_id' => $record->zoom_meeting_id,
                            'course_id' => $record->course_id,
                            'groupid' => $record->groupid,
                            'visible' => $record->visible,
                    ];

                    writer::with_context($context)->export_data(
                            [get_string('privacy:metadata:block_zoomonline_links', 'block_zoomonline')],
                            $data
                    );
                }
            }
        }
    }

    /**
     * Delete personal data for a specific user in a context.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $user = $contextlist->get_user();
        $contexts = $contextlist->get_contexts();

        foreach ($contexts as $context) {
            if ($context->contextlevel == CONTEXT_COURSE) {
                $DB->delete_records('block_zoomonline_links', ['lecturer_id' => $user->id]);
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;

        if ($context->contextlevel == CONTEXT_COURSE) {
            $DB->delete_records('block_zoomonline_links', ['course_id' => $context->instanceid]);
        }
    }

    /**
     * Describe the list of contexts containing user data.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {block_zoomonline_links} bzl
                  JOIN {context} ctx ON ctx.instanceid = bzl.course_id
                 WHERE bzl.lecturer_id = :userid";

        $params = ['userid' => $userid];
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data in a given context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        global $DB;

        $sql = "SELECT lecturer_id FROM {block_zoomonline_links} WHERE course_id = :courseid";
        $params = ['courseid' => $userlist->get_context()->instanceid];
        $userlist->add_users_from_sql($sql, $params);
    }

    /**
     * Delete data for multiple users.
     *
     * @param approved_userlist $userlist The list of approved users whose data should be deleted.
     *
     * @throws dml_exception If an error occurs while deleting records.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        // Get the context.
        $context = $userlist->get_context();

        // Get the user IDs from the approved user list.
        $userids = $userlist->get_userids();

        if ($context->contextlevel == CONTEXT_COURSE) {
            [$insql, $params] = $DB->get_in_or_equal($userids);
            $DB->delete_records_select('block_zoomonline_links', "lecturer_id $insql", $params);
        }
    }
}
