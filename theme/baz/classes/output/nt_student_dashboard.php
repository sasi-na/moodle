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

namespace theme_baz\output;

use renderer_base;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

/**
 * Student dashboard -- "New theme" (two-level top bar) design.
 *
 * Exports exactly the same data as student_dashboard (parent::export_for_template)
 * and only adds presentation helpers on top of it: a merged "Coming up" list,
 * per-course code chips, segment widths for the progress bar and a study-time
 * chart (time between logged actions, gaps over 30 minutes not counted).
 *
 * @package   theme_baz
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class nt_student_dashboard extends student_dashboard {

    /** @var array|null Memoised get_courses() result (parent + "Coming up" share it). */
    protected $ntcourses = null;

    /** @var int Idle gap (seconds) after which time between two actions is not counted. */
    const IDLE_GAP = 1800;

    /** @var array Bar colours for the course list (cycled). */
    const COURSE_COLORS = ['#6D4AE8', '#3B6FE0', '#1FA37A', '#F2A33A', '#E5556E', '#0F1F3D'];

    /** @var array Chip tints matching COURSE_COLORS. */
    const CHIP_TINTS = ['#EFEBFD', '#EAF0FC', '#E6F6F0', '#FEF3E2', '#FDECEF', '#E9ECF2'];

    /**
     * Month grid events: the parent's course + personal events, plus site-wide
     * events (anything an admin adds to the site calendar).
     */
    protected function get_month_events(int $userid, array $courseids, int $now): array {
        return parent::get_month_events($userid, array_values(array_unique(array_merge($courseids, [SITEID]))), $now);
    }

    protected function get_courses(int $userid): array {
        if ($this->ntcourses === null) {
            $this->ntcourses = parent::get_courses($userid);
        }
        return $this->ntcourses;
    }

    public function export_for_template(renderer_base $output): array {
        global $USER;
        $ctx = parent::export_for_template($output);
        $userid = (int) $USER->id;
        $now = time();

        // --- Course list: split a leading code ("4.6 Business ...") into a chip. ---
        $courses = [];
        foreach ($ctx['courses'] as $i => $c) {
            $name = $c['name'];
            $code = '';
            if (preg_match('/^\s*(\d+(?:\.\d+)*)\s*[-:.]?\s+(.+)$/u', $name, $m)) {
                $code = $m[1];
                $name = $m[2];
            }
            $courses[] = $c + [
                'ntcode'  => $code !== '' ? $code : $c['abbr'],
                'ntname'  => $name,
                'ntcolor' => self::COURSE_COLORS[$i % count(self::COURSE_COLORS)],
                'nttint'  => self::CHIP_TINTS[$i % count(self::CHIP_TINTS)],
                'ntdone'  => ((float)($c['progress'] ?? 0)) >= 100,
            ];
        }
        $ctx['ntcourses'] = $courses;

        // --- Progress bar segments (share of courses per state). ---
        $total = (int) $ctx['ringcompleted'] + (int) $ctx['ringinprogress'] + (int) $ctx['ringnotstarted'];
        $seg = function($n) use ($total) {
            return $total > 0 ? round(($n / $total) * 100, 2) : 0;
        };
        $ctx['ntsegdone'] = $seg((int) $ctx['ringcompleted']);
        $ctx['ntsegprog'] = $seg((int) $ctx['ringinprogress']);
        $ctx['ntsegnew'] = $seg((int) $ctx['ringnotstarted']);
        $ctx['nthasnotstarted'] = (int) $ctx['ringnotstarted'] > 0;

        // --- Focus next: add the course's own progress figure. ---
        $ctx['ntfocuspct'] = 0;
        $ctx['ntfocusname'] = '';
        if (!empty($ctx['focus'])) {
            foreach ($ctx['courses'] as $c) {
                if ($c['url'] === $ctx['focus']['url']) {
                    $ctx['ntfocuspct'] = (int) $c['progress'];
                    break;
                }
            }
            $ctx['ntfocusname'] = $ctx['focus']['name'];
        }
        $ctx = array_merge($ctx, $this->nt_focus_details($ctx, $userid, $now));
        $ctx = array_merge($ctx, $this->nt_recent_grades($userid));
        $ctx = array_merge($ctx, $this->nt_week_stats($userid, $now));
        $ctx['ntuserid'] = $userid;
        $ctx['ntgreeting'] = $ctx['greetingtime'] . ', ' . self::nt_display_name();
        $ctx['ntisevening'] = (int) userdate($now, '%H') >= 17;
        $ctx['ntsubline'] = get_string('sd_herosub', 'theme_baz', (object) [
            'deadlines' => (int) $ctx['deadlinecount'], 'classes' => (int) $ctx['liveclasscount']]);

        // --- Mini month calendar: mark days already gone. ---
        if (!empty($ctx['calendar']['cells'])) {
            $todaynum = (int) userdate($now, '%d');
            foreach ($ctx['calendar']['cells'] as &$cell) {
                $cell['past'] = empty($cell['blank']) && (int) $cell['day'] < $todaynum;
            }
            unset($cell);
        }

        // --- Holidays & festivals (Ednxt Top Bar setting) on the month grid. ---
        $holidays = self::nt_holidays();
        $ym = userdate($now, '%Y-%m');
        $monthhols = [];
        if (!empty($ctx['calendar']['cells'])) {
            foreach ($ctx['calendar']['cells'] as &$cell) {
                if (!empty($cell['blank'])) {
                    continue;
                }
                $key = $ym . '-' . sprintf('%02d', (int) $cell['day']);
                if (isset($holidays[$key])) {
                    $h = $holidays[$key];
                    $cell['isholiday'] = $h['type'] === 'holiday';
                    $cell['isfestival'] = $h['type'] !== 'holiday';
                    $cell['holiday'] = $h['name'];
                    $monthhols[] = ['day' => (int) $cell['day'], 'mon' => \core_text::strtoupper(userdate($now, '%b')),
                        'name' => $h['name'], 'isholiday' => $h['type'] === 'holiday',
                        'past' => !empty($cell['past'])];
                }
            }
            unset($cell);
        }
        $ctx['ntmonthhols'] = $monthhols;
        // Month summary + countdown to the next holiday (fills the Coming up card).
        $nhol = 0;
        $nfest = 0;
        foreach ($monthhols as $mh) {
            $mh['isholiday'] ? $nhol++ : $nfest++;
        }
        $nsched = 0;
        foreach ($ctx['calendar']['cells'] ?? [] as $cell) {
            if (!empty($cell['isdue'])) {
                $nsched++;
            }
        }
        $ctx['ntsumhol'] = $nhol;
        $ctx['ntsumfest'] = $nfest;
        $ctx['ntsumsched'] = $nsched;
        $ctx['ntnexthol'] = null;
        $todaymid = usergetmidnight($now);
        for ($d = 0; $d <= 120; $d++) {
            $ts = $todaymid + $d * DAYSECS;
            $key = userdate($ts, '%Y-%m-%d');
            if (isset($holidays[$key]) && $holidays[$key]['type'] === 'holiday') {
                $ctx['ntnexthol'] = [
                    'name' => $holidays[$key]['name'],
                    'date' => userdate($ts, '%a, %d %b'),
                    'days' => $d,
                    'istoday' => $d === 0,
                    'istomorrow' => $d === 1,
                ];
                break;
            }
        }
        $ctx['nthasnexthol'] = !empty($ctx['ntnexthol']);
        $ctx['nthasmonthhols'] = !empty($monthhols);

        // --- Announcements: latest posts in the site + this semester's news forums. ---
        $ctx = array_merge($ctx, $this->nt_announcements($userid, $now));

        // --- Level Up: progress ring geometry (r = 30). ---
        if (!empty($ctx['xp'])) {
            $c = 2 * M_PI * 30;
            $ratio = max(0, min(100, (float) $ctx['xp']['ratio']));
            $ctx['xp']['ringdash'] = round($c * $ratio / 100, 2) . ' ' . round($c, 2);
        }

        // --- Coming up: deadlines + live classes over the next 30 days. ---
        $ctx = array_merge($ctx, $this->coming_up($userid, $now));

        // --- Study time (last 7 days / last 7 weeks). ---
        $ctx = array_merge($ctx, $this->study_time($userid, $now));

        return $ctx;
    }

    /**
     * Next deadlines and live classes (30-day window) as one list.
     *
     * @param int $userid
     * @param int $now
     * @return array
     */
    protected function coming_up(int $userid, int $now): array {
        $out = ['ntcoming' => [], 'nthascoming' => false, 'ntnextlive' => ''];
        try {
            [, , $shortnames] = $this->get_courses($userid);
            [$deadlinedays, $livedays] = $this->get_calendar($userid, $now, $now + 30 * DAYSECS,
                array_values(array_unique(array_merge(array_keys($shortnames ?: []), [SITEID]))), $shortnames ?: []);
            $rows = [];
            foreach ([[$livedays, true], [$deadlinedays, false]] as [$days, $islive]) {
                foreach ($days as $day) {
                    foreach ($day['events'] as $ev) {
                        $ev['islive'] = $islive;
                        $rows[] = $ev;
                    }
                }
            }
            usort($rows, function($a, $b) {
                return $a['timestart'] <=> $b['timestart'];
            });
            $items = [];
            foreach ($rows as $ev) {
                if ($ev['islive'] && $out['ntnextlive'] === '') {
                    $out['ntnextlive'] = userdate($ev['timestart'], '%a %H:%M');
                }
                if (count($items) >= 5) {
                    continue;
                }
                $soon = ($ev['timestart'] - $now) < 3 * DAYSECS;
                $tone = $ev['islive'] ? 'blue' : ($soon ? 'red' : 'amber');
                $items[] = [
                    'day'    => userdate($ev['timestart'], '%d'),
                    'mon'    => \core_text::strtoupper(userdate($ev['timestart'], '%b')),
                    'title'  => $ev['islive']
                        ? get_string('nt_liveprefix', 'theme_baz', $ev['name'])
                        : $ev['name'],
                    'meta'   => userdate($ev['timestart'], '%a · %H:%M')
                        . ($ev['coursename'] !== '' ? ' · ' . $ev['coursename'] : ''),
                    'url'    => $ev['url'],
                    'external' => !empty($ev['external']),
                    'tone'   => $tone,
                    'sort'   => (int) $ev['timestart'],
                ];
            }
            // Holidays & festivals in the same 30-day window.
            $hols = self::nt_holidays();
            for ($d = 0; $d <= 30; $d++) {
                $ts = usergetmidnight($now) + $d * DAYSECS;
                $key = userdate($ts, '%Y-%m-%d');
                if (!isset($hols[$key])) {
                    continue;
                }
                $items[] = [
                    'day' => userdate($ts, '%d'),
                    'mon' => \core_text::strtoupper(userdate($ts, '%b')),
                    'title' => $hols[$key]['name'],
                    'meta' => userdate($ts, '%A') . ' · ' . get_string($hols[$key]['type'] === 'holiday'
                        ? 'nt_holiday' : 'nt_festival', 'theme_baz'),
                    'url' => (new moodle_url('/calendar/view.php', ['view' => 'day', 'time' => $ts]))->out(false),
                    'external' => false,
                    'tone' => $hols[$key]['type'] === 'holiday' ? 'holiday' : 'festival',
                    'sort' => $ts,
                ];
            }
            usort($items, function($a, $b) {
                return ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0);
            });
            $items = array_slice($items, 0, 4);
            $out['ntcoming'] = $items;
            $out['nthascoming'] = !empty($items);
        } catch (\Throwable $e) {
            debugging('nt_student_dashboard coming up: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        return $out;
    }

    /**
     * Time on the platform, from the user's own log: the gap between two
     * consecutive actions counts when it is shorter than IDLE_GAP.
     *
     * @param int $userid
     * @param int $now
     * @return array
     */
    protected function study_time(int $userid, int $now): array {
        global $DB;
        $todaystart = usergetmidnight($now);
        $daystarts = [];
        for ($i = 48; $i >= 0; $i--) {
            $daystarts[] = $todaystart - $i * DAYSECS;
        }
        $secs = array_fill(0, 49, 0);
        try {
            $rs = $DB->get_recordset_sql(
                "SELECT id, timecreated FROM {logstore_standard_log}
                  WHERE userid = :u AND timecreated >= :since
               ORDER BY timecreated ASC, id ASC",
                ['u' => $userid, 'since' => $daystarts[0]]);
            $prev = null;
            foreach ($rs as $r) {
                $t = (int) $r->timecreated;
                if ($prev !== null) {
                    $gap = $t - $prev;
                    if ($gap > 0 && $gap <= self::IDLE_GAP) {
                        $idx = (int) floor(($prev - $daystarts[0]) / DAYSECS);
                        if ($idx >= 0 && $idx < 49) {
                            $secs[$idx] += $gap;
                        }
                    }
                }
                $prev = $t;
            }
            $rs->close();
        } catch (\Throwable $e) {
            debugging('nt_student_dashboard study time: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        // Last 7 days.
        $week = array_slice($secs, 42, 7);
        $weekmax = max(1, max($week));
        $daybars = [];
        $bestidx = null;
        foreach ($week as $i => $s) {
            if ($s > 0 && ($bestidx === null || $s > $week[$bestidx])) {
                $bestidx = $i;
            }
            $daybars[] = [
                'label'  => userdate($daystarts[42 + $i], '%a'),
                'value'  => $this->fmt_hours($s),
                'height' => $s > 0 ? max(4, (int) round(($s / $weekmax) * 100)) : 0,
                'today'  => $i === 6,
            ];
        }
        $weektotal = array_sum($week);

        // Last 7 weeks (each bar = 7 days, oldest first).
        $weeks = [];
        for ($w = 0; $w < 7; $w++) {
            $weeks[] = array_sum(array_slice($secs, $w * 7, 7));
        }
        $wmax = max(1, max($weeks));
        $weekbars = [];
        foreach ($weeks as $w => $s) {
            $weekbars[] = [
                'label'  => userdate($daystarts[$w * 7], '%d %b'),
                'value'  => $this->fmt_hours($s),
                'height' => $s > 0 ? max(4, (int) round(($s / $wmax) * 100)) : 0,
                'today'  => $w === 6,
            ];
        }
        $allbest = null;
        foreach ($weeks as $w => $s) {
            if ($s > 0 && ($allbest === null || $s > $weeks[$allbest])) {
                $allbest = $w;
            }
        }

        // 7-week activity heatmap (Options 3-5): one cell per day, oldest first, level 0-4.
        $heatmax = max(1, max($secs));
        $heat = [];
        foreach ($secs as $i => $s) {
            $heat[] = [
                'label' => userdate($daystarts[$i], '%a %d %b'),
                'value' => $this->fmt_hours($s),
                'lvl'   => $s > 0 ? max(1, min(4, (int) ceil(($s / $heatmax) * 4))) : 0,
                'today' => $i === 48,
            ];
        }

        return [
            'ntheat'        => $heat,
            'ntweeksecs'    => (int) $weektotal,
            'ntdaybars'     => $daybars,
            'ntweekbars'    => $weekbars,
            'ntweektotal'   => $this->fmt_hours($weektotal),
            'ntweekavg'     => $this->fmt_hours((int) round($weektotal / 7)),
            'ntweekbest'    => $bestidx !== null ? $daybars[$bestidx]['label'] : '—',
            'nt7wtotal'     => $this->fmt_hours(array_sum($weeks)),
            'nt7wavg'       => $this->fmt_hours((int) round(array_sum($weeks) / 7)),
            'nt7wbest'      => $allbest !== null ? $weekbars[$allbest]['label'] : '—',
        ];
    }

    /**
     * Details for the "Focus next" card: activities done / total, the next
     * activity to do (first incomplete one, in course order), when the course
     * was last opened and the course's final grade so far.
     *
     * @param array $ctx
     * @param int $userid
     * @param int $now
     * @return array
     */
    protected function nt_focus_details(array $ctx, int $userid, int $now): array {
        global $DB, $CFG;
        $out = ['ntfocusstats' => false];
        if (empty($ctx['focus']['url']) || !preg_match('/[?&]id=(\d+)/', $ctx['focus']['url'], $m)) {
            return $out;
        }
        $courseid = (int) $m[1];
        try {
            require_once($CFG->libdir . '/completionlib.php');
            $course = get_course($courseid);
            $modinfo = get_fast_modinfo($course, $userid);
            $completion = new \completion_info($course);
            $total = 0;
            $done = 0;
            $next = null;
            if ($completion->is_enabled()) {
                foreach ($modinfo->get_cms() as $cm) {
                    if (!$cm->uservisible || $cm->deletioninprogress || !$completion->is_enabled($cm)) {
                        continue;
                    }
                    $total++;
                    $state = $completion->get_data($cm, false, $userid)->completionstate;
                    if (in_array((int) $state, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true)) {
                        $done++;
                    } else if ($next === null && $cm->url) {
                        $next = $cm;
                    }
                }
            }
            $last = $DB->get_field('user_lastaccess', 'timeaccess', ['userid' => $userid, 'courseid' => $courseid]);
            $grade = null;
            try {
                require_once($CFG->libdir . '/gradelib.php');
                $gi = \grade_item::fetch_course_item($courseid);
                if ($gi) {
                    $gg = new \grade_grade(['itemid' => $gi->id, 'userid' => $userid]);
                    if ($gg->finalgrade !== null && !$gg->is_hidden()) {
                        $grade = grade_format_gradevalue($gg->finalgrade, $gi, true, GRADE_DISPLAY_TYPE_PERCENTAGE);
                    }
                }
            } catch (\Throwable $e) {
                $grade = null;
            }
            $out['ntfocusstats'] = true;
            $out['ntfocusdone'] = $done;
            $out['ntfocustotal'] = $total;
            $out['ntfocushastotal'] = $total > 0;
            $out['ntfocusleft'] = max(0, $total - $done);
            $out['ntfocuslast'] = $last ? $this->nt_ago((int) $last, $now) : get_string('never');
            $out['ntfocusgrade'] = $grade;
            $out['ntfocushasgrade'] = $grade !== null && $grade !== '-';
            if ($next) {
                $out['ntfocusnext'] = [
                    'name' => format_string($next->name, true, ['context' => $next->context]),
                    'kind' => get_string('pluginname', 'mod_' . $next->modname),
                    'url' => $next->url->out(false),
                    'icon' => $this->module_icon_svg($next->modname),
                ];
            }
            $out['ntfocushasnext'] = $next !== null;
        } catch (\Throwable $e) {
            debugging('nt_student_dashboard focus details: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $out['ntfocusstats'] = false;
        }
        return $out;
    }

    /**
     * The student's three most recently graded activities (visible grades only).
     *
     * @param int $userid
     * @return array
     */
    protected function nt_recent_grades(int $userid): array {
        global $DB;
        $out = ['ntgrades' => [], 'nthasgrades' => false];
        try {
            $recs = $DB->get_records_sql(
                "SELECT g.id, g.finalgrade, g.timemodified, gi.itemname, gi.grademax, gi.grademin, gi.courseid, c.shortname
                   FROM {grade_grades} g
                   JOIN {grade_items} gi ON gi.id = g.itemid AND gi.itemtype = 'mod' AND gi.hidden = 0
                   JOIN {course} c ON c.id = gi.courseid
                  WHERE g.userid = :u AND g.finalgrade IS NOT NULL AND g.hidden = 0 AND gi.grademax > gi.grademin
               ORDER BY g.timemodified DESC, g.id DESC",
                ['u' => $userid], 0, 3);
            foreach ($recs as $r) {
                $pct = (int) round((($r->finalgrade - $r->grademin) / ($r->grademax - $r->grademin)) * 100);
                $pct = max(0, min(100, $pct));
                $out['ntgrades'][] = [
                    'name' => format_string($r->itemname),
                    'course' => format_string($r->shortname),
                    'date' => $r->timemodified ? userdate($r->timemodified, '%d %b') : '',
                    'pct' => $pct,
                    'score' => format_float($r->finalgrade, 1, true, true) . ' / ' . format_float($r->grademax, 0),
                    'color' => $pct >= 75 ? '#1fa37a' : ($pct >= 50 ? '#f2a33a' : '#e5556e'),
                    'url' => (new moodle_url('/grade/report/user/index.php', ['id' => $r->courseid]))->out(false),
                ];
            }
            $out['nthasgrades'] = !empty($out['ntgrades']);
        } catch (\Throwable $e) {
            debugging('nt_student_dashboard grades: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        return $out;
    }

    /**
     * "My week": the student's own activity in the last 7 days (real counts).
     *
     * @param int $userid
     * @param int $now
     * @return array
     */
    protected function nt_week_stats(int $userid, int $now): array {
        global $DB;
        $since = $now - 7 * DAYSECS;
        $count = function(string $sql, array $params) use ($DB) {
            try {
                return (int) $DB->count_records_sql($sql, $params);
            } catch (\Throwable $e) {
                return 0;
            }
        };
        $done = $count("SELECT COUNT(1) FROM {course_modules_completion}
                         WHERE userid = :u AND completionstate IN (1, 2) AND timemodified >= :t",
            ['u' => $userid, 't' => $since]);
        $quiz = $count("SELECT COUNT(1) FROM {quiz_attempts} WHERE userid = :u AND timestart >= :t AND preview = 0",
            ['u' => $userid, 't' => $since]);
        $posts = $count("SELECT COUNT(1) FROM {forum_posts} WHERE userid = :u AND created >= :t",
            ['u' => $userid, 't' => $since]);
        $subs = $count("SELECT COUNT(1) FROM {assign_submission} WHERE userid = :u AND status = 'submitted' AND timemodified >= :t",
            ['u' => $userid, 't' => $since]);
        $badges = $count("SELECT COUNT(1) FROM {badge_issued} WHERE userid = :u", ['u' => $userid]);
        $url = function($path, $params = []) {
            return (new moodle_url($path, $params))->out(false);
        };
        return ['ntweekstats' => [
            ['label' => get_string('nt_ws_done', 'theme_baz'), 'n' => $done, 'color' => '#1fa37a',
                'icon' => '<circle cx="12" cy="12" r="9"/><path d="m8 12.5 2.8 2.8L16.5 9.5"/>', 'url' => $url('/my/courses.php')],
            ['label' => get_string('nt_ws_quiz', 'theme_baz'), 'n' => $quiz, 'color' => '#6d4ae8',
                'icon' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .8-1 1.5V14M12 17h.01"/>',
                'url' => $url('/grade/report/overview/index.php')],
            ['label' => get_string('nt_ws_subs', 'theme_baz'), 'n' => $subs, 'color' => '#2f6fe4',
                'icon' => '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M5 20h14"/>', 'url' => $url('/calendar/view.php', ['view' => 'upcoming'])],
            ['label' => get_string('nt_ws_posts', 'theme_baz'), 'n' => $posts, 'color' => '#e08a00',
                'icon' => '<path d="M4 5h16v11H9l-5 4z"/>', 'url' => $url('/mod/forum/user.php', ['id' => $userid])],
            ['label' => get_string('nt_ws_badges', 'theme_baz'), 'n' => $badges, 'color' => '#e5556e',
                'icon' => '<circle cx="12" cy="9" r="5"/><path d="m9 13.5-1.5 7L12 18l4.5 2.5-1.5-7"/>', 'url' => $url('/badges/mybadges.php')],
        ]];
    }

    /**
     * "My tasks": the student's pending actionable items (assignments to submit,
     * quizzes to attempt, activities to complete ...) from the calendar's action
     * events - overdue in the last 30 days first, then the next 30 days.
     *
     * @param int $now
     * @return array
     */
    protected function nt_tasks(array $ctx, int $userid, int $now): array {
        global $OUTPUT, $CFG;
        $out = ['nttasks' => [], 'nthastasks' => false, 'nttaskoverdue' => 0, 'nttaskweek' => 0];
        try {
            $events = \core_calendar\local\api::get_action_events_by_timesort(
                $now - 30 * DAYSECS, $now + 30 * DAYSECS, null, 50);
            $today = usergetmidnight($now);
            $rows = [];
            foreach ($events as $ev) {
                $action = $ev->get_action();
                if ($action && !$action->is_actionable() && (int) $action->get_item_count() === 0) {
                    continue;
                }
                $ts = $ev->get_times()->get_sort_time()->getTimestamp();
                $cm = $ev->get_course_module();
                $modname = $cm ? $cm->get('modname') : '';
                $url = $cm ? new moodle_url('/mod/' . $modname . '/view.php', ['id' => $cm->get('id')])
                    : ($action ? $action->get_url() : new moodle_url('/calendar/view.php', ['view' => 'day', 'time' => $ts]));
                $course = $ev->get_course();
                $days = (int) floor((usergetmidnight($ts) - $today) / DAYSECS);
                if ($ts < $now) {
                    $state = 'overdue';
                    $od = max(0, -$days);
                    $when = $od === 0 ? get_string('nt_due_overdue', 'theme_baz') : get_string('nt_overdue_days', 'theme_baz', $od);
                    $out['nttaskoverdue']++;
                } else if ($days === 0) {
                    $state = 'today';
                    $when = get_string('nt_due_today', 'theme_baz');
                } else if ($days === 1) {
                    $state = 'soon';
                    $when = get_string('nt_due_tomorrow', 'theme_baz');
                } else {
                    $state = $days <= 7 ? 'soon' : 'later';
                    $when = get_string('nt_due_indays', 'theme_baz', $days);
                }
                if ($ts >= $now && $days <= 7) {
                    $out['nttaskweek']++;
                }
                $name = format_string($ev->get_name());
                $name = preg_replace('/\s+(is due|is due to be graded|closes|opens|should be completed)$/i', '', $name);
                $icon = '';
                if ($modname !== '') {
                    try {
                        $icon = $OUTPUT->image_url('monologo', 'mod_' . $modname)->out(false);
                    } catch (\Throwable $e) {
                        $icon = '';
                    }
                }
                $rows[] = [
                    'name' => $name,
                    'course' => $course ? format_string($course->get('fullname')) : '',
                    'when' => $when,
                    'date' => userdate($ts, '%a, %d %b · %H:%M'),
                    'state' => $state,
                    'color' => ['overdue' => '#e5556e', 'today' => '#e08a00', 'soon' => '#2f6fe4', 'later' => '#5b6b88'][$state],
                    'icon' => $icon,
                    'action' => ($action && $action->is_actionable()) ? format_string($action->get_name()) : '',
                    'url' => $url->out(false),
                    'sort' => $ts,
                    'overdue' => $ts < $now ? 0 : 1,
                ];
            }
            usort($rows, function($a, $b) {
                if ($a['overdue'] !== $b['overdue']) {
                    return $a['overdue'] <=> $b['overdue'];
                }
                return $a['overdue'] ? $a['sort'] <=> $b['sort'] : $b['sort'] <=> $a['sort'];
            });
            $rows = array_slice($rows, 0, 5);
            // Fewer than 5 dated tasks: add the next unfinished activities of the current courses.
            if (count($rows) < 5 && !empty($ctx['courses'])) {
                require_once($CFG->libdir . '/completionlib.php');
                $seen = [];
                foreach ($rows as $r) {
                    $seen[$r['url']] = true;
                }
                foreach (array_slice($ctx['courses'], 0, 6) as $c) {
                    if (count($rows) >= 5) {
                        break;
                    }
                    if ((int) ($c['progress'] ?? 0) >= 100 || empty($c['url']) || !preg_match('/[?&]id=(\d+)/', $c['url'], $m)) {
                        continue;
                    }
                    $course = get_course((int) $m[1]);
                    $completion = new \completion_info($course);
                    if (!$completion->is_enabled()) {
                        continue;
                    }
                    $added = 0;
                    foreach (get_fast_modinfo($course, $userid)->get_cms() as $cm) {
                        if ($added >= 2 || count($rows) >= 5) {
                            break;
                        }
                        if (!$cm->uservisible || $cm->deletioninprogress || !$cm->url || !$completion->is_enabled($cm)) {
                            continue;
                        }
                        $st = (int) $completion->get_data($cm, false, $userid)->completionstate;
                        if (in_array($st, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true)) {
                            continue;
                        }
                        $url = $cm->url->out(false);
                        if (isset($seen[$url])) {
                            continue;
                        }
                        $seen[$url] = true;
                        $added++;
                        $rows[] = [
                            'name' => format_string($cm->name, true, ['context' => $cm->context]),
                            'course' => format_string($course->fullname),
                            'when' => get_string('nt_todo', 'theme_baz'),
                            'date' => get_string('pluginname', 'mod_' . $cm->modname),
                            'state' => 'todo',
                            'color' => '#2f6fe4',
                            'icon' => $OUTPUT->image_url('monologo', 'mod_' . $cm->modname)->out(false),
                            'action' => get_string('nt_open', 'theme_baz'),
                            'url' => $url,
                        ];
                    }
                }
            }
            $out['nttasks'] = $rows;
            $out['nthastasks'] = !empty($out['nttasks']);
        } catch (\Throwable $e) {
            debugging('nt_student_dashboard tasks: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        return $out;
    }

    /**
     * "Today", "Yesterday", "3 days ago", or a date.
     */
    protected function nt_ago(int $ts, int $now): string {
        $days = (int) floor((usergetmidnight($now) - usergetmidnight($ts)) / DAYSECS);
        if ($days <= 0) {
            return get_string('today');
        }
        if ($days === 1) {
            return get_string('yesterday', 'calendar');
        }
        if ($days < 30) {
            return get_string('nt_daysago', 'theme_baz', $days);
        }
        return userdate($ts, '%d %b %Y');
    }

    /**
     * Holidays & festivals from the Ednxt Top Bar setting
     * (one per line: "YYYY-MM-DD | Name | holiday|festival").
     *
     * @return array date => ['name', 'type']
     */
    public static function nt_holidays(): array {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [];
        $raw = (string) get_config('theme_baz', 'nt_holidays');
        foreach (preg_split('/\R/', $raw) as $line) {
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) < 2 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $parts[0])) {
                continue;
            }
            $type = (isset($parts[2]) && strtolower($parts[2]) === 'festival') ? 'festival' : 'holiday';
            $name = format_string($parts[1]);
            if (isset($cache[$parts[0]])) {
                $cache[$parts[0]]['name'] .= ' / ' . $name;
                if ($type === 'holiday') {
                    $cache[$parts[0]]['type'] = 'holiday';
                }
            } else {
                $cache[$parts[0]] = ['name' => $name, 'type' => $type];
            }
        }
        return $cache;
    }

    /**
     * Latest announcements: news-forum discussions from the site front page and
     * the courses on the dashboard, newest first.
     *
     * @param int $userid
     * @param int $now
     * @return array
     */
    protected function nt_announcements(int $userid, int $now): array {
        global $DB;
        $out = ['ntnews' => [], 'nthasnews' => false, 'ntnewsurl' => ''];
        try {
            [$current] = $this->get_courses($userid);
            $courseids = [SITEID];
            foreach ($current ?: [] as $c) {
                $courseids[] = (int) $c->id;
            }
            [$insql, $params] = $DB->get_in_or_equal(array_unique($courseids), SQL_PARAMS_NAMED);
            $params += ['now1' => $now, 'now2' => $now];
            $sql = "SELECT d.id, d.name, d.timemodified, d.course, p.message, p.messageformat, p.created,
                           u.id AS uid, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                           u.middlename, u.alternatename, c.shortname
                      FROM {forum_discussions} d
                      JOIN {forum} f ON f.id = d.forum AND f.type = 'news'
                      JOIN {forum_posts} p ON p.id = d.firstpost
                      JOIN {user} u ON u.id = p.userid
                      JOIN {course} c ON c.id = d.course
                     WHERE d.course $insql
                       AND (d.timestart = 0 OR d.timestart <= :now1)
                       AND (d.timeend = 0 OR d.timeend > :now2)
                  ORDER BY d.timemodified DESC";
            $items = [];
            foreach ($DB->get_records_sql($sql, $params, 0, 3) as $r) {
                $text = trim(preg_replace('/\s+/', ' ', html_to_text(format_text($r->message, $r->messageformat,
                    ['context' => \context_course::instance($r->course)]), 0, false)));
                $items[] = [
                    'title' => format_string($r->name),
                    'excerpt' => \core_text::substr($text, 0, 110) . (\core_text::strlen($text) > 110 ? '…' : ''),
                    'author' => fullname($r),
                    'where' => $r->course == SITEID ? get_string('site') : format_string($r->shortname),
                    'date' => userdate($r->created, '%d %b'),
                    'isnew' => ($now - (int) $r->created) < 3 * DAYSECS,
                    'url' => (new moodle_url('/mod/forum/discuss.php', ['d' => $r->id]))->out(false),
                ];
            }
            $out['ntnews'] = $items;
            $out['nthasnews'] = !empty($items);
            $siteforum = $DB->get_record('forum', ['course' => SITEID, 'type' => 'news'], 'id', IGNORE_MULTIPLE);
            if ($siteforum) {
                $out['ntnewsurl'] = (new moodle_url('/mod/forum/view.php', ['f' => $siteforum->id]))->out(false);
            }
        } catch (\Throwable $e) {
            debugging('nt_student_dashboard announcements: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        return $out;
    }

    /**
     * The signed-in person's full name for the greeting, in title case
     * ("K SULOCHANARANI" -> "K Sulochanarani"); with "Log in as" it is the
     * person being viewed as.
     *
     * @return string
     */
    public static function nt_display_name(): string {
        global $USER;
        $name = trim(fullname($USER));
        if ($name === '') {
            $name = trim((string) $USER->firstname);
        }
        // Only re-case names typed all in capitals or all in lower case.
        if ($name === \core_text::strtoupper($name) || $name === \core_text::strtolower($name)) {
            $name = mb_convert_case(\core_text::strtolower($name), MB_CASE_TITLE, 'UTF-8');
        }
        return $name;
    }

    /**
     * 0 -> "0h", 1500 -> "25m", 5400 -> "1.5h".
     *
     * @param int $secs
     * @return string
     */
    protected function fmt_hours(int $secs): string {
        if ($secs <= 0) {
            return '0h';
        }
        if ($secs < HOURSECS) {
            return max(1, (int) round($secs / MINSECS)) . 'm';
        }
        $h = round($secs / HOURSECS, 1);
        return rtrim(rtrim(number_format($h, 1, '.', ''), '0'), '.') . 'h';
    }
}
