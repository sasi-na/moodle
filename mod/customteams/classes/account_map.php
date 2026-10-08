<?php
namespace mod_customteams;

defined('MOODLE_INTERNAL') || die();

/**
 * Maps a course to the shared Teams accounts that may host its meetings.
 *
 * The course category tree already encodes what we need:
 *
 *     Batch ("July 2025")  ->  Programme ("MBA")  ->  Semester ("Semester 1")  ->  course
 *
 * which is the same hierarchy local_ams and local_exams query for grades
 * (see local/ams/classes/external.php, the cat_batch/cat_prog/cat_sem join).
 * So the organizer can be derived from the course itself -- no per-course
 * configuration, no tagging, nothing for a coordinator to keep in sync.
 */
class account_map {

    /**
     * Canonical batch label, e.g. "July 2025".
     *
     * The master sheet carries eleven spellings for six real batches --
     * "July-24", "Jan 25", "July 23", and three cells that lost their date
     * formatting and arrive as raw Excel serials (45839, 45474, 45658).
     * Course categories use "Month YYYY", so everything is folded to that.
     *
     * Returns the input trimmed but otherwise untouched when it matches no
     * known shape: the caller decides whether that is an error, and the CLI
     * importer reports it rather than silently writing a row that can never
     * match a category.
     */
    public static function normalise_batch($raw) {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return '';
        }

        // Excel serial date (days since the 1900 epoch, with Excel's leap-year bug,
        // hence the 1899-12-30 base). Bounded to a sane range so that a batch
        // genuinely named "2024" is not reinterpreted as a date.
        if (ctype_digit($raw) && (int) $raw >= 40000 && (int) $raw <= 60000) {
            $date = (new \DateTime('1899-12-30'))->add(new \DateInterval('P' . (int) $raw . 'D'));
            $month = (int) $date->format('n') < 7 ? 'Jan' : 'July';
            return $month . ' ' . $date->format('Y');
        }

        if (preg_match('/^(jan(uary)?|jul(y)?)[\s\-\/]*(\d{2}|\d{4})$/i', $raw, $m)) {
            $month = strtolower(substr($m[1], 0, 3)) === 'jan' ? 'Jan' : 'July';
            $year  = $m[4];
            if (strlen($year) === 2) {
                $year = '20' . $year;
            }
            return $month . ' ' . $year;
        }

