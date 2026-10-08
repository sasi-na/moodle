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
 * Bespoke admin dashboard for site administrators (site-admins only).
 *
 * A friendlier front door to site administration on /my/: site KPIs, learner
 * engagement, system health, quick actions and recent config changes. Every tile
 * links into the existing admin screens — a summary + navigation layer only.
 * Each data section fails soft so nothing breaks the page.
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

/**
 * Renderable + templatable for the admin dashboard.
 */
class admin_dashboard implements renderable, templatable {

    /**
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $USER, $CFG;

        $now = time();
        $daystart = usergetmidnight($now);
        $weekstart = $daystart - 6 * DAYSECS;
        $monthstart = $daystart - 29 * DAYSECS;

        $kpis = $this->get_kpis($daystart);
        $engage = $this->get_engagement($daystart, $weekstart, $monthstart);
        $chart = $this->get_login_chart($now);
        $health = $this->get_health();

        $attention = 0;
        foreach ($health as $h) {
            if ($h['state'] !== 'ok') { $attention++; }
        }

        $greeting = $this->greeting($now);

        return [
            // The persistent left-menu block, from the one shared source, so this
            // dashboard's menu matches every wrapped page instead of carrying its own.
            'homenav' => \theme_baz\local\shell::home_nav(),
            'config'        => ['wwwroot' => $CFG->wwwroot],
            'firstname'     => ucwords(\core_text::strtolower($USER->firstname)),
            'datelabel'     => userdate($now, get_string('strftimedaydate', 'langconfig')),
            'greetingtime'  => $greeting,
            'healthok'      => $attention === 0,
            'attentioncount' => $attention,
            'herotext'      => $attention === 0
                ? get_string('ad_hero_ok', 'theme_baz', $greeting)
                : get_string('ad_hero_warn', 'theme_baz', (object) ['word' => $greeting, 'count' => $attention]),

            // KPI strip.
            'kpi_users'      => number_format($kpis['users']),
            'kpi_activetoday' => number_format($kpis['activetoday']),
            'kpi_courses'    => number_format($kpis['courses']),
            'kpi_enrolments' => $this->human($kpis['enrolments']),
            'kpi_blocked'    => number_format($kpis['blocked']),

            // Engagement.
            'eng_activetoday' => number_format($engage['activetoday']),
            'eng_wau'        => number_format($engage['wau']),
            'eng_mau'        => number_format($engage['mau']),
            'eng_stickiness' => $engage['stickiness'],
            'chartok'        => $chart['ok'],
            'chartarea'      => $chart['area'],
            'chartline'      => $chart['line'],
            'chartlabels'    => $chart['labels'],

            // Health.
            'health'         => $health,
            'attention'      => $attention,

            // Quick-action / nav URLs (into the real admin screens).
            'mycoursesurl'   => (new moodle_url('/my/courses.php'))->out(false),
            'calendarurl'    => (new moodle_url('/calendar/view.php', ['view' => 'month']))->out(false),
            'usersurl'       => (new moodle_url('/admin/user.php'))->out(false),
            'adduserurl'     => (new moodle_url('/user/editadvanced.php', ['id' => -1]))->out(false),
            'coursesurl'     => (new moodle_url('/course/management.php'))->out(false),
            'createcourseurl' => (new moodle_url('/course/edit.php', ['category' => 0]))->out(false),
            'reportsurl'     => (new moodle_url('/report/log/index.php'))->out(false),
            'securityurl'    => (new moodle_url('/admin/category.php', ['category' => 'security']))->out(false),
            'pluginsurl'     => (new moodle_url('/admin/plugins.php'))->out(false),
            'settingsurl'    => (new moodle_url('/admin/search.php'))->out(false),
        ];
    }

    /**
     * Time-of-day greeting word ("morning"/"afternoon"/"evening") for the hero
     * strings (which read "Good {$a}").
     *
     * @param int $now
     * @return string
     */
    protected function greeting(int $now): string {
        $h = (int) userdate($now, '%H');
        if ($h < 12) { return 'morning'; }
        if ($h < 17) { return 'afternoon'; }
        return 'evening';
    }

