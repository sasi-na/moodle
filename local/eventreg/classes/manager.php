<?php
namespace local_eventreg;

defined('MOODLE_INTERNAL') || die();

/**
 * Audience resolution, batch enumeration and open-form lookup for event
 * registration forms.
 *
 * "Batch" is the `cohort_batch` custom user-profile field, not Moodle's built-in
 * cohorts — the same convention used by local_dashboardpoll and the theme_baz
 * dashboard banners. Batch labels routinely contain commas (e.g. "Jan, 2026 -
 * TP"), so a form's audience is stored as comma-joined base64 tokens of those
 * values (see token_encode/token_decode), matching the banner settings.
 *
 * @package   local_eventreg
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {

    /** Profile field shortname used as the batch. */
    const BATCH_FIELD = 'cohort_batch';

    /** Custom profile field shortname holding the learner's mobile number (AMS id 16). */
    const MOBILE_FIELD = 'mobile';

    /** Audience modes. */
    const AUDIENCE_ALL = 0;
    const AUDIENCE_ONLY = 1;
    const AUDIENCE_EXCEPT = 2;

    /** @return \cache */
    protected static function cache(): \cache {
        return \cache::make('local_eventreg', 'forms');
    }

    /**
     * Cluster-wide cache revision. The `forms` MUC cache is APPLICATION mode, so
     * a purge on one web node does not clear the copy held by other nodes. Cached
     * entries carry this revision and are rebuilt when it changes; bumping it goes
     * through set_config(), which propagates to every node.
     */
    protected static function rev(): int {
        return (int) get_config('local_eventreg', 'cacherev');
    }

    // ---- batch helpers ------------------------------------------------------

    /** The user's `cohort_batch` profile value ('' if unset). Memoized. */
    public static function user_batch(int $userid): string {
        static $cache = [];
        if (array_key_exists($userid, $cache)) {
            return $cache[$userid];
        }
        $val = \local_commonutils\user_helper::get_single_profile_field($userid, self::BATCH_FIELD);
        return $cache[$userid] = (string) ($val ?? '');
    }

    /**
     * Distinct non-empty `cohort_batch` values, keyed by base64 token.
     *
     * base64 keys because the values contain commas and the multiselect stores
     * its selection comma-joined (identical rationale to
     * theme_baz_get_cohort_batch_choices()).
     *
     * @return array<string,string> token => human-readable batch label.
     */
    public static function batch_choices(): array {
        global $DB;
        $sql = "SELECT DISTINCT uid.data
                  FROM {user_info_data} uid
                  JOIN {user_info_field} uif ON uif.id = uid.fieldid
                 WHERE uif.shortname = :sn
                   AND uid.data <> ''
              ORDER BY uid.data ASC";
        $choices = [];
        foreach ($DB->get_fieldset_sql($sql, ['sn' => self::BATCH_FIELD]) as $v) {
            $choices[base64_encode($v)] = $v;
        }
        return $choices;
    }

    /**
     * Encode selected batch labels into the stored comma-joined token string.
     *
     * @param string[] $tokens Already-base64 tokens (as returned by a multiselect).
     */
    public static function token_encode(array $tokens): string {
        $clean = array_filter(array_map('trim', $tokens), fn($t) => $t !== '');
        return implode(',', $clean);
    }

    /**
     * Decode a stored token string back to cohort_batch values.
     *
     * @param string $stored Comma-joined base64 tokens.
     * @return string[] Decoded batch labels.
     */
    public static function token_decode(?string $stored): array {
        $values = [];
        foreach (explode(',', (string) $stored) as $token) {
            $token = trim($token);
            if ($token === '') {
                continue;
            }
            $decoded = base64_decode($token, true);
            if ($decoded !== false && $decoded !== '') {
                $values[] = $decoded;
            }
        }
        return $values;
    }

    /** The stored tokens as an array (for prefilling a multiselect). */
    public static function token_list(?string $stored): array {
        $tokens = [];
        foreach (explode(',', (string) $stored) as $token) {
            $token = trim($token);
            if ($token !== '') {
                $tokens[] = $token;
            }
        }
        return $tokens;
    }

    // ---- eligibility --------------------------------------------------------

    /**
     * Whether a user's batch matches a form's audience rule.
     *
     * @param \stdClass $form Form record (needs audiencemode, audiencetokens).
     * @param int $userid
     * @return bool
     */
    public static function user_in_audience(\stdClass $form, int $userid): bool {
        $mode = (int) $form->audiencemode;
        if ($mode === self::AUDIENCE_ALL) {
            return true;
        }
        $batches = self::token_decode($form->audiencetokens);
        $inlist = in_array(self::user_batch($userid), $batches, true);
        return $mode === self::AUDIENCE_ONLY ? $inlist : !$inlist;
    }

    /**
     * Whether a user may open and submit a form right now: it must be open and
     * the user must fall in its audience.
     */
    public static function user_eligible(\stdClass $form, int $userid): bool {
        return $form->status === 'open' && self::user_in_audience($form, $userid);
    }

    /**
     * Open forms this user is eligible for, newest first.
     *
     * @return \stdClass[] Form records.
     */
    public static function open_forms_for_user(int $userid): array {
        global $DB;
        $forms = $DB->get_records('local_eventreg_forms', ['status' => 'open'], 'timemodified DESC');
        $out = [];
        foreach ($forms as $form) {
            if (self::user_in_audience($form, $userid)) {
                $out[] = $form;
            }
        }
        return $out;
    }

    // ---- report helpers -----------------------------------------------------

    /** Number of learners whose cohort_batch falls in the form's audience. */
    public static function audience_size(\stdClass $form): int {
        global $DB;
        $mode = (int) $form->audiencemode;
        if ($mode === self::AUDIENCE_ALL) {
            $sql = "SELECT COUNT(DISTINCT uid.userid)
                      FROM {user_info_data} uid
                      JOIN {user_info_field} uif ON uif.id = uid.fieldid
                     WHERE uif.shortname = :sn AND uid.data <> ''";
            return (int) $DB->count_records_sql($sql, ['sn' => self::BATCH_FIELD]);
        }
        $batches = self::token_decode($form->audiencetokens);
        if (empty($batches)) {
            return 0;
        }
        list($insql, $params) = $DB->get_in_or_equal($batches, SQL_PARAMS_NAMED);
        $params['sn'] = self::BATCH_FIELD;
        $op = $mode === self::AUDIENCE_ONLY ? "uid.data $insql" : "uid.data NOT $insql AND uid.data <> ''";
        $sql = "SELECT COUNT(DISTINCT uid.userid)
                  FROM {user_info_data} uid
                  JOIN {user_info_field} uif ON uif.id = uid.fieldid
                 WHERE uif.shortname = :sn AND $op";
        return (int) $DB->count_records_sql($sql, $params);
    }

    /** Human-readable audience label for the manage list / report header. */
    public static function audience_label(\stdClass $form): string {
        $mode = (int) $form->audiencemode;
        if ($mode === self::AUDIENCE_ALL) {
            return get_string('audiencemode_all', 'local_eventreg');
        }
        $batches = self::token_decode($form->audiencetokens);
        $label = $mode === self::AUDIENCE_ONLY
            ? get_string('audiencemode_only', 'local_eventreg')
            : get_string('audiencemode_except', 'local_eventreg');
        return $label . ': ' . ($batches ? implode(', ', array_map('format_string', $batches)) : '-');
    }

    /**
     * How many files learners have attached to a form, and their total size.
     * Drives the "download all attachments" button on the report.
     *
     * @param int $formid
     * @return \stdClass numfiles, totalbytes
     */
    public static function attachment_stats(int $formid): \stdClass {
        global $DB;
        // Directory records (filename '.') are not real attachments. The
        // component/filearea pair is what makes files.itemid an answer id.
        $sql = "SELECT COUNT(f.id) AS numfiles, COALESCE(SUM(f.filesize), 0) AS totalbytes
                  FROM {files} f
                  JOIN {local_eventreg_answers} a ON a.id = f.itemid
                  JOIN {local_eventreg_submissions} s ON s.id = a.submissionid
                 WHERE f.component = :component
                   AND f.filearea = :filearea
                   AND f.filename <> '.'
                   AND s.formid = :formid";
        $rec = $DB->get_record_sql($sql, [
            'component' => form_builder::FILE_COMPONENT,
            'filearea'  => form_builder::FILE_AREA,
            'formid'    => $formid,
        ]);
        return (object) [
            'numfiles'   => (int) ($rec->numfiles ?? 0),
            'totalbytes' => (int) ($rec->totalbytes ?? 0),
        ];
    }

    /** Whether a form has any submissions (locks the field builder). */
    public static function has_submissions(int $formid): bool {
        global $DB;
        return $DB->record_exists('local_eventreg_submissions', ['formid' => $formid]);
    }

    // ---- cache invalidation -------------------------------------------------

    /**
     * Full purge after an admin mutation. Bumps the cluster-wide revision so
     * every web node rebuilds, then clears the local copy.
     */
    public static function purge_all(): void {
        set_config('cacherev', self::rev() + 1, 'local_eventreg');
        self::cache()->purge();
    }
}
