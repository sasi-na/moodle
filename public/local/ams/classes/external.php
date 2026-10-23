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
                'lastname'          => new external_value(PARAM_TEXT, 'Last name'),
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

        // Context validation.
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/user:create', $context);

        // Check if user already exists.
        $existinguser = $DB->get_record('user', ['email' => $studentdata['email'], 'deleted' => 0]);
        $isupdate = $existinguser !== false;
        
        if ($isupdate) {
            // User exists - update mode
            require_capability('moodle/user:update', $context);
            $userid = $existinguser->id;
            $newuser = $existinguser;
            $plainpassword = null; // Don't generate new password for updates
            $emailsent = false; // Don't send welcome email for updates
            
            // Update basic user fields if provided
            $userupdated = false;
            if (!empty($studentdata['firstname']) && $newuser->firstname != $studentdata['firstname']) {
                $newuser->firstname = $studentdata['firstname'];
                $userupdated = true;
            }
            if (!empty($studentdata['lastname']) && $newuser->lastname != $studentdata['lastname']) {
                $newuser->lastname = $studentdata['lastname'];
                $userupdated = true;
            }
            if (!empty($studentdata['email']) && $newuser->email != $studentdata['email']) {
                $newuser->email = $studentdata['email'];
                $userupdated = true;
            }
            if ($userupdated) {
                user_update_user($newuser, false, false);
                error_log("AMS: Updated basic user fields for existing user {$userid}");
            }
            error_log("AMS: Updating existing user {$userid} ({$newuser->email}) with all profile fields");
        } else {
            // User doesn't exist - create mode
            // Prepare user data for creation.
            $user = new stdClass();
            $user->firstname = $studentdata['firstname'];
            $user->lastname = $studentdata['lastname'];
            $user->email = $studentdata['email'];
            $user->username = $studentdata['email']; // Using email as username
            
            // Generate a random secure password
            $plainpassword = generate_password();
            $user->password = $plainpassword;
            
            $user->confirmed = 1;
            $user->mnethostid = $CFG->mnet_localhost_id;
            $user->auth = 'manual';
            $user->lang = $CFG->lang;
            $user->timezone = 99; // Server timezone
            
            // Create the user.
            $userid = user_create_user($user, true, false);
            
            // Get the complete user object
            $newuser = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        }
        
        // Update custom profile fields (works for both create and update).
        $customfields = [
            'dob' => $studentdata['dob'] ?? null,
            'gender' => $studentdata['gender'] ?? null,
            'mobile' => $studentdata['mobile'] ?? null,
            'enrolmentnumber' => $studentdata['enrolmentnumber'] ?? null,
            'applicationnumber' => $studentdata['applicationnumber'] ?? null,
            'mainprogram' => $studentdata['mainprogram'] ?? null,
            'programregister' => $studentdata['programregister'] ?? null,
            'elective' => $studentdata['elective'] ?? null,
            'usn' => $studentdata['usn'] ?? null,
            'batch' => $studentdata['batch'] ?? null,
            'fathername' => $studentdata['fathername'] ?? null,
            'mothername' => $studentdata['mothername'] ?? null,
            'nationality' => $studentdata['nationality'] ?? null,
            'debid' => $studentdata['debid'] ?? null,
            'abcid' => $studentdata['abcid'] ?? null,
        ];

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
            // Send account creation email (login details)
            $emailsent = self::send_account_creation_email($newuser, $plainpassword);

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
        ];

        // Check if we have a configuration for this field
        if (!isset($fieldconfigs[$shortname])) {
            error_log("AMS: No configuration defined for profile field '{$shortname}'");
            return false;
        }

        $config = $fieldconfigs[$shortname];

        try {
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

    /**
     * Resolve a Moodle user by AMS enrolment number.
     *
     * Students created via local_ams_create_student store the enrolment number in the
     * `enrolmentnumber` custom profile field (user_info_data), NOT in core user.idnumber
     * (which the plugin never sets). So we match that profile field first — mirroring
     * create_student's own field write — and fall back to user.idnumber for any accounts
     * provisioned by another import that did populate it.
     *
     * Does NOT filter on `deleted`: get_student_status deliberately reports deleted and
     * suspended accounts (lms_status = deleted | suspended), unlike the create path.
     *
     * @param string $enrolmentno Enrolment number value.
     * @return \stdClass|false Moodle user record, or false if none matched.
     */
    protected static function find_user_by_enrolment_no($enrolmentno) {
        global $DB;

        $field = $DB->get_record('user_info_field', ['shortname' => 'enrolmentnumber']);
        if ($field) {
            $datarow = $DB->get_record('user_info_data', [
                'fieldid' => $field->id,
                'data'    => $enrolmentno,
            ], '*', IGNORE_MULTIPLE);
            if ($datarow) {
                $user = $DB->get_record('user', ['id' => $datarow->userid]);
                if ($user) {
                    return $user;
                }
            }
        }

        // Fallback: accounts whose core idnumber was set by a non-AMS import path.
        return $DB->get_record('user', ['idnumber' => $enrolmentno], '*', IGNORE_MULTIPLE);
    }

    public static function get_student_status_parameters() {
        return new external_function_parameters([
            'enrollment_no' => new external_value(PARAM_TEXT,  'Enrolment number (matches the enrolmentnumber profile field)', VALUE_OPTIONAL, ''),
            'email'         => new external_value(PARAM_EMAIL, 'Student email',               VALUE_OPTIONAL, ''),
        ]);
    }

    /**
     * Get a student's LMS account status and exam present/absent status per semester.
     *
     * @param string $enrollment_no Enrolment number (matches the enrolmentnumber profile field)
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
            $user = self::find_user_by_enrolment_no($params['enrollment_no']);
        }
        if (!$user && !empty($params['email'])) {
            $user = $DB->get_record('user', ['email' => $params['email']], '*', IGNORE_MULTIPLE);
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
}
