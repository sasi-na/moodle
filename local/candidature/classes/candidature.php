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
 * The candidate approval state machine.
 *
 * @package   local_candidature
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_candidature;

defined('MOODLE_INTERNAL') || die();

/**
 * A candidate's progress from completed profile to a decision.
 *
 * Three states and no more. A candidate who has completed the mandatory profile is
 * `pending` and can sign in but reach nothing except the holding page; `approved`
 * releases them to the site; `rejected` keeps them out.
 *
 * Rejection additionally sets the "Block Access" profile field, which the core patch
 * in login/index.php reads to refuse the sign-in outright. That field is deliberately
 * NOT used for `pending`: it blocks login before authentication, so a pending
 * candidate carrying it could never sign in to complete the very profile that puts
 * them in the queue.
 */
class candidature {

    /** Plugin name for get_config. */
    const PLUGIN = 'local_candidature';

    /** Awaiting a decision. */
    const PENDING = 'pending';

    /** Released to the site. */
    const APPROVED = 'approved';

    /** Refused. */
    const REJECTED = 'rejected';

    /** Profile field the login gate reads. */
    const BLOCK_FIELD = 'blockaccess';

    /** Per-account message the login gate shows in preference to the field description. */
    const BLOCK_MESSAGE_FIELD = 'blockaccessmessages';

    /**
     * @return bool Whether the queue is switched on.
     */
    public static function enabled(): bool {
        return (bool) get_config(self::PLUGIN, 'enabled');
    }

    /**
     * Hours a candidate may sit undecided before oversight is told.
     *
     * @return int
     */
    public static function sla_hours(): int {
        $h = (int) get_config(self::PLUGIN, 'slahours');
        return $h > 0 ? $h : 48;
    }

    /**
     * @return bool Whether rejecting also blocks sign-in.
     */
    public static function reject_blocks_login(): bool {
        return (bool) get_config(self::PLUGIN, 'rejectblockslogin');
    }

    /**
     * The candidature row for a user, if any.
     *
     * @param int $userid
     * @return \stdClass|null
     */
    public static function for_user(int $userid): ?\stdClass {
        global $DB;
        $r = $DB->get_record('local_candidature', ['userid' => $userid]);
        return $r ?: null;
    }

    /**
     * Has this user finished the mandatory profile?
     *
     * Delegates to core rather than keeping a second list of required fields:
     * user_not_fully_set_up() already tests the required custom profile fields via
     * profile_has_required_custom_fields_set(). Marking a field required in the
     * profile field settings is therefore the whole configuration.
     *
     * @param \stdClass $user
     * @return bool
     */
    public static function profile_complete(\stdClass $user): bool {
        return !user_not_fully_set_up($user, true);
    }

    /**
     * Put a user into the queue. Idempotent.
     *
     * @param \stdClass $user
     * @return \stdClass|null The row, or null when the queue is off or they already have one.
     */
    public static function submit(\stdClass $user): ?\stdClass {
        global $DB;

        if (self::for_user((int) $user->id)) {
            return null;
        }

        $now = time();
        $rec = (object) [
            'userid'        => (int) $user->id,
            'status'        => self::PENDING,
            'categoryid'    => self::resolve_category((int) $user->id),
            'submittedtime' => $now,
            'decisiondue'   => $now + (self::sla_hours() * HOURSECS),
            'timecreated'   => $now,
            'timemodified'  => $now,
        ];
        $rec->id = $DB->insert_record('local_candidature', $rec);

        self::log((int) $rec->id, 'submitted', null, (int) $user->id);
        notify::submitted($rec, $user);
        return $rec;
    }

    /**
     * Approve, releasing the candidate to the site.
     *
     * @param int $id
     * @param int $actorid
     * @param string $note
     */
    public static function approve(int $id, int $actorid, string $note = ''): void {
        self::decide($id, self::APPROVED, $actorid, $note);
    }

    /**
     * Reject, and by default also block sign-in.
     *
     * @param int $id
     * @param int $actorid
     * @param string $reason
     */
    public static function reject(int $id, int $actorid, string $reason = ''): void {
        self::decide($id, self::REJECTED, $actorid, $reason);
    }

