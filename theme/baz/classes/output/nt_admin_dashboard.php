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
 * Admin dashboard -- "New theme" (two-level top bar) design.
 *
 * Starts from admin_dashboard's own export (same KPIs, engagement, health, links)
 * and adds the extra panels of the design, all read from the live site: KPI
 * trends, today's site activity, sign-in trend for 7/30/90 days, courses by
 * category and who was online recently. Heavier aggregates are cached for 10 min.
 *
 * @package   theme_baz
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class nt_admin_dashboard extends admin_dashboard {

    /** @var int Seconds the heavier aggregates are reused. */
    const CACHE_TTL = 600;

    public function export_for_template(renderer_base $output): array {
        $ctx = parent::export_for_template($output);
        $now = time();
        $daystart = usergetmidnight($now);

        $agg = $this->cached('aggregates', function() use ($now, $daystart) {
            return [
                'kpis'   => $this->kpi_trends($now, $daystart),
                'chart'  => $this->engagement_series($daystart),
                'cats'   => $this->categories(),
            ];
        });

        $ctx['ntkpis'] = $agg['kpis'];
        $ctx['ntcharts'] = $this->charts($agg['chart'], $daystart);
        $ctx['nthaschart'] = !empty($ctx['ntcharts']);
        $ctx = array_merge($ctx, $this->activity_today($now, $daystart));
        $ctx['ntrecent'] = $this->recent_events($now);
        $ctx['nthasrecent'] = !empty($ctx['ntrecent']);
        $ctx = array_merge($ctx, $this->courses_card($agg['cats']));
        $ctx['ntonline'] = $this->who_online($now);
        $ctx['nthasonline'] = !empty($ctx['ntonline']);

        // Greeting by the viewer's own time of day and first name.
        $word = in_array($ctx['greetingtime'], ['morning', 'afternoon', 'evening'], true) ? $ctx['greetingtime'] : 'morning';
        $ctx['ntgreeting'] = get_string('sd_' . $word, 'theme_baz') . ', ' . nt_student_dashboard::nt_display_name();
        $ctx['ntisevening'] = $word === 'evening';
        $ctx['ntsubline'] = $ctx['healthok']
            ? get_string('nt_admin_sub_ok', 'theme_baz')
            : get_string('nt_admin_sub_warn', 'theme_baz', (int) $ctx['attentioncount']);

        $ctx['ntsiteadminurl'] = (new moodle_url('/admin/search.php'))->out(false);
        $ctx['ntnotificationsurl'] = (new moodle_url('/message/output/popup/notifications.php'))->out(false);
        $ctx['ntactivityurl'] = (new moodle_url('/report/log/index.php', ['id' => SITEID]))->out(false);
        $ctx['nttasksurl'] = (new moodle_url('/admin/tool/task/scheduledtasks.php'))->out(false);
        $ctx['ntonlineurl'] = (new moodle_url('/admin/user.php'))->out(false);
        return $ctx;
    }

    /**
     * Reuse a value for CACHE_TTL seconds.
     *
     * @param string $key
     * @param callable $fn
     * @return mixed
     */
    protected function cached(string $key, callable $fn) {
        try {
            $cache = \cache::make_from_params(\cache_store::MODE_APPLICATION, 'theme_baz', 'ntadmin');
            $hit = $cache->get($key);
            if (is_array($hit) && isset($hit['t']) && (time() - $hit['t']) < self::CACHE_TTL) {
                return $hit['v'];
            }
            $v = $fn();
            $cache->set($key, ['t' => time(), 'v' => $v]);
            return $v;
        } catch (\Throwable $e) {
            return $fn();
        }
    }

    /**
     * Four KPI tiles with a delta badge and an 8-week sparkline.
     *
     * @param int $now
     * @param int $daystart
     * @return array
     */
    protected function kpi_trends(int $now, int $daystart): array {
        global $DB;
        $weeks = [];
        for ($i = 7; $i >= 0; $i--) {
            $weeks[] = $daystart + DAYSECS - $i * WEEKSECS; // End of each week.
        }
        $tiles = [];

        // Total users: cumulative count at the end of each week.
        $users = (int) $DB->count_records('user', ['deleted' => 0, 'confirmed' => 1]);
        $series = [];
        foreach ($weeks as $end) {
            $series[] = (int) $DB->count_records_select('user',
                'deleted = 0 AND confirmed = 1 AND timecreated < :t', ['t' => $end]);
        }
        $new7 = (int) $DB->count_records_select('user', 'deleted = 0 AND confirmed = 1 AND timecreated >= :t',
            ['t' => $now - WEEKSECS]);
        $tiles[] = $this->tile(get_string('nt_kpi_users', 'theme_baz'), number_format($users),
            '+' . number_format($new7), get_string('nt_kpi_users_tip', 'theme_baz'), $series, '#3B6FE0');

        // Enrolments: new enrolments per week; delta = growth over the last 30 days.
        $enrol = (int) $DB->count_records('user_enrolments');
        $series = [];
        foreach ($weeks as $end) {
            $series[] = (int) $DB->count_records_select('user_enrolments',
                'timecreated >= :a AND timecreated < :b', ['a' => $end - WEEKSECS, 'b' => $end]);
        }
        $new30 = (int) $DB->count_records_select('user_enrolments', 'timecreated >= :t', ['t' => $now - 30 * DAYSECS]);
        $base = max(1, $enrol - $new30);
        $pct = round(($new30 / $base) * 100, 1);
        $tiles[] = $this->tile(get_string('nt_kpi_enrolments', 'theme_baz'), $this->human($enrol),
            '+' . $pct . '%', get_string('nt_kpi_enrolments_tip', 'theme_baz'), $series, '#6D4AE8');

        // Active today: users seen since midnight; trend = daily sign-ins (cached series).
        $active = (int) $DB->count_records_select('user', 'deleted = 0 AND lastaccess >= :t', ['t' => $daystart]);
        $series = [];
        $raw = get_config('theme_baz', 'admin_dau_series');
        if ($raw) {
            $data = json_decode($raw, true);
            if (!empty($data['vals']) && is_array($data['vals'])) {
                $series = array_slice(array_map('intval', $data['vals']), -8);
            }
        }
        $yday = (int) $DB->count_records_select('user', 'deleted = 0 AND lastaccess >= :a AND lastaccess < :b',
            ['a' => $daystart - DAYSECS, 'b' => $daystart]);
        $diff = $active - $yday;
        $tiles[] = $this->tile(get_string('nt_kpi_activetoday', 'theme_baz'), number_format($active),
            ($diff >= 0 ? '+' : '') . $diff, get_string('nt_kpi_activetoday_tip', 'theme_baz'), $series, '#1FA37A');

        // New courses: created in the last 30 days; sparkline = per week.
        $newc = (int) $DB->count_records_select('course', 'id <> :site AND timecreated >= :t',
            ['site' => SITEID, 't' => $now - 30 * DAYSECS]);
        $series = [];
        foreach ($weeks as $end) {
            $series[] = (int) $DB->count_records_select('course', 'id <> :site AND timecreated >= :a AND timecreated < :b',
                ['site' => SITEID, 'a' => $end - WEEKSECS, 'b' => $end]);
        }
        $newc7 = (int) $DB->count_records_select('course', 'id <> :site AND timecreated >= :t',
            ['site' => SITEID, 't' => $now - WEEKSECS]);
        $tiles[] = $this->tile(get_string('nt_kpi_newcourses', 'theme_baz'), number_format($newc),
            '+' . $newc7, get_string('nt_kpi_newcourses_tip', 'theme_baz'), $series, '#F2A33A');

        return $tiles;
    }

    /**
     * One KPI tile.
     *
     * @return array
     */
    protected function tile(string $label, string $value, string $delta, string $deltatip, array $series,
            string $color): array {
        [$line, $area] = $this->spark($series, 100, 28);
        return [
            'label' => $label, 'value' => $value, 'delta' => $delta, 'deltatip' => $deltatip,
            'line' => $line, 'area' => $area, 'color' => $color, 'hasspark' => $line !== '',
            'negative' => strpos($delta, '-') === 0,
        ];
    }

    /**
     * SVG line + area paths for a small series in a $w x $h box.
     *
     * @return array [line, area]
     */
    protected function spark(array $vals, int $w, int $h, float $pad = 3): array {
        $n = count($vals);
        if ($n < 2) {
            return ['', ''];
        }
        $mn = min($vals);
        $mx = max($vals);
        $range = max(1, $mx - $mn);
        $pts = [];
        foreach (array_values($vals) as $i => $v) {
            $x = round(($i / ($n - 1)) * $w, 1);
            $y = round($pad + (1 - ($v - $mn) / $range) * ($h - 2 * $pad), 1);
            $pts[] = $x . ' ' . $y;
        }
        $line = 'M' . implode(' L', $pts);
        $area = $line . ' L' . $w . ' ' . $h . ' L0 ' . $h . ' Z';
        return [$line, $area];
    }

    /**
     * Distinct users signing in per day, last 90 days (oldest first).
     *
     * @param int $daystart
     * @return array ['start' => int, 'vals' => int[]]
     */
    protected function engagement_series(int $daystart): array {
        global $DB;
        $days = 90;
        $start = $daystart - ($days - 1) * DAYSECS;
        $vals = array_fill(0, $days, 0);
        try {
            $rs = $DB->get_recordset_sql(
                "SELECT FLOOR((timecreated - :s1) / 86400) AS d, COUNT(DISTINCT userid) AS c
                   FROM {logstore_standard_log}
                  WHERE timecreated >= :s2 AND action = :a AND target = :t
               GROUP BY FLOOR((timecreated - :s3) / 86400)",
                ['s1' => $start, 's2' => $start, 's3' => $start, 'a' => 'loggedin', 't' => 'user']);
            foreach ($rs as $r) {
                $d = (int) $r->d;
                if ($d >= 0 && $d < $days) {
                    $vals[$d] = (int) $r->c;
                }
            }
            $rs->close();
        } catch (\Throwable $e) {
            debugging('nt_admin_dashboard engagement: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        return ['start' => $start, 'vals' => $vals];
    }

    /**
     * Chart paths + labels for the 7 / 30 / 90 day tabs.
     *
     * @return array
     */
    protected function charts(array $series, int $daystart): array {
        $out = [];
        $w = 600;
        $h = 220;
        foreach ([7, 30, 90] as $idx => $n) {
            $vals = array_slice($series['vals'], -$n);
            $start = $daystart - ($n - 1) * DAYSECS;
            $top = max(1, max($vals)) * 1.15;
            $pts = [];
            foreach ($vals as $i => $v) {
                $x = round(($i / ($n - 1)) * $w, 1);
                $y = round($h - ($v / $top) * ($h - 10), 1);
                $pts[] = $x . ' ' . $y;
            }
            $line = 'M' . implode(' L', $pts);
            $labels = [];
            $step = $n === 7 ? 1 : ($n === 30 ? 6 : 15);
            for ($i = 0; $i < $n; $i += $step) {
                $labels[] = [
                    'text' => userdate($start + $i * DAYSECS, $n === 7 ? '%a' : '%d %b'),
                    'left' => round(($i / ($n - 1)) * 100, 2),
                ];
            }
            $out[] = [
                'key' => $n, 'active' => $idx === 0,
                'line' => $line, 'area' => $line . " L$w $h L0 $h Z",
                'labels' => $labels, 'peak' => max($vals),
            ];
        }
        return $out;
    }

    /**
     * Today's actions on the site, by kind, compared with the same weekday last week.
     *
     * @return array
     */
    protected function activity_today(int $now, int $daystart): array {
        global $DB;
        $out = ['ntacttotal' => '0', 'ntactdelta' => '', 'nthasactdelta' => false, 'ntactdeltaneg' => false,
            'ntactparts' => []];
        try {
            $sql = "SELECT crud, COUNT(*) AS c,
                           SUM(CASE WHEN eventname LIKE :g THEN 1 ELSE 0 END) AS g
                      FROM {logstore_standard_log}
                     WHERE timecreated >= :a AND timecreated < :b
                  GROUP BY crud";
            $counts = ['c' => 0, 'r' => 0, 'u' => 0, 'd' => 0];
            $gradec = 0;
            $gradeu = 0;
            foreach ($DB->get_records_sql($sql, ['g' => '%grade%', 'a' => $daystart, 'b' => $now + 1]) as $r) {
                $counts[$r->crud] = (int) $r->c;
                if ($r->crud === 'c') {
                    $gradec = (int) $r->g;
                } else if ($r->crud === 'u' || $r->crud === 'd') {
                    $gradeu += (int) $r->g;
                }
            }
            $total = array_sum($counts);
            $lastweek = (int) $DB->count_records_select('logstore_standard_log',
                'timecreated >= :a AND timecreated < :b',
                ['a' => $daystart - WEEKSECS, 'b' => $now - WEEKSECS]);

            // Views = reads; Grades = grade events; Submissions = other creates; Edits = other updates/deletes.
            $views = $counts['r'];
            $grades = $gradec + $gradeu;
            $subs = $counts['c'] - $gradec;
            $edits = $counts['u'] + $counts['d'] - $gradeu;
            $parts = [
                ['label' => get_string('nt_act_views', 'theme_baz'), 'n' => $views, 'color' => '#3B6FE0'],
                ['label' => get_string('nt_act_submissions', 'theme_baz'), 'n' => $subs, 'color' => '#1FA37A'],
                ['label' => get_string('nt_act_grades', 'theme_baz'), 'n' => $grades, 'color' => '#6D4AE8'],
                ['label' => get_string('nt_act_edits', 'theme_baz'), 'n' => $edits, 'color' => '#F2A33A'],
            ];
            foreach ($parts as &$p) {
                $p['n'] = number_format($p['n']);
            }
            unset($p);
            $out['ntacttotal'] = number_format($total);
            $out['ntactparts'] = $parts;
            if ($lastweek > 0) {
                $pct = (int) round((($total - $lastweek) / $lastweek) * 100);
                $out['ntactdelta'] = get_string('nt_act_vs', 'theme_baz', (object) [
                    'pct' => ($pct >= 0 ? '+' : '') . $pct . '%',
                    'day' => userdate($daystart - WEEKSECS, '%A'),
                ]);
                $out['nthasactdelta'] = true;
                $out['ntactdeltaneg'] = $pct < 0;
            }
        } catch (\Throwable $e) {
            debugging('nt_admin_dashboard activity: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        return $out;
    }

    /**
     * The five latest changes on the site (creates / updates / deletes).
     *
     * @return array
     */
    protected function recent_events(int $now): array {
        global $DB;
        $items = [];
        try {
            $recs = $DB->get_records_sql(
                "SELECT id, eventname, crud, userid, courseid, timecreated
                   FROM {logstore_standard_log}
                  WHERE crud <> 'r' AND timecreated >= :since AND userid > 0
               ORDER BY id DESC",
                ['since' => $now - 14 * DAYSECS], 0, 5);
            if (!$recs) {
                return [];
            }
            $uids = array_unique(array_map(function($r) {
                return $r->userid;
            }, $recs));
            $cids = array_unique(array_filter(array_map(function($r) {
                return $r->courseid;
            }, $recs)));
            $users = $DB->get_records_list('user', 'id', $uids, '',
                'id,' . implode(',', \core_user\fields::get_name_fields()));
            $courses = $cids ? $DB->get_records_list('course', 'id', $cids, '', 'id, shortname') : [];
            foreach ($recs as $r) {
                $class = $r->eventname;
                $title = ltrim(substr($class, strrpos($class, '\\') + 1));
                if (class_exists($class) && method_exists($class, 'get_name')) {
                    try {
                        $title = $class::get_name();
                    } catch (\Throwable $e) {
                        $title = str_replace('_', ' ', $title);
                    }
                }
                $who = isset($users[$r->userid]) ? fullname($users[$r->userid]) : '';
                $where = ($r->courseid && $r->courseid != SITEID && isset($courses[$r->courseid]))
                    ? format_string($courses[$r->courseid]->shortname) : '';
                $kind = stripos($class, 'grade') !== false ? 'grade'
                    : (stripos($class, 'enrol') !== false ? 'enrol'
                    : ($r->crud === 'c' ? 'create' : ($r->crud === 'd' ? 'delete' : 'update')));
                $items[] = [
                    'title' => $title,
                    'sub'   => implode(' · ', array_filter([$who, $where])),
                    'ago'   => $this->ago($now - (int) $r->timecreated),
                    'kind'  => $kind,
                    'is' . $kind => true,
                ];
            }
        } catch (\Throwable $e) {
            debugging('nt_admin_dashboard recent: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        return $items;
    }

    /**
     * Course totals per top-level category (top 3).
     *
     * @return array
     */
    protected function categories(): array {
        global $DB;
        $out = ['count' => 0, 'top' => []];
        try {
            $cats = $DB->get_records('course_categories', null, '', 'id, name, path, coursecount, parent');
            $out['count'] = count($cats);
            $totals = [];
            foreach ($cats as $c) {
                $parts = explode('/', trim($c->path, '/'));
                $root = (int) $parts[0];
                $totals[$root] = ($totals[$root] ?? 0) + (int) $c->coursecount;
            }
            arsort($totals);
            foreach (array_slice($totals, 0, 3, true) as $id => $n) {
                if (!isset($cats[$id]) || $n <= 0) {
                    continue;
                }
                $out['top'][] = ['name' => format_string($cats[$id]->name), 'n' => $n];
            }
        } catch (\Throwable $e) {
            debugging('nt_admin_dashboard categories: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        return $out;
    }

    /**
     * Courses card context.
     *
     * @return array
     */
    protected function courses_card(array $cats): array {
        global $DB, $USER;
        $starred = 0;
        try {
            $starred = (int) $DB->count_records('favourite', ['component' => 'core_course',
                'itemtype' => 'courses', 'userid' => $USER->id]);
        } catch (\Throwable $e) {
            $starred = 0;
        }
        $colors = ['#F2A33A', '#3B6FE0', '#1FA37A'];
        // Display only: the USDC category is not shown on the dashboards (nothing in Moodle is changed).
        $cats['top'] = array_values(array_filter($cats['top'], function($c) {
            return stripos((string) $c['name'], 'USDC') === false;
        }));
        $max = 0;
        foreach ($cats['top'] as $c) {
            $max = max($max, $c['n']);
        }
        $rows = [];
        foreach ($cats['top'] as $i => $c) {
            $rows[] = [
                'name' => $c['name'], 'n' => number_format($c['n']),
                'width' => $max ? max(2, round(($c['n'] / $max) * 100)) : 0,
                'color' => $colors[$i % 3],
            ];
        }
        return [
            'ntcatcount' => number_format($cats['count']),
            'ntstarred' => $starred,
            'ntcatrows' => $rows,
            'nthascatrows' => !empty($rows),
        ];
    }

    /**
     * Users seen most recently (last 24 hours).
     *
     * @return array
     */
    protected function who_online(int $now): array {
        global $DB, $CFG;
        $out = [];
        try {
            $fields = implode(',', \core_user\fields::get_name_fields());
            $recs = $DB->get_records_select('user', 'deleted = 0 AND lastaccess >= :t AND id <> :guest',
                ['t' => $now - DAYSECS, 'guest' => $CFG->siteguest], 'lastaccess DESC',
                'id, lastaccess, ' . $fields, 0, 3);
            $palette = ['#0F1F3D', '#3B6FE0', '#1FA37A', '#6D4AE8'];
            $i = 0;
            foreach ($recs as $u) {
                $age = $now - (int) $u->lastaccess;
                $initials = \core_text::strtoupper(\core_text::substr(trim($u->firstname), 0, 1)
                    . \core_text::substr(trim($u->lastname), 0, 1));
                $state = $age < 5 * MINSECS ? 'on' : ($age < HOURSECS ? 'away' : 'off');
                $out[] = [
                    'name' => fullname($u),
                    'initials' => $initials,
                    'color' => $palette[$i++ % count($palette)],
                    'status' => $state === 'on' ? get_string('nt_onlinenow', 'theme_baz') : $this->ago($age),
                    'state' => $state,
                    'url' => (new moodle_url('/user/profile.php', ['id' => $u->id]))->out(false),
                ];
            }
        } catch (\Throwable $e) {
            debugging('nt_admin_dashboard online: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        return $out;
    }

    /**
     * "10 min ago", "2 hr ago", "3 days ago".
     *
     * @return string
     */
    protected function ago(int $secs): string {
        if ($secs < HOURSECS) {
            return get_string('nt_ago_min', 'theme_baz', max(1, (int) floor($secs / MINSECS)));
        }
        if ($secs < DAYSECS) {
            return get_string('nt_ago_hr', 'theme_baz', (int) floor($secs / HOURSECS));
        }
        return get_string('nt_ago_day', 'theme_baz', (int) floor($secs / DAYSECS));
    }
}
