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
 * Bespoke "My courses" exporter for theme_baz.
 *
 * Assembles the full course list (grouped by category) with progress, faculty,
 * timeline classification, favourite + hidden state and header stats so the
 * mustache template stays logic-free and the JS only handles interaction.
 * Keeps full parity with block_myoverview: filter / sort / view / search /
 * grouping / star / actions / pagination.
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

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->dirroot . '/course/lib.php');

/**
 * Renderable + templatable for the bespoke My courses page.
 */
class student_courses implements renderable, templatable {

    /** @var array Course-cover gradient for even-indexed cards (deep royal blue). */
    const COVER_EVEN = ['#1B4099', '#0D2150'];

    /** @var array Course-cover gradient for odd-indexed cards (sky blue). */
    const COVER_ODD = ['#5B9BFF', '#2563EB'];

    /**
     * Export all context for theme_baz/student-courses.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $USER, $CFG;
        $userid = (int) $USER->id;

        [$courses, $stats, $searchitems] = $this->get_courses($userid);
        $groups = $this->group_courses($courses);
        $featured = $this->pick_featured($courses);

        // Student e-ID Card nav item (same rule as the dashboard).
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

        // Initial filter/sort/view from the block_myoverview prefs (so a student's
        // existing choice carries over and persists via the same keys).
        $filter = (string) get_user_preferences('block_myoverview_user_grouping_preference', 'all');
        // "Remove from view" is disabled; never initialise a student on the removed/hidden filter
        // (its chip is gone and it would show an empty list).
        if ($filter === 'hidden') {
            $filter = 'all';
        }
        $sort = (string) get_user_preferences('block_myoverview_user_sort_preference', 'fullname');
        // Core block_myoverview stores "Last accessed" as the pref value 'lastaccessed'
        // (older/other paths may store 'ul.timeaccess desc'); everything else is a
        // name sort. Normalise to the two modes the bespoke page offers, so the
        // toolbar label renders correctly on first paint (the JS re-syncs too).
        $sortislast = ($sort === 'lastaccessed' || stripos($sort, 'timeaccess') !== false);
        $view = (string) get_user_preferences('block_myoverview_user_view_preference', 'card');
        $validview = in_array($view, ['card', 'list', 'summary'], true) ? $view : 'card';

        return [
            'sitename'       => format_string($GLOBALS['SITE']->shortname),
            'firstname'      => ucwords(\core_text::strtolower($USER->firstname)),
            'coursesurl'     => (new moodle_url('/my/courses.php'))->out(false),
            'calendarurl'    => (new moodle_url('/calendar/view.php'))->out(false),

            // Student e-ID Card (nav).
            'showeidcard'    => $showeidcard,
            'eidcardurl'     => (new moodle_url('/local/studentid/index.php'))->out(false),

            // Support (floating "Need help?" panel, opened from the nav).
            'support_html'   => $support,
            'hassupport'     => $support !== null,
            'askjo'          => $askjo,
            'hasaskjo'       => !empty($askjo),
            'dashboardurl'   => (new moodle_url('/my/'))->out(false),

            'groups'         => $groups,
            'hasgroups'      => !empty($groups),
            'hascourses'     => !empty($courses),
            'totalcourses'   => $stats['total'],

            // Header stats.
            'kpitotal'       => $stats['total'],
            'kpisemester'    => $stats['semester'],
            'kpiinprogress'  => $stats['inprogress'],
            'kpicompleted'   => $stats['completed'],
            'semesternum'    => $stats['highestsem'],
            'hassemester'    => $stats['highestsem'] !== null,

            // Continue learning (featured).
            'featured'       => $featured,
            'hasfeatured'    => !empty($featured),

            // Toolbar initial state.
            'filter'         => $filter,
            'sort'           => $sortislast ? 'lastaccessed' : 'fullname',
            'issortlast'     => $sortislast,
            'view'           => $validview,
            'iscard'         => $validview === 'card',
            'islist'         => $validview === 'list',
            'issummary'      => $validview === 'summary',

            // Client-side search.
            'searchitemsjson' => json_encode($searchitems),

            // Ask JO (reuse the dashboard exporter's chat panel when available).
            'config'         => ['wwwroot' => $CFG->wwwroot],
        ];
    }

    /**
     * Fetch + decorate every enrolled course for the user.
     *
     * @param int $userid
     * @return array [array $courses, array $stats, array $searchitems]
     */
    protected function get_courses(int $userid): array {
        global $DB;

        $enrolled = enrol_get_users_courses($userid, true,
            ['id', 'fullname', 'shortname', 'category', 'visible', 'startdate', 'enddate']);
        if (!$enrolled) {
            return [[], ['total' => 0, 'semester' => 0, 'inprogress' => 0, 'completed' => 0, 'highestsem' => null], []];
        }

        // Batch category names.
        $catids = array_unique(array_map(fn($c) => $c->category, $enrolled));
        $catnames = $DB->get_records_list('course_categories', 'id', $catids, '', 'id, name');

        // Favourites (starred) + hidden (removed from view) + last access — batched.
        $starred = $this->get_favourite_courseids($userid);
        $hidden = $this->get_hidden_courseids($userid);
        $lastaccess = $this->get_last_access($userid);

        // Tag each course with its semester first, so highest_semester() works
        // (it reads ->semesternum) and the header counts can be scoped correctly.
        foreach ($enrolled as $course) {
            $catname = isset($catnames[$course->category]) ? $catnames[$course->category]->name : '';
            $course->semesternum = semester::semester_of($catname);
        }
        $highest = semester::highest_semester($enrolled);

        $now = time();
        $courses = [];
        $searchitems = [];
        $stats = ['total' => 0, 'semester' => 0, 'inprogress' => 0, 'completed' => 0, 'highestsem' => $highest];
        $i = 0;

        foreach ($enrolled as $course) {
            $catname = isset($catnames[$course->category]) ? $catnames[$course->category]->name : '';
            $semnum = $course->semesternum;
            // "Current semester" = the highest semester the student has; when no
            // semester is detectable, treat every course as current.
            $iscurrent = ($highest === null) || ($semnum === $highest);

            [$pct, $done, $total] = $this->course_progress($course, $userid);
            if ($pct === null) {
                $status = 'new';
                $pctint = 0;
            } else {
                $pctint = (int) round($pct);
                $status = $pctint >= 100 ? 'done' : ($pctint > 0 ? 'prog' : 'new');
            }

            $classification = $this->classify($course, $now);
            // Two base cover colours alternated even/odd (deep blue / sky blue) —
            // used as the cover when the course has no uploaded image.
            $palette = ($i % 2 === 0) ? self::COVER_EVEN : self::COVER_ODD;
            $courseimg = $this->course_image($course->id);
            $sm = $this->status_meta($status);
            $faculty = $this->course_faculty($course->id);
            $la = $lastaccess[$course->id] ?? 0;

            // Enrolled counts everything; "this sem" / in-progress / completed are
            // scoped to the current semester only.
            $stats['total']++;
            if ($iscurrent) {
                $stats['semester']++;
                if ($status === 'done') {
                    $stats['completed']++;
                } else if ($status === 'prog') {
                    $stats['inprogress']++;
                }
            }

            $courses[] = [
                'id'          => (int) $course->id,
                'name'        => format_string($course->fullname),
                'code'        => format_string($course->shortname),
                'abbr'        => $this->abbreviate($course->fullname),
                'progress'    => $pctint,
                'barwidth'    => max($pctint, 2),
                'barcolor'    => $sm['bar'],
                'done'        => $done,
                'total'       => $total,
                'hasactivities' => $total > 0,
                'status'      => $status,
                'statusbg'    => $sm['bg'],
                'statusink'   => $sm['ink'],
                'statuslabel' => $sm['label'],
                'gradfrom'    => $palette[0],
                'gradto'      => $palette[1],
                'imageurl'    => $courseimg,
                'hasimage'    => $courseimg !== '',
                'url'         => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                'faculty'     => $faculty['name'],
                'facultyabbr' => $faculty['abbr'],
                'hasfaculty'  => $faculty['name'] !== '',
                'starred'     => isset($starred[$course->id]),
                'hidden'      => isset($hidden[$course->id]),
                'classification' => $classification,
                'lastaccess'  => $la,
                'lastlabel'   => $la ? $this->ago($la, $now) : get_string('sc_notopened', 'theme_baz'),
                'cta'         => $status === 'done' ? get_string('sc_review', 'theme_baz')
                                  : ($status === 'new' ? get_string('sc_start', 'theme_baz')
                                  : get_string('sc_continue', 'theme_baz')),
                'catid'       => (int) $course->category,
                'catname'     => format_string($catname),
                'semnum'      => $semnum,
            ];
            $searchitems[] = [
                'label'    => format_string($course->fullname),
                'sublabel' => format_string($catname),
                'url'      => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            ];
            $i++;
        }

        return [$courses, $stats, $searchitems];
    }