    /**
     * Site-wide KPI counts (all cheap; each guarded).
     *
     * @param int $daystart
     * @return array
     */
    protected function get_kpis(int $daystart): array {
        global $DB;
        $out = ['users' => 0, 'activetoday' => 0, 'courses' => 0, 'enrolments' => 0, 'blocked' => 0];
        try { $out['users'] = (int) $DB->count_records('user', ['deleted' => 0, 'confirmed' => 1]); } catch (\Throwable $e) {}
        try { $out['activetoday'] = (int) $DB->count_records_select('user', 'deleted = 0 AND lastaccess >= :t', ['t' => $daystart]); } catch (\Throwable $e) {}
        try { $out['courses'] = max(0, (int) $DB->count_records('course') - 1); } catch (\Throwable $e) {}
        try { $out['enrolments'] = (int) $DB->count_records('user_enrolments'); } catch (\Throwable $e) {}
        // Accounts that cannot sign in: suspended, OR flagged by the "Block Access"
        // profile field. Counted as one DISTINCT set because the two overlap almost
        // completely in practice -- every blockaccess=YES account on jain is also
        // suspended -- so adding the two counts would double-count them.
        //
        // The field test mirrors login/index.php's gate exactly (lowercased and
        // trimmed). The previous version compared against the literal 'YES', so a
        // value of ' YES ' blocked the user at login but was never counted here.
        try {
            $fid = $DB->get_field('user_info_field', 'id', ['shortname' => 'blockaccess']);
            if ($fid) {
                $datacmp = $DB->sql_compare_text('d.data');
                $sql = "SELECT COUNT(DISTINCT u.id)
                          FROM {user} u
                     LEFT JOIN {user_info_data} d ON d.userid = u.id AND d.fieldid = :f
                         WHERE u.deleted = 0
                           AND (u.suspended = 1 OR LOWER(TRIM($datacmp)) = :v)";
                $out['blocked'] = (int) $DB->get_field_sql($sql, ['f' => $fid, 'v' => 'yes']);
            } else {
                $out['blocked'] = (int) $DB->count_records_select('user',
                    'deleted = 0 AND suspended = 1');
            }
        } catch (\Throwable $e) {}
        return $out;
    }

    /**
     * DAU / WAU / MAU + stickiness from user.lastaccess (point-in-time, cheap).
     *
     * @param int $daystart
     * @param int $weekstart
     * @param int $monthstart
     * @return array
     */
    protected function get_engagement(int $daystart, int $weekstart, int $monthstart): array {
        global $DB;
        $out = ['activetoday' => 0, 'wau' => 0, 'mau' => 0, 'stickiness' => '—'];
        try {
            $out['activetoday'] = (int) $DB->count_records_select('user', 'deleted = 0 AND lastaccess >= :t', ['t' => $daystart]);
            $out['wau'] = (int) $DB->count_records_select('user', 'deleted = 0 AND lastaccess >= :t', ['t' => $weekstart]);
            $out['mau'] = (int) $DB->count_records_select('user', 'deleted = 0 AND lastaccess >= :t', ['t' => $monthstart]);
            // Stickiness uses YESTERDAY as a complete day, not today so far. Today's
            // figure grows all day, so read in the morning it understated engagement
            // by roughly half (measured 6.1% at 11:30 against 12.4% for the
            // completed day).
            $dau = $this->yesterday_dau($daystart);
            if ($out['mau'] > 0 && $dau !== null) {
                $out['stickiness'] = round(($dau / $out['mau']) * 100, 1) . '%';
                $out['dau'] = $dau;
            }
        } catch (\Throwable $e) {}
        return $out;
    }

