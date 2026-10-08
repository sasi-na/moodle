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

/**
 * Bespoke teacher dashboard for teaching-staff (faculty / TA) audiences.
 *
 * Renders on /my/ inside the navy/gold shell (reuses the student #sd-root shell
 * CSS + JS). Everything it shows links into the existing Moodle grader / course
 * / report screens — it is a summary + navigation layer only, and every data
 * section fails soft (empty state) so a query hiccup never breaks the page.
 *
 * @package   theme_baz
 * @copyright 2026 onlinejain.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_baz\output;

use renderable;
use templatable;
use renderer_base;
use moodle_url;
use context_course;
use context_module;
use theme_baz\local\roles;

defined('MOODLE_INTERNAL') || die();

/**
 * Renderable + templatable for the teacher dashboard.
 */
class teacher_dashboard implements renderable, templatable {

    /** @var array Modules treated as "live classes". */
    const LIVE_MODULES = ['zoom', 'bigbluebuttonbn', 'customteams', 'teams', 'msteams', 'jitsi', 'collaborate'];

    /** @var int Safety cap on how many gradable activities we probe per request. */
    const GRADE_PROBE_CAP = 60;

    /** @var int Cap on courses processed in detail (per-course counts/grading).
     *  A normal teacher has a handful; some accounts (content teams) hold roles
     *  on thousands, so bound the expensive per-course work. */
    const COURSE_CAP = 24;

    /**
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $USER, $CFG;

        $userid = (int) $USER->id;
        $now = time();

        // Teaching courses (best-effort; drives everything below). Detailed data
        // is computed over at most COURSE_CAP courses; $totalcourses is the true
        // count for the KPI.
        [$courses, $totstudents, $totalcourses] = $this->get_courses($userid);

        // Grading queue across those courses (accurate — uses core assign counts).
        [$queue, $totgrading] = $this->get_grading_queue($courses);

        // Course cards (student count, to-grade, completion, last activity).
        $cards = $this->build_course_cards($courses, $queue);

        // Live classes today.
        $live = $this->get_live_today($courses, $now);

        // This-week engagement (active learners + submissions/day spark).
        $engage = $this->get_engagement($courses, $now);

        // Average completion across the teacher's courses (cards that have it).
        $withpct = array_filter($cards, fn($c) => $c['hasprog']);
        $avgpct = $withpct ? (int) round(array_sum(array_map(fn($c) => $c['pct'], $withpct)) / count($withpct)) : null;

        // Shell extras (reused from the student shell so the same JS/markup works).
        $askjo = null;
        if (class_exists('\theme_baz\local\askjo')) {
            try { $askjo = \theme_baz\local\askjo::build(); } catch (\Throwable $e) { $askjo = null; }
        }
        $banners = function_exists('theme_baz_get_dashboard_banners') ? theme_baz_get_dashboard_banners() : [];

        // Client-side search suggestions: the teacher's own courses.
        $searchitems = array_map(fn($c) => [
            'label' => $c['name'], 'sublabel' => $c['code'], 'url' => $c['contenturl'],
        ], $cards);

        return [
            // The persistent left-menu block, from the one shared source, so this
            // dashboard's menu matches every wrapped page instead of carrying its own.
            'homenav' => \theme_baz\local\shell::home_nav(),
            'config'         => ['wwwroot' => $CFG->wwwroot],
            'greetingtime'   => $this->greeting($now),
            'firstname'      => ucwords(\core_text::strtolower($USER->firstname)),
            'datelabel'      => userdate($now, get_string('strftimedaydate', 'langconfig')),

            // Hero summary counts.
            'kpicourses'     => $totalcourses,
            'kpistudents'    => $totstudents,
            'kpigrading'     => $totgrading,
            'kpicompletion'  => $avgpct,
            'hascompletion'  => $avgpct !== null,
            'liveclasscount' => count($live),

            // Grading queue.
            'queue'          => $queue,
            'hasqueue'       => !empty($queue),

            // Live classes today.
            'liveclasses'    => $live,
            'hasliveclasses' => !empty($live),

            // Courses.
            'courses'        => $cards,
            'hascourses'     => !empty($cards),

            // Engagement.
            'engage'         => $engage,

            // Nav / quick-action URLs (into the real Moodle screens).
            'coursesurl'     => (new moodle_url('/course/index.php'))->out(false),
            'calendarurl'    => (new moodle_url('/calendar/view.php'))->out(false),
            'gradingurl'     => !empty($queue) ? $queue[0]['url'] : (new moodle_url('/my/'))->out(false),
            'messagesurl'    => (new moodle_url('/message/index.php'))->out(false),
            'reportsurl'     => (new moodle_url('/report/log/index.php'))->out(false),
            'qbankurl'       => !empty($cards) ? (new moodle_url('/question/edit.php', ['courseid' => $cards[0]['id']]))->out(false)
                                               : (new moodle_url('/my/'))->out(false),

            // Ask JO chat + banners (shared shell components).
            'askjo'          => $askjo,
            'hasaskjo'       => !empty($askjo),
            'searchitemsjson' => json_encode($searchitems),
            'banners'        => $banners,
            'hasbanner'      => !empty($banners),
            'bannermulti'    => count($banners) > 1,
            'bannerdelay'    => max(2000, (int) (get_config('theme_baz', 'dashboardbannerdelay') ?: 5000)),
        ];
    }

    /**
     * Time-of-day greeting string.
     *
     * @param int $now
     * @return string
     */
    protected function greeting(int $now): string {
        $h = (int) userdate($now, '%H');
        if ($h < 12) { return get_string('sd_morning', 'theme_baz'); }
        if ($h < 17) { return get_string('sd_afternoon', 'theme_baz'); }
        return get_string('sd_evening', 'theme_baz');
    }

