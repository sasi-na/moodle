<?php
namespace local_zoomattendance\helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Direct-join support: rewrites calendar event links to our join endpoint,
 * resolves Zoom registration URLs to numeric meeting ids, and registers
 * LMS users on the fly so they skip the Zoom registration form.
 */
class join_helper {

    /** Re-verify a webhook 'started' status older than this (seconds). */
    const STARTED_TTL = 4 * 3600;

    /** Minimum gap between inline registry syncs triggered by page loads. */
    const SYNC_THROTTLE = 60;

    /** Cached personal join links older than this are re-registered. */
    const JOINLINK_TTL = 12 * 3600;

    /**
     * Build (without saving) a local_zoom_event_join row for an event + URL.
     */
    private static function build_event_record(int $eventid, string $zoomurl): \stdClass {
        $rec = new \stdClass();
        $rec->event_id = $eventid;
        $rec->zoom_url = $zoomurl;
        $rec->register_hash = self::register_hash($zoomurl);
        $rec->meeting_id = self::meeting_id_from_url($zoomurl);
        $rec->timecreated = time();
        $rec->timemodified = time();
        return $rec;
    }

    /**
     * Find future calendar events that still link straight to Zoom and
     * point their links at join.php instead. The original Zoom URL is kept
     * in local_zoom_event_join as the source of truth / fallback. If an
     * event's Zoom URL changed (rescheduled meeting), the stored mapping is
     * refreshed before the link is rewritten.
     *
     * @return int number of events rewritten
     */
    public static function rewrite_future_events(): int {
        global $DB, $CFG;

        // Course events are where class meetings live; this also keeps the
        // unindexable description LIKE off site/personal calendar rows.
        $sql = "SELECT id, description
                  FROM {event}
                 WHERE timestart > :now
                   AND courseid > 1
                   AND (" . $DB->sql_like('description', ':reg', false) . "
                        OR " . $DB->sql_like('description', ':join', false) . ")";

        $events = $DB->get_records_sql($sql, [
            'now' => time() - DAYSECS, // include today's earlier events too
            'reg' => '%zoom.us/meeting/register/%',
            'join' => '%zoom.us/j/%'
        ]);

        $count = 0;

        foreach ($events as $event) {

            $rawurl = self::extract_zoom_url($event->description);
            if ($rawurl === null) {
                continue;
            }
            $zoomurl = self::decode_url($rawurl);

            $existing = $DB->get_record('local_zoom_event_join', ['event_id' => $event->id]);

            if (!$existing) {
                self::graceful_insert('local_zoom_event_join',
                    self::build_event_record($event->id, $zoomurl), ['event_id' => $event->id]);
            } else if ($existing->zoom_url !== $zoomurl) {
                // Rescheduled: the description carries a new Zoom URL. Refresh
                // the mapping so we don't keep sending students to the old one.
                $existing->zoom_url = $zoomurl;
                $existing->register_hash = self::register_hash($zoomurl);
                $existing->meeting_id = self::meeting_id_from_url($zoomurl);
                $existing->timemodified = time();
                $DB->update_record('local_zoom_event_join', $existing);
            }

            $joinurl = $CFG->wwwroot . '/local/zoomattendance/join.php?eventid=' . $event->id;
            $newdesc = str_replace($rawurl, $joinurl, $event->description);

            if ($newdesc !== $event->description) {
                $DB->set_field('event', 'description', $newdesc, ['id' => $event->id]);
                // Bump timemodified so calendar caches and the mobile app's
                // delta sync pick up the rewritten link.
                $DB->set_field('event', 'timemodified', time(), ['id' => $event->id]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Housekeeping: drop personal join links well past their TTL so the table
     * doesn't grow without bound. Re-registration is idempotent, so a returning
     * user simply gets a fresh link on their next click.
     *
     * @return int rows deleted
     */
    public static function cleanup(): int {
        global $DB;
        $before = $DB->count_records('local_zoom_join_links');
        $DB->delete_records_select('local_zoom_join_links', 'timecreated < ?', [time() - 7 * DAYSECS]);
        return $before - $DB->count_records('local_zoom_join_links');
    }

    /**
     * Refresh the registration-URL -> meeting-id registry from the Zoom
     * account. Hosts are configured as a comma-separated email list.
     *
     * Rows pre-created by a webhook (with an empty register_hash) are
     * backfilled here rather than skipped.
     *
     * @return int number of meetings added or backfilled
     */
    public static function sync_registry(): int {
        global $DB;

        $hosts = get_config('local_zoomattendance', 'zoom_host_emails');
        if (empty($hosts)) {
            debugging('local_zoomattendance: zoom_host_emails is not configured, registry sync skipped',
                DEBUG_NORMAL);
            return 0;
        }

        $changed = 0;

        foreach (array_filter(array_map('trim', explode(',', $hosts))) as $host) {

            $list = zoom_api::get_paginated('/users/' . rawurlencode($host) . '/meetings?type=upcoming',
                'meetings');

            if ($list['status'] !== 200) {
                debugging("local_zoomattendance: listing meetings for {$host} failed (HTTP {$list['status']})",
                    DEBUG_NORMAL);
                continue;
            }

            foreach ($list['items'] as $m) {

                $meetingid = (string)($m['id'] ?? '');
                if ($meetingid === '') {
                    continue;
                }

                $existing = $DB->get_record('local_zoom_meeting_registry', ['meeting_id' => $meetingid]);

                // Skip if already resolved, or if we fetched detail recently —
                // meetings with no registration URL would otherwise be
                // re-fetched on every run since their hash stays empty.
                if ($existing && ($existing->register_hash !== ''
                        || (int)$existing->timemodified > time() - 6 * 3600)) {
                    continue;
                }

                // Registration URL only comes from the meeting detail call.
                $detail = zoom_api::get('/meetings/' . $meetingid);
                if ($detail['status'] !== 200) {
                    continue;
                }

                $hash = self::register_hash((string)($detail['data']['registration_url'] ?? ''));
                $topic = substr((string)($detail['data']['topic'] ?? ''), 0, 255);
                $start = strtotime($detail['data']['start_time'] ?? '') ?: 0;

                if ($existing) {
                    // Backfill the row a webhook created with an empty hash.
                    $existing->register_hash = $hash;
                    if ($topic !== '') {
                        $existing->topic = $topic;
                    }
                    $existing->host_email = $host;
                    if ($start) {
                        $existing->start_time = $start;
                    }
                    $existing->timemodified = time();
                    $DB->update_record('local_zoom_meeting_registry', $existing);
                    $changed++;
                    continue;
                }

                $rec = new \stdClass();
                $rec->meeting_id = $meetingid;
                $rec->topic = $topic;
                $rec->host_email = $host;
                $rec->register_hash = $hash;
                $rec->start_time = $start;
                $rec->live_status = '';
                $rec->status_time = 0;
                $rec->status_checked_at = 0;
                $rec->timecreated = time();
                $rec->timemodified = time();

                if (self::graceful_insert('local_zoom_meeting_registry', $rec, ['meeting_id' => $meetingid])) {
                    $changed++;
                }
            }
        }

        set_config('lastregistrysync', time(), 'local_zoomattendance');

        return $changed;
    }

    /**
     * Run a registry sync at most once per throttle window, under a
     * non-blocking lock, so a burst of page loads can't each launch one.
     */
    private static function maybe_sync_registry(): void {
        $last = (int)get_config('local_zoomattendance', 'lastregistrysync');
        if ($last > time() - self::SYNC_THROTTLE) {
            return;
        }

        $factory = \core\lock\lock_config::get_lock_factory('local_zoomattendance');
        $lock = $factory->get_lock('registry_sync', 0); // Non-blocking.
        if (!$lock) {
            return; // Another request or the cron task is already syncing.
        }

        try {
            $last = (int)get_config('local_zoomattendance', 'lastregistrysync');
            if ($last <= time() - self::SYNC_THROTTLE) {
                self::sync_registry();
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Get (or lazily create) the event-to-Zoom map record for an event.
     */
    public static function ensure_event_record(int $eventid): \stdClass {
        global $DB;

        $rec = $DB->get_record('local_zoom_event_join', ['event_id' => $eventid]);
        if ($rec) {
            return $rec;
        }

        // Event link was visited before any rewrite ran: map it now.
        $event = $DB->get_record('event', ['id' => $eventid], 'id, description', MUST_EXIST);
        $rawurl = self::extract_zoom_url($event->description);
        if ($rawurl === null) {
            throw new \moodle_exception('nozoomlink', 'local_zoomattendance');
        }

        $rec = self::build_event_record($eventid, self::decode_url($rawurl));
        self::graceful_insert('local_zoom_event_join', $rec, ['event_id' => $eventid]);

        // Re-read so we return the persisted row (with id) even if a
        // concurrent request won the insert race.
        return $DB->get_record('local_zoom_event_join', ['event_id' => $eventid], '*', MUST_EXIST);
    }

    /**
     * Resolve the join target for a user cleared to enter the meeting.
     *
     * @param string $meetingid resolved numeric meeting id
     * @param \stdClass $rec the event-join record (its zoom_url is the fallback)
     * @param \stdClass $user the Moodle user joining
     * @return string URL to redirect to (registrant join_url, or the original
     *                Zoom link — passcode intact — as a graceful fallback)
     */
    public static function get_join_url(string $meetingid, \stdClass $rec, \stdClass $user): string {
        global $DB;

        // Returning user with a fresh cached personal join link: reuse it.
        $cached = $DB->get_record('local_zoom_join_links', [
            'userid' => $user->id, 'meeting_id' => $meetingid
        ]);
        if ($cached && $cached->timecreated > time() - self::JOINLINK_TTL) {
            return $cached->join_url;
        }

        // No link, or a stale one (meeting may have been recreated / registrant
        // purged): try to (re)register. The fallback keeps any ?pwd= passcode.
        $joinurl = self::register_user($meetingid, $user, $rec->zoom_url);

        if ($joinurl === null) {
            // Couldn't get a fresh link. A stale cached link is still likely
            // valid (TTL is only a heuristic) and is better than the generic
            // shared URL, which carries no per-user identity for attendance.
            return $cached ? $cached->join_url : $rec->zoom_url;
        }

        // Got a fresh link: replace any stale cache. Caching is best-effort —
        // a failure here must never break the redirect into the meeting.
        try {
            if ($cached) {
                $DB->delete_records('local_zoom_join_links', ['id' => $cached->id]);
            }
            $link = new \stdClass();
            $link->userid = $user->id;
            $link->meeting_id = $meetingid;
            $link->join_url = $joinurl;
            $link->timecreated = time();
            self::graceful_insert('local_zoom_join_links', $link,
                ['userid' => $user->id, 'meeting_id' => $meetingid]);
        } catch (\Throwable $e) {
            debugging('local_zoomattendance: caching join link failed: ' . $e->getMessage(), DEBUG_NORMAL);
        }

        return $joinurl;
    }

    /**
     * Record a live-status change reported by a Zoom webhook or API check.
     *
     * status_time is kept monotonic: any event older than the one already
     * stored is ignored, so a stale/out-of-order webhook retry (even of the
     * opposite status, e.g. a re-delivered 'ended' arriving after a newer
     * 'started') can never overwrite fresher state. Timestamps are clamped to
     * "now" so a bad clock or a forged event can't park the status in the
     * future and freeze later updates. Webhook (Zoom clock) and API (server
     * clock) timestamps are comparable under normal NTP sync; the per-click
     * API re-check corrects any small skew within a minute.
     */
    public static function update_live_status(string $meetingid, string $status, int $eventts,
            string $topic = '', int $starttime = 0): void {
        global $DB;

        $eventts = min($eventts > 0 ? $eventts : time(), time() + 300);

        $rec = $DB->get_record('local_zoom_meeting_registry', ['meeting_id' => $meetingid]);

        if ($rec) {
            if ($eventts < (int)$rec->status_time) {
                return; // Stale / out-of-order; a newer status is already stored.
            }
            $rec->live_status = $status;
            $rec->status_time = $eventts;
            $rec->timemodified = time();
            $DB->update_record('local_zoom_meeting_registry', $rec);
            return;
        }

        // Webhook beat the registry sync to it: create the row now and let
        // the next sync backfill the registration hash.
        $rec = new \stdClass();
        $rec->meeting_id = $meetingid;
        $rec->topic = substr($topic, 0, 255);
        $rec->host_email = '';
        $rec->register_hash = '';
        $rec->start_time = $starttime;
        $rec->live_status = $status;
        $rec->status_time = $eventts;
        $rec->status_checked_at = 0;
        $rec->timecreated = time();
        $rec->timemodified = time();
        self::graceful_insert('local_zoom_meeting_registry', $rec, ['meeting_id' => $meetingid]);
    }

    /**
     * Has a recent, non-stale webhook recorded this meeting as started?
     */
    private static function recorded_live(?\stdClass $rec): bool {
        return $rec && $rec->live_status === 'started'
            && (int)$rec->status_time > time() - self::STARTED_TTL;
    }

    /**
     * Is the meeting running right now?
     *
     * A webhook-recorded 'started' is trusted for free, but only until it goes
     * stale (a lost meeting.ended must not keep the gate open forever). Any
     * other state is double-checked against the Zoom API at most once per
     * minute, and a per-meeting non-blocking lock ensures a stampede of clicks
     * costs a single API request — everyone else trusts the stored state.
     */
    public static function is_meeting_live(string $meetingid): bool {
        global $DB;

        $rec = $DB->get_record('local_zoom_meeting_registry', ['meeting_id' => $meetingid]);

        if (self::recorded_live($rec)) {
            return true;
        }
        if ($rec && (int)$rec->status_checked_at > time() - 60) {
            return false; // Verified not-running within the last minute.
        }

        $factory = \core\lock\lock_config::get_lock_factory('local_zoomattendance');
        $lock = $factory->get_lock('livecheck_' . $meetingid, 0); // Non-blocking.
        if (!$lock) {
            // Another request is checking right now; trust the latest state.
            return self::recorded_live($DB->get_record('local_zoom_meeting_registry',
                ['meeting_id' => $meetingid]));
        }

        try {
            // Re-check inside the lock: the winner may have just updated it.
            $rec = $DB->get_record('local_zoom_meeting_registry', ['meeting_id' => $meetingid]);
            if (self::recorded_live($rec)) {
                return true;
            }
            if ($rec && (int)$rec->status_checked_at > time() - 60) {
                return false;
            }

            $res = zoom_api::get('/meetings/' . $meetingid);

            if ($res['status'] !== 200) {
                // Fail closed (the webhook covers genuinely live meetings), but
                // stamp the throttle so repeated polls of an unfetchable meeting
                // (e.g. a /j/ link with no registry row) don't re-hit the API
                // every cycle.
                if (!$rec) {
                    self::update_live_status($meetingid, 'waiting', time());
                }
                $DB->set_field('local_zoom_meeting_registry', 'status_checked_at', time(),
                    ['meeting_id' => $meetingid]);
                return false;
            }

            $live = ($res['data']['status'] ?? '') === 'started';

            self::update_live_status($meetingid, $live ? 'started' : 'waiting', time(),
                (string)($res['data']['topic'] ?? ''),
                strtotime($res['data']['start_time'] ?? '') ?: 0);
            $DB->set_field('local_zoom_meeting_registry', 'status_checked_at', time(),
                ['meeting_id' => $meetingid]);

            return $live;
        } finally {
            $lock->release();
        }
    }

    /**
     * Resolve the numeric meeting id for an event-join record, syncing the
     * registry (throttled) on a cache miss, and persist the answer.
     */
    public static function resolve_meeting_id(\stdClass $rec): ?string {
        global $DB;

        if (!empty($rec->meeting_id)) {
            return $rec->meeting_id;
        }

        if (empty($rec->register_hash)) {
            return null;
        }

        $registry = $DB->get_record('local_zoom_meeting_registry', ['register_hash' => $rec->register_hash]);

        if (!$registry) {
            self::maybe_sync_registry();
            $registry = $DB->get_record('local_zoom_meeting_registry', ['register_hash' => $rec->register_hash]);
        }

        if (!$registry) {
            debugging('local_zoomattendance: could not resolve meeting id for register_hash '
                . $rec->register_hash . ' (host not in zoom_host_emails?)', DEBUG_NORMAL);
            return null;
        }

        $rec->meeting_id = $registry->meeting_id;
        $rec->timemodified = time();
        $DB->update_record('local_zoom_event_join', $rec);

        return $rec->meeting_id;
    }

    /**
     * Register the user for the meeting and return their personal join_url.
     *
     * @param string $fallbackurl original event URL (kept for passcode) used
     *                            when the meeting has no registration enabled
     * @return string|null join_url, or null to signal "use the fallback"
     */
    private static function register_user(string $meetingid, \stdClass $user, string $fallbackurl): ?string {

        $res = zoom_api::post("/meetings/{$meetingid}/registrants", [
            'email' => $user->email,
            'first_name' => $user->firstname,
            'last_name' => $user->lastname,
            'auto_approve' => true
        ]);

        if ($res['status'] === 201 && !empty($res['data']['join_url'])) {
            return $res['data']['join_url'];
        }

        // Registration not enabled on this meeting (code 300): null tells
        // get_join_url() to fall back to the original event URL, which still
        // carries any ?pwd= passcode. A pre-existing manual registrant, if
        // any, is preferred.
        return self::find_existing_registrant($meetingid, $user->email);
    }

    /**
     * Look up an already-registered user's join_url (e.g. registered via the
     * old manual form before this feature shipped).
     */
    private static function find_existing_registrant(string $meetingid, string $email): ?string {

        $email = strtolower(trim($email));

        $res = zoom_api::get_paginated("/meetings/{$meetingid}/registrants?status=approved", 'registrants');
        if ($res['status'] !== 200) {
            return null;
        }

        foreach ($res['items'] as $r) {
            if (strtolower(trim($r['email'] ?? '')) === $email && !empty($r['join_url'])) {
                return $r['join_url'];
            }
        }

        return null;
    }

    /**
     * Link a freshly-created Zoom meeting to a calendar event.
     *
     * Populates the same three tables that sync_registry() + rewrite_future_events()
     * would eventually fill in from the Zoom side — but immediately, and with the
     * numeric meeting id known up front, so the fragile topic/date guess-matching
     * (EventHelper::map_zoom_to_event) is bypassed entirely. Finally rewrites the
     * registration URL in the event description to our join endpoint, exactly as
     * the cron sweep does for manually-created events.
     */
    public static function link_event_to_meeting(int $eventid, string $registrationurl,
            string $meetingid, string $hostemail, string $topic, int $starttime): void {
        global $DB, $CFG;

        $now = time();
        $hash = self::register_hash($registrationurl);

        // event -> Zoom URL (source of truth / fallback for join.php).
        $join = new \stdClass();
        $join->event_id = $eventid;
        $join->zoom_url = $registrationurl;
        $join->register_hash = $hash;
        $join->meeting_id = $meetingid;
        $join->managed = 1; // Created by the scheduler: eligible for update/delete sync.
        $join->timecreated = $now;
        $join->timemodified = $now;
        self::graceful_insert('local_zoom_event_join', $join, ['event_id' => $eventid]);

        // Meeting registry (register_hash + meeting id -> host / start time).
        if (!$DB->record_exists('local_zoom_meeting_registry', ['meeting_id' => $meetingid])) {
            $reg = new \stdClass();
            $reg->meeting_id = $meetingid;
            $reg->topic = substr($topic, 0, 255);
            $reg->host_email = $hostemail;
            $reg->register_hash = $hash;
            $reg->start_time = $starttime;
            $reg->live_status = '';
            $reg->status_time = 0;
            $reg->status_checked_at = 0;
            $reg->timecreated = $now;
            $reg->timemodified = $now;
            self::graceful_insert('local_zoom_meeting_registry', $reg, ['meeting_id' => $meetingid]);
        }

        // Zoom meeting -> event map (used by the live attendance list).
        if (!$DB->record_exists('local_zoom_event_map', ['meeting_id' => $meetingid])) {
            $map = new \stdClass();
            $map->meeting_id = $meetingid;
            $map->event_id = $eventid;
            $map->timecreated = $now;
            $map->skipped = 0;
            self::graceful_insert('local_zoom_event_map', $map, ['meeting_id' => $meetingid]);
        }

        // Point the description's Zoom link at our join endpoint.
        $event = $DB->get_record('event', ['id' => $eventid], 'id, description');
        if ($event) {
            $joinurl = $CFG->wwwroot . '/local/zoomattendance/join.php?eventid=' . $eventid;
            $newdesc = str_replace($registrationurl, $joinurl, $event->description);
            if ($newdesc !== $event->description) {
                $DB->set_field('event', 'description', $newdesc, ['id' => $eventid]);
                $DB->set_field('event', 'timemodified', $now, ['id' => $eventid]);
            }
        }
    }

    /**
     * Insert a record, tolerating a concurrent insert that won a unique-key
     * race. Returns true if this call created the row.
     */
    private static function graceful_insert(string $table, \stdClass $record, array $uniquekey): bool {
        global $DB;
        try {
            $DB->insert_record($table, $record);
            return true;
        } catch (\dml_exception $e) {
            // Lost the race: the row now exists, which is all we need.
            if ($DB->record_exists($table, $uniquekey)) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Pull the first Zoom registration/join URL out of an event description,
     * exactly as it appears (HTML-encoded) so it can be str_replace()d back.
     * Use decode_url() to get the real URL for storage/redirects.
     */
    public static function extract_zoom_url(?string $description): ?string {
        if (empty($description)) {
            return null;
        }
        if (preg_match('#https://[a-z0-9.]*zoom\.us/(?:meeting/register|j)/[^\s"\'<>]+#i', $description, $m)) {
            return $m[0];
        }
        return null;
    }

    /**
     * Decode HTML entities (e.g. &amp;) in a URL pulled from a description.
     */
    public static function decode_url(string $rawurl): string {
        return html_entity_decode($rawurl, ENT_QUOTES | ENT_HTML5);
    }

    /**
     * The opaque hash at the end of a registration URL, or '' if not one.
     */
    public static function register_hash(string $url): string {
        if (preg_match('#zoom\.us/meeting/register/([^\s"\'<>&/?\#]+)#i', $url, $m)) {
            return $m[1];
        }
        return '';
    }

    /**
     * The numeric meeting id from a plain join URL, or null.
     */
    public static function meeting_id_from_url(string $url): ?string {
        if (preg_match('#zoom\.us/j/(\d+)#i', $url, $m)) {
            return $m[1];
        }
        return null;
    }
}
