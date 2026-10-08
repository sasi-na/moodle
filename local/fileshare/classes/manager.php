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
 * Minting, validating and revoking share links.
 *
 * @package   local_fileshare
 * @copyright 2026 onlinejain.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_fileshare;

use stored_file;
use moodle_url;
use context_module;

defined('MOODLE_INTERNAL') || die();

/**
 * The whole security model lives here.
 *
 * A share token is minted under this plugin's own script name, never `core_files`, so it is
 * inert at tokenpluginfile.php and vice versa. It is bound to one row of {local_fileshare},
 * and that row names exactly one file. Serving calls validate_user_key() and NEVER
 * require_user_key_login(): the difference is that the latter calls set_user() and hands the
 * request the token owner's entire capability scope, which is precisely what makes the
 * tokenpluginfile route unsafe to hand out. Here there is no session to escape from.
 */
class manager {

    /** @var string Our own user-key script name. Must never be 'core_files'. */
    const SCRIPT = 'local_fileshare';

    /** @var int Hard ceiling on any expiry, whatever the admin setting says. */
    const MAX_EXPIRY_DAYS = 90;

    /**
     * Default lifetime, in days, for a new share.
     *
     * @return int
     */
    public static function default_expiry_days(): int {
        $days = (int) get_config('local_fileshare', 'defaultexpirydays');
        return $days > 0 ? $days : 7;
    }

    /**
     * Longest lifetime an author may choose, in days.
     *
     * @return int
     */
    public static function max_expiry_days(): int {
        $days = (int) get_config('local_fileshare', 'maxexpirydays');
        if ($days <= 0) {
            $days = 30;
        }
        return min($days, self::MAX_EXPIRY_DAYS);
    }

    /**
     * Is sharing switched on at all?
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return (bool) get_config('local_fileshare', 'enabled');
    }

    /**
     * The attempt a question usage belongs to.
     *
     * @param int $qubaid question usage id
     * @return \mod_quiz\quiz_attempt
     */
    protected static function attempt(int $qubaid): \mod_quiz\quiz_attempt {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        return \mod_quiz\quiz_attempt::create_from_usage_id($qubaid);
    }