    /**
     * Progress percentage + done/total activities for a course (best effort).
     *
     * @param \stdClass $course
     * @param int $userid
     * @return array [int|null $pct, int $done, int $total]
     */
    protected function course_progress(\stdClass $course, int $userid): array {
        try {
            $full = get_course($course->id);
            $pct = \core_completion\progress::get_course_progress_percentage($full, $userid);
            $total = 0;
            $done = 0;
            $completion = new \completion_info($full);
            if ($completion->is_enabled()) {
                $activities = $completion->get_activities();
                $total = count($activities);
                if ($pct !== null && $total > 0) {
                    $done = (int) round(($pct / 100) * $total);
                }
            }
            return [$pct, $done, $total];
        } catch (\Throwable $e) {
            return [null, 0, 0];
        }
    }

    /**
     * Timeline classification: past | inprogress | future.
     *
     * @param \stdClass $course
     * @param int $now
     * @return string
     */
    protected function classify(\stdClass $course, int $now): string {
        $start = (int) ($course->startdate ?? 0);
        $end = (int) ($course->enddate ?? 0);
        if ($end && $end < $now) {
            return 'past';
        }
        if ($start && $start > $now) {
            return 'future';
        }
        return 'inprogress';
    }

    /**
     * The course's uploaded overview image URL, or '' when none is set (the card
     * then falls back to the gradient + initials cover). Reads file metadata only
     * (no content), so it's a cheap per-course query.
     *
     * @param int $courseid
     * @return string image URL or ''
     */
    protected function course_image(int $courseid): string {
        try {
            $fs = get_file_storage();
            $ctx = \context_course::instance($courseid);
            foreach ($fs->get_area_files($ctx->id, 'course', 'overviewfiles', 0, 'filename', false) as $f) {
                if ($f->is_valid_image()) {
                    return \moodle_url::make_pluginfile_url(
                        $f->get_contextid(), 'course', 'overviewfiles', null,
                        $f->get_filepath(), $f->get_filename())->out(false);
                }
            }
        } catch (\Throwable $e) {
            return '';
        }
        return '';
    }

