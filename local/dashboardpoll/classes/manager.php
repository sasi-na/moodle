<?php
namespace local_dashboardpoll;

defined('MOODLE_INTERNAL') || die();

/**
 * Gating, cohort resolution and cached aggregates for the dashboard poll.
 *
 * The dashboard injection hook + get_active web service run on every student
 * dashboard load, so role / profile lookups are memoized per request and the
 * resolved active poll plus its vote tallies are cached in MUC (invalidated on
 * any admin mutation or new response). Same approach as local_exams\manager.
 *
 * "Cohort" here is the `cohort_batch` custom user-profile field, not Moodle's
 * built-in cohorts.
 *
 * @package   local_dashboardpoll
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {

    /** Profile field shortname used as the cohort/batch. */
    const COHORT_FIELD = 'cohort_batch';

    /** @return \cache */
    protected static function cache(): \cache {
        return \cache::make('local_dashboardpoll', 'active');
    }

    /**
     * Cluster-wide cache revision. The `active` MUC cache is APPLICATION mode,
     * so a $cache->purge() on the admin's pod does not necessarily clear the
     * copy held by other web nodes — which left some pods serving a stale "no
     * active poll" map and the popup appearing only intermittently. Cached
     * entries carry this revision and are rebuilt when it changes; bumping it
     * goes through set_config(), which propagates to every node.
     */
    protected static function rev(): int {
        return (int) get_config('local_dashboardpoll', 'cacherev');
    }

    // ---- audience -----------------------------------------------------------

    /**
     * A "student" is any logged-in user who is not a site admin, manager or
     * teacher. Memoized per request. has_capability checks only apply to the
     * current user, which is all the popup ever evaluates.
     */
    public static function is_student(int $userid): bool {
        global $USER;

        static $cache = [];
        if (array_key_exists($userid, $cache)) {
            return $cache[$userid];
        }

        if (is_siteadmin($userid)) {
            return $cache[$userid] = false;
        }

        $syscontext = \context_system::instance();
        if ($userid == $USER->id
                && (has_capability('moodle/site:config', $syscontext)
                    || has_capability('local/dashboardpoll:manage', $syscontext))) {
            return $cache[$userid] = false;
        }

        // Anyone who can grade in at least one course is staff, not a student.
        $courses = get_user_capability_course('moodle/grade:viewall', $userid, false, 'id');
        if (!empty($courses)) {
            return $cache[$userid] = false;
        }

        return $cache[$userid] = true;
    }

    /**
     * The user's `cohort_batch` profile value ('' if unset). Memoized.
     */
    public static function user_cohort(int $userid): string {
        static $cache = [];
        if (array_key_exists($userid, $cache)) {
            return $cache[$userid];
        }
        $val = \local_commonutils\user_helper::get_single_profile_field($userid, self::COHORT_FIELD);
        return $cache[$userid] = (string) ($val ?? '');
    }

    /**
     * Distinct non-empty `cohort_batch` values across all users, sorted.
     * Used to populate the admin cohort dropdown.
     *
     * @return string[]
     */
    public static function cohort_values(): array {
        global $DB;
        $sql = "SELECT DISTINCT uid.data
                  FROM {user_info_data} uid
                  JOIN {user_info_field} uif ON uif.id = uid.fieldid
                 WHERE uif.shortname = :sn
                   AND uid.data <> ''
              ORDER BY uid.data ASC";
        return array_values($DB->get_fieldset_sql($sql, ['sn' => self::COHORT_FIELD]));
    }

    /**
     * Number of students whose cohort_batch matches (for report response rate).
     */
    public static function cohort_size(string $cohort): int {
        global $DB;
        if ($cohort === '') {
            // "All cohort": count distinct users that carry any cohort_batch value.
            $sql = "SELECT COUNT(DISTINCT uid.userid)
                      FROM {user_info_data} uid
                      JOIN {user_info_field} uif ON uif.id = uid.fieldid
                     WHERE uif.shortname = :sn AND uid.data <> ''";
            return (int) $DB->count_records_sql($sql, ['sn' => self::COHORT_FIELD]);
        }
        $sql = "SELECT COUNT(DISTINCT uid.userid)
                  FROM {user_info_data} uid
                  JOIN {user_info_field} uif ON uif.id = uid.fieldid
                 WHERE uif.shortname = :sn AND uid.data = :val";
        return (int) $DB->count_records_sql($sql, ['sn' => self::COHORT_FIELD, 'val' => $cohort]);
    }

    // ---- active poll resolution --------------------------------------------

    /**
     * Map of cohort value => active poll id (one active poll per audience).
     *
     * @return array<string,int>
     */
    public static function active_map(): array {
        global $DB;
        $cache = self::cache();
        $rev = self::rev();
        $entry = $cache->get('map');
        if ($entry === false || ($entry['rev'] ?? -1) !== $rev) {
            $map = [];
            $recs = $DB->get_records('local_dashboardpoll', ['status' => 'active'], 'id ASC', 'id, cohort');
            foreach ($recs as $r) {
                $map[(string) $r->cohort] = (int) $r->id;
            }
            $cache->set('map', ['rev' => $rev, 'map' => $map]);
            return $map;
        }
        return $entry['map'];
    }

    /**
     * Resolve the active poll targeted at this user: a poll matching the user's
     * cohort wins over the "All Cohort" poll.
     */
    public static function active_poll_for_user(int $userid): ?\stdClass {
        $map = self::active_map();
        if (empty($map)) {
            return null;
        }
        $cohort = self::user_cohort($userid);
        $pollid = $map[$cohort] ?? ($map[''] ?? 0);
        if (!$pollid) {
            return null;
        }
        return self::poll((int) $pollid);
    }

    /** Whether any active poll targets this user (cheap gate for injection). */
    public static function eligible_poll(int $userid): bool {
        return self::active_poll_for_user($userid) !== null;
    }

    /** Cached poll record (id, question, cohort, status), or null. */
    public static function poll(int $pollid): ?\stdClass {
        global $DB;
        $cache = self::cache();
        $key = 'poll_' . $pollid;
        $rev = self::rev();
        $entry = $cache->get($key);
        if ($entry === false || ($entry['rev'] ?? -1) !== $rev) {
            $rec = $DB->get_record('local_dashboardpoll', ['id' => $pollid],
                'id, question, cohort, status, multiselect, maxchoices, resultsdisplay') ?: null;
            $cache->set($key, ['rev' => $rev, 'data' => $rec]);
            return $rec ?: null;
        }
        return $entry['data'] ?: null;
    }

    /** Whether the user is permitted to answer the given poll. */
    public static function user_eligible_for_poll(\stdClass $poll, int $userid): bool {
        if ($poll->cohort === '') {
            return true;
        }
        return $poll->cohort === self::user_cohort($userid);
    }

    // ---- aggregates ---------------------------------------------------------

    /**
     * Cached vote tally for a poll.
     *
     * `total` is the number of selections cast (sum across options); `voters` is
     * the number of distinct people who answered. They are equal for a
     * single-choice poll but diverge once multiple selections are allowed —
     * percentages are taken against `voters` so they read as "share of
     * respondents who picked this".
     *
     * @return array{total:int, voters:int, options:array<int,array{id:int,text:string,votes:int}>}
     */
    public static function aggregate(int $pollid): array {
        global $DB;
        $cache = self::cache();
        $key = 'agg_' . $pollid;
        $agg = $cache->get($key);
        if ($agg === false) {
            $options = $DB->get_records('local_dashboardpoll_options', ['pollid' => $pollid],
                'sortorder ASC, id ASC', 'id, optiontext');
            $counts = $DB->get_records_sql(
                "SELECT optionid, COUNT(*) AS c
                   FROM {local_dashboardpoll_responses}
                  WHERE pollid = :pid
               GROUP BY optionid", ['pid' => $pollid]);
            $voters = (int) $DB->count_records_sql(
                "SELECT COUNT(DISTINCT userid)
                   FROM {local_dashboardpoll_responses}
                  WHERE pollid = :pid", ['pid' => $pollid]);
            $total = 0;
            $opts = [];
            foreach ($options as $o) {
                $votes = isset($counts[$o->id]) ? (int) $counts[$o->id]->c : 0;
                $total += $votes;
                $opts[] = ['id' => (int) $o->id, 'text' => (string) $o->optiontext, 'votes' => $votes];
            }
            $agg = ['total' => $total, 'voters' => $voters, 'options' => $opts];
            $cache->set($key, $agg);
        }
        return $agg;
    }

    /** Whether this user has any response row for the poll. */
    public static function has_answered(int $pollid, int $userid): bool {
        global $DB;
        return $DB->record_exists('local_dashboardpoll_responses',
            ['pollid' => $pollid, 'userid' => $userid]);
    }

    /**
     * Number of selections a user must make before the poll counts as done.
     * A fixed maxchoices on a multi-select poll is treated as a required count;
     * everything else needs a single choice.
     */
    public static function required_choices(\stdClass $poll): int {
        if (!empty($poll->multiselect) && (int) $poll->maxchoices > 0) {
            return (int) $poll->maxchoices;
        }
        return 1;
    }

    /** The option ids this user has currently saved for the poll. */
    public static function user_selection(int $pollid, int $userid): array {
        global $DB;
        return array_map('intval', $DB->get_fieldset_select('local_dashboardpoll_responses',
            'optionid', 'pollid = :pid AND userid = :uid', ['pid' => $pollid, 'uid' => $userid]));
    }

    /**
     * Whether the user has saved enough selections to finish. Until this is
     * true the popup keeps reappearing (with prior picks pre-selected).
     */
    public static function is_complete(\stdClass $poll, int $userid): bool {
        return count(self::user_selection((int) $poll->id, $userid)) >= self::required_choices($poll);
    }

    /**
     * Assemble the web-service payload for a poll. Vote counts/percentages are
     * withheld until the user has finished (no peeking at results before voting),
     * and the user's saved picks are returned so the popup can pre-select them.
     *
     * What the finished student sees is governed by the poll's `resultsdisplay`
     * bitmask: 0 = thank-you card only, bit 1 (1) = percentages, bit 2 (2) = vote
     * counts. When results are hidden entirely the option tallies are suppressed
     * from the payload so they never reach the browser.
     */
    public static function payload(\stdClass $poll, int $userid): array {
        $pollid = (int) $poll->id;
        $selected = self::user_selection($pollid, $userid);
        $answered = count($selected) >= self::required_choices($poll);
        $agg = self::aggregate($pollid);

        $display = (int) $poll->resultsdisplay;
        $showresults = $answered && $display > 0;
        $showpercent = $showresults && ($display & 1);
        $showcounts  = $showresults && ($display & 2);

        // Percentages are a share of respondents (distinct voters), so a
        // multi-select poll's options can sum past 100% — that is expected.
        $voters = (int) $agg['voters'];
        $chosen = array_flip($selected);
        $options = [];
        foreach ($agg['options'] as $o) {
            // Tallies leave the server only when this student may see results.
            $votes = $showresults ? $o['votes'] : 0;
            $percent = ($showresults && $voters > 0)
                ? (int) round($votes * 100 / $voters) : 0;
            $options[] = [
                'id'       => $o['id'],
                'text'     => $o['text'],
                'votes'    => $votes,
                'percent'  => $percent,
                'selected' => isset($chosen[$o['id']]),
            ];
        }

        return [
            'hasactive'   => true,
            'pollid'      => $pollid,
            'question'    => (string) $poll->question,
            'multiselect' => (bool) $poll->multiselect,
            'maxselect'   => (int) $poll->maxchoices,
            'answered'    => $answered,
            'showresults' => $showresults,
            'showpercent' => $showpercent,
            'showvotecounts' => $showcounts,
            'totalvotes'  => $showresults ? $voters : 0,
            'options'     => $options,
        ];
    }

    // ---- cache invalidation -------------------------------------------------

    /** Drop the cached tally for one poll (called after a new response). */
    public static function purge_aggregate(int $pollid): void {
        self::cache()->delete('agg_' . $pollid);
    }

    /**
     * Full purge after an admin mutation (create/edit/activate/close/delete).
     * Bumps the cluster-wide revision so every web node rebuilds, then clears
     * the local copy.
     */
    public static function purge_all(): void {
        set_config('cacherev', self::rev() + 1, 'local_dashboardpoll');
        self::cache()->purge();
    }
}