    /**
     * The teacher's courses (records, capped) + student count across the capped
     * set + the true total teaching-course count.
     *
     * Only the most recent COURSE_CAP visible courses are loaded in detail, so an
     * account assigned to thousands of courses can't make the page hang.
     *
     * @param int $userid
     * @return array [\stdClass[] $courses, int $totalstudents, int $totalcourses]
     */
    protected function get_courses(int $userid): array {
        global $DB;
        try {
            $ids = roles::teaching_courseids($userid);
            $ids = array_values(array_filter($ids, fn($id) => $id > 1)); // drop the site course.
            if (!$ids) {
                return [[], 0, 0];
            }
            $totalcourses = count($ids);
            [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
            // Most recent teaching courses first; cap the detailed set.
            $courses = $DB->get_records_select('course',
                "id $insql AND visible = 1", $params, 'startdate DESC, id DESC',
                'id, fullname, shortname, category, startdate, enddate, enablecompletion',
                0, self::COURSE_CAP);
            $tot = 0;
            foreach ($courses as $c) {
                try {
                    $c->studentcount = (int) count_enrolled_users(context_course::instance($c->id), '', 0, true);
                } catch (\Throwable $e) {
                    $c->studentcount = 0;
                }
                $tot += $c->studentcount;
            }
            return [array_values($courses), $tot, $totalcourses];
        } catch (\Throwable $e) {
            return [[], 0, 0];
        }
    }

    /**
     * Grading queue: assignments across the teacher's courses that need grading,
     * counted with core mod_assign logic (handles team/blind/etc. correctly).
     * Highest-count items first.
     *
     * @param array $courses
     * @return array [array $queue, int $total] queue rows keyed by cmid.
     */
    protected function get_grading_queue(array $courses): array {
        global $CFG, $DB;
        $queue = [];
        $total = 0;
        if (!$courses) {
            return [[], 0];
        }
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $probed = 0;
        foreach ($courses as $course) {
            if ($probed >= self::GRADE_PROBE_CAP) {
                break;
            }
            try {
                $modinfo = get_fast_modinfo($course->id);
                foreach ($modinfo->get_instances_of('assign') as $cm) {
                    if ($probed >= self::GRADE_PROBE_CAP) {
                        break;
                    }
                    if (!$cm->uservisible || $cm->deletioninprogress) {
                        continue;
                    }
                    $probed++;
                    try {
                        $context = context_module::instance($cm->id);
                        $assign = new \assign($context, $cm, $course);
                        $need = (int) $assign->count_submissions_need_grading();
                    } catch (\Throwable $e) {
                        continue;
                    }
                    if ($need <= 0) {
                        continue;
                    }
                    $total += $need;
                    $queue[] = [
                        'cmid'      => $cm->id,
                        'title'     => format_string($cm->name),
                        'coursename' => format_string($course->fullname),
                        'shortname' => format_string($course->shortname),
                        'count'     => $need,
                        'urgent'    => $need >= 10,
                        'url'       => (new moodle_url('/mod/assign/view.php',
                            ['id' => $cm->id, 'action' => 'grading']))->out(false),
                    ];
                }
            } catch (\Throwable $e) {
                continue;
            }
        }
        // Most items first; keep the top 6 for the card.
        usort($queue, fn($a, $b) => $b['count'] <=> $a['count']);
        return [array_slice($queue, 0, 6), $total];
    }

    /**
     * Per-course to-grade totals (from the queue), enrolment counts and a
     * best-effort completion percentage.
     *
     * @param array $courses
     * @param array $queue
     * @return array course cards
     */
    protected function build_course_cards(array $courses, array $queue): array {
        global $DB;
        // Map cmid → course to fold grading counts back per course.
        $percourse = [];
        foreach ($queue as $q) {
            // queue rows don't carry courseid; recompute cheaply below instead.
            $percourse[$q['coursename']] = ($percourse[$q['coursename']] ?? 0) + $q['count'];
        }

        $covers = [['#1B4099', '#0D2150'], ['#5B9BFF', '#2563EB'], ['#1FA37A', '#0E6E50']];
        $cards = [];
        $i = 0;
        foreach ($courses as $c) {
            $name = format_string($c->fullname);
            $pct = null;
            if (!empty($c->enablecompletion) && $c->studentcount > 0) {
                try {
                    $completed = (int) $DB->count_records_select('course_completions',
                        'course = :cid AND timecompleted IS NOT NULL', ['cid' => $c->id]);
                    $pct = (int) round(min(100, ($completed / max(1, $c->studentcount)) * 100));
                } catch (\Throwable $e) {
                    $pct = null;
                }
            }
            $cov = $covers[$i % count($covers)];
            $cards[] = [
                'id'          => (int) $c->id,
                'name'        => $name,
                'code'        => format_string($c->shortname),
                'students'    => (int) $c->studentcount,
                'tograde'     => (int) ($percourse[$name] ?? 0),
                'hastograde'  => !empty($percourse[$name]),
                'pct'         => $pct ?? 0,
                'hasprog'     => $pct !== null,
                'cov1'        => $cov[0],
                'cov2'        => $cov[1],
                'gradeurl'    => (new moodle_url('/grade/report/grader/index.php', ['id' => $c->id]))->out(false),
                'contenturl'  => (new moodle_url('/course/view.php', ['id' => $c->id]))->out(false),
                'peopleurl'   => (new moodle_url('/user/index.php', ['id' => $c->id]))->out(false),
            ];
            $i++;
        }
        return $cards;
    }

    /**
     * Live-class calendar events happening today in the teacher's courses.
     *
     * @param array $courses
     * @param int $now
     * @return array live rows
     */
    protected function get_live_today(array $courses, int $now): array {
        global $DB;
        if (!$courses) {
            return [];
        }
        try {
            $ids = array_map(fn($c) => (int) $c->id, $courses);
            $daystart = usergetmidnight($now);
            $dayend = $daystart + DAYSECS;
            [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
            [$minsql, $mparams] = $DB->get_in_or_equal(self::LIVE_MODULES, SQL_PARAMS_NAMED);
            $params = array_merge($params, $mparams, ['ts1' => $daystart, 'ts2' => $dayend]);
            $events = $DB->get_records_select('event',
                "courseid $insql AND modulename $minsql AND timestart >= :ts1 AND timestart < :ts2",
                $params, 'timestart ASC', 'id, name, courseid, modulename, instance, timestart', 0, 8);
            $rows = [];
            $byid = [];
            foreach ($courses as $c) { $byid[(int) $c->id] = $c; }
            foreach ($events as $ev) {
                $cmid = 0;
                try {
                    $cm = get_coursemodule_from_instance($ev->modulename, $ev->instance, $ev->courseid, false, IGNORE_MISSING);
                    $cmid = $cm ? $cm->id : 0;
                } catch (\Throwable $e) {
                    $cmid = 0;
                }
                $course = $byid[(int) $ev->courseid] ?? null;
                $rows[] = [
                    'time'    => userdate($ev->timestart, '%l:%M'),
                    'ampm'    => trim(userdate($ev->timestart, '%p')) ?: '',
                    'name'    => format_string($ev->name),
                    'meta'    => ($course ? format_string($course->shortname) . ' · ' : '') . ucfirst($ev->modulename),
                    'url'     => $cmid ? (new moodle_url('/mod/' . $ev->modulename . '/view.php', ['id' => $cmid]))->out(false) : '',
                    'hasurl'  => $cmid > 0,
                ];
            }
            return $rows;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * This-week engagement: distinct active learners (last access) + a 7-day
     * submissions-received spark, across the teacher's courses.
     *
     * @param array $courses
     * @param int $now
     * @return array
     */
    protected function get_engagement(array $courses, int $now): array {
        global $DB;
        $out = ['active' => 0, 'submissions' => 0, 'sparkbars' => [], 'hasspark' => false];
        if (!$courses) {
            return $out;
        }
        try {
            $ids = array_map(fn($c) => (int) $c->id, $courses);
            [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
            $weekstart = usergetmidnight($now) - 6 * DAYSECS;

            // Active learners this week (distinct users with a course lastaccess).
            $p = array_merge($params, ['ws' => $weekstart]);
            $out['active'] = (int) $DB->get_field_sql(
                "SELECT COUNT(DISTINCT userid) FROM {user_lastaccess}
                  WHERE courseid $insql AND timeaccess >= :ws", $p);

            // Submissions received this week, by day (assign submissions).
            [$insql2, $params2] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
            $p2 = array_merge($params2, ['ws' => $weekstart]);
            $subs = $DB->get_records_sql(
                "SELECT s.id, s.timemodified
                   FROM {assign_submission} s
                   JOIN {assign} a ON a.id = s.assignment
                  WHERE a.course $insql2 AND s.status = 'submitted' AND s.timemodified >= :ws", $p2);
            $buckets = array_fill(0, 7, 0);
            foreach ($subs as $s) {
                $d = (int) floor(($s->timemodified - $weekstart) / DAYSECS);
                if ($d >= 0 && $d < 7) { $buckets[$d]++; }
            }
            $out['submissions'] = count($subs);
            $mx = max(1, max($buckets));
            $today = (int) floor(($now - $weekstart) / DAYSECS);
            foreach ($buckets as $d => $val) {
                $out['sparkbars'][] = ['height' => (int) round(($val / $mx) * 100), 'dim' => $d > $today];
            }
            $out['hasspark'] = array_sum($buckets) > 0;
            return $out;
        } catch (\Throwable $e) {
            return $out;
        }
    }
}