    /**
     * The course's first course-contact (teacher) — name + initials.
     *
     * @param int $courseid
     * @return array ['name' => string, 'abbr' => string]
     */
    protected function course_faculty(int $courseid): array {
        // Honours the "Primary faculty" course custom field, else the first
        // course contact. Shared with the course-page exporter. (The extra 'id'
        // key is unused here but harmless.)
        return \theme_baz\local\faculty::for_course($courseid);
    }

    /**
     * Favourited course ids for the user.
     *
     * @param int $userid
     * @return array courseid => true
     */
    protected function get_favourite_courseids(int $userid): array {
        $out = [];
        try {
            $usercontext = \context_user::instance($userid);
            $service = \core_favourites\service_factory::get_service_for_user_context($usercontext);
            $favourites = $service->find_favourites_by_type('core_course', 'courses');
            foreach ($favourites as $fav) {
                $out[(int) $fav->itemid] = true;
            }
        } catch (\Throwable $e) {
            return [];
        }
        return $out;
    }

    /**
     * Courses the user has "removed from view" (block_myoverview hidden prefs),
     * so the new page stays in sync with the classic one.
     *
     * @param int $userid
     * @return array courseid => true
     */
    protected function get_hidden_courseids(int $userid): array {
        global $DB;
        $out = [];
        try {
            $prefs = $DB->get_records_select('user_preferences',
                'userid = :uid AND ' . $DB->sql_like('name', ':n'),
                ['uid' => $userid, 'n' => 'block_myoverview_hidden_course_%'], '', 'id, name, value');
            foreach ($prefs as $p) {
                if ((string) $p->value === '1') {
                    $cid = (int) substr($p->name, strlen('block_myoverview_hidden_course_'));
                    if ($cid) {
                        $out[$cid] = true;
                    }
                }
            }
        } catch (\Throwable $e) {
            return [];
        }
        return $out;
    }

    /**
     * Last-access timestamp per course for the user.
     *
     * @param int $userid
     * @return array courseid => timeaccess
     */
    protected function get_last_access(int $userid): array {
        global $DB;
        $out = [];
        try {
            $records = $DB->get_records('user_lastaccess', ['userid' => $userid], '', 'courseid, timeaccess');
            foreach ($records as $r) {
                $out[(int) $r->courseid] = (int) $r->timeaccess;
            }
        } catch (\Throwable $e) {
            return [];
        }
        return $out;
    }

