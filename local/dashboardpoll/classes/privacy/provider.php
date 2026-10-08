<?php
namespace local_dashboardpoll\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use context_system;

/**
 * Privacy provider for local_dashboardpoll — stores one response row per user.
 *
 * @package   local_dashboardpoll
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\core_userlist_provider,
        \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_dashboardpoll_responses', [
            'pollid'      => 'privacy:metadata:responses:pollid',
            'optionid'    => 'privacy:metadata:responses:optionid',
            'userid'      => 'privacy:metadata:responses:userid',
            'timecreated' => 'privacy:metadata:responses:timecreated',
        ], 'privacy:metadata:responses');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if ($DB->record_exists('local_dashboardpoll_responses', ['userid' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context instanceof context_system) {
            $userlist->add_from_sql('userid', 'SELECT userid FROM {local_dashboardpoll_responses}', []);
        }
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        if (!in_array(CONTEXT_SYSTEM, array_map(fn($c) => $c->contextlevel, $contextlist->get_contexts()))) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        $records = $DB->get_records('local_dashboardpoll_responses', ['userid' => $userid]);
        $data = [];
        foreach ($records as $r) {
            $data[] = (object) [
                'pollid'      => $r->pollid,
                'optionid'    => $r->optionid,
                'timecreated' => \core_privacy\local\request\transform::datetime($r->timecreated),
            ];
        }
        if ($data) {
            writer::with_context(context_system::instance())->export_data(
                [get_string('pluginname', 'local_dashboardpoll')],
                (object) ['responses' => $data]
            );
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context instanceof context_system) {
            $DB->delete_records('local_dashboardpoll_responses');
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_system) {
                $DB->delete_records('local_dashboardpoll_responses',
                    ['userid' => $contextlist->get_user()->id]);
            }
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if ($context instanceof context_system) {
            list($insql, $params) = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
            $DB->delete_records_select('local_dashboardpoll_responses', "userid $insql", $params);
        }
    }
}