        return $raw;
    }

    /**
     * Work out the batch and programme for a course by walking its category path.
     *
     * Deliberately not "the course's category, then its parent": open electives
     * and a few legacy courses do not sit under a Semester category, so a fixed
     * depth silently resolves the wrong names. Instead the path is walked from
     * the root and each name is tested against the batches and programmes that
     * actually exist in the mapping table -- if the tree shifts by a level, this
     * still lands on the right pair.
     *
     * @param int $courseid
     * @return array [batch|null, program|null]
     */
    public static function resolve_course($courseid) {
        global $DB;

        $categoryid = $DB->get_field('course', 'category', ['id' => $courseid]);
        if (empty($categoryid)) {
            return [null, null];
        }

        $path = $DB->get_field('course_categories', 'path', ['id' => $categoryid]);
        if (empty($path)) {
            return [null, null];
        }

        $ids = array_filter(explode('/', $path), 'strlen');
        if (empty($ids)) {
            return [null, null];
        }

        list($insql, $params) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $categories = $DB->get_records_select('course_categories', "id $insql", $params, '', 'id, name');

        // Preserve root-to-leaf order; get_records_select does not guarantee it.
        $names = [];
        foreach ($ids as $id) {
            if (isset($categories[$id])) {
                $names[] = trim($categories[$id]->name);
            }
        }

        $knownbatches  = self::known_values('batch');
        $knownprograms = self::known_values('program');

        $batch = null;
        $program = null;

        foreach ($names as $name) {
            $key = \core_text::strtolower($name);

            if ($batch === null && isset($knownbatches[$key])) {
                $batch = $knownbatches[$key];
                continue;
            }

            // Only look for the programme below the batch, so a programme-named
            // category higher up the tree cannot win.
            if ($batch !== null && $program === null && isset($knownprograms[$key])) {
                $program = $knownprograms[$key];
            }
        }

        return [$batch, $program];
    }

    /**
     * Distinct active batch or programme values, keyed by lowercase name for
     * case-insensitive matching against category names.
     *
     * @param string $field 'batch' or 'program'
     * @return array lowercase name => stored name
     */
    private static function known_values($field) {
        global $DB;

        if ($field !== 'batch' && $field !== 'program') {
            throw new \coding_exception('Unsupported field: ' . $field);
        }

        $cache = \cache::make_from_params(\cache_store::MODE_APPLICATION, 'mod_customteams', 'accountmap');
        $cached = $cache->get($field);
        if ($cached !== false) {
            return $cached;
        }

        $values = $DB->get_fieldset_sql(
            "SELECT DISTINCT $field FROM {customteams_accounts} WHERE active = 1"
        );

        $map = [];
        foreach ($values as $value) {
            $map[\core_text::strtolower(trim($value))] = trim($value);
        }

        $cache->set($field, $map);

        return $map;
    }

    /**
     * Drop the cached batch/programme lists. Called by the CLI importer.
     */
    public static function purge_cache() {
        $cache = \cache::make_from_params(\cache_store::MODE_APPLICATION, 'mod_customteams', 'accountmap');
        $cache->purge();
    }

    /**
     * Candidate organizer accounts for a course, best match first.
     *
     * Falls back in three steps so that an unmapped course degrades to a usable
     * form rather than a dead end:
     *   1. exact batch + programme
     *   2. same programme, any batch
     *   3. the fallback pool from plugin settings
     *
     * @param int $courseid
     * @return array upn => label
     */
    public static function candidates($courseid) {
        global $DB;

        list($batch, $program) = self::resolve_course($courseid);

        $records = [];

        if ($batch !== null && $program !== null) {
            $records = $DB->get_records('customteams_accounts',
                ['batch' => $batch, 'program' => $program, 'active' => 1],
                'sortorder ASC, upn ASC');
        }

        if (empty($records) && $program !== null) {
            $records = $DB->get_records('customteams_accounts',
                ['program' => $program, 'active' => 1],
                'sortorder ASC, upn ASC');
        }

        $options = [];
        foreach ($records as $record) {
            $options[$record->upn] = self::label($record);
        }

        if (empty($options)) {
            foreach (self::fallback_pool() as $upn) {
                $options[$upn] = $upn;
            }
        }

        return $options;
    }

    /**
     * Human label for a picker entry. The sheet has no friendly names, so the
     * UPN is the honest default; displayname is used once it is populated.
     */
    private static function label($record) {
        if (!empty($record->displayname)) {
            return $record->displayname . ' (' . $record->upn . ')';
        }

        return $record->upn;
    }

    /**
     * UPNs configured as the last-resort pool.
     *
     * @return array
     */
    public static function fallback_pool() {
        $raw = (string) get_config('customteams', 'fallbackaccounts');

        $upns = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_map('trim', $upns)));
    }

    /**
     * Every active account, with the batch/programme pairs it serves.
     *
     * One mailbox commonly serves several pairs -- 21 of the 120 rows in the
     * master sheet are a repeat of an account already listed -- so the check
     * page reports per account, not per row, and names where each one is used.
     *
     * @return array upn => array of "batch / programme" strings
     */
    public static function usage() {
        global $DB;

        $records = $DB->get_records('customteams_accounts', ['active' => 1],
            'upn ASC, batch ASC, program ASC');

        $usage = [];

        foreach ($records as $record) {
            $usage[$record->upn][] = $record->batch . ' / ' . $record->program;
        }

        return $usage;
    }

    /**
     * The map as JSON, nested batch -> programme -> accounts.
     *
     * An account with no display name and no explicit ordering is written as a
     * bare string, so the common case stays readable:
     *
     *   {
     *     "July 2025": {
     *       "MBA": ["mbageneral.04@jgi.ac.in", "mbageneral.05@jgi.ac.in"]
     *     }
     *   }
     *
     * @return string
     */
    public static function export_json() {
        global $DB;

        $records = $DB->get_records('customteams_accounts', ['active' => 1],
            'batch ASC, program ASC, sortorder ASC, upn ASC');

        $out = [];

        foreach ($records as $record) {
            if (empty($record->displayname) && (int) $record->sortorder === 0) {
                $entry = $record->upn;
            } else {
                $entry = ['upn' => $record->upn];
                if (!empty($record->displayname)) {
                    $entry['displayname'] = $record->displayname;
                }
                if ((int) $record->sortorder !== 0) {
                    $entry['sortorder'] = (int) $record->sortorder;
                }
            }

            $out[$record->batch][$record->program][] = $entry;
        }

        if (empty($out)) {
            return '{}';
        }

        return json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Parse the JSON form of the map into rows ready for sync().
     *
     * Batch names are normalised, so an admin typing "July-24" gets the same
     * row the CLI importer would produce from the master sheet. Unlike the
     * importer this does not reject a batch that fails to look like
     * "Month YYYY": the value only has to match a course category name, and a
     * future tenant may not use Jain's naming. The saved value is re-rendered
     * from the database, so a normalised or mistyped name is visible in the
     * textarea immediately after saving.
     *
     * @param string $json
     * @return array [rows, errors]
     */
    public static function parse_json($json) {
        $json = trim((string) $json);

        if ($json === '') {
            return [[], [get_string('erroremptyjson', 'customteams')]];
        }

        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [[], [get_string('errorbadjson', 'customteams', json_last_error_msg())]];
        }

        if (!is_array($decoded)) {
            return [[], [get_string('errorjsonshape', 'customteams')]];
        }

        $rows = [];
        $errors = [];

        foreach ($decoded as $rawbatch => $programs) {
            $batch = self::normalise_batch($rawbatch);

            if ($batch === '') {
                $errors[] = get_string('errorjsonnobatch', 'customteams');
                continue;
            }

            if (!is_array($programs)) {
                $errors[] = get_string('errorjsonshapeat', 'customteams', s($batch));
                continue;
            }

            foreach ($programs as $program => $entries) {
                $program = trim((string) $program);

                if ($program === '') {
                    $errors[] = get_string('errorjsonnoprogram', 'customteams', s($batch));
                    continue;
                }

                // A single account may be written without the surrounding array.
                if (is_string($entries)) {
                    $entries = [$entries];
                }

                if (!is_array($entries)) {
                    $errors[] = get_string('errorjsonshapeat', 'customteams', s("$batch / $program"));
                    continue;
                }

                foreach ($entries as $entry) {
                    $upn = '';
                    $displayname = null;
                    $sortorder = 0;

                    if (is_string($entry)) {
                        $upn = $entry;
                    } else if (is_array($entry)) {
                        $upn = (string) ($entry['upn'] ?? '');
                        $displayname = isset($entry['displayname']) ? trim((string) $entry['displayname']) : null;
                        $sortorder = isset($entry['sortorder']) ? (int) $entry['sortorder'] : 0;
                    } else {
                        $errors[] = get_string('errorjsonshapeat', 'customteams', s("$batch / $program"));
                        continue;
                    }

                    $upn = \core_text::strtolower(trim($upn));

                    if ($upn === '' || !validate_email($upn)) {
                        $errors[] = get_string('errorjsonbadupn', 'customteams', (object) [
                            'upn'     => s($upn),
                            'batch'   => s($batch),
                            'program' => s($program),
                        ]);
                        continue;
                    }

                    // Same account listed twice under one pair: harmless in the
                    // source, a duplicate option in the picker. Collapse.
                    $key = $batch . '|' . $program . '|' . $upn;

                    $rows[$key] = (object) [
                        'batch'       => $batch,
                        'program'     => $program,
                        'upn'         => $upn,
                        'displayname' => ($displayname !== null && $displayname !== '') ? $displayname : null,
                        'sortorder'   => $sortorder,
                    ];
                }
            }
        }

        return [array_values($rows), $errors];
    }

    /**
     * Write a parsed set of rows to the account map.
     *
     * Rows absent from the input are deactivated rather than deleted: a meeting
     * booked last term still names its organizer, and dropping the row would
     * lose the only record of which mailbox owns that Graph event.
     *
     * @param array $rows objects with batch, program, upn, displayname, sortorder
     * @param bool $deactivatemissing
     * @return array counts keyed created, updated, deactivated
     */
    public static function sync(array $rows, $deactivatemissing = true) {
        global $DB;

        $now = time();
        $seen = [];
        $created = 0;
        $updated = 0;
        $deactivated = 0;

        $transaction = $DB->start_delegated_transaction();

        foreach ($rows as $row) {
            $existing = $DB->get_record('customteams_accounts', [
                'batch'   => $row->batch,
                'program' => $row->program,
                'upn'     => $row->upn,
            ]);

            if ($existing) {
                $existing->displayname  = $row->displayname ?? null;
                $existing->sortorder    = $row->sortorder ?? 0;
                $existing->active       = 1;
                $existing->timemodified = $now;
                $DB->update_record('customteams_accounts', $existing);
                $seen[] = $existing->id;
                $updated++;
            } else {
                $new = (object) [
                    'batch'        => $row->batch,
                    'program'      => $row->program,
                    'upn'          => $row->upn,
                    'displayname'  => $row->displayname ?? null,
                    'sortorder'    => $row->sortorder ?? 0,
                    'active'       => 1,
                    'timecreated'  => $now,
                    'timemodified' => $now,
                ];
                $seen[] = $DB->insert_record('customteams_accounts', $new);
                $created++;
            }
        }

        if ($deactivatemissing) {
            if (empty($seen)) {
                $deactivated = $DB->count_records('customteams_accounts', ['active' => 1]);
                $DB->set_field('customteams_accounts', 'active', 0, ['active' => 1]);
            } else {
                list($notinsql, $params) = $DB->get_in_or_equal($seen, SQL_PARAMS_NAMED, 'id', false);
                $deactivated = $DB->count_records_select('customteams_accounts',
                    "id $notinsql AND active = 1", $params);
                $DB->set_field_select('customteams_accounts', 'active', 0, "id $notinsql", $params);
            }
        }

        $transaction->allow_commit();

        self::purge_cache();

        return ['created' => $created, 'updated' => $updated, 'deactivated' => $deactivated];
    }

    /**
     * Meetings already booked on an account that overlap the given window.
     *
     * Shared accounts are the whole point of the master sheet -- one mailbox
     * serves several batch/programme pairs, and 21 of the 120 rows are the same
     * account listed twice. So double-booking is the realistic failure, and it
     * is caught here rather than at the Graph call, where the meeting would be
     * created regardless.
     *
     * This reads Moodle's own table, not Graph: every meeting is created through
     * this module, so the local record is authoritative and costs one indexed
     * query instead of a network round trip on every form render.
     *
     * @param string $upn
     * @param int $start
     * @param int $end
     * @param int $excludeinstance customteams.id to ignore (the one being edited)
     * @return array of meeting records
     */
    public static function clashes($upn, $start, $end, $excludeinstance = 0) {
        global $DB;

        if (empty($upn) || empty($start) || empty($end)) {
            return [];
        }

        // Half-open overlap: touching meetings (one ends exactly as the next
        // starts) are not a clash.
        $sql = "SELECT ct.id, ct.name, ct.timestart, ct.timeend, ct.course
                  FROM {customteams} ct
                 WHERE ct.organizer = :upn
                   AND ct.timestart < :end
                   AND ct.timeend > :start
                   AND ct.id <> :exclude";

        return $DB->get_records_sql($sql, [
            'upn'     => $upn,
            'start'   => $start,
            'end'     => $end,
            'exclude' => $excludeinstance,
        ]);
    }
}
