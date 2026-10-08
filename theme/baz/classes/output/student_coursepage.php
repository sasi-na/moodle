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
 * Bespoke course-view exporter for theme_baz (student-only).
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
use completion_info;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->dirroot . '/course/lib.php');
require_once($GLOBALS['CFG']->libdir . '/completionlib.php');

/**
 * Renderable + templatable for the bespoke course page.
 */
class student_coursepage implements renderable, templatable {

    /** @var array Live-class modules. */
    const LIVE = ['zoom', 'bigbluebuttonbn', 'customteams', 'teams', 'msteams', 'jitsi', 'collaborate'];

    /** @var array Activity icon inner-SVG markup keyed by display bucket. */
    const ICONS = [
        'file'      => '<path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5"/>',
        'link'      => '<path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/>',
        'page'      => '<path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5M8 13h8M8 17h5"/>',
        'forum'     => '<path d="M21 11.5a8.4 8.4 0 0 1-9 8 9.9 9.9 0 0 1-4-1L3 20l1.5-4a8 8 0 0 1-1-4 8.4 8.4 0 0 1 9-8 8.4 8.4 0 0 1 8.5 7.5z"/>',
        'scheduler' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 9h18M8 3v4M16 3v4M9 14l2 2 4-4"/>',
        'feedback'  => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M8 10h8M8 13h5"/>',
        'quiz'      => '<path d="M9 11l3 3 8-8"/><path d="M20 12v6a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h9"/>',
        'lesson'    => '<path d="M4 5h16M4 5v14a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V5M9 11h6M9 15h4"/>',
        'video'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m10 9 5 3-5 3z" fill="currentColor" stroke="none"/>',
        'assign'    => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
        'live'      => '<rect x="2" y="6" width="14" height="12" rx="2"/><path d="m16 10 6-3v10l-6-3z"/>',
        'other'     => '<circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/>',
    ];

    /**
     * Map a module name to a display bucket (drives icon + tint in the template).
     *
     * @param string $modname
     * @param string $name
     * @return string
     */
    protected function bucket(string $modname, string $name): string {
        if (in_array($modname, self::LIVE, true)) {
            return 'live';
        }
        $map = [
            'resource' => 'file', 'folder' => 'file', 'url' => 'link',
            'book' => 'lesson', 'lesson' => 'lesson', 'quiz' => 'quiz', 'assign' => 'assign',
            'forum' => 'forum', 'feedback' => 'feedback', 'choice' => 'feedback', 'scheduler' => 'scheduler',
        ];
        if ($modname === 'page') {
            return preg_match('/video/i', $name) ? 'video' : 'page';
        }
        return $map[$modname] ?? 'other';
    }

