<?php
namespace local_studentid;

defined('MOODLE_INTERNAL') || die();

class manager {

    const STATUS_DRAFT       = 'draft';
    const STATUS_SUBMITTED   = 'submitted';
    const STATUS_UNDER_REVIEW = 'under_review';
    const STATUS_APPROVED    = 'approved';
    const STATUS_REJECTED    = 'rejected';
    const STATUS_REPLACED    = 'replaced';

    const LOG_CREATE       = 'create';
    const LOG_SUBMIT       = 'submit';
    const LOG_APPROVE      = 'approve';
    const LOG_REJECT       = 'reject';
    const LOG_UPDATE       = 'update';
    const LOG_REPLACE      = 'replace';
    const LOG_UNDER_REVIEW = 'under_review';
    const LOG_GENERATE     = 'generate';
    const LOG_CLARIFY      = 'clarify';

    public static function get_student_data(int $userid): array {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/user/profile/lib.php');

        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        profile_load_data($user);

        $country = '';
        if (!empty($user->country)) {
            $countries = get_string_manager()->get_list_of_countries();
            $country = $countries[$user->country] ?? $user->country;
        }

        // Contact number — prefer the core "Mobile phone" field (phone2), which
        // is where this site holds the number; fall back to the "mobile" custom
        // field then "Phone" (phone1). Strip a leading apostrophe, a common
        // CSV/Excel "store as text" import artefact (e.g. '+919740698164).
        $contact = ltrim(self::first_nonempty(
            $user->phone2 ?? '',
            $user->profile_field_mobile ?? '',
            $user->phone1 ?? ''
        ), "'");

        return [
            'userid'      => $user->id,
            'fullname'    => fullname($user),
            'email'       => $user->email,
            // Program name. Site profile fields vary in shortname (newer
            // "program_registered_for" vs legacy "programregister"/"mainprogram");
            // take the first that is actually filled in. Note ?? does not skip
            // empty strings, which profile_load_data sets for blank fields.
            'program'     => self::first_nonempty(
                                $user->profile_field_program_registered_for ?? '',
                                $user->profile_field_programregister ?? '',
                                $user->profile_field_mainprogram ?? ''
                             ),
            // Batch — prefer the "Cohort/Batch" field (cohort_batch), same field
            // the e-ID eligibility check reads; fall back to legacy "batch".
            'batch'       => self::first_nonempty(
                                $user->profile_field_cohort_batch ?? '',
                                $user->profile_field_batch ?? ''
                             ),
            'usn'         => $user->profile_field_usn ?? '',
            'dob'         => self::resolve_dob($user),
            'contact'     => $contact,
            'city'        => $user->city ?? '',
            'state'       => $user->profile_field_state ?? '',
            'country'     => $country,
        ];
    }

