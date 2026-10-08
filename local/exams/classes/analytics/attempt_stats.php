<?php
namespace local_exams\analytics;

defined('MOODLE_INTERNAL') || die();

/**
 * Per-attempt deep analytics computed from the question engine.
 *
 * Produces accuracy, time-per-question, outcome breakdown, and topic (tag)
 * performance for a single quiz attempt. The result is a plain array suitable
 * for caching as JSON in {local_exams_attempt_stats}.
 *
 * @package   local_exams
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attempt_stats {

    /**
     * Compute the stats for one attempt.
     *
     * @param int $attemptid
     * @return array
     */
    public static function compute(int $attemptid): array {
        $attemptobj = \mod_quiz\quiz_attempt::create($attemptid);
        $attempt = $attemptobj->get_attempt();

        $correct = $partial = $incorrect = $unanswered = $pending = 0;
        $perquestion = [];
        $topics = []; // tag => ['sum'=>fractionsum, 'count'=>n].

        foreach ($attemptobj->get_slots() as $slot) {
            try {
                $qa = $attemptobj->get_question_attempt($slot);
            } catch (\Exception $e) {
                continue;
            }

            $fraction = $qa->get_fraction(); // 0..1 or null.
            $maxmark  = (float) $qa->get_max_mark();
            $mark     = ($fraction === null) ? null : round($fraction * $maxmark, 2);
            $state    = $qa->get_state();

            if ($fraction === null) {
                if ($state == \question_state::$needsgrading) {
                    $pending++;
                    $outcome = 'pending';
                } else {
                    $unanswered++;
                    $outcome = 'unanswered';
                }
            } else if ($fraction >= 0.9999) {
                $correct++;
                $outcome = 'correct';
            } else if ($fraction > 0) {
                $partial++;
                $outcome = 'partial';
            } else {
                $incorrect++;
                $outcome = 'incorrect';
            }

            // Time spent on the question: span of its step timestamps.
            $first = $last = null;
            foreach ($qa->get_step_iterator() as $step) {
                $t = $step->get_timecreated();
                if ($first === null) {
                    $first = $t;
                }
                $last = $t;
            }
            $qtime = ($first !== null && $last !== null) ? max(0, $last - $first) : null;

            $question = $qa->get_question();
            $perquestion[] = [
                'slot'       => $slot,
                'name'       => format_string($question->name),
                'fraction'   => $fraction === null ? null : round($fraction, 3),
                'mark'       => $mark,
                'maxmark'    => round($maxmark, 2),
                'timetaken'  => $qtime,
                'outcome'    => $outcome,
            ];

            // Topic performance from question tags.
            foreach (self::question_tags((int) $question->id) as $tag) {
                if (!isset($topics[$tag])) {
                    $topics[$tag] = ['sum' => 0.0, 'count' => 0];
                }
                $topics[$tag]['sum'] += ($fraction === null ? 0 : $fraction);
                $topics[$tag]['count']++;
            }
        }

        $answered = $correct + $partial + $incorrect;
        $totalq = $answered + $unanswered + $pending;

        $topicout = [];
        foreach ($topics as $tag => $agg) {
            $topicout[] = [
                'tag'        => $tag,
                'count'      => $agg['count'],
                'percentage' => $agg['count'] > 0 ? round($agg['sum'] / $agg['count'] * 100, 1) : 0,
            ];
        }

        $timetaken = null;
        if (!empty($attempt->timefinish) && !empty($attempt->timestart)) {
            $timetaken = max(0, (int) $attempt->timefinish - (int) $attempt->timestart);
        }

        $sumgrades = $attempt->sumgrades === null ? null : (float) $attempt->sumgrades;

        return [
            'attemptid'  => $attemptid,
            'quizid'     => (int) $attemptobj->get_quizid(),
            'userid'     => (int) $attempt->userid,
            'state'      => $attempt->state,
            'sumgrades'  => $sumgrades,
            'timetaken'  => $timetaken,
            'counts'     => [
                'total'      => $totalq,
                'correct'    => $correct,
                'partial'    => $partial,
                'incorrect'  => $incorrect,
                'unanswered' => $unanswered,
                'pending'    => $pending,
            ],
            'accuracy'   => $answered > 0 ? round($correct / $answered * 100, 1) : null,
            'attemptrate'=> $totalq > 0 ? round($answered / $totalq * 100, 1) : null,
            'perquestion'=> $perquestion,
            'topics'     => $topicout,
        ];
    }

    /**
     * Tag names for a question (best-effort; empty array if tagging unavailable).
     *
     * @param int $questionid
     * @return string[]
     */
    protected static function question_tags(int $questionid): array {
        $names = [];
        try {
            $tags = \core_tag_tag::get_item_tags('core_question', 'question', $questionid);
            foreach ($tags as $tag) {
                $names[] = $tag->get_display_name();
            }
        } catch (\Exception $e) {
            return [];
        }
        return $names;
    }
}
