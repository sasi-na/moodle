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
 * Per-batch statistics: the queries, and the config-backed cache they land in.
 *
 * @package   local_batchvitals
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_batchvitals;

defined('MOODLE_INTERNAL') || die();

/**
 * Computes and caches the batch figures.
 *
 * A "batch" is a course category; everything is counted over that category's
 * whole subtree (programme -> semester -> course). The full sweep costs roughly
 * 21s for 13 batches, which is why it runs in cron and the report reads a
 * cached JSON blob rather than querying.
 */
class vitals {

    /** Plugin name, for get_config/set_config. */
    const PLUGIN = 'local_batchvitals';

    /** Config key holding the cached JSON blob. */
    const CACHEKEY = 'cache';

    /** Default sign-in recency window, in days. */
    const DEFAULT_ACTIVE_DAYS = 30;

    /**
     * Below this many tracked completion records a batch's percentage is not
     * comparable with a current one -- the legacy batches have been stripped
     * back to a few hundred records and read as a misleading 100%.
     */
    const THIN_RECORDS = 20000;

    /**
     * Configured batch category ids, in the admin-defined order.
     *
     * @return int[]
     */
    public static function batch_ids(): array {
        $raw = (string) get_config(self::PLUGIN, 'batchcategories');
        if (trim($raw) === '') {
            return [];
        }
        $ids = [];
        foreach (preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) as $bit) {
            if (ctype_digit($bit)) {
                $ids[] = (int) $bit;
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * @return int Sign-in recency window in days.
     */
    public static function active_days(): int {
        $d = (int) get_config(self::PLUGIN, 'activedays');
        return $d > 0 ? $d : self::DEFAULT_ACTIVE_DAYS;
    }

    /**
     * @return bool Whether headcounts are restricted to the student role.
     */
    public static function role_filter(): bool {
        $v = get_config(self::PLUGIN, 'rolefilter');
        return $v === false ? true : (bool) $v;
    }

    /**
     * Role ids carrying the student archetype.
     *
     * @return int[]
     */
    public static function student_roleids(): array {
        global $DB;
        return array_keys($DB->get_records('role', ['archetype' => 'student'], '', 'id'));
    }

    /**
     * The programme categories: the level directly below each configured batch.
     *
     * Every child is included, exam and foundation categories among them --
     * access control decides who sees which, so there is nothing to filter here.
     *
     * @return array catid => ['batchid' => int, 'batchname' => string]
     */
    public static function programme_map(): array {
        global $DB;

        $out = [];
        foreach (self::batch_ids() as $bid) {
            $b = $DB->get_record('course_categories', ['id' => $bid], 'id, name, path, depth');
            if (!$b) {
                continue;
            }
            $kids = $DB->get_records_select('course_categories',
                'path LIKE :p AND depth = :d',
                ['p' => $b->path . '/%', 'd' => $b->depth + 1],
                'name', 'id, name');
            foreach ($kids as $k) {
                $out[(int) $k->id] = [
                    'batchid'   => (int) $b->id,
                    'batchname' => trim(format_string($b->name)),
                ];
            }
        }
        return $out;
    }

    /**
     * Compute every figure for one batch.
     *
     * @param int $categoryid Category to treat as the batch root.
     * @return array|null Null when the category no longer exists.
     */
    public static function compute(int $categoryid): ?array {
        global $DB;

        $started = microtime(true);
        $cat = $DB->get_record('course_categories', ['id' => $categoryid],
            'id, name, path, depth');
        if (!$cat) {
            return null;
        }

        // Match the category itself plus everything beneath it. Using the stored
        // path rather than a built one keeps this correct at any depth.
        $prm = ['p1' => $cat->path, 'p2' => $cat->path . '/%'];
        $intree = '(sub.path = :p1 OR sub.path LIKE :p2)';

        $courses = (int) $DB->count_records_sql(
            "SELECT COUNT(*)
               FROM {course} c
               JOIN {course_categories} sub ON sub.id = c.category
              WHERE $intree", $prm);

        // Programmes are the level directly below the batch.
        $programs = (int) $DB->count_records_sql(
            "SELECT COUNT(*)
               FROM {course_categories} sub
              WHERE sub.path LIKE :p2 AND sub.depth = :d",
            ['p2' => $prm['p2'], 'd' => $cat->depth + 1]);

        $rolejoin = '';
        $roleprm = [];
        if (self::role_filter()) {
            $roleids = self::student_roleids();
            if ($roleids) {
                list($rin, $roleprm) = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'r');
                $rolejoin = "JOIN {context} ctx
                               ON ctx.instanceid = c.id AND ctx.contextlevel = " . CONTEXT_COURSE . "
                             JOIN {role_assignments} ra
                               ON ra.contextid = ctx.id AND ra.userid = ue.userid AND ra.roleid $rin";
            }
        }

        // One pass for all four headcounts. Run as four separate COUNT queries this
        // took 2.49s on the largest batch; conditional aggregation brings it to 1.05s.
        $cut = time() - self::active_days() * DAYSECS;
        $head = $DB->get_record_sql(
            "SELECT COUNT(DISTINCT u.id) AS learners,
                    COUNT(DISTINCT CASE WHEN u.suspended = 1 THEN u.id END) AS susp,
                    COUNT(DISTINCT CASE WHEN u.lastaccess = 0 THEN u.id END) AS nev,
                    COUNT(DISTINCT CASE WHEN u.lastaccess > :cut THEN u.id END) AS act
               FROM {course_categories} sub
               JOIN {course} c ON c.category = sub.id
               JOIN {enrol} e ON e.courseid = c.id
               JOIN {user_enrolments} ue ON ue.enrolid = e.id
               $rolejoin
               JOIN {user} u ON u.id = ue.userid
              WHERE $intree AND u.deleted = 0",
            $prm + $roleprm + ['cut' => $cut]);

        // Course completion is unusable here -- only 21 courses on the site define
        // completion criteria -- so this is activity completion. Deliberately not
        // role-filtered: the join would run against millions of rows for a
        // correction under one percent.
        $comp = $DB->get_record_sql(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN cmc.completionstate > 0 THEN 1 ELSE 0 END) AS done
               FROM {course_modules_completion} cmc
               JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
               JOIN {course} c ON c.id = cm.course
               JOIN {course_categories} sub ON sub.id = c.category
              WHERE $intree", $prm);

        $learners = (int) $head->learners;
        $active = (int) $head->act;
        $never = (int) $head->nev;

        return [
            'id'       => (int) $cat->id,
            'name'     => trim(format_string($cat->name)),
            'learners' => $learners,
            'active'   => $active,
            'never'    => $never,
            'susp'     => (int) $head->susp,
            // Logged in at some point, but not inside the window.
            'dormant'  => max(0, $learners - $active - $never),
            'inactive' => max(0, $learners - $active),
            'courses'  => $courses,
            'programs' => $programs,
            'recs'     => (int) $comp->total,
            'done'     => (int) $comp->done,
            'secs'     => round(microtime(true) - $started, 2),
            'gen'      => time(),
        ];
    }

    /**
     * Compute every configured batch and store the result.
     *
     * @return array The stored payload.
     */
    public static function compute_all(): array {
        $batches = [];
        foreach (self::batch_ids() as $id) {
            $row = self::compute($id);
            if ($row !== null) {
                $batches[] = $row;
            }
        }

        // Programme rows feed the coordinator view. Measured at 17.45s for the
        // 44 programmes under the 13 configured batches, on top of the batch
        // sweep -- still cron-only work.
        $programmes = [];
        foreach (self::programme_map() as $cid => $meta) {
            $row = self::compute($cid);
            if ($row !== null) {
                $programmes[] = $row + $meta;
            }
        }

        $payload = [
            'gen'        => time(),
            'activedays' => self::active_days(),
            'rolefilter' => self::role_filter() ? 1 : 0,
            'batches'    => $batches,
            'programmes' => $programmes,
        ];
        self::store($payload);
        return $payload;
    }

    /**
     * Recompute a single batch, leaving the other cached rows alone.
     *
     * @param int $categoryid
     * @return array|null The refreshed row.
     */
    public static function refresh_one(int $categoryid): ?array {
        $isbatch = in_array($categoryid, self::batch_ids(), true);
        $progmap = self::programme_map();
        if (!$isbatch && !isset($progmap[$categoryid])) {
            return null;
        }
        $row = self::compute($categoryid);
        if ($row === null) {
            return null;
        }
        if (!$isbatch) {
            $row += $progmap[$categoryid];
        }
        $key = $isbatch ? 'batches' : 'programmes';

        $payload = self::load();
        $replaced = false;
        foreach ($payload[$key] as $i => $b) {
            if ((int) $b['id'] === $categoryid) {
                $payload[$key][$i] = $row;
                $replaced = true;
                break;
            }
        }
        if (!$replaced) {
            $payload[$key][] = $row;
        }
        $payload['activedays'] = self::active_days();
        $payload['rolefilter'] = self::role_filter() ? 1 : 0;
        self::store($payload);
        return $row;
    }

    /**
     * @param array $payload
     */
    public static function store(array $payload): void {
        set_config(self::CACHEKEY, json_encode($payload), self::PLUGIN);
    }

    /**
     * Read the cache, in the configured batch order.
     *
     * @return array Always shaped, with an empty batch list when nothing is cached.
     */
    public static function load(): array {
        $raw = get_config(self::PLUGIN, self::CACHEKEY);
        $data = $raw ? json_decode($raw, true) : null;
        if (!is_array($data) || !isset($data['batches']) || !is_array($data['batches'])) {
            $data = ['gen' => 0, 'activedays' => self::active_days(),
                     'rolefilter' => self::role_filter() ? 1 : 0, 'batches' => []];
        }
        if (!isset($data['programmes']) || !is_array($data['programmes'])) {
            $data['programmes'] = [];
        }

        // Present in the order the admin listed, and drop any batch since removed
        // from the setting so a stale cache cannot resurrect it.
        $wanted = self::batch_ids();
        $byid = [];
        foreach ($data['batches'] as $b) {
            if (isset($b['id'])) {
                $byid[(int) $b['id']] = $b;
            }
        }
        $ordered = [];
        foreach ($wanted as $id) {
            if (isset($byid[$id])) {
                $ordered[] = $byid[$id];
            }
        }
        $data['batches'] = $ordered;

        // Programmes: drop any whose batch has since left the setting, and sort
        // by batch then programme name so a coordinator with two sees them in a
        // predictable order.
        $progmap = self::programme_map();
        $progs = [];
        foreach ($data['programmes'] as $p) {
            if (isset($p['id']) && isset($progmap[(int) $p['id']])) {
                $progs[] = $p + $progmap[(int) $p['id']];
            }
        }
        usort($progs, function($a, $b) {
            return [$a['batchname'] ?? '', $a['name'] ?? '']
               <=> [$b['batchname'] ?? '', $b['name'] ?? ''];
        });
        $data['programmes'] = $progs;

        return $data;
    }
}
