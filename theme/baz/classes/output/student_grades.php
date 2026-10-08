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
 * Bespoke Grades overview exporter for theme_baz (student-only).
 *
 * Row-based "grade overview": every enrolled course grouped by semester, with
 * the running course-total grade, plus a trailing "Other enrolments" group for
 * non-academic enrolments (clubs, surveys, programme info). Each course links to
 * its per-course user report.
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
use theme_baz\local\semester;
use theme_baz\local\hidden_totals;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/gradelib.php');

/**
 * Renderable + templatable for the bespoke Grades overview page.
 */
class student_grades implements renderable, templatable {

    /**
     * A "subject" is an academic course (coded like 1.1 / 2.3, or a named
     * assessment course). Everything else (programme info, clubs, surveys) is an
     * "other enrolment".
     */
    protected function is_subject(string $name): bool {
        return (bool) preg_match('/^\s*\d\.\d/', $name) || (bool) preg_match('/reflective assessment/i', $name);
    }

    /**
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $USER, $CFG, $DB, $PAGE;
        $userid = (int) $USER->id;

        $enrolled = enrol_get_users_courses($userid, true,
            ['id', 'fullname', 'shortname', 'category', 'visible', 'showgrades']);

        hidden_totals::preload(array_keys($enrolled), $userid);

        $catids = array_unique(array_map(fn($c) => $c->category, $enrolled));
        $catnames = $catids ? $DB->get_records_list('course_categories', 'id', $catids, '', 'id, name') : [];

        $subjects = [];
        $others = [];
        $gradedcount = 0;
        $pendingcount = 0;
        $highest = null;

        foreach ($enrolled as $course) {
            if (empty($course->showgrades)) {
                continue;
            }
            $catname = isset($catnames[$course->category]) ? $catnames[$course->category]->name : '';
            $semnum = semester::semester_of($catname);
            [$graded, $display, $pct, $maxdisplay, $showmax] = $this->course_grade($course->id, $userid);
            $name = format_string($course->fullname);

            $row = [
                'id'         => $course->id,
                'name'       => $name,
                'catname'    => $catname !== '' ? $catname : format_string($course->shortname),
                'semnum'     => $semnum,
                'graded'     => $graded,
                'gradedisplay' => $display,
                'grademax'   => $maxdisplay,
                'showmax'    => $showmax,
                'pct'        => $pct,
                'pctlabel'   => $pct !== null ? (int) round($pct) : null,
                'barwidth'   => $pct !== null ? max(2, min(100, (int) round($pct))) : 0,
                'haspct'     => $pct !== null,
                'url'        => (new moodle_url('/grade/report/user/index.php',
                    ['id' => $course->id, 'userid' => $userid]))->out(false),
            ];

            if ($this->is_subject($course->fullname)) {
                $subjects[] = $row;
                if ($graded) {
                    $gradedcount++;
                    if ($pct !== null && ($highest === null || $pct > $highest)) {
                        $highest = $pct;
                    }
                } else {
                    $pendingcount++;
                }
            } else {
                $others[] = $row;
            }
        }

        $groups = $this->build_groups($subjects, $others);

        // Course context (overview opened with ?id): keep the Course/Grades tabs
        // and offer the report toggle (Overview <-> per-course User report).
        // NOTE: $PAGE->course is a magic (__get) property — empty()/isset() on it
        // report "not set" even when a real course is loaded, so read it into a
        // local first and test that.
        $pagecourse = $PAGE->course;
        $incourse = $pagecourse && (int) $pagecourse->id !== (int) SITEID;
        $courseid = $incourse ? (int) $pagecourse->id : 0;
        $coursename = $incourse ? format_string($pagecourse->fullname) : '';
        $courseviewurl = $incourse ? (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false) : '';
        $usergradesurl = $incourse ? (new moodle_url('/grade/report/user/index.php',
            ['id' => $courseid, 'userid' => $userid]))->out(false) : '';
        $overviewurl = $incourse ? (new moodle_url('/grade/report/overview/index.php',
            ['id' => $courseid]))->out(false) : '';
        $reports = $incourse ? [
            ['label' => get_string('gv_overviewreport', 'theme_baz'), 'url' => $overviewurl, 'active' => true],
            ['label' => get_string('gv_userreport', 'theme_baz'), 'url' => $usergradesurl, 'active' => false],
        ] : [];

        // Nav extras shared with the other bespoke pages.
        $showeidcard = false;
        if (class_exists('\local_studentid\manager')) {
            try {
                $showeidcard = \local_studentid\manager::is_eligible_batch($userid);
            } catch (\Throwable $e) {
                $showeidcard = false;
            }
        }
        $support = $this->render_support();
        $askjo = \theme_baz\local\askjo::build();

        return [
            'config'        => ['wwwroot' => $CFG->wwwroot],
            'firstname'     => ucwords(\core_text::strtolower($USER->firstname)),

            'gradedcount'   => $gradedcount,
            'pendingcount'  => $pendingcount,
            'highest'       => $highest !== null ? (int) round($highest) . '%' : '—',
            'hascourses'    => !empty($groups),
            'groups'        => $groups,

            'incourse'      => $incourse,
            'coursename'    => $coursename,
            'courseviewurl' => $courseviewurl,
            'coursegradesurl' => $usergradesurl,
            'reports'       => $reports,
            'hasreports'    => !empty($reports),

            'dashboardurl'  => (new moodle_url('/my/'))->out(false),
            'coursesurl'    => (new moodle_url('/my/courses.php'))->out(false),
            'calendarurl'   => (new moodle_url('/calendar/view.php'))->out(false),
            'gradesurl'     => (new moodle_url('/grade/report/overview/index.php'))->out(false),
            'filesurl'      => (new moodle_url('/user/files.php'))->out(false),
            'showeidcard'   => $showeidcard,
            'eidcardurl'    => (new moodle_url('/local/studentid/index.php'))->out(false),
            'support_html'  => $support,
            'hassupport'    => $support !== null,
            'askjo'         => $askjo,
            'hasaskjo'      => !empty($askjo),
        ];
    }

    /**
     * Build semester groups (highest first) from subjects, then append a single
     * "Other enrolments" group.
     */
    protected function build_groups(array $subjects, array $others): array {
        $buckets = [];
        foreach ($subjects as $c) {
            $key = $c['semnum'] !== null ? 's' . $c['semnum'] : 'x' . $c['catname'];
            if (!isset($buckets[$key])) {
                $buckets[$key] = ['name' => $c['catname'], 'semnum' => $c['semnum'], 'courses' => []];
            }
            $buckets[$key]['courses'][] = $c;
        }
        $groups = array_values($buckets);
        usort($groups, function($a, $b) {
            if ($a['semnum'] !== null && $b['semnum'] !== null) {
                return $b['semnum'] <=> $a['semnum'];
            }
            if ($a['semnum'] !== null) {
                return -1;
            }
            if ($b['semnum'] !== null) {
                return 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });

        $out = [];
        foreach ($groups as $g) {
            $courses = $this->sort_courses($g['courses']);
            $gd = count(array_filter($courses, fn($c) => $c['graded']));
            $out[] = [
                'name'  => $g['name'],
                'meta'  => $gd . ' graded · ' . count($courses) . ' ' . ($courses === 1 ? 'course' : 'courses'),
                'courses' => $courses,
            ];
        }
        if (!empty($others)) {
            $courses = $this->sort_courses($others);
            $out[] = [
                'name'    => get_string('gv_other', 'theme_baz'),
                'meta'    => count($courses) . ' · ' . get_string('gv_notgraded', 'theme_baz'),
                'courses' => $courses,
                'isother' => true,
            ];
        }
        return $out;
    }

    /**
     * Graded courses first, then alphabetical.
     */
    protected function sort_courses(array $courses): array {
        usort($courses, fn($a, $b) => ($b['graded'] <=> $a['graded']) ?: strcasecmp($a['name'], $b['name']));
        return $courses;
    }

    /**
     * The user's final grade for a course total, matching the overview report.
     *
     * @return array [bool graded, string display, float|null pct, string maxdisplay, bool showmax]
     */
    protected function course_grade(int $courseid, int $userid): array {
        try {
            $courseitem = \grade_item::fetch_course_item($courseid);
            if (!$courseitem) {
                return [false, '—', null, '', false];
            }
            $cg = new \grade_grade(['itemid' => $courseitem->id, 'userid' => $userid]);
            $cg->grade_item =& $courseitem;
            $final = $cg->finalgrade;
            if (is_null($final) || $cg->is_hidden()) {
                return [false, '—', null, '', false];
            }
            // A total summed from marks this learner cannot see is itself a way of reading
            // them - previous-semester CA is hidden leaf by leaf - and the item's own
            // grademax counts activities that never scored, which is where a 30-mark CA
            // came out as 30/230. Both are restated over what remains visible.
            [$final, $max] = hidden_totals::adjust($courseid, $userid, $courseitem,
                $final === null ? null : (float) $final);
            if ($final === null) {
                return [false, '—', null, '', false];
            }
            $min = (float) $courseitem->grademin;
            $pct = ($max > $min) ? max(0, min(100, ($final - $min) / ($max - $min) * 100)) : null;
            $display = grade_format_gradevalue($final, $courseitem, true, GRADE_DISPLAY_TYPE_REAL);
            $maxdisplay = grade_format_gradevalue($max, $courseitem, true, GRADE_DISPLAY_TYPE_REAL);
            return [true, $display, $pct, $maxdisplay, true];
        } catch (\Throwable $e) {
            debugging('student_grades course_grade: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [false, '—', null, '', false];
        }
    }

    /**
     * Real support block HTML (LSM / ticket / SOP) for the floating help panel.
     */
    protected function render_support(): ?string {
        return theme_baz_support_panel();
    }
}