    /**
     * May the current user share attachments on this attempt?
     *
     * Three gates, all required.
     *
     * check_review_capability() is NOT one of them on its own: despite the name it only asks
     * whether the user holds some reviewing capability somewhere in this quiz, and never looks
     * at whose attempt this is. is_review_allowed() is the gate core pairs it with in review.php,
     * reviewquestion.php and - decisively - quiz_attempt::check_file_access(), the function that
     * decides whether this very file may be fetched through pluginfile.php. It requires
     * mod/quiz:viewreports and, under SEPARATEGROUPS, a group in common with the student.
     * Without it a teacher confined to one group could share the submissions of another.
     *
     * It is required even for your own attempt: publishing a file outside the LMS is a staff
     * action, not something the submitter does to their own work.
     *
     * @param int $qubaid
     * @return bool
     */
    public static function can_share(int $qubaid): bool {
        if (!self::is_enabled()) {
            return false;
        }
        // The theme calls this once per attachment while rendering a review page, and loading an
        // attempt is not cheap, so answer once per usage per request.
        static $cache = [];
        if (array_key_exists($qubaid, $cache)) {
            return $cache[$qubaid];
        }
        $cache[$qubaid] = false;
        try {
            $attemptobj = self::attempt($qubaid);
            if (!has_capability('local/fileshare:share', context_module::instance($attemptobj->get_cmid()))) {
                return false;
            }
            $attemptobj->check_review_capability();
            if (!$attemptobj->is_review_allowed()) {
                return false;
            }
            return $cache[$qubaid] = true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Fetch the attachment named by a usage/slot/pathnamehash triple.
     *
     * Resolving the file through the attempt rather than trusting the hash is what stops a
     * caller sharing an arbitrary file by pasting somebody else's pathnamehash.
     *
     * @param int $qubaid
     * @param int $slot
     * @param string $pathnamehash
     * @return stored_file|null
     */
    public static function find_attachment(int $qubaid, int $slot, string $pathnamehash): ?stored_file {
        try {
            $attemptobj = self::attempt($qubaid);
            $qa = $attemptobj->get_question_attempt($slot);
            $context = context_module::instance($attemptobj->get_cmid());
            foreach ($qa->get_last_qt_files('attachments', $context->id) as $file) {
                if ($file->get_pathnamehash() === $pathnamehash) {
                    return $file;
                }
            }
        } catch (\Throwable $e) {
            return null;
        }
        return null;
    }

    /**
     * Create a share link.
     *
     * @param int $qubaid
     * @param int $slot
     * @param string $pathnamehash
     * @param array $params expirydays, maxdownloads, iprestriction, recipient
     * @return array [stdClass share, string url]
     */
    public static function create(int $qubaid, int $slot, string $pathnamehash, array $params): array {
        global $DB, $USER, $CFG;

        if (!self::is_enabled()) {
            throw new \moodle_exception('sharingdisabled', 'local_fileshare');
        }
        $attemptobj = self::attempt($qubaid);
        $context = context_module::instance($attemptobj->get_cmid());
        require_capability('local/fileshare:share', $context);
        $attemptobj->check_review_capability();
        // The real per-attempt gate; see can_share() for why the line above is not enough.
        if (!$attemptobj->is_review_allowed()) {
            throw new \required_capability_exception($context, 'mod/quiz:viewreports', 'nopermissions', '');
        }

        $file = self::find_attachment($qubaid, $slot, $pathnamehash);
        if (!$file) {
            throw new \moodle_exception('filenotfound', 'local_fileshare');
        }

        $days = (int) ($params['expirydays'] ?? self::default_expiry_days());
        $days = max(1, min($days, self::max_expiry_days()));
        $ip = trim((string) ($params['iprestriction'] ?? ''));

        $share = (object) [
            'pathnamehash' => $file->get_pathnamehash(),
            'contenthash' => $file->get_contenthash(),
            'contextid' => $context->id,
            'qubaid' => $qubaid,
            'slot' => $slot,
            'filename' => $file->get_filename(),
            'filesize' => (int) $file->get_filesize(),
            'studentid' => (int) $attemptobj->get_userid(),
            'createdby' => (int) $USER->id,
            'keyid' => 0,
            'validuntil' => time() + ($days * DAYSECS),
            'iprestriction' => $ip === '' ? null : $ip,
            'maxdownloads' => max(0, (int) ($params['maxdownloads'] ?? 0)),
            'downloads' => 0,
            'lastaccess' => 0,
            'revoked' => 0,
            'recipient' => trim((string) ($params['recipient'] ?? '')) ?: null,
            'timecreated' => time(),
        ];

        // The key needs the share id as its instance, and the share needs the key id to be
        // revocable, so the row is written first and completed once the key exists.
        $share->id = $DB->insert_record('local_fileshare', $share);

        // create_user_key(), never get_user_key(): the latter returns an existing matching key,
        // which is how one immortal token ends up shared across every use.
        $token = create_user_key(self::SCRIPT, $USER->id, $share->id, $share->iprestriction, $share->validuntil);
        $keyid = $DB->get_field('user_private_key', 'id', ['value' => $token], MUST_EXIST);
        $DB->set_field('local_fileshare', 'keyid', $keyid, ['id' => $share->id]);
        $share->keyid = $keyid;

        event\share_created::create([
            'context' => $context,
            'objectid' => $share->id,
            'relateduserid' => $share->studentid,
            'other' => ['filename' => $share->filename, 'validuntil' => $share->validuntil],
        ])->trigger();

        // The token is returned here and never stored: this URL cannot be reconstructed later.
        $url = new moodle_url($CFG->wwwroot . '/local/fileshare/get.php', ['id' => $share->id, 'k' => $token]);

        return [$share, $url->out(false)];
    }

    /**
     * Validate an incoming link and return the share row plus its file.
     *
     * Throws on anything wrong; callers must treat every failure identically so a probe cannot
     * tell an expired link from a wrong token from one that never existed.
     *
     * @param int $id share id
     * @param string $token
     * @return array [stdClass share, stored_file file]
     */
    public static function resolve(int $id, string $token): array {
        global $DB;

        if (!self::is_enabled()) {
            throw new \moodle_exception('sharingdisabled', 'local_fileshare');
        }
        // Checks script + value + instance, then expiry, then IP range. Does NOT log anyone in.
        $key = validate_user_key($token, self::SCRIPT, $id);

        $share = $DB->get_record('local_fileshare', ['id' => $id, 'keyid' => $key->id], '*', MUST_EXIST);
        if (!empty($share->revoked)) {
            throw new \moodle_exception('sharerevoked', 'local_fileshare');
        }
        if ($share->maxdownloads > 0 && $share->downloads >= $share->maxdownloads) {
            throw new \moodle_exception('sharespent', 'local_fileshare');
        }

        $file = get_file_storage()->get_file_by_hash($share->pathnamehash);
        if (!$file || $file->is_directory()) {
            throw new \moodle_exception('filenotfound', 'local_fileshare');
        }
        // pathnamehash is derived from the path, not the bytes, so a replaced file would keep
        // the same hash. Refuse rather than serve something the author never saw.
        if ($file->get_contenthash() !== $share->contenthash) {
            throw new \moodle_exception('filechanged', 'local_fileshare');
        }

        return [$share, $file];
    }

    /**
     * Record an access. Called before the bytes are sent, because send_stored_file() exits.
     *
     * @param \stdClass $share
     * @return void
     */
    public static function record_access(\stdClass $share): void {
        global $DB;

        $DB->execute('UPDATE {local_fileshare} SET downloads = downloads + 1, lastaccess = ? WHERE id = ?',
            [time(), $share->id]);

        try {
            event\share_accessed::create([
                'context' => \context::instance_by_id($share->contextid),
                'objectid' => $share->id,
                'relateduserid' => $share->studentid,
                'other' => ['filename' => $share->filename],
            ])->trigger();
        } catch (\Throwable $e) {
            // An audit failure must not stop the download; the counter above is the durable record.
            debugging('local_fileshare access event: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Revoke a share. Deleting the key is what actually kills the link.
     *
     * @param int $id
     * @return void
     */
    public static function revoke(int $id): void {
        global $DB;

        $share = $DB->get_record('local_fileshare', ['id' => $id], '*', MUST_EXIST);
        if (!self::can_manage($share)) {
            throw new \required_capability_exception(\context_system::instance(),
                'local/fileshare:manageall', 'nopermissions', '');
        }
        if (!empty($share->keyid)) {
            $DB->delete_records('user_private_key', ['id' => $share->keyid, 'script' => self::SCRIPT]);
        }
        $DB->update_record('local_fileshare', (object) ['id' => $id, 'revoked' => 1, 'keyid' => 0]);
    }

    /**
     * May the current user see and revoke this share?
     *
     * @param \stdClass $share
     * @return bool
     */
    public static function can_manage(\stdClass $share): bool {
        global $USER;
        if ((int) $share->createdby === (int) $USER->id) {
            return true;
        }
        return has_capability('local/fileshare:manageall', \context_system::instance());
    }

    /**
     * Human-readable state for a share row.
     *
     * @param \stdClass $share
     * @return string one of live, expiring, expired, revoked, spent
     */
    public static function state(\stdClass $share): string {
        if (!empty($share->revoked)) {
            return 'revoked';
        }
        if ($share->validuntil <= time()) {
            return 'expired';
        }
        if ($share->maxdownloads > 0 && $share->downloads >= $share->maxdownloads) {
            return 'spent';
        }
        if ($share->validuntil - time() < 2 * DAYSECS) {
            return 'expiring';
        }
        return 'live';
    }
}
