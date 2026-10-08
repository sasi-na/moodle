<?php
namespace local_ams;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;
use core_external\external_warnings;
use context_system;
use stdClass;

class external extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function create_student_parameters() {
        return new external_function_parameters([
            'student' => new external_single_structure([
                'firstname'         => new external_value(PARAM_TEXT, 'First name'),
                'lastname'          => new external_value(PARAM_TEXT, 'Last name', VALUE_OPTIONAL),
                'email'             => new external_value(PARAM_EMAIL, 'Email address'),
                'dob'               => new external_value(PARAM_INT, 'Date of birth (timestamp)', VALUE_OPTIONAL),
                'gender'            => new external_value(PARAM_TEXT, 'Gender', VALUE_OPTIONAL),
                'mobile'            => new external_value(PARAM_TEXT, 'Mobile number', VALUE_OPTIONAL),
                'enrolmentnumber'   => new external_value(PARAM_TEXT, 'Enrolment number'),
                'applicationnumber' => new external_value(PARAM_TEXT, 'Application number', VALUE_OPTIONAL),
                'mainprogram'       => new external_value(PARAM_TEXT, 'Main Program', VALUE_OPTIONAL),
                'programregister'   => new external_value(PARAM_TEXT, 'Program Register for', VALUE_OPTIONAL),
                'elective'          => new external_value(PARAM_TEXT, 'Elective', VALUE_OPTIONAL),
                'usn'               => new external_value(PARAM_TEXT, 'USN', VALUE_OPTIONAL),
                'batch'             => new external_value(PARAM_TEXT, 'Batch', VALUE_OPTIONAL),
                'fathername'        => new external_value(PARAM_TEXT, 'Father name', VALUE_OPTIONAL),
                'mothername'        => new external_value(PARAM_TEXT, 'Mother name', VALUE_OPTIONAL),
                'nationality'       => new external_value(PARAM_TEXT, 'Nationality', VALUE_OPTIONAL),
                'debid'             => new external_value(PARAM_TEXT, 'DEB ID', VALUE_OPTIONAL),
                'abcid'             => new external_value(PARAM_TEXT, 'ABC ID', VALUE_OPTIONAL),
            ]),
            'courseshortcodes' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Course short code'),
                'List of course short codes to enroll the student in',
                VALUE_OPTIONAL
            ),
        ]);
    }

    /**
     * Create or update a student account (upsert).
     * If user exists, updates profile fields. If not, creates new user.
     *
     * @param array $student Student details
     * @param array $courseshortcodes List of course short codes to enroll in
     * @return array
     */
    public static function create_student($student, $courseshortcodes = []) {
        global $CFG, $DB;

        // Parameter validation.
        $params = self::validate_parameters(self::create_student_parameters(), [
            'student' => $student,
            'courseshortcodes' => $courseshortcodes,
        ]);
        $studentdata = $params['student'];
        $courseshortcodes = $params['courseshortcodes'] ?? [];
        
        //Set default value for lastname if not passed as moodle expects it to be mandatory
        if (empty($studentdata['lastname'])){
            $studentdata['lastname'] = '.';
        }
        // Context validation.
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/user:create', $context);

        // Find an existing account. Priority: enrolment number (core idnumber) ->
        // application number (profile field) -> email. If any single identifier resolves
        // to 2+ live accounts, block and alert rather than guess which one to touch.
        $existinguser = false;
        $matchedby = '';

        if (!empty($studentdata['enrolmentnumber'])) {
            $enrol = trim($studentdata['enrolmentnumber']);
            $ids = array_keys($DB->get_records('user', ['idnumber' => $enrol, 'deleted' => 0], 'id ASC', 'id'));
            if (count($ids) >= 2) {
                self::send_duplicate_notification('enrolment number', $enrol, $ids, $studentdata);
                throw new \moodle_exception('duplicateenrolment', 'local_ams', '', null,
                    'Multiple (' . count($ids) . ") accounts exist with enrolment number {$enrol}; create/update blocked.");
            } else if (count($ids) === 1) {
                $existinguser = $DB->get_record('user', ['id' => reset($ids)], '*', MUST_EXIST);
                $matchedby = 'enrolment number';
            }
        }

        if (!$existinguser && !empty($studentdata['applicationnumber'])) {
            $appno = trim($studentdata['applicationnumber']);
            $ids = self::find_users_by_profile_field('applicationnumber', $appno);
            if (count($ids) >= 2) {
                self::send_duplicate_notification('application number', $appno, $ids, $studentdata);
                throw new \moodle_exception('duplicateapplication', 'local_ams', '', null,
                    'Multiple (' . count($ids) . ") accounts exist with application number {$appno}; create/update blocked.");
            } else if (count($ids) === 1) {
                $existinguser = $DB->get_record('user', ['id' => reset($ids)], '*', MUST_EXIST);
                $matchedby = 'application number';
            }
        }

        if (!$existinguser && !empty($studentdata['email'])) {
            $emaillc = strtolower(trim($studentdata['email']));
            $ids = array_keys($DB->get_records('user', ['email' => $emaillc, 'deleted' => 0], 'id ASC', 'id'));
            if (count($ids) >= 2) {
                self::send_duplicate_notification('email', $emaillc, $ids, $studentdata);
                throw new \moodle_exception('duplicateemail', 'local_ams', '', null,
                    'Multiple (' . count($ids) . ") accounts exist with email {$emaillc}; create/update blocked.");
            } else if (count($ids) === 1) {
                $existinguser = $DB->get_record('user', ['id' => reset($ids)], '*', MUST_EXIST);
                $matchedby = 'email';
            }
        }

        if ($existinguser !== false) {
            // UPDATE MODE — exactly one existing account matched. Only a deliberately narrow
            // set of fields may be changed on an existing account: email (+ username) and
            // mobile here, plus the small allow-list in $updatablefields below. Identity and
            // programme fields (name, idnumber/enrolment number, application number, main
            // program, program registered for, elective, usn, batch, DEB/ABC id) are owned by
            // the LMS once the account exists and are never overwritten from AMS.
            require_capability('moodle/user:update', $context);
            $isupdate = true;
            $userid = $existinguser->id;
            $newuser = $existinguser;
            $plainpassword = null;

            $userupdated = false;
            if (!empty($studentdata['email']) && strtolower($newuser->email) != strtolower(trim($studentdata['email']))) {
                $newemail = strtolower(trim($studentdata['email']));
                // The new email must not already belong to a different live account, else the
                // update would silently create two accounts with the same email.
                $emailowner = $DB->get_record_select('user',
                    'LOWER(email) = ? AND deleted = 0 AND id <> ?',
                    [$newemail, $newuser->id], 'id', IGNORE_MULTIPLE);
                if ($emailowner) {
                    self::send_duplicate_notification('email', $newemail,
                        [(int) $emailowner->id, (int) $newuser->id], $studentdata);
                    throw new \moodle_exception('duplicateemail', 'local_ams', '', null,
                        "Cannot set email {$newemail} on user {$newuser->id}: already used by user {$emailowner->id}.");
                }
                $newuser->email = $newemail;
                // Keep username aligned with email unless it collides with another account.
                $collision = $DB->get_record('user', ['username' => $newemail, 'deleted' => 0]);
                if (!$collision || (int) $collision->id === (int) $newuser->id) {
                    $newuser->username = $newemail;
                } else {
                    error_log("AMS: user {$newuser->id} email updated to {$newemail} but username kept (collision with user {$collision->id})");
                }
                $userupdated = true;
            }
            // user.idnumber (the enrolment number) is intentionally left alone on update —
            // not even backfilled — because lookups key off it and AMS must not re-stamp it.
            if (!empty($studentdata['mobile']) && (string) $newuser->phone2 !== (string) $studentdata['mobile']) {
                $newuser->phone2 = $studentdata['mobile'];
                $userupdated = true;
            }
            if ($userupdated) {
                user_update_user($newuser, false, false);
            }
            error_log("AMS: create_student updating existing user {$userid} (matched by {$matchedby})");
        } else {
            // CREATE MODE — no existing account.
            $isupdate = false;
            $user = new stdClass();
            $user->firstname = $studentdata['firstname'];
            $user->lastname = $studentdata['lastname'];
            $user->email = strtolower(trim($studentdata['email']));
            $user->username = strtolower(trim($studentdata['email'])); // Using email as username

            // Store the AMS enrolment number in core user.idnumber so lookups that key
            // off idnumber (e.g. local_ams_get_student_status) can find this account.
            if (!empty($studentdata['enrolmentnumber'])) {
                $user->idnumber = trim($studentdata['enrolmentnumber']);
            }
            // Mobile goes to the core "Mobile phone" field (user.phone2, Optional section).
            if (!empty($studentdata['mobile'])) {
                $user->phone2 = $studentdata['mobile'];
            }

            // Random by default; the fixed one when local_welcome is configured with it.
            $plainpassword = class_exists('\local_welcome\message')
                ? \local_welcome\message::new_password()
                : generate_password();
            $user->password = $plainpassword;

            $user->confirmed = 1;
            $user->mnethostid = $CFG->mnet_localhost_id;
            $user->auth = 'manual';
            $user->lang = $CFG->lang;
            $user->timezone = 99; // Server timezone

            $userid = user_create_user($user, true, false);
            $newuser = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        }
        
        // Save custom profile fields into the LMS Student Information category.
        // Array KEYS are the LMS profile field shortnames (the save target);
        // VALUES still come from the AMS-sent params. mobile/nationality have no
        // LMS Student Information equivalent, so they stay on their AMS fields.
        $customfields = [
            'gender'                        => $studentdata['gender'] ?? null,
            // mobile now goes to core user.phone2 ("Mobile phone", Optional section)
            // above, not a custom profile field.
            'provisional_enrollment_number' => $studentdata['enrolmentnumber'] ?? null,
            'applicationnumber'             => $studentdata['applicationnumber'] ?? null,
            // AMS' mandatory 'mainprogram' is the programme the learner registered for, so it
            // lands in the LMS 'program_registered_for' field. The old 'main_program' field is
            // no longer written (existing values are left alone, never cleared), and the
            // optional 'programregister' param is ignored here — it remains writable through
            // local_ams_update_student.
            'program_registered_for'        => $studentdata['mainprogram'] ?? null,
            'elective'                      => $studentdata['elective'] ?? null,
            'usn'                           => $studentdata['usn'] ?? null,
            'cohort_batch'                  => $studentdata['batch'] ?? null,
            'father_name'                   => $studentdata['fathername'] ?? null,
            'mother_name'                   => $studentdata['mothername'] ?? null,
            'nationality'                   => $studentdata['nationality'] ?? null,       // stays AMS field (id 23)
            'deb_id'                        => $studentdata['debid'] ?? null,
            'abc_id'                        => $studentdata['abcid'] ?? null,
        ];

        // dob arrives as a unix timestamp (PARAM_INT); the LMS 'dateofbirth' field
        // is text formatted DD/MM/YYYY, so convert before saving.
        if (!empty($studentdata['dob'])) {
            $customfields['dateofbirth'] = date('d/m/Y', (int)$studentdata['dob']);
        }

        if ($isupdate) {
            // On an existing account AMS may only refresh these. Everything else in the
            // mapping above (enrolment/application number, programme, elective, usn, batch,
            // DEB/ABC id) is LMS-owned after creation and is left exactly as it is.
            $updatablefields = ['gender', 'dateofbirth', 'father_name', 'mother_name', 'nationality'];
            $customfields = array_intersect_key($customfields, array_flip($updatablefields));
        }

        // Ensure profile fields exist before saving data
        $fielderrors = [];
        foreach ($customfields as $shortname => $value) {
            if ($value !== null && $value !== '') {
                // Check if the field exists, if not, try to create it
                if (!$DB->record_exists('user_info_field', ['shortname' => $shortname])) {
                    error_log("AMS: Profile field '{$shortname}' does not exist. Attempting to create it.");
                    $fieldcreated = self::ensure_profile_field_exists($shortname);
                    if (!$fieldcreated) {
                        $fielderrors[] = "Profile field '{$shortname}' does not exist and could not be created";
                        error_log("AMS: ERROR - Failed to create profile field '{$shortname}' for user {$userid}");
                        continue;
                    }
                }
                
                // Now save the data
                try {
                    $userobj = new stdClass();
                    $userobj->id = $userid;
                    $userobj->{'profile_field_' . $shortname} = $value;
                    profile_save_data($userobj);
                    error_log("AMS: Successfully saved profile field '{$shortname}' = '{$value}' for user {$userid}");
                } catch (\Exception $e) {
                    $fielderrors[] = "Failed to save profile field '{$shortname}': " . $e->getMessage();
                    error_log("AMS: ERROR - Failed to save profile field '{$shortname}' for user {$userid}: " . $e->getMessage());
                }
            }
        }
        
        // Log any field errors
        if (!empty($fielderrors)) {
            error_log("AMS: Profile field errors for user {$userid}: " . implode('; ', $fielderrors));
        }

        // ENROLL: Enroll student in provided courseshortcodes if given, else fallback to program mapping
        $enrolledcourses = [];
        $enrollmenterrors = [];
        if (!empty($courseshortcodes)) {
            $enrol = enrol_get_plugin('manual');
            if ($enrol) {
                foreach ($courseshortcodes as $shortname) {
                    try {
                        $course = $DB->get_record('course', ['shortname' => $shortname], '*', IGNORE_MISSING);
                        if ($course) {
                            $instance = $DB->get_record('enrol', [
                                'courseid' => $course->id,
                                'enrol' => 'manual',
                                'status' => ENROL_INSTANCE_ENABLED
                            ], '*', IGNORE_MISSING);
                            if (!$instance) {
                                $instanceid = $enrol->add_instance($course);
                                $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
                            }
                            if ($instance) {
                                // Check if already enrolled to avoid redundant notifications
                                if (!$DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid])) {
                                    $studentrole = $DB->get_record('role', ['shortname' => 'student']);
                                    $roleid = $studentrole ? $studentrole->id : 5;
                                    $enrol->enrol_user($instance, $userid, $roleid, 0, 0, ENROL_USER_ACTIVE);
                                    $enrolledcourses[] = [
                                        'courseid' => $course->id,
                                        'shortname' => $course->shortname,
                                        'fullname' => $course->fullname,
                                    ];
                                    error_log("AMS: Auto-enrolled user {$userid} in course {$course->shortname} (ID: {$course->id})");
                                } else {
                                    error_log("AMS: User {$userid} already enrolled in course {$course->shortname} - skipping auto-enrollment");
                                }
                            }
                        } else {
                            $enrollmenterrors[] = "Course not found: {$shortname}";
                            error_log("AMS: Course not found for auto-enrollment: {$shortname}");
                        }
                    } catch (\Exception $e) {
                        $enrollmenterrors[] = "Error enrolling in {$shortname}: " . $e->getMessage();
                        error_log("AMS: Error auto-enrolling user {$userid} in {$shortname}: " . $e->getMessage());
                    }
                }
            } else {
                $enrollmenterrors[] = "Manual enrolment plugin not available";
                error_log("AMS: Manual enrolment plugin not available for auto-enrollment");
            }
        } else {
            // No course shortcodes provided, skip auto-enrollment
            error_log("AMS: No course shortcodes provided for user {$userid} - skipping auto-enrollment");
        }

        // SEND EMAIL ONLY FOR NEW USERS (not for updates)
        if (!$isupdate) {
            // The emailed password is one-time: require the learner to set their own on
            // first login (governed by the local_welcome force-change setting).
            if (!class_exists('\local_welcome\message') || \local_welcome\message::forcepasswordchange_enabled()) {
                set_user_preference('auth_forcepasswordchange', 1, $newuser);
            }

            // Send the branded welcome email carrying the generated login password.
            // Prefer the shared local_welcome template (single source of truth across
            // channels); fall back to the built-in message if that plugin is absent.
            if (class_exists('\local_welcome\message')) {
                $emailsent = \local_welcome\message::send_credentials_email($newuser, $plainpassword, 'welcome');
            } else {
                $emailsent = self::send_account_creation_email($newuser, $plainpassword);
            }

            // Send enrollment email if student was auto-enrolled
            if (!empty($enrolledcourses)) {
                self::send_course_enrollment_email($newuser, $enrolledcourses);
            }

            // Send notification to site administrators
            self::send_admin_notification($newuser, $studentdata, $enrolledcourses);

            // Log email status for debugging
            if ($emailsent) {
                error_log("AMS: Account creation email sent successfully to user {$userid} ({$newuser->email})");
            } else {
                error_log("AMS: Failed to send account creation email to user {$userid} ({$newuser->email})");
            }
        } else {
            // For updates, check if student was enrolled in new courses and send enrollment email
            if (!empty($enrolledcourses)) {
                self::send_course_enrollment_email($newuser, $enrolledcourses);
            }
            $emailsent = false;
            error_log("AMS: User {$userid} ({$newuser->email}) updated");
        }

        // Audit trail in the logstore. Custom events (not core user_created/user_updated)
        // so we do NOT trigger the local_welcome observer -> no duplicate welcome email.
        $eventdata = [
            'objectid'      => $userid,
            'relateduserid' => $userid,
            'context'       => \context_user::instance($userid),
            'other'         => ['action' => $isupdate ? 'updated' : 'created', 'matchedby' => $matchedby, 'source' => 'create_student'],
        ];
        if ($isupdate) {
            \local_ams\event\student_updated::create($eventdata)->trigger();
        } else {
            \local_ams\event\student_created::create($eventdata)->trigger();
        }

        return [
            'id' => $userid,
            'username' => $newuser->username,
            'action' => $isupdate ? 'updated' : 'created',
            'emailsent' => $emailsent,
            'enrolledcourses' => $enrolledcourses,
            'enrollmenterrors' => $enrollmenterrors,
            'fielderrors' => $fielderrors ?? [],
        ];
    }

    /**
     * Resolve the configured alert-recipient email addresses.
     * Falls back to the shipped default until the admin setting is first saved;
     * an explicitly-cleared setting returns an empty array (alerts disabled).
     *
     * @return string[] Trimmed, non-empty email addresses.
     */
    private static function get_notify_recipients() {
        $recipients = get_config('local_ams', 'skipnotifyemails');
        if ($recipients === false) {
            // Setting not yet saved on this site: fall back to the shipped default.
            $recipients = 'bhushan@usdcglobal.com';
        }
        return array_filter(array_map('trim', explode(',', (string)$recipients)));
    }

    /**
     * Send a plain-text alert (with a simple HTML rendering) to the given addresses.
     *
     * @param string[] $addresses Recipient email addresses.
     * @param string $subject Email subject.
     * @param string $message Plain-text body.
     * @return void
     */
    private static function mail_recipients(array $addresses, $subject, $message) {
        $messagehtml = nl2br(s($message));
        $from = \core_user::get_support_user();
        $base = \core_user::get_support_user();
        foreach ($addresses as $addr) {
            $to = clone $base;
            $to->email = $addr;
            $to->firstname = 'AMS';
            $to->lastname = 'Notification';
            email_to_user($to, $from, $subject, $message, $messagehtml);
        }
    }

    /**
     * Notify configured recipients that an AMS update was BLOCKED because multiple LMS
     * accounts share the same identifier (email or enrolment number).
     *
     * @param string $idtype Human label for the identifier, e.g. "email" / "enrolment number".
     * @param string $idvalue The duplicated value.
     * @param int[] $userids Ids of every matching account.
     * @param array $data The incoming AMS request payload (for context).
     * @return void
     */
    private static function send_duplicate_notification($idtype, $idvalue, $userids, $data) {
        global $CFG, $SITE, $DB;

        $addresses = self::get_notify_recipients();
        if (empty($addresses)) {
            return;
        }

        $sitename = format_string($SITE->fullname);
        $subject = "[{$sitename}] AMS update BLOCKED — multiple accounts with same {$idtype}: {$idvalue}";

        $lines = [
            "An AMS update was blocked because multiple LMS accounts share the same {$idtype}.",
            "No account was modified.",
            "",
            ucfirst($idtype) . " : {$idvalue}",
            "Matches : " . count($userids),
            "Time    : " . userdate(time()),
            "",
            "Incoming AMS request",
            "--------------------",
            "Email              : " . ($data['email'] ?? ''),
            "Enrolment number   : " . ($data['enrolmentnumber'] ?? ''),
        ];
        if (array_key_exists('elective', $data)) {
            $lines[] = "Elective           : " . $data['elective'];
        }
        if (array_key_exists('programregister', $data)) {
            $lines[] = "Program registered : " . $data['programregister'];
        }
        if (array_key_exists('status', $data)) {
            $lines[] = "Requested status   : " . $data['status'];
        }
        $lines[] = "";
        $lines[] = "Matching LMS accounts (update NOT applied to any)";
        $lines[] = "-------------------------------------------------";
        foreach ($userids as $uid) {
            $u = $DB->get_record('user', ['id' => $uid], 'id,username,email,firstname,lastname,idnumber');
            if ($u) {
                $lines[] = "  id={$u->id} | {$u->username} | {$u->email} | {$u->firstname} {$u->lastname} | idnumber={$u->idnumber}";
                $lines[] = "     {$CFG->wwwroot}/user/profile.php?id={$u->id}";
            }
        }
        $lines[] = "";
        $lines[] = "Please de-duplicate these accounts before retrying the update.";
        $lines[] = "";
        $lines[] = "— {$sitename} (automated notification, local_ams)";

        self::mail_recipients($addresses, $subject, implode("\n", $lines));
        error_log("AMS: duplicate-{$idtype} alert sent for {$idvalue} (" . count($userids) . " matches) to " . implode(',', $addresses));
    }

    /**
     * Find non-deleted users whose custom profile field (by shortname) equals a value.
     * Used to detect duplicate application numbers during create/update matching.
     *
     * @param string $shortname Profile field shortname.
     * @param string $value Value to match.
     * @return int[] Ids of matching non-deleted users.
     */
    private static function find_users_by_profile_field($shortname, $value) {
        global $DB;
        $sql = "SELECT u.id
                  FROM {user} u
                  JOIN {user_info_data} d ON d.userid = u.id
                  JOIN {user_info_field} f ON f.id = d.fieldid
                 WHERE f.shortname = :shortname AND d.data = :value AND u.deleted = 0";
        return array_keys($DB->get_records_sql($sql, ['shortname' => $shortname, 'value' => $value]));
    }

    /**
     * Send account creation email to newly created student with login credentials.
     *
     * @param stdClass $user The user object
     * @param string $plainpassword The plain text password
     * @return bool Success status
     */
    private static function send_account_creation_email($user, $plainpassword) {
        global $CFG, $SITE;

        // Check if email is properly configured
        if (empty($CFG->noreplyaddress)) {
            error_log("AMS: Warning - noreplyaddress not configured in Moodle");
        }

        $from = \core_user::get_support_user();
        
        // Prepare email subject
        $subject = "Welcome to " . format_string($SITE->fullname) . " - Your Login Details";
        
        // Prepare email message
        $message = "Dear {$user->firstname} {$user->lastname},\n\n";
        $message .= "Your student account has been created successfully.\n\n";
        $message .= "Your login details are:\n";
        $message .= "------------------------------------\n";
        $message .= "Username: {$user->username}\n";
        $message .= "Password: {$plainpassword}\n";
        $message .= "------------------------------------\n\n";
        $message .= "You can login at: {$CFG->wwwroot}/login/\n\n";
        
        $message .= "Please keep this information secure and change your password after first login.\n\n";
        $message .= "Welcome to " . format_string($SITE->fullname) . "!\n\n";
        $message .= "If you need help, please contact: {$CFG->supportemail}";

        // HTML version
        $messagehtml = "<p>Dear {$user->firstname} {$user->lastname},</p>";
        $messagehtml .= "<p>Your student account has been created successfully.</p>";
        $messagehtml .= "<p><strong>Your login details are:</strong></p>";
        $messagehtml .= "<div style='background-color: #f5f5f5; padding: 15px; border-left: 4px solid #4CAF50;'>";
        $messagehtml .= "<p><strong>Username:</strong> {$user->username}<br>";
        $messagehtml .= "<strong>Password:</strong> {$plainpassword}</p>";
        $messagehtml .= "</div>";
        
        $messagehtml .= "<p><a href='{$CFG->wwwroot}/login/' style='background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; display: inline-block; margin: 10px 0;'>Login Now</a></p>";
        $messagehtml .= "<p><em>Please keep this information secure and change your password after first login.</em></p>";
        $messagehtml .= "<p>Welcome to " . format_string($SITE->fullname) . "!</p>";
        $messagehtml .= "<hr><p><small>If you need help, please contact: {$CFG->supportemail}</small></p>";

        // Log the attempt
        error_log("AMS: Attempting to send account creation email to {$user->email}");
        
        // Send email
        $result = email_to_user($user, $from, $subject, $message, $messagehtml);
        
        return $result;
    }

    /**
     * Send course enrollment email to student when they are enrolled in courses.
     *
     * @param stdClass $user The user object
     * @param array $enrolledcourses Array of enrolled courses
     * @return bool Success status
     */
    private static function send_course_enrollment_email($user, $enrolledcourses) {
        global $CFG, $SITE;

        if (empty($enrolledcourses)) {
            return true;
        }

        $from = \core_user::get_support_user();
        
        // Prepare email subject
        $subject = "Course Enrollment Confirmation - " . format_string($SITE->fullname);
        
        // Prepare email message
        $message = "Dear {$user->firstname} {$user->lastname},\n\n";
        $message .= "You have been successfully enrolled in the following courses:\n";
        $message .= "------------------------------------\n";
        foreach ($enrolledcourses as $course) {
            $message .= "• {$course['fullname']} ({$course['shortname']})\n";
        }
        $message .= "------------------------------------\n\n";
        $message .= "You can access your courses at: {$CFG->wwwroot}/my/\n\n";
        $message .= "If you need help, please contact: {$CFG->supportemail}";

        // HTML version
        $messagehtml = "<p>Dear {$user->firstname} {$user->lastname},</p>";
        $messagehtml .= "<p style='margin-top: 20px;'><strong>You have been successfully enrolled in the following courses:</strong></p>";
        $messagehtml .= "<div style='background-color: #e7f3ff; padding: 15px; border-left: 4px solid #2196F3;'>";
        $messagehtml .= "<ul style='margin: 0; padding-left: 20px;'>";
        foreach ($enrolledcourses as $course) {
            $courseurl = $CFG->wwwroot . '/course/view.php?id=' . ($course['courseid'] ?? $course['id']);
            $messagehtml .= "<li style='margin: 5px 0;'><a href='{$courseurl}' style='color: #2196F3; text-decoration: none;'><strong>{$course['fullname']}</strong></a> ({$course['shortname']})</li>";
        }
        $messagehtml .= "</ul>";
        $messagehtml .= "</div>";
        $messagehtml .= "<p style='margin-top: 15px;'><a href='{$CFG->wwwroot}/my/' style='background-color: #2196F3; color: white; padding: 10px 20px; text-decoration: none; display: inline-block;'>View My Courses</a></p>";
        $messagehtml .= "<hr><p><small>If you need help, please contact: {$CFG->supportemail}</small></p>";

        // Log the attempt
        error_log("AMS: Attempting to send enrollment email to {$user->email}");
        
        // Send email
        $result = email_to_user($user, $from, $subject, $message, $messagehtml);
        
        return $result;
    }

    /**
     * Send notification to administrators about newly created student.
     *
     * @param stdClass $user The user object
     * @param array $studentdata Additional student data
     * @param array $enrolledcourses Array of enrolled courses
     * @return void
     */
    private static function send_admin_notification($user, $studentdata, $enrolledcourses = []) {
        global $CFG, $SITE;

        // Get all site administrators
        $admins = get_admins();
        
        if (empty($admins)) {
            return; // No admins to notify
        }

        $from = \core_user::get_support_user();
        
        // Prepare notification subject
        $subject = "New Student Created: {$user->firstname} {$user->lastname}";
        
        // Prepare notification message
        $message = "A new student account has been created via AMS Integration.\n\n";
        $message .= "Student Details:\n";
        $message .= "------------------------------------\n";
        $message .= "Name: {$user->firstname} {$user->lastname}\n";
        $message .= "Email: {$user->email}\n";
        $message .= "Username: {$user->username}\n";
        $message .= "User ID: {$user->id}\n";
        
        if (!empty($studentdata['enrolmentnumber'])) {
            $message .= "Enrollment Number: {$studentdata['enrolmentnumber']}\n";
        }
        if (!empty($studentdata['batch'])) {
            $message .= "Batch: {$studentdata['batch']}\n";
        }
        if (!empty($studentdata['mainprogram'])) {
            $message .= "Main Program: {$studentdata['mainprogram']}\n";
        }
        if (!empty($studentdata['programregister'])) {
            $message .= "Program Register: {$studentdata['programregister']}\n";
        }
        if (!empty($studentdata['mobile'])) {
            $message .= "Mobile: {$studentdata['mobile']}\n";
        }
        
        $message .= "------------------------------------\n\n";
        
        // Add enrolled courses information
        if (!empty($enrolledcourses)) {
            $message .= "Automatically Enrolled in Courses:\n";
            $message .= "------------------------------------\n";
            foreach ($enrolledcourses as $course) {
                $message .= "• {$course['fullname']} ({$course['shortname']})\n";
            }
            $message .= "------------------------------------\n\n";
        }
        
        $message .= "View Profile: {$CFG->wwwroot}/user/profile.php?id={$user->id}\n";
        $message .= "Edit Profile: {$CFG->wwwroot}/user/editadvanced.php?id={$user->id}\n\n";
        $message .= "Created at: " . userdate(time()) . "\n";

        // HTML version
        $messagehtml = "<h3>New Student Account Created</h3>";
        $messagehtml .= "<p>A new student account has been created via <strong>AMS Integration</strong>.</p>";
        $messagehtml .= "<table style='border-collapse: collapse; width: 100%; max-width: 600px;'>";
        $messagehtml .= "<tr style='background-color: #f5f5f5;'><th style='padding: 10px; text-align: left; border: 1px solid #ddd;'>Field</th><th style='padding: 10px; text-align: left; border: 1px solid #ddd;'>Value</th></tr>";
        $messagehtml .= "<tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Name</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>{$user->firstname} {$user->lastname}</td></tr>";
        $messagehtml .= "<tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Email</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>{$user->email}</td></tr>";
        $messagehtml .= "<tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Username</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>{$user->username}</td></tr>";
        $messagehtml .= "<tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>User ID</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>{$user->id}</td></tr>";
        
        if (!empty($studentdata['enrolmentnumber'])) {
            $messagehtml .= "<tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Enrollment Number</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>{$studentdata['enrolmentnumber']}</td></tr>";
        }
        if (!empty($studentdata['batch'])) {
            $messagehtml .= "<tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Batch</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>{$studentdata['batch']}</td></tr>";
        }
        if (!empty($studentdata['mainprogram'])) {
            $messagehtml .= "<tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Main Program</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>{$studentdata['mainprogram']}</td></tr>";
        }
        if (!empty($studentdata['programregister'])) {
            $messagehtml .= "<tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Program Register</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>{$studentdata['programregister']}</td></tr>";
        }
        if (!empty($studentdata['mobile'])) {
            $messagehtml .= "<tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Mobile</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>{$studentdata['mobile']}</td></tr>";
        }
        
        $messagehtml .= "</table>";
        
        // Add enrolled courses in HTML
        if (!empty($enrolledcourses)) {
            $messagehtml .= "<h4 style='margin-top: 20px;'>Automatically Enrolled in Courses:</h4>";
            $messagehtml .= "<div style='background-color: #e7f3ff; padding: 15px; border-left: 4px solid #2196F3;'>";
            $messagehtml .= "<ul style='margin: 0; padding-left: 20px;'>";
            foreach ($enrolledcourses as $course) {
                $courseurl = $CFG->wwwroot . '/course/view.php?id=' . $course['courseid'];
                $messagehtml .= "<li style='margin: 5px 0;'><a href='{$courseurl}' style='color: #2196F3; text-decoration: none;'><strong>{$course['fullname']}</strong></a> ({$course['shortname']})</li>";
            }
            $messagehtml .= "</ul>";
            $messagehtml .= "</div>";
        }
        
        $messagehtml .= "<p style='margin-top: 20px;'>";
        $messagehtml .= "<a href='{$CFG->wwwroot}/user/profile.php?id={$user->id}' style='background-color: #2196F3; color: white; padding: 10px 20px; text-decoration: none; display: inline-block; margin-right: 10px;'>View Profile</a>";
        $messagehtml .= "<a href='{$CFG->wwwroot}/user/editadvanced.php?id={$user->id}' style='background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; display: inline-block;'>Edit Profile</a>";
        $messagehtml .= "</p>";
        $messagehtml .= "<hr><p><small>Created at: " . userdate(time()) . "</small></p>";

        // Send to all admins
        foreach ($admins as $admin) {
            email_to_user($admin, $from, $subject, $message, $messagehtml);
        }
    }

    /**
     * Returns description of method result value
     *
     * @return external_single_structure
     */
    public static function create_student_returns() {
        return new external_single_structure([
            'id'       => new external_value(PARAM_INT, 'User ID'),
            'username' => new external_value(PARAM_TEXT, 'Username'),
            'action'   => new external_value(PARAM_TEXT, 'Action performed: created or updated'),
            'emailsent' => new external_value(PARAM_BOOL, 'Whether welcome email was sent successfully'),
            'enrolledcourses' => new external_multiple_structure(
                new external_single_structure([
                    'courseid' => new external_value(PARAM_INT, 'Course ID'),
                    'shortname' => new external_value(PARAM_TEXT, 'Course shortname'),
                    'fullname' => new external_value(PARAM_TEXT, 'Course fullname'),
                ]),
                'List of courses automatically enrolled in',
                VALUE_OPTIONAL
            ),
            'enrollmenterrors' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Enrollment error message'),
                'Any errors encountered during auto-enrollment',
                VALUE_OPTIONAL
            ),
            'fielderrors' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Profile field error message'),
                'Any errors encountered during profile field update',
                VALUE_OPTIONAL
            ),
        ]);
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function enrol_student_parameters() {
        return new external_function_parameters([
            'username' => new external_value(PARAM_TEXT, 'Username or email of the student'),
            'courseshortcodes' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Course short code'),
                'List of course short codes to enroll the student in'
            ),
        ]);
    }

    /**
     * Enroll a student in courses using course short codes.
     *
     * @param string $username Username or email of the student
     * @param array $courseshortcodes List of course short codes
     * @return array
     */
    public static function enrol_student($username, $courseshortcodes) {
        global $DB;

        // Parameter validation.
        $params = self::validate_parameters(self::enrol_student_parameters(), [
            'username' => $username,
            'courseshortcodes' => $courseshortcodes,
        ]);
        $username = $params['username'];
        $courseshortcodes = $params['courseshortcodes'];

        // Context validation.
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/course:enrolconfig', $context);

        // Get user by username or email
        $user = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
        if (!$user) {
            // Try by email if username didn't work
            $user = $DB->get_record('user', ['email' => $username, 'deleted' => 0]);
        }
        
        if (!$user) {
            throw new \moodle_exception('invaliduser', 'error', '', null, 'User not found with username/email: ' . $username);
        }

        $enrolledcourses = [];
        $errors = [];
        $enrol = enrol_get_plugin('manual');
        
        if (!$enrol) {
            throw new \moodle_exception('pluginnotinstalled', 'enrol_manual');
        }

        foreach ($courseshortcodes as $shortcode) {
            try {
                // Get course by shortname
                $course = $DB->get_record('course', ['shortname' => $shortcode], '*', IGNORE_MISSING);
                
                if (!$course) {
                    $errors[] = [
                        'shortcode' => $shortcode,
                        'error' => 'Course not found with shortcode: ' . $shortcode
                    ];
                    error_log("AMS: Course not found with shortcode: {$shortcode}");
                    continue;
                }

                // Get or create manual enrolment instance
                $instance = $DB->get_record('enrol', [
                    'courseid' => $course->id,
                    'enrol' => 'manual',
                    'status' => ENROL_INSTANCE_ENABLED
                ], '*', IGNORE_MISSING);

                // If manual enrollment doesn't exist, create it
                if (!$instance) {
                    $instanceid = $enrol->add_instance($course);
                    $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
                }

                if ($instance) {
                    // Get student role ID
                    $studentrole = $DB->get_record('role', ['shortname' => 'student']);
                    $roleid = $studentrole ? $studentrole->id : 5;

                    // Check if already enrolled
                    if ($DB->record_exists('user_enrolments', [
                        'enrolid' => $instance->id,
                        'userid' => $user->id
                    ])) {
                        $errors[] = [
                            'shortcode' => $shortcode,
                            'error' => 'Student already enrolled in this course'
                        ];
                        error_log("AMS: User {$user->id} already enrolled in course {$shortcode}");
                        continue;
                    }

                    // Enroll the user
                    $enrol->enrol_user($instance, $user->id, $roleid, 0, 0, ENROL_USER_ACTIVE);
                    
                    $enrolledcourses[] = [
                        'courseid' => $course->id,
                        'shortcode' => $course->shortname,
                        'fullname' => $course->fullname
                    ];
                    
                    error_log("AMS: Enrolled user {$user->id} ({$username}) in course {$shortcode} (ID: {$course->id})");
                } else {
                    $errors[] = [
                        'shortcode' => $shortcode,
                        'error' => 'Manual enrolment instance could not be created'
                    ];
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'shortcode' => $shortcode,
                    'error' => $e->getMessage()
                ];
                error_log("AMS: Error enrolling user {$user->id} in {$shortcode}: " . $e->getMessage());
            }
        }

        // Send enrollment email if any courses were successfully enrolled
        if (!empty($enrolledcourses)) {
            self::send_course_enrollment_email($user, $enrolledcourses);
        }

        return [
            'status' => empty($errors) ? 'success' : 'partial',
            'userid' => $user->id,
            'username' => $user->username,
            'enrolledcourses' => $enrolledcourses,
            'errors' => $errors,
        ];
    }

    /**
     * Returns description of method result value
     *
     * @return external_single_structure
     */
    public static function enrol_student_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'Status message (success or partial)'),
            'userid' => new external_value(PARAM_INT, 'User ID of the enrolled student'),
            'username' => new external_value(PARAM_TEXT, 'Username of the enrolled student'),
            'enrolledcourses' => new external_multiple_structure(
                new external_single_structure([
                    'courseid' => new external_value(PARAM_INT, 'Course ID'),
                    'shortcode' => new external_value(PARAM_TEXT, 'Course short code'),
                    'fullname' => new external_value(PARAM_TEXT, 'Course full name'),
                ]),
                'List of courses successfully enrolled in'
            ),
            'errors' => new external_multiple_structure(
                new external_single_structure([
                    'shortcode' => new external_value(PARAM_TEXT, 'Course short code that failed'),
                    'error' => new external_value(PARAM_TEXT, 'Error message'),
                ]),
                'List of enrollment errors'
            ),
        ]);
    }

    /**
     * Ensure a custom profile field exists in the system.
     * If the field doesn't exist, attempt to create it.
     *
     * @param string $shortname The short name of the profile field
     * @return bool True if field exists or was created successfully, false otherwise
     */
    protected static function ensure_profile_field_exists($shortname) {
        global $DB;

        // Check if field already exists by shortname
        if ($DB->record_exists('user_info_field', ['shortname' => $shortname])) {
            return true;
        }

        // Define field configurations
        $fieldconfigs = [
            'dob' => [
                'datatype' => 'datetime',
                'name' => 'Date of Birth',
                'description' => 'Student date of birth',
                'categoryid' => 1,
            ],
            'gender' => [
                'datatype' => 'text',
                'name' => 'Gender',
                'description' => 'Student gender',
                'categoryid' => 1,
            ],
            'mobile' => [
                'datatype' => 'text',
                'name' => 'Mobile Number',
                'description' => 'Student mobile number',
                'categoryid' => 1,
            ],
            'enrolmentnumber' => [
                'datatype' => 'text',
                'name' => 'Enrolment Number',
                'description' => 'Student enrolment number',
                'categoryid' => 1,
            ],
            'applicationnumber' => [
                'datatype' => 'text',
                'name' => 'Application Number',
                'description' => 'Student application number',
                'categoryid' => 1,
            ],
            'mainprogram' => [
                'datatype' => 'text',
                'name' => 'Main Program',
                'description' => 'Student main program',
                'categoryid' => 1,
            ],
            'programregister' => [
                'datatype' => 'text',
                'name' => 'Program Register',
                'description' => 'Program registered for',
                'categoryid' => 1,
            ],
            'elective' => [
                'datatype' => 'text',
                'name' => 'Elective',
                'description' => 'Student elective courses',
                'categoryid' => 1,
            ],
            'usn' => [
                'datatype' => 'text',
                'name' => 'USN',
                'description' => 'University Seat Number',
                'categoryid' => 1,
            ],
            'batch' => [
                'datatype' => 'text',
                'name' => 'Batch',
                'description' => 'Student batch',
                'categoryid' => 1,
            ],
            'fathername' => [
                'datatype' => 'text',
                'name' => 'Father Name',
                'description' => 'Student father name',
                'categoryid' => 1,
            ],
            'mothername' => [
                'datatype' => 'text',
                'name' => 'Mother Name',
                'description' => 'Student mother name',
                'categoryid' => 1,
            ],
            'nationality' => [
                'datatype' => 'text',
                'name' => 'Nationality',
                'description' => 'Student nationality',
                'categoryid' => 1,
            ],
            'debid' => [
                'datatype' => 'text',
                'name' => 'DEB ID',
                'description' => 'Digital Education Board ID',
                'categoryid' => 1,
            ],
            'abcid' => [
                'datatype' => 'text',
                'name' => 'ABC ID',
                'description' => 'Academic Bank of Credits ID',
                'categoryid' => 1,
            ],
            // LMS Student Information field that create_student writes 'mainprogram' into.
            // Unlike the legacy AMS shortnames above it belongs in the LMS category, so it is
            // resolved by name; 'categoryid' is only the fallback if that category is absent.
            'program_registered_for' => [
                'datatype' => 'text',
                'name' => 'Program registered for',
                'description' => 'Programme the student registered for',
                'categoryname' => 'LMS Student Information',
                'categoryid' => 1,
            ],
        ];

        // Check if we have a configuration for this field
        if (!isset($fieldconfigs[$shortname])) {
            error_log("AMS: No configuration defined for profile field '{$shortname}'");
            return false;
        }

        $config = $fieldconfigs[$shortname];

        try {
            // Prefer a category matched by name when the config names one (LMS fields live in
            // their own category, not the AMS default).
            if (!empty($config['categoryname'])) {
                $namedcat = $DB->get_record('user_info_category', ['name' => $config['categoryname']], 'id', IGNORE_MULTIPLE);
                if ($namedcat) {
                    $config['categoryid'] = $namedcat->id;
                }
            }

            // Ensure the default category exists
            if (!$DB->record_exists('user_info_category', ['id' => $config['categoryid']])) {
                // Create default category if it doesn't exist
                $category = new \stdClass();
                $category->name = 'Student Information';
                $category->sortorder = 1;
                $categoryid = $DB->insert_record('user_info_category', $category);
                $config['categoryid'] = $categoryid;
            }

            // Create the profile field
            $field = new \stdClass();
            $field->shortname = $shortname;
            $field->name = $config['name'];
            $field->datatype = $config['datatype'];
            $field->description = $config['description'];
            $field->descriptionformat = FORMAT_HTML;
            $field->categoryid = $config['categoryid'];
            $field->sortorder = $DB->count_records('user_info_field') + 1;
            $field->required = 0;
            $field->locked = 0;
            $field->visible = 2; // Visible to everyone
            $field->forceunique = 0;
            $field->signup = 0;
            $field->defaultdata = '';
            $field->defaultdataformat = FORMAT_HTML;

            $fieldid = $DB->insert_record('user_info_field', $field);
            
            if ($fieldid) {
                error_log("AMS: Successfully created profile field '{$shortname}' (ID: {$fieldid})");
                return true;
            }
        } catch (\Exception $e) {
            error_log("AMS: Exception while creating profile field '{$shortname}': " . $e->getMessage());
            return false;
        }

        return false;
    }

    // ── get_student_status ───────────────────────────────────────────────────

    public static function get_student_status_parameters() {
        return new external_function_parameters([
            'enrollment_no' => new external_value(PARAM_TEXT,  'Enrolment number (idnumber)', VALUE_OPTIONAL, ''),
            'email'         => new external_value(PARAM_EMAIL, 'Student email',               VALUE_OPTIONAL, ''),
        ]);
    }

    /**
     * Get a student's LMS account status and exam present/absent status per semester.
     *
     * @param string $enrollment_no Enrolment number (idnumber field)
     * @param string $email Student email
     * @return array
     */
    public static function get_student_status($enrollment_no = '', $email = '') {
        global $DB;

        $params = self::validate_parameters(self::get_student_status_parameters(), [
            'enrollment_no' => $enrollment_no,
            'email'         => $email,
        ]);

        if (empty($params['enrollment_no']) && empty($params['email'])) {
            throw new \invalid_parameter_exception('At least one of enrollment_no or email is required.');
        }

        $context = context_system::instance();
        self::validate_context($context);

        $user = null;
        if (!empty($params['enrollment_no'])) {
            $user = $DB->get_record('user', ['idnumber' => $params['enrollment_no']]);
        }
        if (!$user && !empty($params['email'])) {
            $user = $DB->get_record('user', ['email' => $params['email']]);
        }

        if (!$user) {
            return [
                'found'            => false,
                'userid'           => 0,
                'username'         => '',
                'email'            => '',
                'idnumber'         => '',
                'firstname'        => '',
                'lastname'         => '',
                'lms_status'       => 'not_found',
                'suspended'        => false,
                'deleted'          => false,
                'lastaccess'       => 0,
                'lastaccess_human' => '',
                'semesters'        => [],
            ];
        }

        if ($user->deleted) {
            $status = 'deleted';
        } else if ($user->suspended) {
            $status = 'suspended';
        } else {
            $status = 'active';
        }

        // Exam status: for every semester the student is enrolled in, count quizzes
        // with and without a finalgrade (present = has grade, absent = no grade).
        $sql = "
            SELECT
                cat_batch.name  AS batch,
                cat_prog.name   AS program,
                cat_sem.name    AS semester,
                COUNT(DISTINCT q.id) AS total_exams,
                COUNT(DISTINCT CASE WHEN gg.finalgrade IS NOT NULL THEN q.id END) AS present,
                COUNT(DISTINCT CASE WHEN gg.finalgrade IS NULL THEN q.id END) AS absent
            FROM {user_enrolments} ue
            JOIN {enrol} e              ON ue.enrolid = e.id
            JOIN {course} c             ON e.courseid = c.id
            JOIN {course_categories} cat_sem   ON c.category = cat_sem.id
            JOIN {course_categories} cat_prog  ON cat_sem.parent = cat_prog.id
            JOIN {course_categories} cat_batch ON cat_prog.parent = cat_batch.id
            JOIN {quiz} q               ON q.course = c.id
            JOIN {grade_items} gi       ON gi.iteminstance = q.id
                                        AND gi.itemmodule = 'quiz'
                                        AND gi.courseid = c.id
            LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = ue.userid
            WHERE ue.userid = :userid
              AND cat_sem.name LIKE 'Semester%'
            GROUP BY cat_batch.name, cat_prog.name, cat_sem.name
            ORDER BY cat_batch.name, cat_prog.name, cat_sem.name
        ";

        $records = $DB->get_records_sql($sql, ['userid' => $user->id]);

        $semesters = [];
        foreach ($records as $row) {
            $total   = (int) $row->total_exams;
            $present = (int) $row->present;
            $absent  = (int) $row->absent;

            $semesters[] = [
                'batch'          => $row->batch,
                'program'        => $row->program,
                'semester'       => $row->semester,
                'total_exams'    => $total,
                'present'        => $present,
                'absent'         => $absent,
                'status_summary' => $present > 0 ? 'present' : 'absent',
            ];
        }

        return [
            'found'            => true,
            'userid'           => (int) $user->id,
            'username'         => $user->username,
            'email'            => $user->email,
            'idnumber'         => $user->idnumber,
            'firstname'        => $user->firstname,
            'lastname'         => $user->lastname,
            'lms_status'       => $status,
            'suspended'        => (bool) $user->suspended,
            'deleted'          => (bool) $user->deleted,
            'lastaccess'       => (int) $user->lastaccess,
            'lastaccess_human' => $user->lastaccess ? userdate($user->lastaccess) : '',
            'semesters'        => $semesters,
        ];
    }

    public static function get_student_status_returns() {
        return new external_single_structure([
            'found'            => new external_value(PARAM_BOOL, 'Whether student was found'),
            'userid'           => new external_value(PARAM_INT,  'Moodle user ID'),
            'username'         => new external_value(PARAM_TEXT, 'Username'),
            'email'            => new external_value(PARAM_TEXT, 'Email'),
            'idnumber'         => new external_value(PARAM_TEXT, 'Enrolment number (idnumber)'),
            'firstname'        => new external_value(PARAM_TEXT, 'First name'),
            'lastname'         => new external_value(PARAM_TEXT, 'Last name'),
            'lms_status'       => new external_value(PARAM_TEXT, 'active | suspended | deleted | not_found'),
            'suspended'        => new external_value(PARAM_BOOL, 'Account suspended flag'),
            'deleted'          => new external_value(PARAM_BOOL, 'Account deleted flag'),
            'lastaccess'       => new external_value(PARAM_INT,  'Last access unix timestamp (0 = never)'),
            'lastaccess_human' => new external_value(PARAM_TEXT, 'Last access human readable string'),
            'semesters'        => new external_multiple_structure(
                new external_single_structure([
                    'batch'          => new external_value(PARAM_TEXT, 'Batch name (e.g. Jan, 2025 Batch)'),
                    'program'        => new external_value(PARAM_TEXT, 'Program name (e.g. Master of Business Administration)'),
                    'semester'       => new external_value(PARAM_TEXT, 'Semester name (e.g. Semester - 01)'),
                    'total_exams'    => new external_value(PARAM_INT,  'Total number of exams/quizzes in this semester'),
                    'present'        => new external_value(PARAM_INT,  'Number of exams with a grade (present)'),
                    'absent'         => new external_value(PARAM_INT,  'Number of exams without a grade (absent)'),
                    'status_summary' => new external_value(PARAM_TEXT, 'present if attended any exam, absent if none'),
                ]),
                'Exam status per semester'
            ),
        ]);
    }

    // =========================================================================
    // local_ams_update_student
    // Updates profile fields (elective, programregister) for an existing student.
    // Lookup by email or enrolmentnumber.
    // =========================================================================

    public static function update_student_parameters() {
        return new external_function_parameters([
            'student' => new external_single_structure([
                'email'           => new external_value(PARAM_EMAIL, 'Email address (used to look up user)', VALUE_OPTIONAL),
                'enrolmentnumber' => new external_value(PARAM_TEXT,  'Enrolment number (used to look up user)', VALUE_OPTIONAL),
                'elective'        => new external_value(PARAM_TEXT,  'New elective value', VALUE_OPTIONAL),
                'programregister' => new external_value(PARAM_TEXT,  'New program register value', VALUE_OPTIONAL),
            ]),
        ]);
    }

    public static function update_student($student) {
        global $DB;

        $params = self::validate_parameters(self::update_student_parameters(), ['student' => $student]);
        $data = $params['student'];

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/user:update', $context);

        // Normalise identifiers from the request.
        $email = !empty($data['email']) ? strtolower(trim($data['email'])) : '';
        $enrol = !empty($data['enrolmentnumber']) ? trim($data['enrolmentnumber']) : '';

        $user = null;
        $setemail = false; // Whether to write the incoming email onto the matched user.

        // 1) Look up by email.
        if ($email !== '') {
            $matches = $DB->get_records('user', ['email' => $email, 'deleted' => 0], 'id ASC', 'id');
            $count = count($matches);
            if ($count >= 2) {
                // Block: multiple live accounts share this email.
                self::send_duplicate_notification('email', $email, array_keys($matches), $data);
                throw new \moodle_exception('duplicateemail', 'local_ams', '', null,
                    "Multiple ({$count}) accounts exist with email {$email}; update blocked.");
            } else if ($count === 1) {
                $user = $DB->get_record('user', ['id' => reset($matches)->id], '*', MUST_EXIST);
            }
            // $count === 0 -> fall through to enrolment lookup (email-change scenario).
        }

        // 2) Email not found (or not supplied) -> look up by enrolment number.
        // The real enrolment number lives in core user.idnumber (set on create + backfilled);
        // the legacy 'enrolmentnumber' profile field is empty and is intentionally NOT used.
        if (!$user && $enrol !== '') {
            $matches = $DB->get_records('user', ['idnumber' => $enrol, 'deleted' => 0], 'id ASC', 'id');
            $count = count($matches);
            if ($count >= 2) {
                self::send_duplicate_notification('enrolment number', $enrol, array_keys($matches), $data);
                throw new \moodle_exception('duplicateenrolment', 'local_ams', '', null,
                    "Multiple ({$count}) accounts exist with enrolment number {$enrol}; update blocked.");
            } else if ($count === 1) {
                $user = $DB->get_record('user', ['id' => reset($matches)->id], '*', MUST_EXIST);
                // Matched by enrolment because the incoming email was not found -> update the email.
                if ($email !== '') {
                    $setemail = true;
                }
            }
        }

        if (!$user) {
            throw new \moodle_exception('usernotfound', 'local_ams', '', null, 'No user found for given email/enrolmentnumber');
        }

        // Apply the email change (matched by enrolment, incoming email is new to the LMS).
        if ($setemail && strtolower($user->email) !== $email) {
            $userupdate = (object) ['id' => $user->id, 'email' => $email];
            // Keep the login username aligned with the email if the new value is free.
            $clash = $DB->get_record('user', ['username' => $email, 'deleted' => 0]);
            if (!$clash || (int) $clash->id === (int) $user->id) {
                $userupdate->username = $email;
            } else {
                error_log("AMS update_student: user {$user->id} email -> {$email} but username kept (collision with user {$clash->id})");
            }
            user_update_user($userupdate, false, false);
            $user = $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
            error_log("AMS update_student: user {$user->id} email updated to {$email} (matched by enrolment number {$enrol})");
        }

        // Update only the provided profile fields, writing into the LMS Student Information
        // category. Map the AMS request key -> LMS profile field shortname (matches the
        // create_student remapping): programregister -> program_registered_for.
        $fieldmap = [
            'elective'        => 'elective',               // already an LMS field
            'programregister' => 'program_registered_for', // LMS field (not the dead AMS 'programregister')
        ];
        foreach ($fieldmap as $datakey => $targetshortname) {
            if (empty($data[$datakey])) {
                continue;
            }
            if ($DB->record_exists('user_info_field', ['shortname' => $targetshortname])) {
                $userobj = new stdClass();
                $userobj->id = $user->id;
                $userobj->{'profile_field_' . $targetshortname} = $data[$datakey];
                profile_save_data($userobj);
            }
        }

        \local_ams\event\student_updated::create([
            'objectid'      => $user->id,
            'relateduserid' => $user->id,
            'context'       => \context_user::instance($user->id),
            'other'         => ['action' => 'updated', 'source' => 'update_student'],
        ])->trigger();

        return ['id' => $user->id, 'status' => 'updated'];
    }

    public static function update_student_returns() {
        return new external_single_structure([
            'id'     => new external_value(PARAM_INT,  'User ID'),
            'status' => new external_value(PARAM_TEXT, 'Result status'),
        ]);
    }

    // =========================================================================
    // local_ams_get_student_lastaccess
    // Look up a student by their provisional enrollment number (the
    // 'enrolmentnumber' custom profile field) and return their last access time.
    // Returns found=false when no matching student exists.
    // =========================================================================

    public static function get_student_lastaccess_parameters() {
        return new external_function_parameters([
            'enrolmentnumber' => new external_value(PARAM_TEXT, 'Provisional enrollment number'),
        ]);
    }

    /**
     * Get a student's last access time by provisional enrollment number.
     *
     * @param string $enrolmentnumber Provisional enrollment number (enrolmentnumber custom field)
     * @return array
     */
    public static function get_student_lastaccess($enrolmentnumber) {
        global $DB;

        $params = self::validate_parameters(self::get_student_lastaccess_parameters(), [
            'enrolmentnumber' => $enrolmentnumber,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/user:viewdetails', $context);

        // Look up the user by enrolment number via core user.idnumber (set on create +
        // backfilled). The legacy 'enrolmentnumber' profile field is empty and unused.
        $enrol = trim((string) $params['enrolmentnumber']);
        $user = false;
        if ($enrol !== '') {
            $user = $DB->get_record('user', ['idnumber' => $enrol, 'deleted' => 0], '*', IGNORE_MULTIPLE);
        }

        if (!$user) {
            return [
                'found'            => false,
                'userid'           => 0,
                'lastaccess'       => 0,
                'lastaccess_human' => '',
            ];
        }

        return [
            'found'            => true,
            'userid'           => (int) $user->id,
            'lastaccess'       => (int) $user->lastaccess,
            'lastaccess_human' => $user->lastaccess ? userdate($user->lastaccess) : '',
        ];
    }

    public static function get_student_lastaccess_returns() {
        return new external_single_structure([
            'found'            => new external_value(PARAM_BOOL, 'Whether a matching student was found'),
            'userid'           => new external_value(PARAM_INT,  'Moodle user ID (0 if not found)'),
            'lastaccess'       => new external_value(PARAM_INT,  'Last access unix timestamp (0 = never / not found)'),
            'lastaccess_human' => new external_value(PARAM_TEXT, 'Last access human readable string'),
        ]);
    }

    // =========================================================================
    // local_ams_update_user_status
    // Set a user's account status (active|suspended) by provisional enrollment
    // number. On suspend, record a note in the user's description and kill their
    // sessions. On activate, clear the description. Returns code 200 on success,
    // 400 on user-not-found or invalid input.
    // =========================================================================

    public static function update_user_status_parameters() {
        return new external_function_parameters([
            'enrolmentnumber' => new external_value(PARAM_TEXT,  'Provisional enrollment number'),
            'status'          => new external_value(PARAM_ALPHA, 'New status: active or suspended'),
        ]);
    }

    /**
     * Set a user's account status (active|suspended) by provisional enrollment number.
     *
     * @param string $enrolmentnumber Provisional enrollment number (enrolmentnumber custom field)
     * @param string $status active|suspended
     * @return array
     */
    public static function update_user_status($enrolmentnumber, $status) {
        global $DB;

        $params = self::validate_parameters(self::update_user_status_parameters(), [
            'enrolmentnumber' => $enrolmentnumber,
            'status'          => $status,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/user:update', $context);

        // Validate the requested status.
        $status = strtolower($params['status']);
        if ($status !== 'active' && $status !== 'suspended') {
            return [
                'code'      => 400,
                'status'    => 'error',
                'message'   => "Invalid status '{$params['status']}': must be 'active' or 'suspended'",
                'userid'    => 0,
                'suspended' => false,
            ];
        }

        // Look up the user by enrolment number. The real enrolment number lives in core
        // user.idnumber (set on create + backfilled); the legacy 'enrolmentnumber' profile
        // field is empty and is intentionally NOT used.
        $enrol = trim((string) $params['enrolmentnumber']);
        $user = false;
        if ($enrol !== '') {
            $matches = $DB->get_records('user', ['idnumber' => $enrol, 'deleted' => 0], 'id ASC', 'id');
            $count = count($matches);
            if ($count >= 2) {
                // Block: multiple live accounts share this enrolment number.
                self::send_duplicate_notification('enrolment number', $enrol, array_keys($matches),
                    ['enrolmentnumber' => $enrol, 'status' => $params['status']]);
                return [
                    'code'      => 409,
                    'status'    => 'error',
                    'message'   => "Multiple ({$count}) accounts exist with enrolment number {$enrol}; status change blocked.",
                    'userid'    => 0,
                    'suspended' => false,
                ];
            } else if ($count === 1) {
                $user = $DB->get_record('user', ['id' => reset($matches)->id], '*', MUST_EXIST);
            }
        }

        if (!$user) {
            return [
                'code'      => 400,
                'status'    => 'error',
                'message'   => 'User not found',
                'userid'    => 0,
                'suspended' => false,
            ];
        }

        try {
            $update = new stdClass();
            $update->id = $user->id;

            if ($status === 'suspended') {
                $update->suspended = 1;
                $update->description = 'Suspended from ERP on ' . userdate(time());
                $update->descriptionformat = FORMAT_PLAIN;
                user_update_user($update, false, false);
                // Force the user out of any active sessions immediately.
                \core\session\manager::kill_user_sessions($user->id);
                error_log("AMS: Suspended user {$user->id} ({$user->email}) via ERP status update");
            } else {
                $update->suspended = 0;
                $update->description = '';
                $update->descriptionformat = FORMAT_PLAIN;
                user_update_user($update, false, false);
                error_log("AMS: Activated user {$user->id} ({$user->email}) via ERP status update");
            }
        } catch (\Exception $e) {
            error_log("AMS: Failed to update status for user {$user->id}: " . $e->getMessage());
            return [
                'code'      => 400,
                'status'    => 'error',
                'message'   => 'Failed to update status: ' . $e->getMessage(),
                'userid'    => (int) $user->id,
                'suspended' => (bool) $user->suspended,
            ];
        }

        \local_ams\event\status_changed::create([
            'objectid'      => $user->id,
            'relateduserid' => $user->id,
            'context'       => \context_user::instance($user->id),
            'other'         => ['status' => $status, 'source' => 'update_user_status'],
        ])->trigger();

        return [
            'code'      => 200,
            'status'    => 'success',
            'message'   => "User status set to {$status}",
            'userid'    => (int) $user->id,
            'suspended' => $status === 'suspended',
        ];
    }

    public static function update_user_status_returns() {
        return new external_single_structure([
            'code'      => new external_value(PARAM_INT,  'Result code: 200 success, 400 failure'),
            'status'    => new external_value(PARAM_TEXT, 'success or error'),
            'message'   => new external_value(PARAM_TEXT, 'Human readable result message'),
            'userid'    => new external_value(PARAM_INT,  'Moodle user ID (0 if not found)'),
            'suspended' => new external_value(PARAM_BOOL, 'Whether the account is now suspended'),
        ]);
    }

    // =========================================================================
    // local_ams_reset_password
    // Generate a new random password for an existing learner (looked up by
    // provisional enrolment number or email), store it, force a change at next
    // login, and email the updated login details via the branded local_welcome
    // reset template. Returns code 200 on success, 400 on failure.
    // =========================================================================

    public static function reset_password_parameters() {
        return new external_function_parameters([
            'enrolmentnumber' => new external_value(PARAM_TEXT,  'Provisional enrollment number', VALUE_OPTIONAL, ''),
            'email'           => new external_value(PARAM_EMAIL, 'Student email',                 VALUE_OPTIONAL, ''),
        ]);
    }

    /**
     * Reset a learner's password and email them the new credentials.
     *
     * @param string $enrolmentnumber Provisional enrollment number (idnumber / enrolmentnumber field)
     * @param string $email Student email
     * @return array
     */
    public static function reset_password($enrolmentnumber = '', $email = '') {
        global $DB;

        $params = self::validate_parameters(self::reset_password_parameters(), [
            'enrolmentnumber' => $enrolmentnumber,
            'email'           => $email,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/user:update', $context);

        if (empty($params['enrolmentnumber']) && empty($params['email'])) {
            return [
                'code'      => 400,
                'status'    => 'error',
                'message'   => 'At least one of enrolmentnumber or email is required',
                'userid'    => 0,
                'emailsent' => false,
            ];
        }

        // Resolve exactly one live account. Priority: core idnumber -> enrolmentnumber
        // profile field -> email. Refuse to act if the identifier is ambiguous.
        $user = false;
        if (!empty($params['enrolmentnumber'])) {
            $enrol = trim($params['enrolmentnumber']);
            $ids = array_keys($DB->get_records('user', ['idnumber' => $enrol, 'deleted' => 0], 'id ASC', 'id'));
            if (count($ids) >= 2) {
                return self::reset_password_error("Multiple accounts share enrolment number {$enrol}; reset blocked");
            } else if (count($ids) === 1) {
                $user = $DB->get_record('user', ['id' => reset($ids)], '*', MUST_EXIST);
            }
            if (!$user) {
                $pids = self::find_users_by_profile_field('enrolmentnumber', $enrol);
                if (count($pids) >= 2) {
                    return self::reset_password_error("Multiple accounts share enrolment number {$enrol}; reset blocked");
                } else if (count($pids) === 1) {
                    $user = $DB->get_record('user', ['id' => reset($pids)], '*', MUST_EXIST);
                }
            }
        }

        if (!$user && !empty($params['email'])) {
            $emaillc = strtolower(trim($params['email']));
            $ids = array_keys($DB->get_records('user', ['email' => $emaillc, 'deleted' => 0], 'id ASC', 'id'));
            if (count($ids) >= 2) {
                return self::reset_password_error("Multiple accounts share email {$emaillc}; reset blocked");
            } else if (count($ids) === 1) {
                $user = $DB->get_record('user', ['id' => reset($ids)], '*', MUST_EXIST);
            }
        }

        if (!$user) {
            return [
                'code'      => 400,
                'status'    => 'error',
                'message'   => 'User not found',
                'userid'    => 0,
                'emailsent' => false,
            ];
        }

        // Only internally-authenticated accounts have a Moodle-managed password.
        $auth = get_auth_plugin($user->auth);
        if (!$auth->is_internal() || !$auth->can_change_password()) {
            return [
                'code'      => 400,
                'status'    => 'error',
                'message'   => "Cannot reset password for externally-authenticated account (auth: {$user->auth})",
                'userid'    => (int) $user->id,
                'emailsent' => false,
            ];
        }

        try {
            $plainpassword = class_exists('\local_welcome\message')
                ? \local_welcome\message::new_password()
                : generate_password();
            update_internal_user_password($user, $plainpassword);

            if (!class_exists('\local_welcome\message') || \local_welcome\message::forcepasswordchange_enabled()) {
                set_user_preference('auth_forcepasswordchange', 1, $user);
            }

            // Invalidate existing sessions so the old password stops working immediately.
            \core\session\manager::destroy_user_sessions($user->id);

            if (class_exists('\local_welcome\message')) {
                $emailsent = \local_welcome\message::send_credentials_email($user, $plainpassword, 'reset');
            } else {
                $emailsent = self::send_account_creation_email($user, $plainpassword);
            }

            error_log("AMS: reset password for user {$user->id} ({$user->email}); emailsent=" . ($emailsent ? '1' : '0'));
        } catch (\Exception $e) {
            error_log("AMS: Failed to reset password for user {$user->id}: " . $e->getMessage());
            return [
                'code'      => 400,
                'status'    => 'error',
                'message'   => 'Failed to reset password: ' . $e->getMessage(),
                'userid'    => (int) $user->id,
                'emailsent' => false,
            ];
        }

        return [
            'code'      => 200,
            'status'    => 'success',
            'message'   => $emailsent ? 'Password reset and emailed' : 'Password reset but email failed to send',
            'userid'    => (int) $user->id,
            'emailsent' => (bool) $emailsent,
        ];
    }

    /**
     * Build a uniform 400 error payload for reset_password ambiguity/failure cases.
     *
     * @param string $message
     * @return array
     */
    private static function reset_password_error($message) {
        return [
            'code'      => 400,
            'status'    => 'error',
            'message'   => $message,
            'userid'    => 0,
            'emailsent' => false,
        ];
    }

    public static function reset_password_returns() {
        return new external_single_structure([
            'code'      => new external_value(PARAM_INT,  'Result code: 200 success, 400 failure'),
            'status'    => new external_value(PARAM_TEXT, 'success or error'),
            'message'   => new external_value(PARAM_TEXT, 'Human readable result message'),
            'userid'    => new external_value(PARAM_INT,  'Moodle user ID (0 if not found)'),
            'emailsent' => new external_value(PARAM_BOOL, 'Whether the reset email was sent'),
        ]);
    }
}
