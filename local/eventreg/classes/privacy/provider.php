<?php
namespace local_eventreg\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use context_system;

/**
 * Privacy provider for local_eventreg — stores one submission per user per form
 * plus that submission's answers.
 *
 * @package   local_eventreg
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\core_userlist_provider,
        \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_eventreg_submissions', [
            'formid'      => 'privacy:metadata:submissions:formid',
            'userid'      => 'privacy:metadata:submissions:userid',
            'timecreated' => 'privacy:metadata:submissions:timecreated',
        ], 'privacy:metadata:submissions');
        $collection->add_database_table('local_eventreg_answers', [
            'fieldid' => 'privacy:metadata:answers:fieldid',
            'value'   => 'privacy:metadata:answers:value',
        ], 'privacy:metadata:answers');
        // File-upload questions keep the learner's attachments in the file API.
        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:files');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if ($DB->record_exists('local_eventreg_submissions', ['userid' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context instanceof context_system) {
            $userlist->add_from_sql('userid', 'SELECT userid FROM {local_eventreg_submissions}', []);
        }
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        if (!in_array(CONTEXT_SYSTEM, array_map(fn($c) => $c->contextlevel, $contextlist->get_contexts()))) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        $submissions = $DB->get_records('local_eventreg_submissions', ['userid' => $userid]);
        $data = [];
        $systemcontext = context_system::instance();
        foreach ($submissions as $sub) {
            // The question type comes along so file answers can also export their
            // attachments, keyed by answer id like the file area itself.
            $answers = $DB->get_records_sql(
                "SELECT a.id, a.fieldid, a.value, f.type
                   FROM {local_eventreg_answers} a
              LEFT JOIN {local_eventreg_fields} f ON f.id = a.fieldid
                  WHERE a.submissionid = :sid", ['sid' => $sub->id]);
            $answerdata = [];
            foreach ($answers as $a) {
                $answerdata[] = (object) ['fieldid' => $a->fieldid, 'value' => $a->value];
                if ($a->type === 'file') {
                    // The writer appends "_files/<area>/<itemid>" itself, so the
                    // attachments already land in a folder of their own per answer.
                    writer::with_context($systemcontext)->export_area_files(
                        [get_string('pluginname', 'local_eventreg')],
                        \local_eventreg\form_builder::FILE_COMPONENT,
                        \local_eventreg\form_builder::FILE_AREA,
                        $a->id);
                }
            }
            $data[] = (object) [
                'formid'      => $sub->formid,
                'timecreated' => \core_privacy\local\request\transform::datetime($sub->timecreated),
                'answers'     => $answerdata,
            ];
        }
        if ($data) {
            writer::with_context(context_system::instance())->export_data(
                [get_string('pluginname', 'local_eventreg')],
                (object) ['submissions' => $data]
            );
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context instanceof context_system) {
            get_file_storage()->delete_area_files($context->id,
                \local_eventreg\form_builder::FILE_COMPONENT,
                \local_eventreg\form_builder::FILE_AREA);
            $DB->delete_records('local_eventreg_answers');
            $DB->delete_records('local_eventreg_submissions');
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_system) {
                self::delete_user_submissions($contextlist->get_user()->id);
            }
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context instanceof context_system) {
            foreach ($userlist->get_userids() as $userid) {
                self::delete_user_submissions($userid);
            }
        }
    }

    /** Delete a user's submissions, their answers and any uploaded attachments. */
    protected static function delete_user_submissions(int $userid): void {
        global $DB;
        $subids = $DB->get_fieldset_select('local_eventreg_submissions', 'id', 'userid = :u', ['u' => $userid]);
        if ($subids) {
            list($insql, $params) = $DB->get_in_or_equal($subids, SQL_PARAMS_NAMED);
            $answerids = $DB->get_fieldset_select('local_eventreg_answers', 'id', "submissionid $insql", $params);
            $fs = get_file_storage();
            $contextid = context_system::instance()->id;
            foreach ($answerids as $answerid) {
                $fs->delete_area_files($contextid,
                    \local_eventreg\form_builder::FILE_COMPONENT,
                    \local_eventreg\form_builder::FILE_AREA,
                    $answerid);
            }
            $DB->delete_records_select('local_eventreg_answers', "submissionid $insql", $params);
        }
        $DB->delete_records('local_eventreg_submissions', ['userid' => $userid]);
    }
}
