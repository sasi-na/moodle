<?php
namespace local_dashboardpoll;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;

/**
 * Web services backing the dashboard poll popup.
 *
 * @package   local_dashboardpoll
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external extends external_api {

    /** Shared shape of the poll payload returned by both endpoints. */
    private static function poll_structure(): external_single_structure {
        return new external_single_structure([
            'hasactive'   => new external_value(PARAM_BOOL, 'Whether an active poll targets this user'),
            'pollid'      => new external_value(PARAM_INT, 'Poll id (0 if none)'),
            'question'    => new external_value(PARAM_RAW, 'Poll question'),
            'multiselect' => new external_value(PARAM_BOOL, 'Whether multiple options may be selected'),
            'maxselect'   => new external_value(PARAM_INT, 'Max selections for a multi-select poll (0 = no limit)'),
            'answered'    => new external_value(PARAM_BOOL, 'Whether the user has already answered'),
            'showresults' => new external_value(PARAM_BOOL, 'Whether to show results (vs a thank-you card) after voting'),
            'showpercent' => new external_value(PARAM_BOOL, 'Whether to show the per-option percentage on the results card'),
            'showvotecounts' => new external_value(PARAM_BOOL, 'Whether to show the per-option vote count on the results card'),
            'totalvotes'  => new external_value(PARAM_INT, 'Number of respondents (0 until the user has answered)'),
            'options'    => new external_multiple_structure(new external_single_structure([
                'id'       => new external_value(PARAM_INT, 'Option id'),
                'text'     => new external_value(PARAM_RAW, 'Option text'),
                'votes'    => new external_value(PARAM_INT, 'Vote count (0 until answered)'),
                'percent'  => new external_value(PARAM_INT, 'Share of votes 0-100 (0 until answered)'),
                'selected' => new external_value(PARAM_BOOL, 'Whether the current user has this option saved'),
            ])),
        ]);
    }

    /** Empty payload used when there is nothing to show. */
    private static function empty_payload(): array {
        return [
            'hasactive'   => false,
            'pollid'      => 0,
            'question'    => '',
            'multiselect' => false,
            'maxselect'   => 0,
            'answered'    => false,
            'showresults' => false,
            'showpercent' => false,
            'showvotecounts' => false,
            'totalvotes'  => 0,
            'options'     => [],
        ];
    }

    // ---- get_active ---------------------------------------------------------

    public static function get_active_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    public static function get_active_returns(): external_single_structure {
        return self::poll_structure();
    }

    /**
     * Resolve the active poll for the current user.
     *
     * @return array
     */
    public static function get_active(): array {
        global $USER;
        self::validate_parameters(self::get_active_parameters(), []);
        self::validate_context(\context_system::instance());

        if (!manager::is_student((int) $USER->id)) {
            return self::empty_payload();
        }

        $poll = manager::active_poll_for_user((int) $USER->id);
        if (!$poll) {
            return self::empty_payload();
        }

        return manager::payload($poll, (int) $USER->id);
    }

    // ---- submit -------------------------------------------------------------

    public static function submit_parameters(): external_function_parameters {
        return new external_function_parameters([
            'pollid'    => new external_value(PARAM_INT, 'Poll id'),
            'optionids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Chosen option id'),
                'Selected option ids (one for single-choice, several for multi-select)'),
        ]);
    }

    public static function submit_returns(): external_single_structure {
        return self::poll_structure();
    }

    /**
     * Record the current user's answer(s) and return the aggregate results.
     *
     * @param int $pollid
     * @param int[] $optionids
     * @return array
     */
    public static function submit(int $pollid, array $optionids): array {
        global $USER, $DB;
        self::validate_parameters(self::submit_parameters(),
            ['pollid' => $pollid, 'optionids' => $optionids]);
        self::validate_context(\context_system::instance());

        $userid = (int) $USER->id;
        if (!manager::is_student($userid)) {
            throw new \moodle_exception('nopermissions', 'error', '', 'submit');
        }

        $poll = manager::poll($pollid);
        if (!$poll || $poll->status !== 'active') {
            throw new \moodle_exception('invalidrecord', 'error', '', 'poll');
        }
        if (!manager::user_eligible_for_poll($poll, $userid)) {
            throw new \moodle_exception('nopermissions', 'error', '', 'poll');
        }

        // Normalise the selection: unique, at least one, within the poll's
        // single/multi rules, and never above the cap. Submitting fewer than the
        // required number is allowed — the picks are saved and the popup returns
        // until the student reaches the required count.
        $optionids = array_values(array_unique(array_filter($optionids)));
        if (empty($optionids)) {
            throw new \moodle_exception('invalidrecord', 'error', '', 'option');
        }
        if (!$poll->multiselect && count($optionids) > 1) {
            throw new \moodle_exception('invalidrecord', 'error', '', 'option');
        }
        if ($poll->multiselect && $poll->maxchoices > 0 && count($optionids) > $poll->maxchoices) {
            throw new \moodle_exception('toomanychoices', 'local_dashboardpoll');
        }
        foreach ($optionids as $optionid) {
            if (!$DB->record_exists('local_dashboardpoll_options', ['id' => $optionid, 'pollid' => $pollid])) {
                throw new \moodle_exception('invalidrecord', 'error', '', 'option');
            }
        }

        // Replace the user's saved picks with the submitted set so they can
        // adjust an in-progress answer across dashboard loads. Once the poll is
        // complete, results are revealed and the popup no longer appears, so a
        // completed answer is never re-opened for editing.
        if (!manager::is_complete($poll, $userid)) {
            $now = time();
            $transaction = $DB->start_delegated_transaction();
            $DB->delete_records('local_dashboardpoll_responses', ['pollid' => $pollid, 'userid' => $userid]);
            foreach ($optionids as $optionid) {
                $DB->insert_record('local_dashboardpoll_responses', (object) [
                    'pollid'      => $pollid,
                    'optionid'    => $optionid,
                    'userid'      => $userid,
                    'timecreated' => $now,
                ]);
            }
            $transaction->allow_commit();
            manager::purge_aggregate($pollid);
        }

        $poll = manager::poll($pollid);
        return manager::payload($poll, $userid);
    }
}