    /**
     * Record a decision and act on it.
     *
     * @param int $id
     * @param string $status
     * @param int $actorid
     * @param string $reason
     */
    protected static function decide(int $id, string $status, int $actorid, string $reason): void {
        global $DB;

        $rec = $DB->get_record('local_candidature', ['id' => $id], '*', MUST_EXIST);
        $now = time();
        $DB->update_record('local_candidature', (object) [
            'id'           => $id,
            'status'       => $status,
            'decidedby'    => $actorid,
            'decidedtime'  => $now,
            'reason'       => $reason,
            'timemodified' => $now,
        ]);
        $rec = $DB->get_record('local_candidature', ['id' => $id]);

        // Rejection is the only state the login gate is used for; approving must
        // always clear it, including when an earlier rejection is overturned.
        self::set_login_block($rec, $status === self::REJECTED && self::reject_blocks_login());

        self::log($id, $status, $reason !== '' ? $reason : null, $actorid);
        notify::decided($rec);
    }

    /**
     * Reopen a decided candidature, putting it back in the queue.
     *
     * @param int $id
     * @param int $actorid
     */
    public static function reopen(int $id, int $actorid): void {
        global $DB;

        $rec = $DB->get_record('local_candidature', ['id' => $id], '*', MUST_EXIST);
        $now = time();
        $DB->update_record('local_candidature', (object) [
            'id'           => $id,
            'status'       => self::PENDING,
            'decidedby'    => null,
            'decidedtime'  => null,
            'reason'       => null,
            'flaggedtime'  => null,
            'decisiondue'  => $now + (self::sla_hours() * HOURSECS),
            'timemodified' => $now,
        ]);
        self::set_login_block($rec, false);
        self::log($id, 'reopened', null, $actorid);
    }

    /**
     * Set or clear the Block Access profile field that login/index.php reads.
     *
     * Handled carefully, because this field is not ours. On jain it carries a live
     * meaning -- document verification -- with thousands of accounts blocked by it,
     * and the message a blocked user sees is the FIELD's description, one global
     * string, not anything specific to this candidate. Two rules follow:
     *
     *   - never overwrite a block somebody else set;
     *   - never clear one unless this plugin is the thing that set it, recorded on
     *     the candidature row.
     *
     * @param \stdClass $rec The candidature row.
     * @param bool $blocked
     */
    protected static function set_login_block(\stdClass $rec, bool $blocked): void {
        global $DB;

        $fieldid = $DB->get_field('user_info_field', 'id', ['shortname' => self::BLOCK_FIELD]);
        if (!$fieldid) {
            return;
        }
        $userid = (int) $rec->userid;
        $existing = $DB->get_record('user_info_data', ['userid' => $userid, 'fieldid' => $fieldid]);
        $currently = $existing ? strtolower(trim((string) $existing->data)) === 'yes' : false;

        if ($blocked) {
            if ($currently) {
                // Already blocked for some other reason. Leave it, and do not claim it.
                return;
            }
            if ($existing) {
                $DB->set_field('user_info_data', 'data', 'YES', ['id' => $existing->id]);
            } else {
                $DB->insert_record('user_info_data', (object) [
                    'userid' => $userid, 'fieldid' => $fieldid,
                    'data' => 'YES', 'dataformat' => 0,
                ]);
            }
            self::set_block_message($userid, trim((string) $rec->reason));
            $DB->set_field('local_candidature', 'loginblocked', 1, ['id' => $rec->id]);
            return;
        }

        // Clearing. Only ours to clear.
        if (empty($rec->loginblocked)) {
            return;
        }
        if ($existing) {
            $DB->set_field('user_info_data', 'data', '', ['id' => $existing->id]);
        }
        self::set_block_message($userid, '');
        $DB->set_field('local_candidature', 'loginblocked', 0, ['id' => $rec->id]);
    }

    /**
     * Write the account's own block message, which the login gate prefers over the
     * field description. Only ever called alongside a block this plugin owns, so it
     * cannot overwrite a message set for some other reason.
     *
     * @param int $userid
     * @param string $message Empty clears it.
     */
    protected static function set_block_message(int $userid, string $message): void {
        global $DB;

        $fieldid = $DB->get_field('user_info_field', 'id',
            ['shortname' => self::BLOCK_MESSAGE_FIELD]);
        if (!$fieldid) {
            return;
        }
        $existing = $DB->get_record('user_info_data', ['userid' => $userid, 'fieldid' => $fieldid]);
        if ($existing) {
            $DB->set_field('user_info_data', 'data', $message, ['id' => $existing->id]);
        } else if ($message !== '') {
            $DB->insert_record('user_info_data', (object) [
                'userid' => $userid, 'fieldid' => $fieldid,
                'data' => $message, 'dataformat' => FORMAT_PLAIN,
            ]);
        }
    }

