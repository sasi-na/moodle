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
 * Bespoke per-course grade report (gradebook table) for theme_baz (student-only).
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
use grade_item;
use grade_grade;
use theme_baz\local\hidden_totals;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/gradelib.php');

/**
 * Renderable + templatable for the bespoke per-course grade report.
 */
class student_gradereport implements renderable, templatable {

    /** @var array Activity icon inner-SVG markup keyed by bucket. */
    const ICONS = [
        'quiz'    => '<path d="M9 11l3 3 8-8"/><path d="M20 12v6a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h9"/>',
        'lesson'  => '<path d="M4 5h16M4 5v14a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V5M9 11h6M9 15h4"/>',
        'assign'  => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
        'forum'   => '<path d="M21 11.5a8.4 8.4 0 0 1-9 8 9.9 9.9 0 0 1-4-1L3 20l1.5-4a8 8 0 0 1-1-4 8.4 8.4 0 0 1 9-8 8.4 8.4 0 0 1 8.5 7.5z"/>',
        'feedback' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M8 10h8M8 13h5"/>',
        'manual'  => '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'other'   => '<path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5"/>',
    ];

    /**
     * Module name -> display bucket.
     */
    protected function bucket(string $module): string {
        if ($module === '') {
            return 'manual';
        }
        if (isset(self::ICONS[$module])) {
            return $module;
        }
        return 'other';
    }

    /**
     * Human type label for a grade item.
     */
    protected function typelabel(string $itemtype, string $module): string {
        if ($itemtype === 'manual') {
            return get_string('gr_manualitem', 'theme_baz');
        }
        if ($module === '') {
            return get_string('gr_item', 'theme_baz');
        }
        try {
            return get_string('pluginname', 'mod_' . $module);
        } catch (\Throwable $e) {
            return ucfirst($module);
        }
    }

    /**
     * Normalised "family" name for grouping when the gradebook has no real
     * category tree, e.g. "Continuous Assessment 1" -> "Continuous Assessment".
     */
    protected function name_prefix(string $name): string {
        // Collapse repeated whitespace first so "On  Screen" and "On Screen"
        // group together despite source-data typos.
        $name = preg_replace('/\s+/u', ' ', trim($name));
        $p = preg_replace(
            '/\s*[-–—:]*\s*((module|week|part|unit|section|chapter|day|no\.?|attempt|task)\s*)?\d+\s*$/iu',
            '', $name);
        $p = trim((string) $p);
        return $p !== '' ? $p : $name;
    }