    /**
     * Group decorated courses by semester (falling back to category), semesters
     * first (highest first), then other categories alphabetically. Each group
     * carries a count + avg progress.
     *
     * Semester courses bucket on the semester NUMBER, not the category id. Every
     * program tree has its own category literally named "Semester - 01", and some
     * courses are shared across programs -- the Generative AI course lives in the
     * MCA tree but is taken by MBA, M.Com and MA students too. Bucketing on the
     * category id gave those students two groups both titled "Semester - 01",
     * which reads as a duplicate. The program a course is filed under is an
     * internal content detail, so merge rather than disambiguate.
     *
     * Categories with no detectable semester (clubs, Learner Engagement) still
     * bucket per category, since their names are all that distinguish them.
     *
     * @param array $courses
     * @return array
     */
    protected function group_courses(array $courses): array {
        $buckets = [];
        foreach ($courses as $c) {
            $semnum = $c['semnum'];
            $key = $semnum !== null ? 'sem-' . $semnum : 'cat-' . $c['catid'];
            if (!isset($buckets[$key])) {
                $buckets[$key] = ['name' => $c['catname'], 'semnum' => $semnum, 'courses' => [], 'names' => []];
            }
            $buckets[$key]['courses'][] = $c;
            // Merged semesters can span categories whose names differ in spelling
            // ("Semester - 01" vs "Semester - 1"); label the group with whichever
            // spelling covers most of the student's courses.
            $name = $c['catname'];
            $buckets[$key]['names'][$name] = ($buckets[$key]['names'][$name] ?? 0) + 1;
        }
        foreach ($buckets as &$bucket) {
            arsort($bucket['names']);
            $bucket['name'] = (string) array_key_first($bucket['names']);
            unset($bucket['names']);
        }
        unset($bucket);

        $groups = array_values($buckets);
        usort($groups, function($a, $b) {
            // Semester groups first, highest semester first; then by name.
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

        $first = true;
        foreach ($groups as &$g) {
            $sum = 0;
            foreach ($g['courses'] as $c) {
                $sum += $c['progress'];
            }
            $count = count($g['courses']);
            $g['count'] = $count;
            $g['avg'] = $count ? (int) round($sum / $count) : 0;
            $g['open'] = $first; // first (current-semester) group expanded by default.
            $first = false;
        }
        unset($g);
        return $groups;
    }

    /**
     * The "Continue learning" course: the most-recently-accessed in-progress one.
     *
     * @param array $courses
     * @return array|null
     */
    protected function pick_featured(array $courses): ?array {
        $best = null;
        foreach ($courses as $c) {
            if ($c['status'] !== 'prog' || $c['hidden']) {
                continue;
            }
            if ($best === null || $c['lastaccess'] > $best['lastaccess']) {
                $best = $c;
            }
        }
        return $best;
    }

    /**
     * Visual metadata for a completion status.
     *
     * @param string $status one of done|prog|new
     * @return array
     */
    protected function status_meta(string $status): array {
        $map = [
            'done' => ['bar' => '#1FA37A', 'bg' => '#E6F6F0', 'ink' => '#1FA37A', 'label' => get_string('sc_completed', 'theme_baz')],
            'prog' => ['bar' => 'var(--bz-accent)', 'bg' => 'var(--bz-accent-bg)', 'ink' => 'var(--bz-accent-text2)', 'label' => get_string('sc_inprogress', 'theme_baz')],
            'new'  => ['bar' => '#C7D2DC', 'bg' => '#EEF2F6', 'ink' => '#5C7184', 'label' => get_string('sc_notstarted', 'theme_baz')],
        ];
        return $map[$status];
    }

    /**
     * Two-letter monogram from a course name.
     *
     * @param string $name
     * @return string
     */
    protected function abbreviate(string $name): string {
        $name = trim(preg_replace('/^[0-9.\s]+/', '', $name));
        $words = preg_split('/\s+/', $name);
        $letters = '';
        foreach ($words as $word) {
            if ($word !== '' && ctype_alpha($word[0])) {
                $letters .= \core_text::strtoupper(\core_text::substr($word, 0, 1));
            }
            if (\core_text::strlen($letters) >= 2) {
                break;
            }
        }
        return $letters !== '' ? $letters : \core_text::strtoupper(\core_text::substr($name, 0, 2));
    }

    /**
     * Render the real support block HTML (LSM / ticket / SOP) for the nav panel.
     *
     * @return string|null
     */
    protected function render_support(): ?string {
        return theme_baz_support_panel();
    }

    /**
     * Compact "x ago" label.
     *
     * @param int $then
     * @param int $now
     * @return string
     */
    protected function ago(int $then, int $now): string {
        $diff = max(0, $now - $then);
        if ($diff < HOURSECS) {
            return get_string('sc_opened_recent', 'theme_baz');
        }
        if ($diff < DAYSECS) {
            return get_string('sc_opened_ago', 'theme_baz', (int) floor($diff / HOURSECS) . 'h');
        }
        return get_string('sc_opened_ago', 'theme_baz', (int) floor($diff / DAYSECS) . 'd');
    }
}
