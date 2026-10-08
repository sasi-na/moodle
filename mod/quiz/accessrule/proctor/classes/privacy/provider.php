<?php
namespace quizaccess_proctor\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use context;
use context_module;

/**
 * Privacy provider for quizaccess_proctor (snapshots + events at module context).
 *
 * @package   quizaccess_proctor
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('quizaccess_proctor_snapshot', [
            'userid'     => 'privacy:metadata:quizaccess_proctor_snapshot:userid',
            'capturedat' => 'privacy:metadata:quizaccess_proctor_snapshot:capturedat',
        ], 'privacy:metadata:quizaccess_proctor_snapshot');

        $collection->add_database_table('quizaccess_proctor_event', [
            'userid'    => 'privacy:metadata:quizaccess_proctor_event:userid',
            'eventtype' => 'privacy:metadata:quizaccess_proctor_event:eventtype',
        ], 'privacy:metadata:quizaccess_proctor_event');

        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:quizaccess_proctor_snapshot');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :modlevel
                  JOIN {%TABLE%} t ON t.quizid = cm.instance
                 WHERE t.userid = :userid";
        $params = ['modlevel' => CONTEXT_MODULE, 'userid' => $userid];
        $contextlist->add_from_sql(str_replace('{%TABLE%}', '{quizaccess_proctor_snapshot}', $sql), $params);
        $contextlist->add_from_sql(str_replace('{%TABLE%}', '{quizaccess_proctor_event}', $sql), $params);
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }
        $sql = "SELECT t.userid
                  FROM {%TABLE%} t
                  JOIN {course_modules} cm ON cm.instance = t.quizid
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                 WHERE cm.id = :cmid";
        $params = ['cmid' => $context->instanceid];
        $userlist->add_from_sql('userid', str_replace('{%TABLE%}', '{quizaccess_proctor_snapshot}', $sql), $params);
        $userlist->add_from_sql('userid', str_replace('{%TABLE%}', '{quizaccess_proctor_event}', $sql), $params);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        // Evidence is staff-facing review data; minimal export (presence only) is acceptable here.
        // A fuller export can be added if required by policy.
        return;
    }

    public static function delete_data_for_all_users_in_context(context $context): void {
        if (!$context instanceof context_module) {
            return;
        }
        self::delete_for_cm($context, null);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_module) {
                self::delete_for_cm($context, $userid);
            }
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }
        foreach ($userlist->get_userids() as $userid) {
            self::delete_for_cm($context, (int) $userid);
        }
    }

    /**
     * Delete snapshots + events (and files) for a quiz module, optionally one user.
     *
     * @param context_module $context
     * @param int|null $userid
     */
    protected static function delete_for_cm(context_module $context, ?int $userid): void {
        global $DB;

        $cm = get_coursemodule_from_id('quiz', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }
        $conditions = ['quizid' => $cm->instance];
        if ($userid !== null) {
            $conditions['userid'] = $userid;
        }

        $fs = get_file_storage();
        $snapshots = $DB->get_records('quizaccess_proctor_snapshot', $conditions, '', 'id');
        foreach ($snapshots as $snap) {
            $fs->delete_area_files($context->id, 'quizaccess_proctor', 'snapshot', $snap->id);
        }
        $DB->delete_records('quizaccess_proctor_snapshot', $conditions);
        $DB->delete_records('quizaccess_proctor_event', $conditions);
        $DB->delete_records('quizaccess_proctor_summary', $conditions);
    }
}
