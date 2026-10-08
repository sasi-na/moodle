<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_reflectiveassessment;

defined('MOODLE_INTERNAL') || die();

/**
 * Guide-course summary: one row per Reflective Assessment guide course.
 *
 * Progress is read from the question's final state, not the gradebook: a blank
 * submission is auto-scored zero on finish and would otherwise count as graded.
 * Manual grading states = evaluated, needsgrading = awaiting, rest = blank.
 *
 * @package   local_reflectiveassessment
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class summary {

    /** @var string Default LIKE pattern selecting the guide courses. */
    const DEFAULT_COURSE_PATTERN = '%Reflective Assessment%';

    /** @var string Default LIKE pattern selecting the assessment quiz within them. */
    const DEFAULT_QUIZ_PATTERN = '%Reflective Assessment%';

    /** @var string Batch categories reported on; 17 is July, 2025 Batch. Empty means all. */
    const DEFAULT_BATCH_CATEGORIES = '17';

    /**
     * @var string Accounts that hold a teacher role in the guide courses but are
     * not the guide: shared per-programme "DSM" mailboxes, the content team and
     * the site admin.
     */
    const DEFAULT_SERVICE_ACCOUNTS = 'deekshitha.l@usdcglobal.com
muthulakshmi@onlinejain.com
comm@jainonline.info
mbajuly25@onlinejain.com
majuly25@onlinejain.com
mcajuly25@onlinejain.com
bcajuly25@onlinejain.com
bcomjuly25@onlinejain.com
bbajuly25@onlinejain.com
mcomjuly25@onlinejain.com';

    /** @var string Guide account states. */
    const GUIDE_ACTIVE = 'active';
    /** @var string */
    const GUIDE_SUSPENDED = 'suspended';
    /** @var string */
    const GUIDE_DELETED = 'deleted';
    /** @var string */
    const GUIDE_UNRESOLVED = 'unresolved';

    /**
     * Read a configured LIKE pattern, falling back to the shipped default.
     *
     * @param string $name setting name
     * @param string $default default pattern
     * @return string
     */
    protected static function pattern(string $name, string $default): string {
        $value = trim((string) get_config('local_reflectiveassessment', $name));
        return $value === '' ? $default : $value;
    }

    /**
     * Email addresses that must never be reported as a guide or an evaluator.
     *
     * @return string[] lower-cased addresses
     */
    public static function service_accounts(): array {
        $raw = get_config('local_reflectiveassessment', 'serviceaccounts');
        if ($raw === false || trim((string) $raw) === '') {
            $raw = self::DEFAULT_SERVICE_ACCOUNTS;
        }
        $parts = preg_split('/[\s,;]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);
        return array_map('strtolower', array_map('trim', $parts));
    }

    /**
     * Every guide course on the site, with batch/programme/semester resolved.
     *
     * @return \stdClass[] keyed by course id
     */
    public static function get_courses(): array {
        global $DB;

        $pattern = self::pattern('coursepattern', self::DEFAULT_COURSE_PATTERN);
        $like = $DB->sql_like('c.fullname', ':pattern', false);
        $courses = $DB->get_records_sql("
                SELECT c.id, c.shortname, c.fullname, c.category, c.visible, cc.path
                  FROM {course} c
                  JOIN {course_categories} cc ON cc.id = c.category
                 WHERE {$like}
              ORDER BY c.shortname ASC", ['pattern' => $pattern]);

        if (!$courses) {
            return [];
        }

        $names = $DB->get_records_menu('course_categories', null, '', 'id, name');
        foreach ($courses as $course) {
            $chain = array_values(array_filter(explode('/', $course->path)));
            $depth = count($chain);
            $course->semesterid = $depth >= 1 ? (int) $chain[$depth - 1] : 0;
            $course->programid  = $depth >= 2 ? (int) $chain[$depth - 2] : 0;
            $course->batchid    = $depth >= 3 ? (int) $chain[$depth - 3] : 0;
            $course->semester = $course->semesterid ? trim($names[$course->semesterid] ?? '') : '';
            $course->program  = $course->programid ? trim($names[$course->programid] ?? '') : '';
            $course->batch    = $course->batchid ? trim($names[$course->batchid] ?? '') : '';
        }

        return $courses;
    }

    /**
     * Batch category ids the report is limited to, empty meaning all of them.
     *
     * @return int[]
     */
    public static function batch_filter(): array {
        $raw = get_config('local_reflectiveassessment', 'batchcategories');
        if ($raw === false) {
            $raw = self::DEFAULT_BATCH_CATEGORIES;
        }
        return array_values(array_filter(array_map('intval',
            preg_split('/[\s,]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY))));
    }

    /**
     * The batch/semester periods that guide courses exist for.
     *
     * @return \stdClass[] keyed by period key, newest batch first
     */
    public static function get_periods(): array {
        $batches = self::batch_filter();
        $periods = [];
        foreach (self::get_courses() as $course) {
            if ($batches && !in_array((int) $course->batchid, $batches, true)) {
                continue;
            }
            $key = $course->batchid . '|' . $course->semester;
            if (!isset($periods[$key])) {
                $periods[$key] = (object) [
                    'key'       => $key,
                    'batchid'   => $course->batchid,
                    'batch'     => $course->batch,
                    'semester'  => $course->semester,
                    'label'     => trim($course->batch . ' — ' . $course->semester, ' —'),
                    'courseids' => [],
                ];
            }
            $periods[$key]->courseids[] = (int) $course->id;
        }

        uasort($periods, function($a, $b) {
            return [$b->batchid, $a->semester] <=> [$a->batchid, $b->semester];
        });

        return $periods;
    }

    /**
     * Classify a question's final state into one of the three progress buckets.
     *
     * @param string $state question_attempt_steps.state
     * @return string one of evaluated|awaiting|blank
     */
    public static function classify_state(string $state): string {
        if (strpos($state, 'mangr') === 0 || $state === 'mangaveup') {
            return 'evaluated';
        }
        if ($state === 'needsgrading') {
            return 'awaiting';
        }
        return 'blank';
    }

    /**
     * The assessment quiz in each of the given courses, keyed by course id.
     *
     * @param int[] $courseids
     * @return \stdClass[]
     */
    public static function get_quizzes(array $courseids): array {
        global $DB;

        list($insql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');
        $params['qpattern'] = self::pattern('quizpattern', self::DEFAULT_QUIZ_PATTERN);
        $like = $DB->sql_like('q.name', ':qpattern', false);

        $quizzes = [];
        $rs = $DB->get_records_sql("
                SELECT q.id, q.course, q.name, q.grade
                  FROM {quiz} q
                 WHERE q.course {$insql} AND {$like}
              ORDER BY q.course ASC, q.id ASC", $params);
        foreach ($rs as $quiz) {
            // One assessment per guide course; oldest wins if there are two.
            if (!isset($quizzes[$quiz->course])) {
                $quizzes[$quiz->course] = $quiz;
            }
        }
        return $quizzes;
    }

    /**
     * Count of students allocated to each guide course, keyed by course id.
     *
     * @param int[] $courseids
     * @return int[]
     */
    protected static function get_allocated(array $courseids): array {
        global $DB;

        list($insql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');
        $params['student'] = 'student';

        return $DB->get_records_sql_menu("
                SELECT ctx.instanceid, COUNT(DISTINCT ra.userid)
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
                  JOIN {role} r ON r.id = ra.roleid AND r.archetype = :student
                  JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
                 WHERE ctx.instanceid {$insql}
              GROUP BY ctx.instanceid", $params);
    }

    /**
     * Correlated EXISTS restricting attempts to currently allocated students.
     *
     * Attempts survive unenrolment and user deletion, so without this a course
     * can report more submissions than it has students.
     *
     * @return string SQL fragment, correlated on the outer aliases qa and q
     */
    public static function allocated_student_sql(): string {
        return "EXISTS (SELECT 1
                          FROM {role_assignments} alra
                          JOIN {context} alctx ON alctx.id = alra.contextid
                               AND alctx.contextlevel = 50
                               AND alctx.instanceid = q.course
                          JOIN {role} alr ON alr.id = alra.roleid
                               AND alr.archetype = 'student'
                          JOIN {user} alu ON alu.id = alra.userid AND alu.deleted = 0
                         WHERE alra.userid = qa.userid)";
    }

    /**
     * Correlated subquery picking a student's latest finished attempt.
     *
     * A handful of students hold two finished attempts; counting both would
     * report more submissions than there are students.
     *
     * @return string SQL fragment, correlated on the outer alias qa
     */
    public static function latest_attempt_sql(): string {
        return "(SELECT MAX(qalatest.id)
                   FROM {quiz_attempts} qalatest
                  WHERE qalatest.quiz = qa.quiz
                    AND qalatest.userid = qa.userid
                    AND qalatest.state = qa.state)";
    }

    /**
     * Progress buckets per quiz, with the marks awarded for evaluated attempts.
     *
     * Each attempt is reduced to its first question so multi-question
     * assessments still yield one row per student.
     *
     * @param int[] $quizids
     * @return array quizid => ['evaluated'=>['n'=>int,'marks'=>float], ...]
     */
    protected static function get_progress(array $quizids): array {
        global $DB;

        list($insql, $params) = $DB->get_in_or_equal($quizids, SQL_PARAMS_NAMED, 'q');
        $params['finished'] = 'finished';

        $rows = $DB->get_recordset_sql("
                SELECT qa.quiz, qas.state, COUNT(*) AS n, SUM(qa.sumgrades) AS marks
                  FROM {quiz_attempts} qa
                  JOIN {quiz} q ON q.id = qa.quiz
                  JOIN {question_attempts} qat ON qat.id = (
                           SELECT MIN(qat2.id)
                             FROM {question_attempts} qat2
                            WHERE qat2.questionusageid = qa.uniqueid)
                  JOIN {question_attempt_steps} qas ON qas.id = (
                           SELECT MAX(qas2.id)
                             FROM {question_attempt_steps} qas2
                            WHERE qas2.questionattemptid = qat.id)
                 WHERE qa.quiz {$insql} AND qa.state = :finished
                       AND qa.id = " . self::latest_attempt_sql() . "
                       AND " . self::allocated_student_sql() . "
              GROUP BY qa.quiz, qas.state", $params);

        $progress = [];
        foreach ($rows as $row) {
            $bucket = self::classify_state($row->state);
            if (!isset($progress[$row->quiz][$bucket])) {
                $progress[$row->quiz][$bucket] = ['n' => 0, 'marks' => 0.0];
            }
            $progress[$row->quiz][$bucket]['n'] += (int) $row->n;
            $progress[$row->quiz][$bucket]['marks'] += (float) $row->marks;
        }
        $rows->close();

        return $progress;
    }

    /**
     * Who entered the marks, per quiz.
     *
     * The evaluator of record is the author of the latest manual grading step,
     * so a re-mark counts once.
     *
     * @param int[] $quizids
     * @return array quizid => [userid => ['n'=>int, 'marks'=>float, 'first'=>int, 'last'=>int]]
     */
    public static function get_evaluators(array $quizids): array {
        global $DB;

        list($insql, $params) = $DB->get_in_or_equal($quizids, SQL_PARAMS_NAMED, 'q');
        $params['finished'] = 'finished';
        $params['manual'] = 'mangr%';
        $params['gaveup'] = 'mangaveup';
        $params['manualfinal'] = 'mangr%';
        $params['gaveupfinal'] = 'mangaveup';
        $like = $DB->sql_like('qas2.state', ':manual', false);
        $likefinal = $DB->sql_like('fs.state', ':manualfinal', false);

        // The final state must still be a grading state: an attempt marked and
        // later reset counts as awaiting, so it must not count as evaluated here.
        $rows = $DB->get_recordset_sql("
                SELECT qa.quiz, qas.userid, COUNT(*) AS n, SUM(qa.sumgrades) AS marks,
                       MIN(qas.timecreated) AS firstgraded, MAX(qas.timecreated) AS lastgraded
                  FROM {quiz_attempts} qa
                  JOIN {quiz} q ON q.id = qa.quiz
                  JOIN {question_attempts} qat ON qat.id = (
                           SELECT MIN(qat2.id)
                             FROM {question_attempts} qat2
                            WHERE qat2.questionusageid = qa.uniqueid)
                  JOIN {question_attempt_steps} qas ON qas.id = (
                           SELECT MAX(qas2.id)
                             FROM {question_attempt_steps} qas2
                            WHERE qas2.questionattemptid = qat.id
                              AND ({$like} OR qas2.state = :gaveup))
                  JOIN {question_attempt_steps} fs ON fs.id = (
                           SELECT MAX(fs2.id)
                             FROM {question_attempt_steps} fs2
                            WHERE fs2.questionattemptid = qat.id)
                 WHERE qa.quiz {$insql} AND qa.state = :finished
                       AND ({$likefinal} OR fs.state = :gaveupfinal)
                       AND qa.id = " . self::latest_attempt_sql() . "
                       AND " . self::allocated_student_sql() . "
              GROUP BY qa.quiz, qas.userid", $params);

        $evaluators = [];
        foreach ($rows as $row) {
            $evaluators[$row->quiz][(int) $row->userid] = [
                'n' => (int) $row->n,
                'marks' => (float) $row->marks,
                'first' => (int) $row->firstgraded,
                'last' => (int) $row->lastgraded,
            ];
        }
        $rows->close();

        foreach ($evaluators as $quizid => $byuser) {
            uasort($byuser, function($a, $b) {
                return $b['n'] <=> $a['n'];
            });
            $evaluators[$quizid] = $byuser;
        }

        return $evaluators;
    }

    /**
     * Teacher-role holders per course, service accounts already removed.
     *
     * @param int[] $courseids
     * @return array courseid => \stdClass[] user records
     */
    protected static function get_teachers(array $courseids): array {
        global $DB;

        list($insql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');
        $service = self::service_accounts();
        $namefields = \core_user\fields::for_name()->get_sql('u')->selects;

        $rows = $DB->get_recordset_sql("
                SELECT DISTINCT ctx.instanceid AS courseid, u.id {$namefields},
                       u.email, u.deleted, u.suspended
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
                  JOIN {role} r ON r.id = ra.roleid
                  JOIN {user} u ON u.id = ra.userid
                 WHERE ctx.instanceid {$insql}
                       AND r.archetype IN ('editingteacher', 'teacher')
                       AND u.deleted = 0
              ORDER BY u.lastname ASC, u.firstname ASC", $params);

        $teachers = [];
        foreach ($rows as $row) {
            if (in_array(strtolower($row->email), $service, true)) {
                continue;
            }
            $courseid = (int) $row->courseid;
            unset($row->courseid);
            $teachers[$courseid][(int) $row->id] = $row;
        }
        $rows->close();

        return $teachers;
    }

    /**
     * User records for everyone named in the report, deleted accounts included.
     *
     * @param int[] $userids
     * @return \stdClass[] keyed by user id
     */
    public static function get_users(array $userids): array {
        global $DB;

        if (!$userids) {
            return [];
        }
        list($insql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
        $namefields = \core_user\fields::for_name()->get_sql('u')->selects;

        return $DB->get_records_sql("
                SELECT u.id {$namefields}, u.email, u.deleted, u.suspended
                  FROM {user} u
                 WHERE u.id {$insql}", $params);
    }

    /**
     * Build the guide-course summary for one batch/semester period.
     *
     * @param string $periodkey key from {@see get_periods()}
     * @param string $sort one of submitted|guide|evaluated|awaiting
     * @return \stdClass {period, rows, totals, generated}
     */
    public static function build(string $periodkey, string $sort = 'submitted'): \stdClass {
        global $DB;

        $periods = self::get_periods();
        if (!isset($periods[$periodkey])) {
            throw new \moodle_exception('invalidperiod', 'local_reflectiveassessment');
        }
        $period = $periods[$periodkey];

        $courses = self::get_courses();
        $courseids = $period->courseids;
        $quizzes = self::get_quizzes($courseids);
        $allocated = self::get_allocated($courseids);
        $teachers = self::get_teachers($courseids);

        $quizids = array_map(function($quiz) {
            return (int) $quiz->id;
        }, $quizzes);
        $progress = $quizids ? self::get_progress(array_values($quizids)) : [];
        $evaluators = $quizids ? self::get_evaluators(array_values($quizids)) : [];

        $userids = [];
        foreach ($evaluators as $byuser) {
            $userids += array_flip(array_keys($byuser));
        }
        $users = self::get_users(array_keys($userids));
        $service = self::service_accounts();

        $rows = [];
        foreach ($courseids as $courseid) {
            $course = $courses[$courseid];
            $quiz = $quizzes[$courseid] ?? null;
            $quizid = $quiz ? (int) $quiz->id : 0;

            $evaluated = (int) ($progress[$quizid]['evaluated']['n'] ?? 0);
            $awaiting  = (int) ($progress[$quizid]['awaiting']['n'] ?? 0);
            $blank     = (int) ($progress[$quizid]['blank']['n'] ?? 0);
            $marks     = (float) ($progress[$quizid]['evaluated']['marks'] ?? 0);
            $submitted = $evaluated + $awaiting + $blank;
            $alloc     = (int) ($allocated[$courseid] ?? 0);

            $byuser = $evaluators[$quizid] ?? [];
            $guide = self::resolve_guide($teachers[$courseid] ?? [], $byuser, $users, $service);

            $breakdown = [];
            $last = 0;
            $shared = false;
            foreach ($byuser as $userid => $stats) {
                $user = $users[$userid] ?? null;
                $name = $user ? fullname($user) : get_string('unknownuser', 'local_reflectiveassessment');
                $breakdown[] = $name . ' (' . $stats['n'] . ')';
                $last = max($last, $stats['last']);
                if ($user && in_array(strtolower($user->email), $service, true)) {
                    $shared = true;
                }
            }

            $rows[] = (object) [
                'batch'        => $course->batch,
                'program'      => $course->program,
                'semester'     => $course->semester,
                'guide'        => $guide->name,
                'guideemail'   => $guide->email,
                'guidestatus'  => $guide->status,
                'courseid'     => (int) $courseid,
                'coursecode'   => $course->shortname,
                'allocated'    => $alloc,
                'submitted'    => $submitted,
                'answered'     => $evaluated + $awaiting,
                'evaluated'    => $evaluated,
                'awaiting'     => $awaiting,
                'blank'        => $blank,
                'notattempted' => max(0, $alloc - $submitted),
                'pctsubmitted' => $alloc ? round(100 * $submitted / $alloc) : null,
                'pctevaluated' => $submitted ? round(100 * $evaluated / $submitted) : null,
                'avgmark'      => $evaluated ? round($marks / $evaluated, 1) : null,
                'maxmark'      => $quiz ? round((float) $quiz->grade, 2) : null,
                'evaluatedby'  => implode(', ', $breakdown),
                'sharedmarker' => $shared,
                'lastevaluated' => $last,
            ];
        }

        self::sort_rows($rows, $sort);

        return (object) [
            'period'    => $period,
            'rows'      => $rows,
            'totals'    => self::totals($rows),
            'generated' => time(),
        ];
    }

    /**
     * The guide of record: the course teacher, falling back to the top
     * evaluator where the guide's account has been deleted along with its role.
     *
     * @param \stdClass[] $teachers non-service teachers on the course
     * @param array $byuser evaluator stats keyed by user id, busiest first
     * @param \stdClass[] $users user records for the evaluators
     * @param string[] $service service account addresses
     * @return \stdClass {name, email, status}
     */
    protected static function resolve_guide(array $teachers, array $byuser, array $users, array $service): \stdClass {
        foreach (array_keys($byuser) as $userid) {
            if (isset($teachers[$userid])) {
                return self::guide_from_user($teachers[$userid]);
            }
        }
        if ($teachers) {
            return self::guide_from_user(reset($teachers));
        }
        foreach (array_keys($byuser) as $userid) {
            $user = $users[$userid] ?? null;
            if ($user && !in_array(strtolower($user->email), $service, true)) {
                return self::guide_from_user($user);
            }
        }
        return (object) [
            'name'   => get_string('guideunresolved', 'local_reflectiveassessment'),
            'email'  => '',
            'status' => self::GUIDE_UNRESOLVED,
        ];
    }

    /**
     * Describe a guide from their user record.
     *
     * @param \stdClass $user
     * @return \stdClass {name, email, status}
     */
    protected static function guide_from_user(\stdClass $user): \stdClass {
        if (!empty($user->deleted)) {
            $status = self::GUIDE_DELETED;
        } else if (!empty($user->suspended)) {
            $status = self::GUIDE_SUSPENDED;
        } else {
            $status = self::GUIDE_ACTIVE;
        }
        return (object) [
            'name'   => fullname($user),
            'email'  => $status === self::GUIDE_DELETED ? '' : $user->email,
            'status' => $status,
        ];
    }

    /**
     * Order the rows.
     *
     * @param \stdClass[] $rows modified in place
     * @param string $sort
     */
    protected static function sort_rows(array &$rows, string $sort): void {
        $comparators = [
            'submitted' => function($a, $b) {
                return [$b->submitted, $a->guide] <=> [$a->submitted, $b->guide];
            },
            'guide' => function($a, $b) {
                return [$a->guide, $a->coursecode] <=> [$b->guide, $b->coursecode];
            },
            'evaluated' => function($a, $b) {
                return [$a->pctevaluated ?? 101, $b->submitted] <=> [$b->pctevaluated ?? 101, $a->submitted];
            },
            'awaiting' => function($a, $b) {
                return [$b->awaiting, $a->guide] <=> [$a->awaiting, $b->guide];
            },
        ];
        usort($rows, $comparators[$sort] ?? $comparators['submitted']);
    }

    /**
     * Column totals, with percentages recomputed rather than averaged.
     *
     * @param \stdClass[] $rows
     * @return \stdClass
     */
    protected static function totals(array $rows): \stdClass {
        $totals = (object) [
            'courses' => count($rows),
            'allocated' => 0, 'submitted' => 0, 'answered' => 0,
            'evaluated' => 0, 'awaiting' => 0, 'blank' => 0, 'notattempted' => 0,
            'pctsubmitted' => null, 'pctevaluated' => null, 'avgmark' => null,
        ];
        $marks = 0.0;
        foreach ($rows as $row) {
            foreach (['allocated', 'submitted', 'answered', 'evaluated', 'awaiting', 'blank', 'notattempted'] as $field) {
                $totals->{$field} += $row->{$field};
            }
            if ($row->avgmark !== null) {
                $marks += $row->avgmark * $row->evaluated;
            }
        }
        if ($totals->allocated) {
            $totals->pctsubmitted = round(100 * $totals->submitted / $totals->allocated);
        }
        if ($totals->submitted) {
            $totals->pctevaluated = round(100 * $totals->evaluated / $totals->submitted);
        }
        if ($totals->evaluated) {
            $totals->avgmark = round($marks / $totals->evaluated, 1);
        }
        return $totals;
    }

    /**
     * Column headings for the export, in report order.
     *
     * @return string[]
     */
    public static function export_columns(): array {
        return [
            get_string('col_batch', 'local_reflectiveassessment'),
            get_string('col_program', 'local_reflectiveassessment'),
            get_string('col_semester', 'local_reflectiveassessment'),
            get_string('col_guide', 'local_reflectiveassessment'),
            get_string('col_guideemail', 'local_reflectiveassessment'),
            get_string('col_guidestatus', 'local_reflectiveassessment'),
            get_string('col_coursecode', 'local_reflectiveassessment'),
            get_string('col_allocated', 'local_reflectiveassessment'),
            get_string('col_submitted', 'local_reflectiveassessment'),
            get_string('col_answered', 'local_reflectiveassessment'),
            get_string('col_evaluated', 'local_reflectiveassessment'),
            get_string('col_awaiting', 'local_reflectiveassessment'),
            get_string('col_blank', 'local_reflectiveassessment'),
            get_string('col_notattempted', 'local_reflectiveassessment'),
            get_string('col_pctsubmitted', 'local_reflectiveassessment'),
            get_string('col_pctevaluated', 'local_reflectiveassessment'),
            get_string('col_avgmark', 'local_reflectiveassessment'),
            get_string('col_maxmark', 'local_reflectiveassessment'),
            get_string('col_evaluatedby', 'local_reflectiveassessment'),
            get_string('col_lastevaluated', 'local_reflectiveassessment'),
        ];
    }

    /**
     * Flatten one row for the export, in the same order as the headings.
     *
     * @param \stdClass $row
     * @return array
     */
    public static function export_row(\stdClass $row): array {
        return [
            $row->batch,
            $row->program,
            $row->semester,
            $row->guide,
            $row->guideemail,
            get_string('guidestatus_' . $row->guidestatus, 'local_reflectiveassessment'),
            $row->coursecode,
            $row->allocated,
            $row->submitted,
            $row->answered,
            $row->evaluated,
            $row->awaiting,
            $row->blank,
            $row->notattempted,
            $row->pctsubmitted,
            $row->pctevaluated,
            $row->avgmark,
            $row->maxmark,
            $row->evaluatedby,
            $row->lastevaluated ? userdate($row->lastevaluated, get_string('strftimedate', 'langconfig')) : '',
        ];
    }
}