    /**
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $USER, $CFG, $DB, $PAGE;
        $course = $PAGE->course;
        $userid = (int) $USER->id;
        $context = \context_course::instance($course->id);

        $modinfo = get_fast_modinfo($course, $userid);
        $completion = new completion_info($course);
        $completionon = $completion->is_enabled();
        $format = course_get_format($course);

        $sectionsout = [];
        $overalldone = 0;
        $overalltotal = 0;
        $resume = null;
        $secidx = 0;

        foreach ($modinfo->get_section_info_all() as $section) {
            if (!$section->uservisible) {
                continue;
            }
            $cmids = $modinfo->sections[$section->section] ?? [];
            $acts = [];
            $sdone = 0;
            $stotal = 0;
            foreach ($cmids as $cmid) {
                $cm = $modinfo->cms[$cmid];
                if (!$cm->uservisible || $cm->deletioninprogress) {
                    continue;
                }
                if ($cm->modname === 'label') {
                    // Labels are inline text/media blocks (often section instructions);
                    // render their content in place rather than as an activity row.
                    $labelhtml = '';
                    try {
                        $labelhtml = trim((string) $cm->get_formatted_content(
                            ['overflowdiv' => false, 'noclean' => true, 'filter' => true]));
                    } catch (\Throwable $e) {
                        $labelhtml = '';
                    }
                    if ($labelhtml !== '') {
                        $acts[] = ['islabel' => true, 'labelhtml' => $labelhtml];
                    }
                    continue;
                }
                $name = format_string($cm->name);
                $bucket = $this->bucket($cm->modname, $name);
                $hasurl = !empty($cm->url);

                // Completion state (+ manual toggle capability).
                $state = 'none';
                $tracked = false;
                $manual = false;
                $manualtarget = 1;
                if ($completionon && $completion->is_enabled($cm) != COMPLETION_TRACKING_NONE) {
                    $tracked = true;
                    $cd = $completion->get_data($cm, false, $userid);
                    $done = in_array((int) $cd->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true);
                    $state = $done ? 'done' : 'todo';
                    $stotal++;
                    if ($done) {
                        $sdone++;
                    }
                    if ((int) $completion->is_enabled($cm) === COMPLETION_TRACKING_MANUAL) {
                        $manual = true;
                        $manualtarget = $done ? 0 : 1;
                    }
                }

                $available = empty($cm->availableinfo) && $cm->available;
                $cta = $this->cta($bucket, $state === 'done', $available);

                // Activity dates (opened / due / closes).
                $dates = [];
                try {
                    if (class_exists('\core\activity_dates')) {
                        foreach (\core\activity_dates::get_dates_for_module($cm, $userid) as $d) {
                            if (!empty($d['timestamp'])) {
                                $dates[] = [
                                    'label' => trim((string) ($d['label'] ?? '')),
                                    'text'  => userdate($d['timestamp'], get_string('strftimedatetimeshort')),
                                ];
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    $dates = [];
                }

                // Inline description shown on the course page.
                $desc = '';
                if (!empty($cm->showdescription)) {
                    try {
                        $desc = trim((string) $cm->get_formatted_content(['overflowdiv' => false, 'noclean' => true, 'filter' => true]));
                    } catch (\Throwable $e) {
                        $desc = '';
                    }
                }

                // Forum unread count.
                $unread = 0;
                if ($cm->modname === 'forum') {
                    try {
                        require_once($GLOBALS['CFG']->dirroot . '/mod/forum/lib.php');
                        if (function_exists('forum_tp_count_forum_unread_posts')) {
                            $unread = (int) forum_tp_count_forum_unread_posts($cm, $course);
                        }
                    } catch (\Throwable $e) {
                        $unread = 0;
                    }
                }

                $acts[] = [
                    'name'   => $name,
                    'type'   => $bucket,
                    'iconsvg' => self::ICONS[$bucket] ?? self::ICONS['other'],
                    'typelabel' => $this->typelabel($cm->modname),
                    'meta'   => $cm->availableinfo ? strip_tags(format_string($cm->availableinfo)) : '',
                    'haslock' => !empty($cm->availableinfo),
                    'state'  => $state,        // done | todo | none
                    'isdone' => $state === 'done',
                    'tracked' => $tracked,
                    'manual' => $manual,
                    'manualtarget' => $manualtarget,
                    'cmid'   => (int) $cm->id,
                    'hasurl' => $hasurl,
                    'url'    => $hasurl ? $cm->url->out(false) : '',
                    'cta'    => $cta,
                    'dates'  => $dates,
                    'hasdates' => !empty($dates),
                    'desc'   => $desc,
                    'hasdesc' => $desc !== '',
                    'unread' => $unread,
                    'hasunread' => $unread > 0,
                ];

                if ($resume === null && $state === 'todo' && $available && $hasurl) {
                    $resume = ['name' => $name, 'url' => $cm->url->out(false), 'section' => '', 'type' => $this->typelabel($cm->modname)];
                }
            }

            if (empty($acts)) {
                continue;
            }
            $secname = format_string($format->get_section_name($section));
            $pct = $stotal ? (int) round($sdone / $stotal * 100) : 0;

            // Section summary/intro text.
            $secsummary = '';
            if (!empty($section->summary)) {
                $secsummary = trim(format_text($section->summary, $section->summaryformat,
                    ['context' => $context, 'noclean' => true]));
            }
            // Section-level restriction message ("Not available unless…").
            $secrestrict = '';
            if (!empty($section->availableinfo)) {
                $secrestrict = strip_tags(format_string($section->availableinfo));
            }

            $sectionsout[] = [
                'id'      => 's-' . $section->id,
                'name'    => $secname,
                'summary' => $secsummary,
                'hassummary' => $secsummary !== '',
                'restrict' => $secrestrict,
                'hasrestrict' => $secrestrict !== '',
                'acts'    => $acts,
                'done'    => $sdone,
                'total'   => $stotal,
                'pct'     => $pct,
                'hastotal' => $stotal > 0,
                'open'    => $secidx < 3, // first three expanded.
                'tocstate' => $stotal ? ($pct >= 100 ? 'done' : ($pct > 0 ? 'prog' : 'todo')) : 'todo',
            ];
            $overalldone += $sdone;
            $overalltotal += $stotal;
            $secidx++;
        }

        $overallpct = $overalltotal ? (int) round($overalldone / $overalltotal * 100) : 0;
        if ($resume === null && !empty($sectionsout)) {
            // Everything done (or nothing tracked): point at the first activity.
            $firstsec = $sectionsout[0];
            if (!empty($firstsec['acts'])) {
                $a = $firstsec['acts'][0];
                $resume = ['name' => $a['name'], 'url' => $a['url'], 'type' => $a['typelabel']];
            }
        }

        // --- Faculty / mentor + scheduler "book" link. ---
        $faculty = $this->course_faculty($course->id);
        $bookurl = $this->scheduler_url($modinfo);

        // Message-the-mentor link: only when core messaging is on and the mentor
        // is a real, different user from the student.
        $messageurl = null;
        if (!empty($CFG->messaging) && !empty($faculty['id']) && $faculty['id'] !== (int) $userid) {
            $messageurl = (new moodle_url('/message/index.php', ['id' => $faculty['id']]))->out(false);
        }

        // --- Announcements (news forum). ---
        $announcements = $this->announcements($course->id);

        // --- Key dates (course calendar events, next 45 days). ---
        $keydates = $this->key_dates($userid, $course->id);

        // --- Eyebrow: semester / category. ---
        $catname = '';
        if (!empty($course->category)) {
            $cat = $DB->get_record('course_categories', ['id' => $course->category], 'name');
            $catname = $cat ? format_string($cat->name) : '';
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

        return [
            'config'       => ['wwwroot' => $CFG->wwwroot],
            'coursename'   => format_string($course->fullname),
            'eyebrow'      => $catname !== '' ? $catname : format_string($course->shortname),
            'sectioncount' => count($sectionsout),
            'overallpct'   => $overallpct,
            'sections'     => $sectionsout,
            'hassections'  => !empty($sectionsout),
            'toc'          => array_map(fn($s) => [
                'id' => $s['id'], 'name' => $s['name'], 'done' => $s['done'], 'total' => $s['total'],
                'tocstate' => $s['tocstate'],
            ], $sectionsout),
            'resume'       => $resume,
            'hasresume'    => $resume !== null,
            'faculty'      => $faculty['name'],
            'facultyabbr'  => $faculty['abbr'],
            'hasfaculty'   => $faculty['name'] !== '',
            'bookurl'      => $bookurl,
            'hasbook'      => $bookurl !== null,
            'messageurl'   => $messageurl,
            'hasmessage'   => $messageurl !== null,
            'announcements' => $announcements,
            'hasannounce'  => !empty($announcements),
            'keydates'     => $keydates,
            'haskeydates'  => !empty($keydates),

            // Sesskey for the inline manual-completion toggle form.
            'sesskey'      => sesskey(),
            'togglecompletionurl' => (new moodle_url('/course/togglecompletion.php'))->out(false),
            'courseviewurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            // Badges tab (only when the course awards active badges).
            'hasbadges'    => $DB->record_exists_select('badge', 'courseid = ? AND status IN (1,3)', [$course->id]),
            'badgesurl'    => (new moodle_url('/badges/view.php', ['type' => 2, 'id' => $course->id]))->out(false),

            // Tab + nav URLs.
            'gradesurl'    => (new moodle_url('/grade/report/user/index.php', ['id' => $course->id]))->out(false),
            'participantsurl' => (new moodle_url('/user/index.php', ['id' => $course->id]))->out(false),
            'competenciesurl' => (new moodle_url('/admin/tool/lp/coursecompetencies.php', ['courseid' => $course->id]))->out(false),
            // Competencies tab: only shown when the course actually has competencies
            // (none do on JAIN today), so students don't see an empty tab.
            'hascompetencies' => $DB->record_exists('competency_coursecomp', ['courseid' => $course->id]),
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
     * CTA label for an activity row.
     */
    protected function cta(string $bucket, bool $done, bool $available): string {
        if (!$available) {
            return get_string('cp_locked', 'theme_baz');
        }
        if ($bucket === 'live') {
            return get_string('cp_join', 'theme_baz');
        }
        if ($bucket === 'scheduler') {
            return get_string('cp_book', 'theme_baz');
        }
        if ($bucket === 'quiz') {
            return $done ? get_string('cp_review', 'theme_baz') : get_string('cp_attempt', 'theme_baz');
        }
        if ($bucket === 'assign') {
            return $done ? get_string('cp_view', 'theme_baz') : get_string('cp_submit', 'theme_baz');
        }
        if ($bucket === 'video') {
            return $done ? get_string('cp_rewatch', 'theme_baz') : get_string('cp_watch', 'theme_baz');
        }
        return $done ? get_string('cp_review', 'theme_baz') : get_string('cp_open', 'theme_baz');
    }

