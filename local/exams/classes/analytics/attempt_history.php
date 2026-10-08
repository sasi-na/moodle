<?php
namespace local_exams\analytics;

defined('MOODLE_INTERNAL') || die();

/**
 * Attempt history for a user on a quiz, read from the stock quiz tables.
 *
 * @package   local_exams
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attempt_history {

    /**
     * All of the user's attempts at a quiz, newest first, scaled to the quiz grade.
     *
     * @param int $userid
     * @param int $quizid
     * @return array {quizname, grademax, attempts: [...]}
     */
    public static function get_for_quiz(int $userid, int $quizid): array {
        global $DB;

        $quiz = $DB->get_record('quiz', ['id' => $quizid], 'id, name, grade, sumgrades', MUST_EXIST);
        $grademax = (float) $quiz->grade;

        $records = $DB->get_records('quiz_attempts',
            ['quiz' => $quizid, 'userid' => $userid],
            'attempt DESC',
            'id, attempt, state, timestart, timefinish, sumgrades');

        $attempts = [];
        foreach ($records as $r) {
            $grade = null;
            $pct = null;
            if ($r->sumgrades !== null && !empty($quiz->sumgrades)) {
                $grade = round($r->sumgrades / $quiz->sumgrades * $grademax, 2);
                $pct = $grademax > 0 ? round($grade / $grademax * 100, 1) : null;
            }
            $attempts[] = [
                'attemptid'  => (int) $r->id,
                'attempt'    => (int) $r->attempt,
                'state'      => $r->state,
                'finished'   => $r->state === 'finished',
                'timestart'  => $r->timestart ? userdate($r->timestart) : '-',
                'duration'   => ($r->timefinish && $r->timestart) ? format_time($r->timefinish - $r->timestart) : '-',
                'grade'      => $grade,
                'pctlabel'   => $pct === null ? '-' : ($pct . '%'),
                'resulturl'  => (new \moodle_url('/local/exams/result.php', ['attempt' => $r->id]))->out(false),
            ];
        }

        return [
            'quizname'  => format_string($quiz->name),
            'grademax'  => round($grademax, 2),
            'attempts'  => $attempts,
            'hasdata'   => !empty($attempts),
        ];
    }
}
