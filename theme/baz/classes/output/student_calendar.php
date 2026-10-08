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
 * Bespoke Calendar exporter for theme_baz (Month grid + Focus board).
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

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->dirroot . '/calendar/lib.php');

/**
 * Renderable + templatable for the bespoke Calendar page.
 */
class student_calendar implements renderable, templatable {

    /** @var array Modules treated as "live classes". */
    const LIVE = ['zoom', 'bigbluebuttonbn', 'customteams', 'teams', 'msteams', 'jitsi', 'collaborate'];

    /** @var array type => hex colour (mirrors the SCSS). */
    const COLOURS = ['deadline' => '#E5556E', 'course' => '#406CD2', 'live' => '#8B5CF6', 'user' => '#1FA37A', 'site' => '#5C7184'];

    /**
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $USER, $CFG;
        $userid = (int) $USER->id;

        $view = optional_param('view', 'month', PARAM_ALPHA);
        if (!in_array($view, ['month', 'week', 'day', 'upcoming'], true)) {
            $view = 'month';
        }
        $course = optional_param('course', 0, PARAM_INT);
        $time = optional_param('time', 0, PARAM_INT);
        $now = time();
        $base = $time ?: $now;

        // Enrolled courses (for the course filter + scoping events).
        $enrolled = enrol_get_users_courses($userid, true, ['id', 'fullname', 'shortname']);
        $courseids = array_keys($enrolled);
        // Optional single-course scope (course filter dropdown).
        $scopeids = ($course && isset($enrolled[$course])) ? [$course] : $courseids;

        // Respect the site's configured first day of week (0=Sun .. 6=Sat).
        $startwday = (int) \core_calendar\type_factory::get_calendar_instance()->get_starting_weekday();

        // Ordered short day-of-week names for the month grid header.
        $weekdaynames = [];
        $refsunday = make_timestamp(2024, 1, 7, 12, 0, 0); // 2024-01-07 is a Sunday.
        for ($i = 0; $i < 7; $i++) {
            $weekdaynames[] = userdate($refsunday + (($startwday + $i) % 7) * DAYSECS, '%a');
        }

        // --- View-specific data + label + prev/next anchors. ---
        switch ($view) {
            case 'week':
                $vd = $this->build_week($base, $now, $userid, $scopeids, $startwday);
                break;
            case 'day':
                $vd = $this->build_day($base, $now, $userid, $scopeids);
                break;
            case 'upcoming':
                $vd = $this->build_upcoming($now, $userid, $scopeids);
                break;
            default:
                $vd = $this->build_month($base, $now, $userid, $scopeids, $startwday);
                break;
        }

        // Build a calendar URL for a given view, carrying time + course scope.
        $vurl = function($v, $t = null) use ($course) {
            $params = ['view' => $v];
            if ($t !== null) {
                $params['time'] = $t;
            }
            if ($course) {
                $params['course'] = $course;
            }
            return (new moodle_url('/calendar/view.php', $params))->out(false);
        };

        // --- Course filter list ---
        $coursefilter = [['id' => 0, 'name' => get_string('cal_allcourses', 'theme_baz'), 'selected' => !$course]];
        foreach ($enrolled as $c) {
            $coursefilter[] = ['id' => $c->id, 'name' => format_string($c->fullname), 'selected' => ($course == $c->id)];
        }

        // --- Nav extras (e-ID + support), shared with the other bespoke pages. ---
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

        $data = [
            'config'        => ['wwwroot' => $CFG->wwwroot],
            'firstname'     => ucwords(\core_text::strtolower($USER->firstname)),
            'askjo'         => $askjo,
            'hasaskjo'      => !empty($askjo),

            // View state.
            'ismonth'       => $view === 'month',
            'isweek'        => $view === 'week',
            'isday'         => $view === 'day',
            'isupcoming'    => $view === 'upcoming',
            'hasdatenav'    => $view !== 'upcoming',
            'label'         => $vd['label'],
            'weekdaynames'  => $weekdaynames,

            // Toolbar nav.
            'prevurl'       => isset($vd['prevtime']) ? $vurl($view, $vd['prevtime']) : null,
            'nexturl'       => isset($vd['nexttime']) ? $vurl($view, $vd['nexttime']) : null,
            'todayurl'      => $vurl($view),
            'monthurl'      => $vurl('month', $base),
            'weekurl'       => $vurl('week', $base),
            'dayurl'        => $vurl('day', $base),
            'upcomingurl'   => $vurl('upcoming'),
            'managesuburl'  => (new moodle_url('/calendar/managesubscriptions.php'))->out(false),
            'exporturl'     => (new moodle_url('/calendar/export.php'))->out(false),

            'coursefilter'  => $coursefilter,

            // Nav.
            'dashboardurl'  => (new moodle_url('/my/'))->out(false),
            'coursesurl'    => (new moodle_url('/my/courses.php'))->out(false),
            'calendarurl'   => (new moodle_url('/calendar/view.php'))->out(false),
            'gradesurl'     => (new moodle_url('/grade/report/overview/index.php'))->out(false),
            'filesurl'      => (new moodle_url('/user/files.php'))->out(false),
            'showeidcard'   => $showeidcard,
            'eidcardurl'    => (new moodle_url('/local/studentid/index.php'))->out(false),
            'support_html'  => $support,
            'hassupport'    => $support !== null,
        ];

        // Merge in view payload (cells/board, weekdays, dayevents, upgroups).
        unset($vd['label'], $vd['prevtime'], $vd['nexttime']);
        return array_merge($data, $vd);
    }

    /**
     * Month view: 7-column grid (Sunday-first) + the focus board.
     *
     * @param int $base
     * @param int $now
     * @param int $userid
     * @param array $courseids
     * @return array
     */
    protected function build_month(int $base, int $now, int $userid, array $courseids, int $startwday = 0): array {
        $year = (int) userdate($base, '%Y');
        $month = (int) userdate($base, '%m');
        $daysinmonth = (int) (new \DateTime(sprintf('%04d-%02d-01', $year, $month)))->format('t');
        $mstart = make_timestamp($year, $month, 1, 0, 0, 0);
        $mend = make_timestamp($year, $month, $daysinmonth, 23, 59, 59);

        // Pull events across a window covering the month + the board horizon.
        $winstart = min($mstart, $now - 14 * DAYSECS);
        $winend = max($mend, $now + 45 * DAYSECS);
        $events = $this->fetch_events($userid, $winstart, $winend, $courseids);

        $bydaykey = [];
        foreach ($events as $e) {
            if ($e['ts'] >= $mstart && $e['ts'] <= $mend) {
                $bydaykey[(int) userdate($e['ts'], '%d')][] = $e;
            }
        }
        $today = (int) userdate($now, '%d');
        $istodaymonth = (userdate($now, '%Y%m') === userdate($base, '%Y%m'));
        $firstdow = (int) userdate($mstart, '%w');
        // Leading blanks = offset of the 1st from the configured week-start day.
        $lead = ($firstdow - $startwday + 7) % 7;
        $cells = [];
        for ($i = 0; $i < $lead; $i++) {
            $cells[] = ['blank' => true];
        }
        for ($d = 1; $d <= $daysinmonth; $d++) {
            $dayevs = $bydaykey[$d] ?? [];
            $dayts = make_timestamp($year, $month, $d);
            $cells[] = [
                'blank'    => false,
                'day'      => $d,
                'col'      => ($lead + ($d - 1)) % 7,
                'istoday'  => $istodaymonth && $d === $today,
                'events'   => array_slice($dayevs, 0, 2),
                'more'     => count($dayevs) > 2 ? (count($dayevs) - 2) : 0,
                'hasmore'  => count($dayevs) > 2,
                'url'      => (new moodle_url('/calendar/view.php', ['view' => 'day', 'time' => $dayts]))->out(false),
            ];
        }

        // Focus board (relative to now).
        $startday = make_timestamp((int) userdate($now, '%Y'), (int) userdate($now, '%m'), (int) userdate($now, '%d'), 0, 0, 0);
        $dow = (int) userdate($now, '%w');
        $endweek = $startday + (7 - $dow) * DAYSECS;
        $endnext = $endweek + 7 * DAYSECS;
        $buckets = ['overdue' => [], 'week' => [], 'next' => [], 'later' => []];
        foreach ($events as $e) {
            $ts = $e['ts'];
            if ($ts < $startday) {
                if ($e['type'] === 'deadline' && $ts >= $now - 14 * DAYSECS) {
                    $buckets['overdue'][] = $e;
                }
            } else if ($ts <= $endweek) {
                $buckets['week'][] = $e;
            } else if ($ts <= $endnext) {
                $buckets['next'][] = $e;
            } else {
                $buckets['later'][] = $e;
            }
        }
        $board = [
            ['key' => 'overdue', 'label' => get_string('cal_overdue', 'theme_baz'), 'tone' => 'over'],
            ['key' => 'week', 'label' => get_string('cal_thisweek', 'theme_baz'), 'tone' => 'week'],
            ['key' => 'next', 'label' => get_string('cal_nextweek', 'theme_baz'), 'tone' => 'next'],
            ['key' => 'later', 'label' => get_string('cal_later', 'theme_baz'), 'tone' => 'later'],
        ];
        foreach ($board as &$col) {
            $items = array_slice($buckets[$col['key']], 0, 5);
            $col['items'] = $items;
            $col['count'] = count($buckets[$col['key']]);
            $col['hasitems'] = !empty($items);
        }
        unset($col);

        return [
            'cells'    => $cells,
            'board'    => $board,
            'label'    => userdate($base, '%B %Y'),
            'prevtime' => strtotime('-1 month', $mstart),
            'nexttime' => strtotime('+1 month', $mstart),
        ];
    }