    /**
     * Human module type label.
     */
    protected function typelabel(string $modname): string {
        if (in_array($modname, self::LIVE, true)) {
            return get_string('cp_liveclass', 'theme_baz');
        }
        if ($modname === 'page') {
            return get_string('pluginname', 'mod_page');
        }
        try {
            return get_string('pluginname', 'mod_' . $modname);
        } catch (\Throwable $e) {
            return ucfirst($modname);
        }
    }

    /**
     * Course contact (faculty) name, initials + user id.
     */
    protected function course_faculty(int $courseid): array {
        // Honours the "Primary faculty" course custom field, else the first
        // course contact. Shared with the course-cards exporter.
        return \theme_baz\local\faculty::for_course($courseid);
    }

    /**
     * A scheduler (mentoring) activity URL in the course, if any.
     */
    protected function scheduler_url(\course_modinfo $modinfo): ?string {
        foreach (['scheduler', 'organizer'] as $mod) {
            if (!empty($modinfo->instances[$mod])) {
                foreach ($modinfo->instances[$mod] as $cm) {
                    if ($cm->uservisible && $cm->url) {
                        return $cm->url->out(false);
                    }
                }
            }
        }
        return null;
    }

    /**
     * Latest announcements from the course news forum.
     */
    protected function announcements(int $courseid): array {
        global $DB, $CFG;
        $out = [];
        try {
            $forum = $DB->get_record('forum', ['course' => $courseid, 'type' => 'news'], 'id', IGNORE_MULTIPLE);
            if (!$forum) {
                return [];
            }
            $rows = $DB->get_records('forum_discussions', ['forum' => $forum->id], 'timemodified DESC', 'id, name, timemodified', 0, 3);
            foreach ($rows as $r) {
                $out[] = [
                    'title' => format_string($r->name),
                    'when'  => $this->ago((int) $r->timemodified),
                    'url'   => (new moodle_url('/mod/forum/discuss.php', ['d' => $r->id]))->out(false),
                ];
            }
        } catch (\Throwable $e) {
            return [];
        }
        return $out;
    }