    /**
     * Distinct users who signed in yesterday -- a complete day.
     *
     * Prefers the value cached by {@see \theme_baz\task\refresh_admin_engagement},
     * and only when that value actually refers to yesterday (a wedged cron would
     * otherwise pin the tile to an old day). Falls back to the live query, which is
     * cheap for a single day, and to null if even that fails so the caller can show
     * a dash rather than a wrong number.
     *
     * @param int $daystart midnight today
     * @return int|null
     */
    protected function yesterday_dau(int $daystart): ?int {
        global $DB;

        $ystart = $daystart - DAYSECS;
        try {
            $raw = get_config('theme_baz', 'admin_dau_series');
            if (!empty($raw)) {
                $data = json_decode($raw, true);
                if (isset($data['ydau'], $data['yday']) && (int) $data['yday'] === $ystart) {
                    return (int) $data['ydau'];
                }
            }
        } catch (\Throwable $e) {
            // Fall through to the live query.
        }
        try {
            return (int) $DB->get_field_sql(
                "SELECT COUNT(DISTINCT userid)
                   FROM {logstore_standard_log}
                  WHERE timecreated >= :a AND timecreated < :b AND action = :action",
                ['a' => $ystart, 'b' => $daystart, 'action' => 'loggedin']);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Server-rendered SVG paths for the 30-day daily sign-ins area chart.
     *
     * The underlying logstore query is far too heavy to run in the request path
     * (the log table is huge), so it is precomputed off-request by the
     * {@see \theme_baz\task\refresh_admin_engagement} scheduled task and cached in
     * plugin config; here we only read that cache and draw. Fails soft (ok=false
     * → the template shows the stat tiles only) when the cache is absent or stale.
     *
     * @param int $now
     * @return array [ok, area(path d), line(path d), labels]
     */
    protected function get_login_chart(int $now): array {
        $out = ['ok' => false, 'area' => '', 'line' => '', 'labels' => []];
        try {
            $raw = get_config('theme_baz', 'admin_dau_series');
            if (empty($raw)) {
                return $out;
            }
            $data = json_decode($raw, true);
            if (empty($data['vals']) || !is_array($data['vals']) || empty($data['start'])) {
                return $out;
            }
            // Ignore a stale cache (the task refreshes daily); a gap means cron is
            // wedged and the trend would mislead.
            if (!empty($data['gen']) && ($now - (int) $data['gen']) > 3 * DAYSECS) {
                return $out;
            }
            $vals = array_map('intval', array_values($data['vals']));
            $start = (int) $data['start'];
            $days = count($vals);
            if ($days < 2) {
                return $out;
            }
            // Scale to a 620x150 viewBox.
            $w = 620; $h = 150; $padt = 14; $padb = 22;
            $mn = min($vals); $mx = max($vals);
            $range = max(1, $mx - $mn);
            $lo = $mn - $range * 0.35; $hi = $mx + $range * 0.12;
            $span = max(1, $hi - $lo);
            $x = fn($i) => round(($i / ($days - 1)) * ($w - 6) + 3, 1);
            $y = fn($v) => round($padt + (1 - ($v - $lo) / $span) * ($h - $padt - $padb), 1);
            $line = '';
            foreach ($vals as $i => $v) {
                $line .= ($i ? 'L' : 'M') . $x($i) . ' ' . $y($v) . ' ';
            }
            $out['line'] = trim($line);
            $out['area'] = 'M' . $x(0) . ' ' . $y($lo) . ' '
                . 'L' . substr(trim($line), 1) . ' '
                . 'L' . $x($days - 1) . ' ' . $y($lo) . ' Z';
            // three x-axis labels (first / mid / last day).
            foreach ([0, (int) floor($days / 2), $days - 1] as $i) {
                $out['labels'][] = [
                    'x' => $x($i),
                    'anchor' => $i === 0 ? 'start' : ($i === $days - 1 ? 'end' : 'middle'),
                    'text' => userdate($start + $i * DAYSECS, '%e %b'),
                ];
            }
            $out['ok'] = true;
        } catch (\Throwable $e) {
            return ['ok' => false, 'area' => '', 'line' => '', 'labels' => []];
        }
        return $out;
    }

    /**
     * System-health rows (each cheap + guarded).
     *
     * @param array $kpis
     * @return array
     */
    protected function get_health(): array {
        global $DB;

        $rows = [];
        $now = time();

        // Is cron running at all?
        try {
            $last = (int) $DB->get_field_sql('SELECT MAX(lastruntime) FROM {task_scheduled}');
            $rows[] = [
                'label' => get_string('ad_h_cron', 'theme_baz'),
                'value' => $last ? get_string('ago', 'core_message', format_time($now - $last)) : '—',
                'state' => ($last && ($now - $last) < HOURSECS) ? 'ok' : 'warn',
            ];
        } catch (\Throwable $e) {}

        // Scheduled tasks sitting in failure backoff. Distinct from the row above:
        // cron can be running perfectly while an individual task has been broken
        // for months.
        try {
            $failing = (int) $DB->count_records_select('task_scheduled', 'faildelay > 0 AND disabled = 0');
            $rows[] = [
                'label' => get_string('ad_h_failingtasks', 'theme_baz'),
                'value' => $failing ? number_format($failing) : get_string('ad_h_none', 'theme_baz'),
                'state' => $failing > 0 ? 'warn' : 'ok',
            ];
        } catch (\Throwable $e) {}

        // The ad-hoc queue: depth, failures, and how long the oldest item has
        // waited. Worth its own row because the Cron row reads "ok" whenever
        // anything ran recently -- which is how a 19-day backlog of assignment
        // notification tasks went unnoticed on this site.
        try {
            $depth = (int) $DB->count_records('task_adhoc');
            $qfail = (int) $DB->count_records_select('task_adhoc', 'faildelay > 0');
            $oldest = (int) $DB->get_field_sql('SELECT MIN(timecreated) FROM {task_adhoc}');
            $agedays = $oldest ? (int) floor(($now - $oldest) / DAYSECS) : 0;

            if ($depth === 0) {
                $value = get_string('ad_h_queueempty', 'theme_baz');
            } else {
                $value = get_string('ad_h_queued', 'theme_baz', number_format($depth));
                if ($qfail > 0) {
                    $value .= ' · ' . get_string('ad_h_nfailing', 'theme_baz', number_format($qfail));
                } else if ($agedays >= 1) {
                    $value .= ' · ' . get_string('ad_h_oldestdays', 'theme_baz', $agedays);
                }
            }
            $rows[] = [
                'label' => get_string('ad_h_queue', 'theme_baz'),
                'value' => $value,
                'state' => ($qfail > 0 || $agedays >= 1) ? 'warn' : 'ok',
            ];
        } catch (\Throwable $e) {}

        // Failed sign-ins yesterday. Read from the cron cache so the dashboard
        // never pays for a logstore query; falls back to the live one-day query,
        // which is cheap because timecreated is indexed.
        $failed = $this->failed_logins_yesterday(usergetmidnight($now));
        if ($failed !== null) {
            $rows[] = [
                'label' => get_string('ad_h_failedlogins', 'theme_baz'),
                'value' => number_format($failed),
                'state' => 'ok',
            ];
        }

        return $rows;
    }

    /**
     * Failed sign-in attempts yesterday, from the cached value where possible.
     *
     * @param int $daystart midnight today
     * @return int|null null when it cannot be determined, so the caller omits the row
     */
    protected function failed_logins_yesterday(int $daystart): ?int {
        global $DB;

        $ystart = $daystart - DAYSECS;
        try {
            $raw = get_config('theme_baz', 'admin_dau_series');
            if (!empty($raw)) {
                $data = json_decode($raw, true);
                if (isset($data['yfail'], $data['yday']) && (int) $data['yday'] === $ystart) {
                    return (int) $data['yfail'];
                }
            }
        } catch (\Throwable $e) {
            // Fall through to the live query.
        }
        try {
            return (int) $DB->get_field_sql(
                "SELECT COUNT(*)
                   FROM {logstore_standard_log}
                  WHERE timecreated >= :a AND timecreated < :b
                    AND action = :action AND target = :target",
                ['a' => $ystart, 'b' => $daystart, 'action' => 'failed', 'target' => 'user_login']);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Compact human count (e.g. 213000 → "213k").
     *
     * @param int $n
     * @return string
     */
    protected function human(int $n): string {
        if ($n >= 1000000) { return round($n / 1000000, 1) . 'M'; }
        if ($n >= 1000) { return round($n / 1000) . 'k'; }
        return (string) $n;
    }
}