    /**
     * Week view: seven day columns (Sunday-first) for the week containing $base.
     *
     * @param int $base
     * @param int $now
     * @param int $userid
     * @param array $courseids
     * @return array
     */
    protected function build_week(int $base, int $now, int $userid, array $courseids, int $startwday = 0): array {
        $dow = (int) userdate($base, '%w');
        $daymid = make_timestamp((int) userdate($base, '%Y'), (int) userdate($base, '%m'), (int) userdate($base, '%d'), 0, 0, 0);
        // Walk back to the configured first day of the week.
        $weekstart = $daymid - (($dow - $startwday + 7) % 7) * DAYSECS;
        $weekend = $weekstart + 7 * DAYSECS - 1;
        $events = $this->fetch_events($userid, $weekstart, $weekend, $courseids);

        $byday = [];
        foreach ($events as $e) {
            $k = (int) floor(($e['ts'] - $weekstart) / DAYSECS);
            if ($k >= 0 && $k < 7) {
                $byday[$k][] = $e;
            }
        }
        $todaymid = make_timestamp((int) userdate($now, '%Y'), (int) userdate($now, '%m'), (int) userdate($now, '%d'), 0, 0, 0);
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $dts = $weekstart + $i * DAYSECS;
            $days[] = [
                'dow'      => userdate($dts, '%a'),
                'daynum'   => (int) userdate($dts, '%e'),
                'istoday'  => $dts === $todaymid,
                'dayurl'   => (new moodle_url('/calendar/view.php', ['view' => 'day', 'time' => $dts]))->out(false),
                'events'   => $byday[$i] ?? [],
                'hasevents' => !empty($byday[$i]),
            ];
        }