    /**
     * Return the first non-empty (after trimming) value from the arguments,
     * or '' if all are empty. Used to tolerate differing profile field
     * shortnames across the site's student-information fields.
     *
     * @param string ...$values
     * @return string
     */
    private static function first_nonempty(string ...$values): string {
        foreach ($values as $value) {
            $value = trim($value);
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }

    /**
     * Resolve the student's date of birth as a DD/MM/YYYY string.
     *
     * Prefer the text "Date of Birth (DD/MM/YYYY)" field (dateofbirth), which
     * holds real birth dates already formatted. Fall back to the datetime "dob"
     * field, which stores a Unix timestamp, formatted to the same DD/MM/YYYY.
     *
     * @param object $user A user record after profile_load_data().
     * @return string
     */
    private static function resolve_dob(object $user): string {
        $text = trim((string) ($user->profile_field_dateofbirth ?? ''));
        if ($text !== '') {
            return $text;
        }
        $ts = $user->profile_field_dob ?? '';
        if (!empty($ts) && is_numeric($ts)) {
            return userdate((int) $ts, '%d/%m/%Y');
        }
        return '';
    }

    /**
     * Batches eligible for the e-ID Card when the admin setting is empty.
     * Also the shipped default of local_studentid/eligiblebatches.
     */
    const DEFAULT_ELIGIBLE_BATCHES = 'July 2025, Jan 2025';

    /**
     * The batches allowed to use the e-ID Card, as configured in
     * local_studentid/eligiblebatches (comma-separated, e.g. "July 2025, Jan 2025").
     *
     * @return string[] Raw batch specs, empty entries removed.
     */
    public static function get_eligible_batches(): array {
        $raw = (string) get_config('local_studentid', 'eligiblebatches');
        if (trim($raw) === '') {
            $raw = self::DEFAULT_ELIGIBLE_BATCHES;
        }
        return array_values(array_filter(array_map('trim', explode(',', $raw)), 'strlen'));
    }

    /**
     * Whether the user belongs to one of the eligible batches, based on their
     * "Cohort/Batch" custom profile field (shortname: cohort_batch).
     *
     * Both the stored value and each configured batch are normalised
     * (lower-cased, all non-alphanumeric characters stripped), then matched on
     * their month prefix (first three letters) and four-digit year. So a
     * configured "July 2025" accepts every spelling/punctuation/order variant —
     * "July 2025", "jul 2025", "(July,2025)", "JULY-2025", "2025 July" — while
     * still rejecting "Jan 2025" and "July 2024". Fails closed on an empty
     * profile value.
     *
     * @param int $userid
     * @return bool
     */
    public static function is_eligible_batch(int $userid): bool {
        global $DB, $CFG;

        // The navigation hooks call this on every page load for every logged-in
        // student, and the profile_load_data() below is comparatively expensive,
        // so memoize the result per request keyed by userid.
        static $cache = [];
        if (array_key_exists($userid, $cache)) {
            return $cache[$userid];
        }

        require_once($CFG->dirroot . '/user/profile/lib.php');

        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        profile_load_data($user);

        $batch = self::normalise_batch($user->profile_field_cohort_batch ?? '');
        if ($batch === '') {
            return $cache[$userid] = false;
        }

        foreach (self::get_eligible_batches() as $spec) {
            if (self::batch_matches($batch, $spec)) {
                return $cache[$userid] = true;
            }
        }
        return $cache[$userid] = false;
    }

    /** Lower-case a batch value and strip everything that is not a letter or digit. */
    private static function normalise_batch(string $value): string {
        return preg_replace('/[^a-z0-9]/', '', strtolower(trim($value)));
    }

    /**
     * Whether an already-normalised profile batch value satisfies one configured
     * batch spec.
     *
     * The spec's word set and year are compared against the profile value's,
     * words on their first three letters, so spelling, punctuation and order
     * still do not matter — "July 2025", "jul 2025", "(July,2025)", "2025 July"
     * all match a stored "July, 2025". The comparison is exact rather than a
     * substring one, so "Jan 2026" matches only the plain "Jan, 2026" batch and
     * not its "Jan, 2026 - GL" / "- TP" siblings; those need their own spec
     * ("Jan 2026 GL"). A spec carrying only a year matches any batch of that
     * year, and a spec with neither word nor year never matches.
     *
     * @param string $normalised Normalised profile value, e.g. "july2025".
     * @param string $spec       Raw configured batch, e.g. "Jan 2025".
     * @return bool
     */
    private static function batch_matches(string $normalised, string $spec): bool {
        $n = self::normalise_batch($spec);
        $specwords = self::batch_words($n);
        $specyear = preg_match('/\d{4}/', $n, $y) ? $y[0] : '';

        if (!$specwords && $specyear === '') {
            return false;
        }
        if ($specyear !== '' && strpos($normalised, $specyear) === false) {
            return false;
        }
        if ($specwords && $specwords !== self::batch_words($normalised)) {
            return false;
        }
        return true;
    }

    /**
     * The letter runs of a normalised batch value, each cut to three characters
     * and sorted, e.g. "jan2026gl" gives ["gl", "jan"].
     *
     * @param string $normalised
     * @return string[]
     */
    private static function batch_words(string $normalised): array {
        preg_match_all('/[a-z]+/', $normalised, $matches);
        $words = array_map(static fn($w) => substr($w, 0, 3), $matches[0]);
        sort($words);
        return $words;
    }

    /**
     * Whether the user is a Student ID Verifier — i.e. explicitly assigned the
     * dedicated "studentidverifier" role at system level.
     *
     * This is intentionally stricter than has_capability('local/studentid:verify'):
     * the verify capability is also held by managers (via archetype) and site
     * admins, but the Verifier Dashboard link/experience is reserved for users
     * who actually hold the created verifier role.
     *
     * @param int $userid
     * @return bool
     */
    public static function is_verifier(int $userid): bool {
        global $DB;

        // Called up to three times per request across the navigation hooks
        // (extend_navigation, render_navbar_output, after_require_login), so
        // memoize per request keyed by userid.
        static $cache = [];
        if (array_key_exists($userid, $cache)) {
            return $cache[$userid];
        }

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'studentidverifier']);
        if (!$roleid) {
            return $cache[$userid] = false;
        }
        return $cache[$userid] = user_has_role_assignment($userid, $roleid, \context_system::instance()->id);
    }

    public static function get_active_request(int $userid): ?object {
        global $DB;
        $sql = "SELECT * FROM {local_studentid_requests}
                WHERE userid = :userid AND status != :replaced
                ORDER BY timecreated DESC LIMIT 1";
        return $DB->get_record_sql($sql, ['userid' => $userid, 'replaced' => self::STATUS_REPLACED]) ?: null;
    }

    public static function get_request(int $requestid): ?object {
        global $DB;
        return $DB->get_record('local_studentid_requests', ['id' => $requestid]) ?: null;
    }

    public static function get_user_requests(int $userid): array {
        global $DB;
        return array_values($DB->get_records('local_studentid_requests', ['userid' => $userid], 'timecreated DESC'));
    }

    public static function save_request(int $userid, object $formdata): int {
        global $DB;

        $request = self::get_active_request($userid);
        $now = time();

        // An already-submitted / under-review / approved request must not be
        // duplicated. The form's GET guard redirects in this case, but a re-POST
        // (browser Back to a filled form, a duplicate tab, or a double submit)
        // reaches here directly — without this check it would fall through to the
        // create branch below and silently make a second request.
        if ($request && in_array($request->status,
                [self::STATUS_SUBMITTED, self::STATUS_UNDER_REVIEW, self::STATUS_APPROVED], true)) {
            throw new \moodle_exception('erroractiveexists', 'local_studentid');
        }

        if ($request && in_array($request->status, [self::STATUS_DRAFT, self::STATUS_REJECTED])) {
            if ($request->status === self::STATUS_REJECTED) {
                $DB->set_field('local_studentid_requests', 'status', self::STATUS_REPLACED, ['id' => $request->id]);
                // The superseded request's uploads are no longer needed — drop
                // them so stale photo/Aadhaar/ABC PII does not accumulate.
                self::delete_request_files($request->id);
                self::log_action($userid, self::LOG_REPLACE, json_encode(['old_requestid' => $request->id]));
                $request = null;
            }
        }

        $studentdata = self::get_student_data($userid);
        $usn = $studentdata['usn'] ?: '';

        if ($request && $request->status === self::STATUS_DRAFT) {
            $request->blood_group   = $formdata->blood_group;
            $request->city          = $formdata->city ?? $studentdata['city'];
            $request->state         = $formdata->state ?? $studentdata['state'];
            $request->country       = $formdata->country ?? $studentdata['country'];
            $request->timemodified  = $now;
            $DB->update_record('local_studentid_requests', $request);
            self::log_action($userid, self::LOG_UPDATE, json_encode(['requestid' => $request->id]));
            $requestid = $request->id;
        } else {
            $record = new \stdClass();
            $record->userid       = $userid;
            $record->usn          = $usn;
            $record->status       = self::STATUS_DRAFT;
            $record->blood_group  = $formdata->blood_group;
            $record->city         = $formdata->city ?? $studentdata['city'];
            $record->state        = $formdata->state ?? $studentdata['state'];
            $record->country      = $formdata->country ?? $studentdata['country'];
            $record->timecreated  = $now;
            $record->timemodified = $now;
            $requestid = $DB->insert_record('local_studentid_requests', $record);
            self::log_action($userid, self::LOG_CREATE, json_encode(['requestid' => $requestid]));
        }

        return $requestid;
    }

    /**
     * Save a native $_FILES upload into Moodle file storage.
     * Returns the stored filename, or '' if nothing was uploaded.
     */
    public static function save_file_from_upload(int $requestid, string $filearea, array $uploadedfile, int $userid): string {
        global $DB, $USER;

        if (empty($uploadedfile['tmp_name']) || $uploadedfile['error'] !== UPLOAD_ERR_OK) {
            return '';
        }

        $imgmimes  = ['image/jpeg', 'image/jpg', 'image/pjpeg', 'image/png', 'image/x-png'];
        // All document uploads are images only (JPG/PNG); PDF is no longer
        // accepted for new uploads (including ABC ID). Existing PDF uploads
        // remain stored and viewable.
        $allowed = [
            'photo'         => $imgmimes,
            'aadhaar_front' => $imgmimes,
            'aadhaar_back'  => $imgmimes,
            'abc_id'        => $imgmimes,
            'tenth_marks'   => $imgmimes,
            'olevel'        => $imgmimes,
        ];
        $mime = mime_content_type($uploadedfile['tmp_name']);
        if (isset($allowed[$filearea]) && !in_array($mime, $allowed[$filearea])) {
            throw new \moodle_exception('errorinvalidfile', 'local_studentid');
        }

        $maxbytes = ($filearea === 'photo') ? 2097152 : 5242880;
        if ($uploadedfile['size'] > $maxbytes) {
            throw new \moodle_exception('errorfilesize', 'local_studentid');
        }

        $syscontext = \context_system::instance();
        $fs = get_file_storage();

        // Delete existing file in this area/item.
        $fs->delete_area_files($syscontext->id, 'local_studentid', $filearea, $requestid);

        $filename = \clean_filename($uploadedfile['name']);
        $filerecord = [
            'contextid'    => $syscontext->id,
            'component'    => 'local_studentid',
            'filearea'     => $filearea,
            'itemid'       => $requestid,
            'filepath'     => '/',
            'filename'     => $filename,
            'userid'       => $userid,
            'timecreated'  => time(),
            'timemodified' => time(),
        ];
        $fs->create_file_from_pathname($filerecord, $uploadedfile['tmp_name']);

        // Update the path column in the DB.
        $colmap = ['photo' => 'photo_path', 'aadhaar_front' => 'aadhaar_front_path', 'aadhaar_back' => 'aadhaar_back_path', 'abc_id' => 'abc_id_path', 'tenth_marks' => 'tenth_marks_path', 'olevel' => 'olevel_path'];
        if (isset($colmap[$filearea])) {
            $DB->set_field('local_studentid_requests', $colmap[$filearea], $filename, ['id' => $requestid]);
        }

        return $filename;
    }

    public static function submit_request(int $requestid, int $userid): void {
        global $DB;

        $request = $DB->get_record('local_studentid_requests', ['id' => $requestid, 'userid' => $userid], '*', MUST_EXIST);

        if (!in_array($request->status, [self::STATUS_DRAFT])) {
            throw new \moodle_exception('errorinvalidstatus', 'local_studentid');
        }

        if (empty($request->photo_path)) {
            throw new \moodle_exception('errornophoto', 'local_studentid');
        }

        if (empty($request->aadhaar_front_path) || empty($request->aadhaar_back_path)) {
            throw new \moodle_exception('errornodocument', 'local_studentid');
        }

        // Country, state and city are required for every student. This is the
        // authoritative gate: the request form also validates them, but enforcing
        // it here means a draft can never be submitted/approved without a location
        // (e.g. one created by an older form version during a rollout).
        if (empty(trim((string) $request->country))
                || empty(trim((string) $request->state))
                || empty(trim((string) $request->city))) {
            throw new \moodle_exception('errornolocation', 'local_studentid');
        }

        // Academic documents differ by country: Indian students supply the ABC ID
        // and Class 10 Marks Sheet; everyone else supplies the O Level Certificate.
        $isindia = strcasecmp($request->country, 'India') === 0;
        if ($isindia) {
            if (empty($request->abc_id_path)) {
                throw new \moodle_exception('errornoabcid', 'local_studentid');
            }
            if (empty($request->tenth_marks_path)) {
                throw new \moodle_exception('errornotenthmarks', 'local_studentid');
            }
        } else {
            if (empty($request->olevel_path)) {
                throw new \moodle_exception('errornoolevel', 'local_studentid');
            }
        }

        $now = time();
        $DB->set_field('local_studentid_requests', 'status', self::STATUS_SUBMITTED, ['id' => $requestid]);
        $DB->set_field('local_studentid_requests', 'timesubmitted', $now, ['id' => $requestid]);
        $DB->set_field('local_studentid_requests', 'timemodified', $now, ['id' => $requestid]);

        self::log_action($userid, self::LOG_SUBMIT, json_encode(['requestid' => $requestid]));
        self::send_notification($userid, 'submitted', ['requestid' => $requestid]);
    }

    public static function approve_request(int $requestid, string $comment, int $verifierid): void {
        global $DB;

        $request = $DB->get_record('local_studentid_requests', ['id' => $requestid], '*', MUST_EXIST);

        if (!in_array($request->status, [self::STATUS_SUBMITTED, self::STATUS_UNDER_REVIEW])) {
            throw new \moodle_exception('errorinvalidstatus', 'local_studentid');
        }

        $now = time();
        $update = new \stdClass();
        $update->id             = $requestid;
        $update->status         = self::STATUS_APPROVED;
        $update->review_comment = $comment;
        $update->reviewed_by    = $verifierid;
        $update->timemodified   = $now;
        $update->timereviewed   = $now;
        $DB->update_record('local_studentid_requests', $update);

        self::log_action($verifierid, self::LOG_APPROVE, json_encode(['requestid' => $requestid, 'studentid' => $request->userid]));
        self::send_notification($request->userid, 'approved', ['requestid' => $requestid, 'comment' => $comment]);
    }

    public static function reject_request(int $requestid, string $comment, int $verifierid): void {
        global $DB;

        if (empty(trim($comment))) {
            throw new \moodle_exception('errorcommentrequired', 'local_studentid');
        }

        $request = $DB->get_record('local_studentid_requests', ['id' => $requestid], '*', MUST_EXIST);

        if (!in_array($request->status, [self::STATUS_SUBMITTED, self::STATUS_UNDER_REVIEW])) {
            throw new \moodle_exception('errorinvalidstatus', 'local_studentid');
        }

        $now = time();
        $update = new \stdClass();
        $update->id             = $requestid;
        $update->status         = self::STATUS_REJECTED;
        $update->review_comment = $comment;
        $update->reviewed_by    = $verifierid;
        $update->timemodified   = $now;
        $update->timereviewed   = $now;
        $DB->update_record('local_studentid_requests', $update);

        self::log_action($verifierid, self::LOG_REJECT, json_encode(['requestid' => $requestid, 'studentid' => $request->userid, 'comment' => $comment]));
        self::send_notification($request->userid, 'rejected', ['requestid' => $requestid, 'comment' => $comment]);
    }

    public static function set_under_review(int $requestid, int $verifierid): void {
        global $DB;

        $request = $DB->get_record('local_studentid_requests', ['id' => $requestid], '*', MUST_EXIST);

        if ($request->status !== self::STATUS_SUBMITTED) {
            throw new \moodle_exception('errorinvalidstatus', 'local_studentid');
        }

        $now = time();
        $update = new \stdClass();
        $update->id           = $requestid;
        $update->status       = self::STATUS_UNDER_REVIEW;
        $update->reviewed_by  = $verifierid;
        $update->timemodified = $now;
        $DB->update_record('local_studentid_requests', $update);

        self::log_action($verifierid, self::LOG_UNDER_REVIEW, json_encode(['requestid' => $requestid]));
    }

    public static function generate_id_card(int $requestid, int $generatorid): int {
        global $DB;

        $request = $DB->get_record('local_studentid_requests', ['id' => $requestid], '*', MUST_EXIST);

        if ($request->status !== self::STATUS_APPROVED) {
            throw new \moodle_exception('errorinvalidstatus', 'local_studentid');
        }

        $existing = $DB->get_record('local_studentid_cards', ['requestid' => $requestid]);
        if ($existing) {
            return $existing->id;
        }

        $syscontext = \context_system::instance();
        $fs = get_file_storage();
        $pdf = self::render_card_pdf($request);

        // Insert the card row first so the auto-increment id can seed a
        // collision-free card number. The previous count()+1 scheme could race
        // (two concurrent generations getting the same count) and reused
        // numbers after any deletion.
        $cardrecord                = new \stdClass();
        $cardrecord->requestid     = $requestid;
        $cardrecord->userid        = $request->userid;
        $cardrecord->file_path     = '';
        $cardrecord->idcard_number = '';
        $cardrecord->generated_by  = $generatorid;
        $cardrecord->timegenerated = time();
        $cardid = $DB->insert_record('local_studentid_cards', $cardrecord);

        $cardnumber = self::generate_card_number($cardid);
        $DB->set_field('local_studentid_cards', 'idcard_number', $cardnumber, ['id' => $cardid]);
        $DB->set_field('local_studentid_cards', 'file_path', $cardnumber . '.pdf', ['id' => $cardid]);

        $filerecord = [
            'contextid'    => $syscontext->id,
            'component'    => 'local_studentid',
            'filearea'     => 'idcard',
            'itemid'       => $cardid,
            'filepath'     => '/',
            'filename'     => $cardnumber . '.pdf',
            'timecreated'  => time(),
            'timemodified' => time(),
            'userid'       => $generatorid,
        ];
        $fs->create_file_from_string($filerecord, $pdf);

        self::log_action($generatorid, self::LOG_GENERATE, json_encode(['requestid' => $requestid, 'cardid' => $cardid, 'cardnumber' => $cardnumber]));
        self::send_notification($request->userid, 'generated', ['cardnumber' => $cardnumber]);

        return $cardid;
    }

    /**
     * Build the ID card PDF bytes for a request: assembles the student data
     * (with request overrides) and the uploaded photo, then renders the PDF.
     *
     * @param object $request A local_studentid_requests record.
     * @return string Binary PDF content.
     */
    private static function render_card_pdf(object $request): string {
        $studentdata = self::get_student_data($request->userid);
        $studentdata['blood_group'] = $request->blood_group ?? '';
        // Prefer overrides stored in the request (student may have corrected them).
        if (!empty($request->city))    $studentdata['city']    = $request->city;
        if (!empty($request->state))   $studentdata['state']   = $request->state;
        if (!empty($request->country)) $studentdata['country'] = $request->country;

        $syscontext = \context_system::instance();
        $fs = get_file_storage();
        $photodata = '';
        $photos = $fs->get_area_files($syscontext->id, 'local_studentid', 'photo', $request->id, 'itemid', false);
        foreach ($photos as $pfile) {
            $photodata = $pfile->get_content();
            break;
        }

        return idcard_generator::generate($studentdata, $photodata);
    }

    /**
     * Re-render the PDF for an already-generated card, replacing the stored file
     * in place. The card row, card number and filename are preserved (so links
     * and download counts stay valid) and the student is NOT re-notified — this
     * is for bulk template/watermark refreshes, not new issuance.
     *
     * @param int $cardid     The local_studentid_cards id to regenerate.
     * @param int $generatorid The user id performing the regeneration (for the log).
     * @return string The card number that was regenerated.
     */
    public static function regenerate_card(int $cardid, int $generatorid): string {
        global $DB;

        $card    = $DB->get_record('local_studentid_cards', ['id' => $cardid], '*', MUST_EXIST);
        $request = $DB->get_record('local_studentid_requests', ['id' => $card->requestid], '*', MUST_EXIST);

        $pdf = self::render_card_pdf($request);

        $syscontext = \context_system::instance();
        $fs = get_file_storage();
        $filename = !empty($card->file_path) ? $card->file_path : ($card->idcard_number . '.pdf');

        // Replace the stored PDF in place, keeping the same item id / filename.
        $fs->delete_area_files($syscontext->id, 'local_studentid', 'idcard', $cardid);
        $filerecord = [
            'contextid'    => $syscontext->id,
            'component'    => 'local_studentid',
            'filearea'     => 'idcard',
            'itemid'       => $cardid,
            'filepath'     => '/',
            'filename'     => $filename,
            'timecreated'  => time(),
            'timemodified' => time(),
            'userid'       => $generatorid,
        ];
        $fs->create_file_from_string($filerecord, $pdf);

        $DB->set_field('local_studentid_cards', 'timegenerated', time(), ['id' => $cardid]);
        self::log_action($generatorid, self::LOG_GENERATE,
            json_encode(['regenerated' => true, 'cardid' => $cardid, 'cardnumber' => $card->idcard_number]));

        return $card->idcard_number;
    }

    public static function get_card_for_request(int $requestid): ?object {
        global $DB;
        return $DB->get_record('local_studentid_cards', ['requestid' => $requestid]) ?: null;
    }

    /**
     * Batch-load cards for many requests, keyed by requestid.
     * Avoids an N+1 query when rendering the verifier dashboard.
     *
     * @param int[] $requestids
     * @return array requestid => card record
     */
    public static function get_cards_for_requests(array $requestids): array {
        global $DB;
        if (empty($requestids)) {
            return [];
        }
        $cards = $DB->get_records_list('local_studentid_cards', 'requestid', $requestids);
        $byrequest = [];
        foreach ($cards as $card) {
            $byrequest[$card->requestid] = $card;
        }
        return $byrequest;
    }

    public static function get_card_for_user(int $userid): ?object {
        global $DB;
        $sql = "SELECT c.* FROM {local_studentid_cards} c
                JOIN {local_studentid_requests} r ON r.id = c.requestid
                WHERE c.userid = :userid AND r.status = :approved
                ORDER BY c.timegenerated DESC LIMIT 1";
        return $DB->get_record_sql($sql, ['userid' => $userid, 'approved' => self::STATUS_APPROVED]) ?: null;
    }

    public static function get_all_requests(array $filters = [], string $sort = 'timecreated', string $dir = 'DESC', int $page = 0, int $perpage = 20): array {
        global $DB;

        $validcols = ['timecreated', 'timesubmitted', 'timemodified', 'status', 'usn'];
        if (!in_array($sort, $validcols)) {
            $sort = 'timecreated';
        }
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        [$where, $params] = self::build_filter_sql($filters);

        $sql = "SELECT r.*, " . $DB->sql_concat('u.firstname', "' '", 'u.lastname') . " AS fullname,
                       u.email
                FROM {local_studentid_requests} r
                JOIN {user} u ON u.id = r.userid
                $where
                ORDER BY r.$sort $dir";

        return array_values($DB->get_records_sql($sql, $params, $page * $perpage, $perpage));
    }

    public static function count_requests(array $filters = []): int {
        global $DB;
        [$where, $params] = self::build_filter_sql($filters);
        $sql = "SELECT COUNT(*) FROM {local_studentid_requests} r JOIN {user} u ON u.id = r.userid $where";
        return (int) $DB->count_records_sql($sql, $params);
    }

    /**
     * Fetch all requests matching the given filters for the reviewer report
     * (no pagination). Excludes drafts/replaced (via build_filter_sql) and
     * joins the generated card number. Supports the same filters as the
     * dashboard plus optional submitted/verification date ranges:
     * status, search, subfrom, subto, verfrom, verto.
     *
     * @param array $filters
     * @return array Request records with extra fullname, email, idcard_number fields.
     */
    public static function get_requests_for_report(array $filters = []): array {
        global $DB;

        [$where, $params] = self::build_filter_sql($filters);

        $sql = "SELECT r.*, " . $DB->sql_concat('u.firstname', "' '", 'u.lastname') . " AS fullname,
                       u.email, c.idcard_number
                  FROM {local_studentid_requests} r
                  JOIN {user} u ON u.id = r.userid
             LEFT JOIN {local_studentid_cards} c ON c.requestid = r.id
                $where
              ORDER BY r.timesubmitted DESC, r.id DESC";

        return array_values($DB->get_records_sql($sql, $params));
    }

    public static function get_dashboard_stats(): array {
        global $DB;

        $today = mktime(0, 0, 0, date('n'), date('j'), date('Y'));

        // All per-status request counts in a single grouped query instead of
        // one COUNT per status.
        $statusrows = $DB->get_records_sql(
            "SELECT status, COUNT(*) AS cnt FROM {local_studentid_requests} GROUP BY status"
        );
        $countfor = function(string $status) use ($statusrows): int {
            return isset($statusrows[$status]) ? (int) $statusrows[$status]->cnt : 0;
        };

        return [
            'total_pending'        => $countfor(self::STATUS_SUBMITTED),
            'under_review'         => $countfor(self::STATUS_UNDER_REVIEW),
            // Count by the dedicated review timestamp on the request itself.
            // This is reliable (does not depend on the audit log being written)
            // and is not skewed by a later timemodified bump (e.g. an approved
            // student refreshing their info).
            'approved_today'       => $DB->count_records_select('local_studentid_requests', "status = :s AND timereviewed >= :t", ['s' => self::STATUS_APPROVED, 't' => $today]),
            'rejected_today'       => $DB->count_records_select('local_studentid_requests', "status = :s AND timereviewed >= :t", ['s' => self::STATUS_REJECTED, 't' => $today]),
            'total_approved'       => $countfor(self::STATUS_APPROVED),
            'total_rejected'       => $countfor(self::STATUS_REJECTED),
            // Cards downloaded at least once, and those downloaded today.
            'ids_downloaded_total' => $DB->count_records_select('local_studentid_cards', 'download_count > 0'),
            'ids_downloaded_today' => $DB->count_records_select('local_studentid_cards', 'last_downloaded >= :today', ['today' => $today]),
        ];
    }

    public static function log_action(int $actorid, string $action, string $context = ''): void {
        global $DB;
        $record              = new \stdClass();
        $record->userid      = $actorid;
        $record->action      = $action;
        $record->context     = $context;
        $record->timecreated = time();
        try {
            $DB->insert_record('local_studentid_logs', $record);
        } catch (\Exception $e) {
            // Log failures must never break the main workflow.
            debugging('local_studentid log_action failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    public static function send_notification(int $userid, string $type, array $data = []): void {
        if (!get_config('local_studentid', 'emailnotifications')) {
            return;
        }

        global $DB, $CFG;
        $user = $DB->get_record('user', ['id' => $userid]);
        if (!$user) {
            return;
        }

        $subject = '';
        $body    = '';

        switch ($type) {
            case 'submitted':
                $subject = get_string('email_submitted_subject', 'local_studentid');
                $body    = get_string('email_submitted_body', 'local_studentid', fullname($user));
                break;
            case 'approved':
                $subject = get_string('email_approved_subject', 'local_studentid');
                $body    = get_string('email_approved_body', 'local_studentid', fullname($user));
                break;
            case 'rejected':
                $subject = get_string('email_rejected_subject', 'local_studentid');
                $a = new \stdClass();
                $a->name    = fullname($user);
                $a->comment = $data['comment'] ?? '';
                $body       = get_string('email_rejected_body', 'local_studentid', $a);
                break;
            case 'generated':
                $subject = get_string('email_generated_subject', 'local_studentid');
                $body    = get_string('email_generated_body', 'local_studentid', fullname($user));
                break;
            default:
                return;
        }

        $noreply = \core_user::get_noreply_user();
        email_to_user($user, $noreply, $subject, $body);
    }

    public static function get_status_label(string $status): string {
        $map = [
            self::STATUS_DRAFT        => get_string('statusdraft', 'local_studentid'),
            self::STATUS_SUBMITTED    => get_string('statussubmitted', 'local_studentid'),
            self::STATUS_UNDER_REVIEW => get_string('statusunderreview', 'local_studentid'),
            self::STATUS_APPROVED     => get_string('statusapproved', 'local_studentid'),
            self::STATUS_REJECTED     => get_string('statusrejected', 'local_studentid'),
            self::STATUS_REPLACED     => get_string('statusreplaced', 'local_studentid'),
        ];
        return $map[$status] ?? ucfirst($status);
    }

    public static function get_status_badge_class(string $status): string {
        $map = [
            self::STATUS_DRAFT        => 'bg-secondary text-white',
            self::STATUS_SUBMITTED    => 'bg-primary text-white',
            self::STATUS_UNDER_REVIEW => 'bg-warning text-dark',
            self::STATUS_APPROVED     => 'bg-success text-white',
            self::STATUS_REJECTED     => 'bg-danger text-white',
            self::STATUS_REPLACED     => 'bg-dark text-white',
        ];
        return 'badge ' . ($map[$status] ?? 'bg-secondary text-white');
    }

    public static function get_photo_url(int $requestid, string $filename): \moodle_url {
        $syscontext = \context_system::instance();
        return \moodle_url::make_pluginfile_url(
            $syscontext->id, 'local_studentid', 'photo', $requestid, '/', $filename
        );
    }

    public static function get_aadhaar_url(int $requestid, string $filearea, string $filename): \moodle_url {
        $syscontext = \context_system::instance();
        return \moodle_url::make_pluginfile_url(
            $syscontext->id, 'local_studentid', $filearea, $requestid, '/', $filename
        );
    }

    public static function get_card_url(int $cardid, string $filename): \moodle_url {
        $syscontext = \context_system::instance();
        return \moodle_url::make_pluginfile_url(
            $syscontext->id, 'local_studentid', 'idcard', $cardid, '/', $filename
        );
    }

    public static function get_abc_id_url(int $requestid, string $filename): \moodle_url {
        $syscontext = \context_system::instance();
        return \moodle_url::make_pluginfile_url(
            $syscontext->id, 'local_studentid', 'abc_id', $requestid, '/', $filename
        );
    }

    public static function get_tenth_marks_url(int $requestid, string $filename): \moodle_url {
        $syscontext = \context_system::instance();
        return \moodle_url::make_pluginfile_url(
            $syscontext->id, 'local_studentid', 'tenth_marks', $requestid, '/', $filename
        );
    }

    public static function get_olevel_url(int $requestid, string $filename): \moodle_url {
        $syscontext = \context_system::instance();
        return \moodle_url::make_pluginfile_url(
            $syscontext->id, 'local_studentid', 'olevel', $requestid, '/', $filename
        );
    }

    public static function increment_download_count(int $cardid): void {
        global $DB;
        $card = $DB->get_record('local_studentid_cards', ['id' => $cardid]);
        if (!$card) {
            return;
        }
        $DB->set_field('local_studentid_cards', 'download_count', ((int) $card->download_count) + 1, ['id' => $cardid]);
        $DB->set_field('local_studentid_cards', 'last_downloaded', time(), ['id' => $cardid]);
    }

    public static function update_request_info(int $requestid, int $userid): void {
        global $DB;
        $request = $DB->get_record('local_studentid_requests', ['id' => $requestid, 'userid' => $userid], '*', MUST_EXIST);
        $studentdata = self::get_student_data($userid);

        $update               = new \stdClass();
        $update->id           = $requestid;
        // Location: the request holds student-entered form values that often
        // do not exist in the Moodle profile at all. Only refresh them from a
        // NON-EMPTY profile value — copying blanks here used to wipe the
        // student's own city/state/country (and their card) when they clicked
        // "Update Info" with an empty profile.
        if (trim((string) $studentdata['city']) !== '') {
            $update->city = $studentdata['city'];
        }
        if (trim((string) $studentdata['state']) !== '') {
            $update->state = $studentdata['state'];
        }
        if (trim((string) $studentdata['country']) !== '') {
            $update->country = $studentdata['country'];
        }
        $update->usn          = $studentdata['usn'];
        $update->timemodified = time();
        $DB->update_record('local_studentid_requests', $update);

        self::log_action($userid, self::LOG_UPDATE, json_encode(['requestid' => $requestid, 'action' => 'update_info']));
    }

    private static function generate_card_number(int $cardid): string {
        // Seed the sequence from the card's auto-increment id: unique by
        // construction, so it cannot race with a concurrent generation nor
        // reuse a number after a card row is deleted.
        return 'SID-' . date('Y') . '-' . str_pad((string) $cardid, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Delete all uploaded files (photo, Aadhaar, ABC ID, 10th marks, O Level) for a request.
     */
    public static function delete_request_files(int $requestid): void {
        $syscontext = \context_system::instance();
        $fs = get_file_storage();
        foreach (['photo', 'aadhaar_front', 'aadhaar_back', 'abc_id', 'tenth_marks', 'olevel'] as $area) {
            $fs->delete_area_files($syscontext->id, 'local_studentid', $area, $requestid);
        }
    }

    private static function build_filter_sql(array $filters): array {
        global $DB;

        $conditions = [];
        $params     = [];

        // Verifiers never see draft or replaced requests.
        $conditions[] = 'r.status NOT IN (:st_draft, :st_replaced)';
        $params['st_draft']    = self::STATUS_DRAFT;
        $params['st_replaced'] = self::STATUS_REPLACED;

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $conditions[] = 'r.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $conditions[] = '(' . implode(' OR ', [
                $DB->sql_like('u.firstname', ':s1', false),
                $DB->sql_like('u.lastname', ':s2', false),
                $DB->sql_like('r.usn', ':s3', false),
            ]) . ')';
            $params['s1'] = $search;
            $params['s2'] = $search;
            $params['s3'] = $search;
        }

        // Optional date ranges (unix timestamps) — used by the reviewer report.
        if (!empty($filters['subfrom'])) {
            $conditions[] = 'r.timesubmitted >= :subfrom';
            $params['subfrom'] = (int) $filters['subfrom'];
        }
        if (!empty($filters['subto'])) {
            $conditions[] = 'r.timesubmitted <= :subto';
            $params['subto'] = (int) $filters['subto'];
        }
        if (!empty($filters['verfrom'])) {
            $conditions[] = 'r.timereviewed >= :verfrom';
            $params['verfrom'] = (int) $filters['verfrom'];
        }
        if (!empty($filters['verto'])) {
            $conditions[] = 'r.timereviewed <= :verto';
            $params['verto'] = (int) $filters['verto'];
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);
        return [$where, $params];
    }
}