    /**
     * Which programme category a candidate belongs to, if it can be worked out.
     *
     * Read from the AMS profile fields. Returns null when they are absent or do not
     * match a category, in which case only a site-wide reviewer sees the candidate --
     * better than guessing a programme and hiding them from the right person.
     *
     * @param int $userid
     * @return int|null
     */
    public static function resolve_category(int $userid): ?int {
        global $DB;

        $prog = self::profile_value($userid, (string) get_config(self::PLUGIN, 'programfield'));
        $batch = self::profile_value($userid, (string) get_config(self::PLUGIN, 'batchfield'));
        if ($prog === '' || $batch === '') {
            return null;
        }

        // The batch is a depth-2 category and the programme sits directly beneath it.
        $batchcat = $DB->get_record_select('course_categories',
            'depth = 2 AND ' . $DB->sql_like('name', ':n', false), ['n' => '%' . $DB->sql_like_escape($batch) . '%'],
            'id, path, depth', IGNORE_MULTIPLE);
        if (!$batchcat) {
            return null;
        }
        $child = $DB->get_record_select('course_categories',
            'depth = :d AND ' . $DB->sql_like('path', ':p', false) . ' AND '
                . $DB->sql_like('name', ':n', false),
            ['d' => $batchcat->depth + 1, 'p' => $batchcat->path . '/%',
             'n' => '%' . $DB->sql_like_escape($prog) . '%'],
            'id', IGNORE_MULTIPLE);

        return $child ? (int) $child->id : null;
    }

    /**
     * A custom profile field value by shortname.
     *
     * @param int $userid
     * @param string $shortname
     * @return string Empty when unset or unconfigured.
     */
    protected static function profile_value(int $userid, string $shortname): string {
        global $DB;

        if ($shortname === '') {
            return '';
        }
        $fieldid = $DB->get_field('user_info_field', 'id', ['shortname' => $shortname]);
        if (!$fieldid) {
            return '';
        }
        $v = $DB->get_field('user_info_data', 'data', ['userid' => $userid, 'fieldid' => $fieldid]);
        return $v === false ? '' : trim((string) $v);
    }

    /**
     * Categories whose queue this viewer may work, or true for all.
     *
     * @param int|null $userid
     * @return array|bool Category ids, or true when the viewer sees everything.
     */
    public static function reviewable_categories(?int $userid = null) {
        global $DB, $USER;

        $userid = $userid ?: (int) $USER->id;
        if (has_capability('local/candidature:reviewall', \context_system::instance(), $userid)) {
            return true;
        }

        $out = [];
        foreach ($DB->get_fieldset_sql(
            "SELECT DISTINCT ctx.instanceid
               FROM {role_assignments} ra
               JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :lvl
              WHERE ra.userid = :userid", ['lvl' => CONTEXT_COURSECAT, 'userid' => $userid]) as $catid) {
            $ctx = \context_coursecat::instance((int) $catid, IGNORE_MISSING);
            if ($ctx && has_capability('local/candidature:review', $ctx, $userid)) {
                // A reviewer on a batch also reviews the programmes beneath it.
                $out[(int) $catid] = true;
                $cat = $DB->get_record('course_categories', ['id' => $catid], 'path');
                if ($cat) {
                    foreach ($DB->get_fieldset_select('course_categories', 'id',
                            $DB->sql_like('path', ':p', false),
                            ['p' => $cat->path . '/%']) as $childid) {
                        $out[(int) $childid] = true;
                    }
                }
            }
        }
        return array_keys($out);
    }

    /**
     * May this viewer decide this particular candidate?
     *
     * A candidature with no resolved programme can only be decided site-wide: there
     * is no category to check a Program Manager against, and letting any reviewer
     * claim it would cross programme boundaries.
     *
     * @param \stdClass $rec
     * @param int|null $userid
     * @return bool
     */
    public static function can_decide(\stdClass $rec, ?int $userid = null): bool {
        global $USER;

        $userid = $userid ?: (int) $USER->id;
        if (has_capability('local/candidature:reviewall', \context_system::instance(), $userid)) {
            return true;
        }
        if (empty($rec->categoryid)) {
            return false;
        }
        $ctx = \context_coursecat::instance((int) $rec->categoryid, IGNORE_MISSING);
        return $ctx && has_capability('local/candidature:review', $ctx, $userid);
    }

    /**
     * Append to the audit trail.
     *
     * @param int $candidatureid
     * @param string $action
     * @param string|null $detail
     * @param int|null $actorid
     */
    public static function log(int $candidatureid, string $action, ?string $detail = null,
                              ?int $actorid = null): void {
        global $DB;

        $DB->insert_record('local_candidature_event', (object) [
            'candidatureid' => $candidatureid,
            'actorid'       => $actorid,
            'action'        => $action,
            'detail'        => $detail,
            'timecreated'   => time(),
        ]);
    }
}