        return [
            'weekdays' => $days,
            'label'    => userdate($weekstart, '%e %b') . ' – ' . userdate($weekstart + 6 * DAYSECS, '%e %b %Y'),
            'prevtime' => $weekstart - 7 * DAYSECS,
            'nexttime' => $weekstart + 7 * DAYSECS,
        ];
    }

    /**
     * Day view: a single day's events as a timeline list.
     *
     * @param int $base
     * @param int $now
     * @param int $userid
     * @param array $courseids
     * @return array
     */
    protected function build_day(int $base, int $now, int $userid, array $courseids): array {
        $y = (int) userdate($base, '%Y');
        $m = (int) userdate($base, '%m');
        $d = (int) userdate($base, '%d');
        $dstart = make_timestamp($y, $m, $d, 0, 0, 0);
        $dend = make_timestamp($y, $m, $d, 23, 59, 59);
        $events = $this->fetch_events($userid, $dstart, $dend, $courseids);

        return [
            'dayevents'    => $events,
            'hasdayevents' => !empty($events),
            'label'        => userdate($dstart, '%A, %e %B %Y'),
            'prevtime'     => $dstart - DAYSECS,
            'nexttime'     => $dstart + DAYSECS,
        ];
    }

    /**
     * Upcoming view: events from now forward, grouped by day.
     *
     * @param int $now
     * @param int $userid
     * @param array $courseids
     * @return array
     */
    protected function build_upcoming(int $now, int $userid, array $courseids): array {
        $events = $this->fetch_events($userid, $now, $now + 60 * DAYSECS, $courseids);
        $todaymid = make_timestamp((int) userdate($now, '%Y'), (int) userdate($now, '%m'), (int) userdate($now, '%d'), 0, 0, 0);
        $groups = [];
        $order = [];
        foreach ($events as $e) {
            $gkey = userdate($e['ts'], '%Y%m%d');
            if (!isset($groups[$gkey])) {
                $emid = make_timestamp((int) userdate($e['ts'], '%Y'), (int) userdate($e['ts'], '%m'), (int) userdate($e['ts'], '%d'), 0, 0, 0);
                $diff = (int) round(($emid - $todaymid) / DAYSECS);
                if ($diff === 0) {
                    $lbl = get_string('cal_today', 'theme_baz');
                } else if ($diff === 1) {
                    $lbl = get_string('cal_tomorrow', 'theme_baz');
                } else {
                    $lbl = userdate($e['ts'], '%A, %e %b');
                }
                $groups[$gkey] = ['datelbl' => $lbl, 'events' => []];
                $order[] = $gkey;
            }
            $groups[$gkey]['events'][] = $e;
        }
        $list = [];
        foreach ($order as $k) {
            $list[] = $groups[$k];
        }

        return [
            'upgroups'    => $list,
            'hasupcoming' => !empty($list),
            'label'       => get_string('cal_upcoming', 'theme_baz'),
        ];
    }

    /**
     * Fetch + normalise calendar events into a flat, sorted array.
     *
     * @param int $userid
     * @param int $from
     * @param int $to
     * @param array $courseids
     * @return array of ['ts','time','day','name','type','colour','url','action','course']
     */
    protected function fetch_events(int $userid, int $from, int $to, array $courseids): array {
        $out = [];
        try {
            $raw = calendar_get_events($from, $to, $userid, true, $courseids ?: false, true, true);
        } catch (\Throwable $e) {
            debugging('student_calendar events: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
        foreach ($raw as $event) {
            $mod = !empty($event->modulename) ? $event->modulename : '';
            $etype = !empty($event->eventtype) ? $event->eventtype : '';
            $type = $this->classify($mod, $etype, $event);
            // Deeplink resolution (most specific target first):
            //  1. Activity events -> the activity (/mod/<mod>/view.php?id=<cmid>).
            //  2. Other events whose description carries a link (e.g. a live-class
            //     "Join Meeting" URL) -> that link, opened in a new tab.
            //  3. Course events -> the course; everything else -> that day.
            $newtab = false;
            $url = !empty($event->courseid) && $event->courseid != SITEID
                ? (new moodle_url('/course/view.php', ['id' => $event->courseid]))->out(false)
                : (new moodle_url('/calendar/view.php', ['view' => 'day', 'time' => (int) $event->timestart]))->out(false);
            if ($mod !== '' && !empty($event->instance)) {
                try {
                    $cm = get_coursemodule_from_instance($mod, $event->instance,
                        !empty($event->courseid) ? $event->courseid : 0, false, IGNORE_MISSING);
                    if ($cm) {
                        $url = (new moodle_url('/mod/' . $mod . '/view.php', ['id' => $cm->id]))->out(false);
                    }
                } catch (\Throwable $e) {
                    // Keep fallback URL.
                }
            } else if (!empty($event->description)
                    && preg_match('/href\s*=\s*["\']?(https?:\/\/[^"\'\s>]+)/i', $event->description, $m)) {
                $url = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
                $newtab = true;
            }
            $out[] = [
                'ts'     => (int) $event->timestart,
                'time'   => userdate($event->timestart, get_string('strftimetime24', 'langconfig')),
                'datelbl' => userdate($event->timestart, '%e %b'),
                'name'   => $this->clean_name(format_string($event->name)),
                'type'   => $type,
                'colour' => self::COLOURS[$type],
                'url'    => $url,
                'newtab' => $newtab,
                'action' => $type === 'live' ? get_string('cal_join', 'theme_baz')
                            : ($type === 'deadline' ? get_string('cal_open', 'theme_baz') : get_string('cal_view', 'theme_baz')),
            ];
        }
        usort($out, fn($a, $b) => $a['ts'] <=> $b['ts']);
        return $out;
    }

    /**
     * Classify an event into a colour bucket.
     *
     * @param string $mod
     * @param string $etype
     * @param \stdClass $event
     * @return string
     */
    protected function classify(string $mod, string $etype, \stdClass $event): string {
        if ($mod !== '' && in_array($mod, self::LIVE, true)) {
            return 'live';
        }
        if (!empty($event->timeduration) && $event->timeduration > 0) {
            return 'live';
        }
        if ($mod !== '') {
            return 'deadline';
        }
        if ($etype === 'user') {
            return 'user';
        }
        if ($etype === 'course' || $etype === 'group') {
            return 'course';
        }
        return 'site';
    }

    /**
     * Tidy a Moodle event name for display.
     *
     * Strips the trailing action suffix ("… closes/opens/is due"), and for the
     * underscore-delimited course events we see in this install
     * ("20VMBOC206_Business Law and Corporate Governance_Prof Shilpa Pradeep")
     * drops a leading course-code segment and a trailing faculty segment so the
     * readable title remains.
     *
     * @param string $name
     * @return string
     */
    protected function clean_name(string $name): string {
        $original = trim($name);
        $cleaned = preg_replace('/\s+(closes|opens|is due|due|is overdue|opened|closed)$/i', '', $original);
        $cleaned = trim((string) $cleaned);

        if (strpos($cleaned, '_') !== false) {
            $parts = array_map('trim', explode('_', $cleaned));
            $parts = array_values(array_filter($parts, fn($p) => $p !== ''));
            // Drop a leading course-code segment (uppercase alnum with a digit, e.g. 20VMBOC206).
            if (count($parts) > 1 && preg_match('/^[A-Z0-9][A-Z0-9\-\/.]{2,}$/', $parts[0])
                    && preg_match('/\d/', $parts[0]) && !preg_match('/\s/', $parts[0])) {
                array_shift($parts);
            }
            // Drop a trailing faculty segment ("Prof …", "Dr …", etc.).
            if (count($parts) > 1 && preg_match('/^(prof|dr|mr|mrs|ms|miss|faculty)\b/i', end($parts))) {
                array_pop($parts);
            }
            $cleaned = trim(implode(' ', $parts));
        }

        return $cleaned !== '' ? $cleaned : ($original !== '' ? $original : $name);
    }

    /**
     * Render the real support block HTML (LSM / ticket / SOP) for the nav panel.
     *
     * @return string|null
     */
    protected function render_support(): ?string {
        return theme_baz_support_panel();
    }
}
