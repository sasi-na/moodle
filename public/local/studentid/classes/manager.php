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

        return [
            'userid'      => $user->id,
            'fullname'    => fullname($user),
            'email'       => $user->email,
            'program'     => $user->profile_field_mainprogram ?? $user->profile_field_programregister ?? '',
            'batch'       => $user->profile_field_batch ?? '',
            'usn'         => $user->profile_field_usn ?? '',
            'dob'         => !empty($user->profile_field_dob)
                                ? userdate($user->profile_field_dob, get_string('strftimedate', 'langconfig'))
                                : '',
            'contact'     => $user->profile_field_mobile ?? $user->phone1 ?? '',
            'city'        => $user->city ?? '',
            'state'       => $user->profile_field_state ?? '',
            'country'     => $country,
        ];
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
        $pdfs      = ['application/pdf', 'application/x-pdf'];
        $allowed = [
            'photo'         => $imgmimes,
            'aadhaar_front' => array_merge($imgmimes, $pdfs),
            'aadhaar_back'  => array_merge($imgmimes, $pdfs),
            'abc_id'        => $pdfs,
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
        $colmap = ['photo' => 'photo_path', 'aadhaar_front' => 'aadhaar_front_path', 'aadhaar_back' => 'aadhaar_back_path', 'abc_id' => 'abc_id_path'];
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

        if (empty($request->abc_id_path)) {
            throw new \moodle_exception('errornoabcid', 'local_studentid');
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

        $studentdata = self::get_student_data($request->userid);
        $studentdata['blood_group'] = $request->blood_group ?? '';
        // Prefer overrides stored in the request (student may have corrected them).
        if (!empty($request->city))    $studentdata['city']    = $request->city;
        if (!empty($request->state))   $studentdata['state']   = $request->state;
        if (!empty($request->country)) $studentdata['country'] = $request->country;

        $syscontext = \context_system::instance();
        $fs = get_file_storage();
        $photodata = '';

        $photos = $fs->get_area_files($syscontext->id, 'local_studentid', 'photo', $requestid, 'itemid', false);
        foreach ($photos as $pfile) {
            $photodata = $pfile->get_content();
            break;
        }

        $pdf = idcard_generator::generate($studentdata, $photodata);

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

        $validcols = ['timecreated', 'timesubmitted', 'status', 'usn'];
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
        $update->city         = $studentdata['city'];
        $update->state        = $studentdata['state'];
        $update->country      = $studentdata['country'];
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
     * Delete all uploaded files (photo, Aadhaar, ABC ID) for a request.
     */
    public static function delete_request_files(int $requestid): void {
        $syscontext = \context_system::instance();
        $fs = get_file_storage();
        foreach (['photo', 'aadhaar_front', 'aadhaar_back', 'abc_id'] as $area) {
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

        $where = 'WHERE ' . implode(' AND ', $conditions);
        return [$where, $params];
    }
}
