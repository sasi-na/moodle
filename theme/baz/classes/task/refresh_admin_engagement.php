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
 * Scheduled task that precomputes the admin dashboard's 30-day sign-in trend.
 *
 * @package   theme_baz
 * @copyright 2026 onlinejain.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_baz\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Runs the (expensive) logstore query for daily sign-ins over the last 30 days
 * off-request and caches the resulting series in plugin config, so the admin
 * dashboard never touches the log table while rendering /my/.
 */
class refresh_admin_engagement extends \core\task\scheduled_task {

    /** Number of days in the trend window. */
    const DAYS = 30;

    /**
     * @return string
     */
    public function get_name() {
        return get_string('task_refreshadminengagement', 'theme_baz');
    }

    /**
     * Compute and cache the daily sign-in counts.
     */
    public function execute() {
        global $DB;

        $days = self::DAYS;
        $start = usergetmidnight(time()) - ($days - 1) * DAYSECS;

        // Daily count of "loggedin" events. Heavy (the log table is large) but this
        // runs in cron, not in the request path.
        $rows = $DB->get_records_sql(
            "SELECT FLOOR((timecreated - :start) / :day) AS d, COUNT(*) AS c
               FROM {logstore_standard_log}
              WHERE timecreated >= :start2 AND action = :action
           GROUP BY FLOOR((timecreated - :start3) / :day2)",
            ['start' => $start, 'start2' => $start, 'start3' => $start,
             'day' => DAYSECS, 'day2' => DAYSECS, 'action' => 'loggedin']);

        $vals = array_fill(0, $days, 0);
        foreach ($rows as $r) {
            $d = (int) $r->d;
            if ($d >= 0 && $d < $days) {
                $vals[$d] = (int) $r->c;
            }
        }

        // Yesterday's DAU as a COMPLETE day, for the stickiness tile. It cannot come
        // from user.lastaccess: that column holds only the most recent access, so a
        // "lastaccess fell yesterday" test silently drops everyone who returned
        // today and undercounts. COUNT(DISTINCT userid) over a single day is cheap
        // (measured 0.48s) because timecreated is indexed -- unlike the 30-day
        // grouped query above, which is why that one lives here in the first place.
        $ystart = usergetmidnight(time()) - DAYSECS;
        $ydau = (int) $DB->get_field_sql(
            "SELECT COUNT(DISTINCT userid)
               FROM {logstore_standard_log}
              WHERE timecreated >= :a AND timecreated < :b AND action = :action",
            ['a' => $ystart, 'b' => $ystart + DAYSECS, 'action' => 'loggedin']);

        // Failed sign-ins for the same completed day, for the health panel.
        $yfail = (int) $DB->get_field_sql(
            "SELECT COUNT(*)
               FROM {logstore_standard_log}
              WHERE timecreated >= :a AND timecreated < :b
                AND action = :action AND target = :target",
            ['a' => $ystart, 'b' => $ystart + DAYSECS,
             'action' => 'failed', 'target' => 'user_login']);

        set_config('admin_dau_series', json_encode([
            'start' => $start,
            'vals'  => array_values($vals),
            'ydau'  => $ydau,
            'yfail' => $yfail,
            'yday'  => $ystart,
            'gen'   => time(),
        ]), 'theme_baz');

        mtrace("theme_baz admin engagement: cached {$days}-day sign-in series ("
            . array_sum($vals) . " sign-ins); yesterday DAU {$ydau}, failed sign-ins {$yfail}.");
    }
}
