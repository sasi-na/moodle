<?php
namespace local_exams\runner;

defined('MOODLE_INTERNAL') || die();

use mod_quiz\quiz_settings;
use mod_quiz\quiz_attempt;

/**
 * Builds the single JSON exam payload delivered to the client runner.
 *
 * Starts (or resumes) a normal Moodle quiz attempt so the question engine stays
 * the source of truth, renders every question to HTML once, and returns it all
 * in one go. After this, the attempt is taken fully client-side with no further
 * per-question DB writes until the batched materialization at submit.
 *
 * @package   local_exams
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payload_builder {

    /** Grace seconds added to the token expiry beyond the time limit. */
    const GRACE = 120;

    /**
     * Whether the runner is enabled for a quiz.
     *
     * @param int $quizid
     * @return bool
     */
    public static function runner_enabled(int $quizid): bool {
        global $DB;
        return (bool) $DB->get_field('local_exams_runner_quiz', 'enabled', ['quizid' => $quizid]);
    }

    /**
     * Start or resume a runner attempt and build its payload.
     *
     * @param int $cmid Quiz course-module id.
     * @param int $userid
     * @param \moodle_url $pageurl The runner page URL (used for question rendering).
     * @return array The JSON-able exam payload.
     */
    public static function build(int $cmid, int $userid, \moodle_url $pageurl): array {
        global $DB, $PAGE;

        $quizobj = quiz_settings::create_for_cmid($cmid, $userid);
        $quiz = $quizobj->get_quiz();
        $timenow = time();

        // Basic access gate (SEB / proctor preflight integrate here later).
        require_capability('mod/quiz:attempt', $quizobj->get_context(), $userid);
        $accessmanager = $quizobj->get_access_manager($timenow);
        if ($messages = $accessmanager->prevent_access()) {
            throw new \moodle_exception('attempterror', 'quiz', '', implode(' ', $messages));
        }

        // Resume an unfinished attempt if one exists; else start a new one.
        $unfinished = quiz_get_user_attempt_unfinished($quiz->id, $userid);
        if ($unfinished) {
            $attempt = $unfinished;
        } else {
            $attempts = quiz_get_user_attempts($quiz->id, $userid, 'finished', true);
            $lastattempt = $attempts ? end($attempts) : false;
            $attemptnumber = $lastattempt ? $lastattempt->attempt + 1 : 1;

            if ($notallowed = $accessmanager->prevent_new_attempt(count($attempts), $lastattempt ?: null)) {
                throw new \moodle_exception('attempterror', 'quiz', '', implode(' ', $notallowed));
            }

            $attempt = quiz_prepare_and_start_new_attempt(
                $quizobj, $attemptnumber, $lastattempt ?: null, false, [], [], $userid);
        }

        $attemptobj = quiz_attempt::create($attempt->id);

        // Track the runner attempt lifecycle.
        if (!$DB->record_exists('local_exams_runner_attempt', ['attemptid' => $attempt->id])) {
            $DB->insert_record('local_exams_runner_attempt', (object) [
                'attemptid'   => (int) $attempt->id,
                'quizid'      => (int) $quiz->id,
                'userid'      => (int) $userid,
                'status'      => 'inprogress',
                'answersjson' => null,
                'timecreated' => $timenow,
            ]);
        }

        // Render each question once.
        $renderer = $PAGE->get_renderer('mod_quiz');
        $questions = [];
        foreach ($attemptobj->get_slots() as $i => $slot) {
            if (!$attemptobj->is_real_question($slot)) {
                continue; // Skip descriptions.
            }
            $questions[] = [
                'slot'   => $slot,
                'number' => $attemptobj->get_question_number($slot),
                'html'   => $attemptobj->render_question($slot, false, $renderer, $pageurl),
            ];
        }

        $timelimit = (int) $quiz->timelimit; // Seconds, 0 = none.
        $timestart = (int) $attempt->timestart;

        $tokenpayload = [
            'attemptid' => (int) $attempt->id,
            'userid'    => (int) $userid,
            'quizid'    => (int) $quiz->id,
            'timestart' => $timestart,
            'timelimit' => $timelimit,
            'exp'       => $timelimit ? ($timestart + $timelimit + self::GRACE) : ($timenow + DAYSECS),
        ];

        return [
            'attemptid' => (int) $attempt->id,
            'token'     => token::sign($tokenpayload),
            'quizname'  => format_string($quiz->name),
            'navmethod' => $quiz->navmethod,
            'timestart' => $timestart,
            'timelimit' => $timelimit,
            'servernow' => $timenow,
            'questions' => $questions,
            'cmid'      => $cmid,
        ];
    }
}
