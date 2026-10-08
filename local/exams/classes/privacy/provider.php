<?php
namespace local_exams\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use context_system;
use context;

/**
 * Privacy provider for local_exams.
 *
 * All personal data (derived per-attempt analytics, generated result sheets) is
 * stored at the system context.
 *
 * @package   local_exams
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_exams_attempt_stats', [
            'userid'    => 'privacy:metadata:local_exams_attempt_stats:userid',
            'statsjson' => 'privacy:metadata:local_exams_attempt_stats:statsjson',
        ], 'privacy:metadata:local_exams_attempt_stats');

        $collection->add_database_table('local_exams_resultsheets', [
            'userid' => 'privacy:metadata:local_exams_resultsheets:userid',
        ], 'privacy:metadata:local_exams_resultsheets');

        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:local_exams_resultsheets');

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT 1
                  FROM {local_exams_attempt_stats} s
                 WHERE s.userid = :u1
                 UNION
                SELECT 1
                  FROM {local_exams_resultsheets} r
                 WHERE r.userid = :u2";
        global $DB;
        if ($DB->record_exists_sql($sql, ['u1' => $userid, 'u2' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_system) {
            return;
        }
        $userlist->add_from_sql('userid', "SELECT userid FROM {local_exams_attempt_stats}", []);
        $userlist->add_from_sql('userid', "SELECT userid FROM {local_exams_resultsheets}", []);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (!in_array(CONTEXT_SYSTEM, array_map(function(context $c) {
            return $c->contextlevel;
        }, $contextlist->get_contexts()))) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $context = context_system::instance();

        $stats = $DB->get_records('local_exams_attempt_stats', ['userid' => $userid]);
        if ($stats) {
            $export = array_map(function($r) {
                return ['attemptid' => $r->attemptid, 'quizid' => $r->quizid, 'stats' => json_decode($r->statsjson, true)];
            }, array_values($stats));
            writer::with_context($context)->export_data(['Exams', 'Attempt analytics'], (object) ['attempts' => $export]);
        }

        // Result sheet files.
        $sheets = $DB->get_records('local_exams_resultsheets', ['userid' => $userid]);
        foreach ($sheets as $sheet) {
            writer::with_context($context)->export_area_files(['Exams', 'Result sheet'], 'local_exams', 'resultsheet', $sheet->id);
        }
    }

    public static function delete_data_for_all_users_in_context(context $context): void {
        if (!$context instanceof context_system) {
            return;
        }
        self::purge();
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_system) {
                self::purge((int) $contextlist->get_user()->id);
            }
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_system) {
            return;
        }
        foreach ($userlist->get_userids() as $userid) {
            self::purge((int) $userid);
        }
    }

    /**
     * Delete stored data for one user (or everyone when $userid is null).
     *
     * @param int|null $userid
     */
    protected static function purge(?int $userid = null): void {
        global $DB;

        $conditions = $userid ? ['userid' => $userid] : [];
        $fs = get_file_storage();
        $context = context_system::instance();

        $sheets = $DB->get_records('local_exams_resultsheets', $conditions);
        foreach ($sheets as $sheet) {
            $fs->delete_area_files($context->id, 'local_exams', 'resultsheet', $sheet->id);
        }
        $DB->delete_records('local_exams_resultsheets', $conditions);
        $DB->delete_records('local_exams_attempt_stats', $conditions);
        $DB->delete_records('local_exams_stats_queue', $conditions);
    }
}
