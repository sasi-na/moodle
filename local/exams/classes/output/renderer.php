<?php
namespace local_exams\output;

defined('MOODLE_INTERNAL') || die();

use local_exams\manager;
use local_exams\analytics\scorecard;
use local_exams\analytics\stats_cache;
use local_exams\analytics\cohort_stats;

/**
 * Renderer for the exam portal pages.
 *
 * Each method returns HTML built from a Mustache template plus an embedded JSON
 * data-island; the matching AMD module reads the island and draws the charts.
 *
 * @package   local_exams
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {

    /** JSON for embedding in a <script type="application/json"> island. */
    protected function island(array $data): string {
        return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Student exam dashboard: cross-semester scorecard + trends.
     *
     * @param int $userid
     * @return string
     */
    public function student_dashboard(int $userid): string {
        $student = manager::get_student_data($userid);

        if (!manager::results_visible($userid)) {
            return $this->render_from_template('local_exams/student_dashboard', [
                'studentname' => $student->fullname,
                'idnumber'    => $student->idnumber,
                'locked'      => true,
                'lockedmsg'   => get_string('resultsnotreleased', 'local_exams'),
                'datajson'    => $this->island([]),
            ]);
        }

        $card = scorecard::get_for_user($userid);

        $trendlabels = [];
        $trenddata = [];
        foreach ($card['semesters'] as &$sem) {
            $sem['haspercent'] = $sem['percentage'] !== null;
            if ($sem['percentage'] !== null) {
                $trendlabels[] = $sem['name'];
                $trenddata[] = $sem['percentage'];
            }
        }
        unset($sem);

        $overall = $card['overall'];

        $context = [
            'studentname'  => $student->fullname,
            'idnumber'     => $student->idnumber,
            'program'      => $student->program,
            'batch'        => $student->batch,
            'locked'       => false,
            'hasdata'      => !empty($card['semesters']),
            'noresultsmsg' => get_string('noresults', 'local_exams'),
            'overall'      => [
                'percentage' => $overall['percentage'],
                'haspercent' => $overall['percentage'] !== null,
                'totalexams' => $overall['totalexams'],
                'present'    => $overall['present'],
                'absent'     => $overall['absent'],
            ],
            'semesters'    => $card['semesters'],
            'downloadurl'  => (new \moodle_url('/local/exams/download.php'))->out(false),
            'datajson'     => $this->island([
                'trend'      => ['labels' => $trendlabels, 'data' => $trenddata],
                'attendance' => ['present' => $overall['present'], 'absent' => $overall['absent']],
            ]),
        ];

        $this->page->requires->js_call_amd('local_exams/dashboard', 'init');
        return $this->render_from_template('local_exams/student_dashboard', $context);
    }

    /**
     * Single-exam deep result page.
     *
     * @param \mod_quiz\quiz_attempt $attemptobj
     * @return string
     */
    public function single_result(\mod_quiz\quiz_attempt $attemptobj): string {
        global $DB;

        $attemptid = $attemptobj->get_attemptid();
        $quizid = $attemptobj->get_quizid();
        $quiz = $attemptobj->get_quiz();

        $stats = stats_cache::get($attemptid);
        $cohort = cohort_stats::get($quizid);

        // Scale the attempt's raw sumgrades to the quiz grade.
        $grademax = (float) $quiz->grade;
        $usergrade = null;
        if (!empty($quiz->sumgrades) && $stats['sumgrades'] !== null) {
            $usergrade = round($stats['sumgrades'] / $quiz->sumgrades * $grademax, 2);
        }
        $percentage = ($usergrade !== null && $grademax > 0) ? round($usergrade / $grademax * 100, 1) : null;

        $rank = ($usergrade !== null) ? cohort_stats::rank_for($quizid, $usergrade)
                                      : ['rank' => null, 'total' => 0, 'percentile' => null];

        // Pass/fail against the activity gradepass if set.
        $gradepass = (float) $DB->get_field('grade_items',
            'gradepass', ['iteminstance' => $quizid, 'itemmodule' => 'quiz'], IGNORE_MULTIPLE);
        $passed = ($gradepass > 0 && $usergrade !== null) ? ($usergrade >= $gradepass) : null;

        $counts = $stats['counts'];

        // Time-per-question series.
        $qlabels = [];
        $qtimes = [];
        $scatter = [];
        foreach ($stats['perquestion'] as $i => $pq) {
            $qlabels[] = 'Q' . ($i + 1);
            $qtimes[] = $pq['timetaken'];
            if ($pq['timetaken'] !== null && $pq['fraction'] !== null) {
                $scatter[] = ['x' => $pq['timetaken'], 'y' => round($pq['fraction'] * 100, 1)];
            }
        }

        $topiclabels = array_column($stats['topics'], 'tag');
        $topicvals = array_column($stats['topics'], 'percentage');

        $context = [
            'quizname'   => format_string($quiz->name),
            'percentage' => $percentage,
            'haspercent' => $percentage !== null,
            'usergrade'  => $usergrade,
            'grademax'   => round($grademax, 2),
            'passed'     => $passed,
            'haspass'    => $passed !== null,
            'rank'       => $rank['rank'],
            'ranktotal'  => $rank['total'],
            'percentile' => $rank['percentile'],
            'haspercentile' => $rank['percentile'] !== null,
            'timetaken'  => $stats['timetaken'] !== null ? format_time($stats['timetaken']) : '-',
            'accuracy'   => $stats['accuracy'],
            'counts'     => $counts,
            'hastopics'  => !empty($topiclabels),
            'perquestion'=> $stats['perquestion'],
            'datajson'   => $this->island([
                'percentile'  => $rank['percentile'],
                'comparison'  => [
                    'you'    => $usergrade,
                    'mean'   => $cohort['mean'],
                    'median' => $cohort['median'],
                    'topper' => $cohort['max'],
                ],
                'outcome'     => [$counts['correct'], $counts['partial'], $counts['incorrect'], $counts['unanswered'] + $counts['pending']],
                'topics'      => ['labels' => $topiclabels, 'data' => $topicvals],
                'qtime'       => ['labels' => $qlabels, 'data' => $qtimes],
                'scatter'     => $scatter,
                'distribution'=> $cohort['histogram'] ?? [],
            ]),
        ];

        $this->page->requires->js_call_amd('local_exams/result', 'init');
        return $this->render_from_template('local_exams/result', $context);
    }

    /**
     * Attempt-history page for a user on a quiz.
     *
     * @param int $userid
     * @param int $quizid
     * @return string
     */
    public function attempt_history(int $userid, int $quizid): string {
        $data = \local_exams\analytics\attempt_history::get_for_quiz($userid, $quizid);
        return $this->render_from_template('local_exams/attempt_history', $data);
    }

    /**
     * Client-side exam runner.
     *
     * @param array $payload The exam payload from payload_builder::build().
     * @param array|null $proctorcfg Proctor client config, or null when not proctored.
     * @return string
     */
    public function runner(array $payload, ?array $proctorcfg = null): string {
        $questions = [];
        foreach ($payload['questions'] as $i => $q) {
            $questions[] = [
                'slot'    => $q['slot'],
                'number'  => $q['number'],
                'html'    => $q['html'],
                'first'   => $i === 0,
                'index'   => $i,
            ];
        }

        $proctored = $proctorcfg !== null;
        $context = [
            'quizname'     => $payload['quizname'],
            'token'        => $payload['token'],
            'attemptid'    => $payload['attemptid'],
            'questions'    => $questions,
            'numquestions' => count($questions),
            'instructions' => get_string('runner_instructions', 'local_exams'),
            'proctor'      => $proctored,
            'wantmic'      => $proctored && !empty($proctorcfg['audiomonitor']),
            'wantscreen'   => $proctored && !empty($proctorcfg['screenrecord']),
            'datajson'     => $this->island([
                'token'     => $payload['token'],
                'attemptid' => $payload['attemptid'],
                'timestart' => $payload['timestart'],
                'timelimit' => $payload['timelimit'],
                'servernow' => $payload['servernow'],
                'navmethod' => $payload['navmethod'],
                'slots'     => array_column($questions, 'slot'),
            ]),
        ];

        return $this->render_from_template('local_exams/runner', $context);
    }

    /**
     * Faculty analytics dashboard: list of exams with cohort headline stats.
     *
     * @return string
     */
    public function faculty_dashboard(): string {
        global $USER;
        $data = \local_exams\analytics\faculty::exam_list($USER->id);

        $labels = [];
        $means = [];
        foreach ($data['exams'] as $e) {
            if ($e['meanpct'] !== null) {
                $labels[] = $e['quizname'];
                $means[] = $e['meanpct'];
            }
        }

        $context = [
            'exams'    => $data['exams'],
            'hasdata'  => $data['hasdata'],
            'overall'  => $data['overall'],
            'haspct'   => $data['overall']['avgpct'] !== null,
            'datajson' => $this->island(['means' => ['labels' => $labels, 'data' => $means]]),
        ];

        $this->page->requires->js_call_amd('local_exams/faculty', 'init');
        return $this->render_from_template('local_exams/faculty_dashboard', $context);
    }

    /**
     * Faculty deep analysis for one exam: distribution, item analysis, toppers.
     *
     * @param int $quizid
     * @return string
     */
    public function faculty_exam(int $quizid): string {
        global $USER;
        $a = \local_exams\analytics\faculty::exam_analysis($quizid, $USER->id);
        $cohort = $a['cohort'];

        $context = [
            'quizname'     => $a['quizname'],
            'attemptcount' => $a['attemptcount'],
            'mean'         => $cohort['mean'],
            'median'       => $cohort['median'],
            'sd'           => $cohort['sd'],
            'max'          => $cohort['max'],
            'count'        => $cohort['count'],
            'items'        => $a['items'],
            'toppers'      => $a['toppers'],
            'hastoppers'   => !empty($a['toppers']),
            'backurl'      => (new \moodle_url('/local/exams/index.php', ['view' => 'faculty']))->out(false),
            'datajson'     => $this->island([
                'distribution' => $cohort['histogram'] ?? [],
                'items'        => [
                    'labels' => array_map(function($i) {
                        return 'Q' . $i['slot'];
                    }, $a['items']),
                    'data'   => array_column($a['items'], 'facility'),
                ],
                'topics'       => [
                    'labels' => array_column($a['topics'], 'tag'),
                    'data'   => array_column($a['topics'], 'percentage'),
                ],
            ]),
        ];

        $this->page->requires->js_call_amd('local_exams/faculty', 'initExam');
        return $this->render_from_template('local_exams/faculty_exam', $context);
    }
}