    /**
     * Upcoming course calendar events (next 45 days).
     */
    protected function key_dates(int $userid, int $courseid): array {
        global $CFG;
        require_once($CFG->dirroot . '/calendar/lib.php');
        $out = [];
        try {
            $now = time();
            $events = calendar_get_events($now, $now + 45 * DAYSECS, $userid, false, [$courseid], true, true);
            foreach ($events as $e) {
                $url = !empty($e->modulename) && !empty($e->instance)
                    ? (new moodle_url('/mod/' . $e->modulename . '/view.php', ['id' => 0]))->out(false)
                    : (new moodle_url('/calendar/view.php', ['view' => 'day', 'time' => (int) $e->timestart]))->out(false);
                $out[] = [
                    'day'   => userdate($e->timestart, '%e'),
                    'mon'   => userdate($e->timestart, '%b'),
                    'name'  => format_string($e->name),
                    'time'  => userdate($e->timestart, get_string('strftimetime24', 'langconfig')),
                ];
                if (count($out) >= 4) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            return [];
        }
        return $out;
    }

    /**
     * Compact "x ago" label.
     */
    protected function ago(int $then): string {
        $diff = max(0, time() - $then);
        if ($diff < DAYSECS) {
            return (int) floor($diff / HOURSECS) . 'h ago';
        }
        if ($diff < 30 * DAYSECS) {
            return (int) floor($diff / DAYSECS) . 'd ago';
        }
        return userdate($then, '%e %b');
    }

    /**
     * Real support block HTML for the floating help panel.
     */
    protected function render_support(): ?string {
        return theme_baz_support_panel();
    }
}
