<?php
namespace local_studentid\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\context;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_studentid_requests', [
            'userid'         => 'privacy:metadata:requests:userid',
            'usn'            => 'privacy:metadata:requests:usn',
            'status'         => 'privacy:metadata:requests:status',
            'photo_path'     => 'privacy:metadata:requests:photo_path',
            'blood_group'    => 'privacy:metadata:requests:blood_group',
            'review_comment' => 'privacy:metadata:requests:review_comment',
            'timecreated'    => 'privacy:metadata:requests:timecreated',
        ], 'privacy:metadata:requests');

        $collection->add_database_table('local_studentid_cards', [
            'userid'        => 'privacy:metadata:cards:userid',
            'idcard_number' => 'privacy:metadata:cards:idcard_number',
            'file_path'     => 'privacy:metadata:cards:file_path',
            'timegenerated' => 'privacy:metadata:cards:timegenerated',
        ], 'privacy:metadata:cards');

        $collection->add_database_table('local_studentid_logs', [
            'userid'      => 'privacy:metadata:logs:userid',
            'action'      => 'privacy:metadata:logs:action',
            'context'     => 'privacy:metadata:logs:context',
            'timecreated' => 'privacy:metadata:logs:timecreated',
        ], 'privacy:metadata:logs');

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id FROM {context} ctx
                WHERE ctx.contextlevel = :contextlevel AND ctx.instanceid = 0
                  AND EXISTS (SELECT 1 FROM {local_studentid_requests} r WHERE r.userid = :userid)";
        $contextlist->add_from_sql($sql, ['userid' => $userid, 'contextlevel' => CONTEXT_SYSTEM]);
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $sql = "SELECT userid FROM {local_studentid_requests}";
        $userlist->add_from_sql('userid', $sql, []);
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        if (empty($contextlist->count())) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        $context = \context_system::instance();

        $requests = $DB->get_records('local_studentid_requests', ['userid' => $userid]);
        foreach ($requests as $request) {
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_studentid'), 'request_' . $request->id],
                $request
            );
        }

        $cards = $DB->get_records('local_studentid_cards', ['userid' => $userid]);
        foreach ($cards as $card) {
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_studentid'), 'card_' . $card->id],
                $card
            );
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        global $DB;
        $DB->delete_records('local_studentid_requests');
        $DB->delete_records('local_studentid_cards');
        $DB->delete_records('local_studentid_logs');
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $DB->delete_records('local_studentid_requests', ['userid' => $userid]);
        $DB->delete_records('local_studentid_cards', ['userid' => $userid]);
        $DB->delete_records('local_studentid_logs', ['userid' => $userid]);
    }

    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        list($insql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_studentid_requests', "userid $insql", $params);
        $DB->delete_records_select('local_studentid_cards', "userid $insql", $params);
        $DB->delete_records_select('local_studentid_logs', "userid $insql", $params);
    }
}