    /**
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $USER, $CFG, $DB, $PAGE;
        $course = $PAGE->course;
        $userid = (int) $USER->id;
        $courseid = (int) $course->id;

        $modinfo = get_fast_modinfo($course, $userid);
        $instances = $modinfo->get_instances();

        $items = $DB->get_records('grade_items', ['courseid' => $courseid], 'sortorder ASC');
        $cats = $DB->get_records('grade_categories', ['courseid' => $courseid]);

        $total = ['hasgrade' => false, 'grade' => '—', 'max' => 0, 'pct' => 0];
        $leaves = [];
        $graded = 0;
        $pending = 0;
        $cattotals = [];

        foreach ($items as $it) {
            $gi = grade_item::fetch(['id' => $it->id]);
            // "Grade type: None" keeps the grade_items row, and its stale aggregationcoef2 with it.
            // Core drops these in grade_category::fetch_course_tree(); a direct table read does not.
            if (!$gi || (int) $gi->gradetype === GRADE_TYPE_NONE) {
                continue;
            }
            $gg = new grade_grade(['itemid' => $it->id, 'userid' => $userid]);
            // Assigned so is_hidden() below does not re-fetch the item we already hold.
            $gg->grade_item = $gi;
            // Per-GRADE visibility, not just the item's. grade_grade::is_hidden() ORs in
            // grade_item::is_hidden(), so this is a strict superset of the item-level test it
            // replaces - and it is the only one that sees a mark hidden for THIS learner alone,
            // which is how previous-semester CA marks are hidden (local_cagrades\past_ca). Without
            // it this report would keep showing marks that core and the mobile app already hide.
            if ($gg->is_hidden()) {
                continue;
            }
            $final = $gg->finalgrade;
            $max = (float) $gi->grademax;
            $min = (float) $gi->grademin;
            $isgraded = ($final !== null);
            $disp = $isgraded ? grade_format_gradevalue($final, $gi, true, GRADE_DISPLAY_TYPE_REAL) : null;
            $pct = ($isgraded && $max > $min) ? (int) round(($final - $min) / ($max - $min) * 100) : null;

            if ($it->itemtype === 'course') {
                // The total sums leaves this learner may not see - previous-semester CA is
                // hidden leaf by leaf - and its grademax counts activities that never
                // scored. Both are restated over what remains visible.
                [$adjusted, $adjmax] = hidden_totals::adjust($courseid, $userid, $gi,
                    $final === null ? null : (float) $final);
                $isgraded = ($adjusted !== null);
                $adjpct = ($isgraded && $adjmax > $min)
                    ? (int) round(($adjusted - $min) / ($adjmax - $min) * 100) : null;
                $total = [
                    'hasgrade' => $isgraded,
                    'grade' => $isgraded ? grade_format_gradevalue($adjusted, $gi, true, GRADE_DISPLAY_TYPE_REAL) : '—',
                    'max' => $this->fmtnum($adjmax),
                    'pct' => $adjpct ?? 0,
                ];
                continue;
            }
            if ($it->itemtype === 'category') {
                // A category total - "Continuous Assessment" scoring the best two of three, for
                // instance. It is not an assessment the learner sits, so it is not a row of its own;
                // it becomes the subtotal on its group's heading. Keyed by the category it totals,
                // which is the value the leaves below carry in their own categoryid.
                $cattotals[(int) $it->iteminstance] = [
                    'hasgrade' => $isgraded,
                    'grade' => $isgraded ? $disp : '—',
                    'max' => $this->fmtnum($max),
                    'haspct' => $pct !== null,
                    'pct' => $pct ?? 0,
                    // The category's own weight in the course total. The leaves inside it carry
                    // weights relative to the category, which would add up to 100% and say
                    // something quite untrue about the course.
                    'weightraw' => (float) $it->aggregationcoef2,
                ];
                continue;
            }

            // A quiz whose weight was deliberately overridden to zero contributes nothing to the
            // course total - it is not part of the learner's grade, so it is not one of their rows.
            // Limited to quizzes so the weight-zero VPL lab practice items, which carry real marks
            // learners still want to see, keep their rows. Reached only after the course and
            // category totals above have taken their branch.
            if ($it->itemmodule === 'quiz'
                    && (int) $it->weightoverride === 1 && (float) $it->aggregationcoef2 == 0.0) {
                continue;
            }

            $catid = (int) $it->categoryid;
            $module = (string) $it->itemmodule;
            $bucket = $this->bucket($module);
            $name = $gi->get_name() ?: format_string($it->itemname);
            $weightraw = (float) $it->aggregationcoef2;

            // Deep link to the activity + grade-analysis link (mirrors core).
            $viewurl = null;
            $analysisurl = null;
            if ($it->itemtype === 'mod' && $module !== '' && !empty($it->iteminstance)
                    && isset($instances[$module][$it->iteminstance])) {
                $cm = $instances[$module][$it->iteminstance];
                if ($cm->uservisible && $cm->url) {
                    $viewurl = $cm->url->out(false);
                }
                if ($cm->uservisible && file_exists($CFG->dirroot . '/mod/' . $module . '/grade.php')) {
                    $analysisurl = (new moodle_url('/mod/' . $module . '/grade.php', [
                        'id' => $cm->id,
                        'itemid' => $gi->id,
                        'itemnumber' => $gi->itemnumber,
                        'gradeid' => $gg->id ?: 0,
                        'userid' => $userid,
                    ]))->out(false);
                }
            }

            // Feedback (plain text, collapsed whitespace).
            $feedback = '';
            if (!empty($gg->feedback)) {
                $feedback = trim(format_text($gg->feedback, $gg->feedbackformat ?? FORMAT_MOODLE,
                    ['context' => \context_course::instance($courseid)]));
            }

            $catname = $this->category_name($catid, $cats);

            $leaves[] = [
                'catid'    => $catid,
                'catname'  => $catname,
                'prefix'   => $this->name_prefix($name),
                'name'     => $name,
                'type'     => $bucket,
                'iconsvg'  => self::ICONS[$bucket] ?? self::ICONS['other'],
                'typelabel' => $this->typelabel($it->itemtype, $module),
                'hasweight' => $weightraw > 0,
                'weight'   => $weightraw > 0 ? $this->fmtnum(round($weightraw * 100, 1)) . '%' : '—',
                'weightraw' => $weightraw,
                'isgraded' => $isgraded,
                'grade'    => $isgraded ? $disp : '—',
                'max'      => $this->fmtnum($max),
                'range'    => $this->fmtnum($min) . '–' . $this->fmtnum($max),
                'haspct'   => $pct !== null,
                'pct'      => $pct ?? 0,
                'hasurl'   => $viewurl !== null,
                'url'      => $viewurl,
                'hasanalysis' => $analysisurl !== null,
                'analysisurl' => $analysisurl,
                'hasfeedback' => $feedback !== '',
                'feedback' => $feedback,
            ];
            if ($isgraded) {
                $graded++;
            } else {
                $pending++;
            }
        }

        // Group by real grade category where the item sits in one, and by name family for items
        // left in the course root — which, before the Continuous Assessment category existed, was
        // every item in every course.
        $buckets = [];
        $order = [];
        foreach ($leaves as $lf) {
            $isrealcat = $lf['catname'] !== null;
            $key = $isrealcat ? 'c' . $lf['catid'] : 'p:' . $lf['prefix'];
            $label = $isrealcat ? $lf['catname'] : $lf['prefix'];
            if (!isset($buckets[$key])) {
                $buckets[$key] = [
                    'name' => $label,
                    'items' => [],
                    'wtraw' => 0.0,
                    'catid' => $isrealcat ? $lf['catid'] : 0,
                ];
                $order[] = $key;
            }
            $buckets[$key]['items'][] = $lf;
            $buckets[$key]['wtraw'] += $lf['weightraw'];
        }

        $groupsout = [];
        foreach ($order as $key) {
            $g = $buckets[$key];
            $subtotal = $cattotals[$g['catid']] ?? null;
            // Inside a real grade category the leaves are weighted relative to the category, so they
            // sum to 100% and say something quite untrue about the course. The category's own weight
            // is the honest figure — and where it is not available, because the category total is
            // hidden from the learner, no figure is shown at all.
            $isrealcat = $g['catid'] > 0;
            $wtraw = $isrealcat ? ($subtotal['weightraw'] ?? 0.0) : $g['wtraw'];
            // A weight can never exceed 100% — guard rounding overshoot.
            $wt = min(100.0, round($wtraw * 100, 1));
            $groupsout[] = [
                'name' => $g['name'],
                'haswt' => $wt > 0,
                'wtlabel' => $this->fmtnum($wt) . '% of course grade',
                'items' => $g['items'],
                'hassubtotal' => $subtotal !== null,
                'subtotal' => $subtotal,
                'subtotallabel' => get_string('gr_subtotal', 'theme_baz', $g['name']),
            ];
        }

        // Eyebrow: category / semester name.
        $catname = '';
        if (!empty($course->category)) {
            $cc = $DB->get_record('course_categories', ['id' => $course->category], 'name');
            $catname = $cc ? format_string($cc->name) : '';
        }

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

        // Report toggle (User report <-> all-courses Overview report) — a custom
        // control so the current report is always shown and styled to match.
        $reports = [
            ['label' => get_string('gv_userreport', 'theme_baz'),
                'url' => (new moodle_url('/grade/report/user/index.php',
                    ['id' => $courseid, 'userid' => $userid]))->out(false), 'active' => true],
            ['label' => get_string('gv_overviewreport', 'theme_baz'),
                'url' => (new moodle_url('/grade/report/overview/index.php',
                    ['id' => $courseid]))->out(false), 'active' => false],
        ];

        return [
            'config'       => ['wwwroot' => $CFG->wwwroot],
            'reports'      => $reports,
            'hasreports'   => true,
            'coursename'   => format_string($course->fullname),
            'eyebrow'      => $catname !== '' ? $catname : format_string($course->shortname),
            'total'        => $total,
            'graded'       => $graded,
            'pending'      => $pending,
            'groups'       => $groupsout,
            'hasitems'     => !empty($groupsout),

            'courseurl'    => (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
            'participantsurl' => (new moodle_url('/user/index.php', ['id' => $courseid]))->out(false),
            'dashboardurl' => (new moodle_url('/my/'))->out(false),
            'coursesurl'   => (new moodle_url('/my/courses.php'))->out(false),
            'calendarurl'  => (new moodle_url('/calendar/view.php'))->out(false),
            'mygradesurl'  => (new moodle_url('/grade/report/overview/index.php'))->out(false),
            'filesurl'     => (new moodle_url('/user/files.php'))->out(false),
            'showeidcard'  => $showeidcard,
            'eidcardurl'   => (new moodle_url('/local/studentid/index.php'))->out(false),
            'support_html' => $support,
            'hassupport'   => $support !== null,
            'askjo'        => $askjo,
            'hasaskjo'     => !empty($askjo),
        ];
    }

    /**
     * Friendly category name, or null for the course root category.
     */
    protected function category_name(int $catid, array $cats): ?string {
        if (isset($cats[$catid])) {
            $name = trim((string) $cats[$catid]->fullname);
            if ($name !== '' && $name !== '?') {
                return format_string($name);
            }
        }
        return null;
    }

    /**
     * Trim trailing .00 from numbers for display.
     */
    protected function fmtnum(float $n): string {
        if (floor($n) == $n) {
            return (string) (int) $n;
        }
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }

    /**
     * Real support block HTML for the floating help panel (mirrors other views).
     */
    protected function render_support(): ?string {
        return theme_baz_support_panel();
    }
}
