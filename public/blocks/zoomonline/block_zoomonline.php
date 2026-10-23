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

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/blocks/zoomonline/amazon/simple_storage_service.php');
require_once($CFG->dirroot . '/blocks/zoomonline/classes/block_zoomonline_zoom_helper.php');

/**
 * Zoom Online block class.
 *
 * This block provides integration with Zoom, allowing users to easily access and manage Zoom meetings within Moodle.
 *
 * @package    block_zoomonline
 * @copyright  2024 Ciaran Mac Donncha
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_zoomonline extends block_base {

    /**
     * Initializes the block's title.
     *
     * This method sets the title for the Zoom Online block, which will be displayed
     * at the top of the block on the Moodle site.
     *
     * @return void
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_zoomonline');
    }

    /**
     * Gets the content for the block.
     *
     * This function is responsible for generating the content displayed within the Zoom Online block.
     * It returns a content object with the text and footer properties that are rendered on the page.
     *
     * @return stdClass|null The content object with properties text and footer, or null if content should not be displayed.
     */
    public function get_content() {

        global $USER, $COURSE;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $coursecontext = context_course::instance($COURSE->id);

        $clientid = get_config('block_zoomonline', 'client_id');
        $clientsecret = get_config('block_zoomonline', 'client_secret');
        $accountid = get_config('block_zoomonline', 'account_id');

        if (get_config('block_zoomonline', 'useattendance')) {
            $installedmodules = core_plugin_manager::instance()->get_plugins_of_type('mod');

            if (!array_key_exists('attendance', $installedmodules) &&
                    has_capability('moodle/course:manageactivities', $coursecontext, $USER->id)) {
                $this->content->text = get_string('attendance_module_warning', 'block_zoomonline');
                return $this->content;
            }
        }

        if (empty($clientid) || empty($clientsecret) || empty($accountid)) {
            $this->content->text = has_capability('moodle/course:manageactivities', $coursecontext, $USER->id)
                    ? get_string('zoom_api_missing_warning', 'block_zoomonline')
                    : '';
            return $this->content;
        }

        if ($this->block_zoomonline_course_has_ended($COURSE->enddate)) {
            $this->content->text = has_capability('moodle/course:manageactivities', $coursecontext, $USER->id)
                    ? get_string('course_end_warning_manage', 'block_zoomonline')
                    : get_string('course_end_warning_view', 'block_zoomonline');
            return $this->content;
        }

        $teachers = $this->block_zoomonline_get_course_teachers();

        $groups = $this->block_zoomonline_get_course_groups();

        $this->content->text = $this->block_zoomonline_generate_meeting_html($teachers, $groups);

        return $this->content;
    }

    /**
     * Checks if the course has ended.
     *
     * @param int $courseenddate The end date of the course as a Unix timestamp.
     * @return bool Returns true if the course has ended, false otherwise.
     */
    private function block_zoomonline_course_has_ended($courseenddate) {
        return time() > $courseenddate;
    }

    /**
     * Retrieves the teachers for the current course.
     *
     * @return array An array of teacher objects.
     */
    private function block_zoomonline_get_course_teachers() {
        global $COURSE, $DB;
        $role = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $context = context_course::instance($COURSE->id);
        return get_role_users($role->id, $context);
    }

    /**
     * Retrieves the groups for the current course.
     *
     * @return array An array of group objects, or a default group if none exist.
     */
    private function block_zoomonline_get_course_groups() {
        global $COURSE, $DB;

        // Retrieve the setting from the plugin configuration.
        $groupsenabled = get_config('block_zoomonline', 'enable_groups');

        // If groups are disabled, return the default "no groups" option.
        if (!$groupsenabled) {
            return [(object) ['id' => 0, 'name' => '']];
        }

        // Otherwise, return the groups for the course.
        return $DB->get_records('groups', ['courseid' => $COURSE->id]) ?: [(object) ['id' => 0, 'name' => '']];
    }

    /**
     * Generates HTML for meeting links.
     *
     * @param array $teachers Array of teacher objects.
     * @param array $groups Array of group objects.
     * @return string HTML content for meeting links.
     */
    private function block_zoomonline_generate_meeting_html($teachers, $groups) {

        $html = "<div>";
        foreach ($teachers as $teacher) {
            foreach ($groups as $group) {
                $html .= $this->block_zoomonline_process_group_for_teacher($teacher, $group);
            }
        }
        return $html . "</div>" . $this->block_zoomonline_generate_style_and_script();
    }

    /**
     * Processes a group for a teacher and generates the meeting link.
     *
     * @param object $teacher The teacher object.
     * @param object $group The group object.
     * @return string HTML for the meeting link.
     */
    private function block_zoomonline_process_group_for_teacher($teacher, $group) {
        global $DB, $COURSE;
        $groupid = $group->id;
        $zoomdata = $DB->get_record('block_zoomonline_links', [
                'course_id' => $COURSE->id,
                'lecturer_id' => $teacher->id,
                'groupid' => $groupid,
        ]);

        $meetingid = $this->block_zoomonline_manage_zoom_meeting($zoomdata, $teacher, $group);

        if ($meetingid) {
            $this->block_zoomonline_process_zoom_meeting($meetingid, $teacher);
        }

        return $meetingid ? $this->block_zoomonline_generate_meeting_link($teacher, $group, $meetingid, $zoomdata->visible ?? 1) :
                '';
    }

    /**
     * Manages the Zoom meeting for a teacher and group.
     *
     * @param object|false $zoomdata The Zoom data record, or false if not found.
     * @param object $teacher The teacher object.
     * @param object $group The group object.
     * @return string|null The meeting ID if successful, null otherwise.
     */
    private function block_zoomonline_manage_zoom_meeting($zoomdata, $teacher, $group) {
        global $USER;
        $meetingid = $zoomdata->zoom_meeting_id ?? '';
        $hostemail = $teacher->email;
        if ($meetingid &&
                !\block_zoomonline\block_zoomonline_zoom_helper::block_zoomonline_check_meeting_exists($meetingid, $hostemail)) {
            $this->block_zoomonline_delete_invalid_meeting($zoomdata);
            $meetingid = '';
        }
        if (!$meetingid && $USER->id === $teacher->id) {
            $meetingid = $this->block_zoomonline_create_zoom_meeting($teacher, $group);
        }
        return $meetingid;
    }

    /**
     * Deletes an invalid meeting record from the database.
     *
     * @param object $zoomdata The Zoom data record to delete.
     */
    private function block_zoomonline_delete_invalid_meeting($zoomdata) {
        global $DB;
        $DB->delete_records('block_zoomonline_links', ['id' => $zoomdata->id]);

    }

    /**
     * Creates a new Zoom meeting for a teacher and group.
     *
     * @param object $teacher The teacher object.
     * @param object $group The group object.
     * @return string|null The created meeting ID, or null if creation failed.
     */
    private function block_zoomonline_create_zoom_meeting($teacher, $group) {
        global $COURSE, $DB;
        $meetingtopic = $group->id ? "GID_{$group->id}_{$COURSE->idnumber}_{$group->name}" : $COURSE->idnumber;
        $meetingid =
                \block_zoomonline\block_zoomonline_zoom_helper::block_zoomonline_create_meeting($teacher->email, $meetingtopic);
        if ($meetingid) {
            $zoomdata = (object) [
                    'course_id' => $COURSE->id,
                    'crscode' => $COURSE->idnumber,
                    'lecturer_id' => $teacher->id,
                    'zoom_meeting_id' => $meetingid,
                    'groupid' => $group->id,
                    'visible' => 1,
                    'timemodified' => time(),
            ];
            $DB->insert_record('block_zoomonline_links', $zoomdata);
        }
        return $meetingid;
    }

    /**
     * Processes a Zoom meeting, including recordings and attendance if configured.
     *
     * @param string $meetingid The Zoom meeting ID.
     * @param object $teacher The teacher object.
     */
    private function block_zoomonline_process_zoom_meeting($meetingid, $teacher) {
        global $COURSE;
        $this->block_zoomonline_process_recordings($meetingid, $teacher->firstname, $teacher->lastname, $COURSE->id);
        if (get_config('block_zoomonline', 'useattendance')) {
            $installedmodules = core_plugin_manager::instance()->get_plugins_of_type('mod');

            if (array_key_exists('attendance', $installedmodules)) {
                $this->block_zoomonline_process_attendance($meetingid, $COURSE->id);
                $this->block_zoomonline_check_live_meeting($meetingid, $COURSE->id);
            } else {
                echo "Attendance module is not installed. Skipping attendance processing.";
            }
        }
    }

    /**
     * Process Zoom meeting recordings.
     *
     * This function handles the processing of Zoom recordings for a given meeting.
     * It retrieves the Zoom recordings and processes them based on the provided meeting ID.
     *
     * @param int $meetingid The Zoom meeting ID.
     * @param string|null $firstname Optional. First name of the meeting host.
     * @param string|null $lastname Optional. Last name of the meeting host.
     * @param int|null $crsid Optional. Course ID associated with the Zoom meeting.
     * @param int|null $lecid Optional. Lecturer ID associated with the Zoom meeting.
     *
     * @return void
     */
    public function block_zoomonline_process_recordings($meetingid, $firstname = '', $lastname = '', $crsid = null, $lecid = null) {
        global $COURSE, $DB;
        $embedtype = get_config('block_zoomonline', 'embedtype');
        $storagetype = get_config('block_zoomonline', 'storagetype');
        if ($lecid) {
            $lecturer = $DB->get_record('user', ['id' => $lecid], 'firstname, lastname');
            $firstname = $lecturer->firstname ?? $firstname;
            $lastname = $lecturer->lastname ?? $lastname;
        }
        $courseid = $crsid ?: $COURSE->id;
        $accesskey = \block_zoomonline\block_zoomonline_zoom_helper::block_zoomonline_get_current_access_key();
        $recordings = \block_zoomonline\block_zoomonline_zoom_helper::block_zoomonline_get_meeting_recordings($meetingid);

        foreach ($recordings as $rec) {
            if ($rec['file_type'] !== 'MP4') {
                continue;
            }
            $videoid = trim($rec['id']);
            $downloadurl = $rec['download_url'];
            $recordingdate = $this->block_zoomonline_format_recording_date($rec['recording_start']);
            $shareurl = $rec['play_url'];
            $vid = trim("$firstname $lastname");

            if ($embedtype == 'book') {
                $scid = $this->block_zoomonline_create_section($courseid);
                $bkid = $this->block_zoomonline_create_book($vid, $courseid, $scid);

                if ($this->block_zoomonline_chapter_exists($bkid, $recordingdate)) {
                    continue;
                }
            }

            $tempfilepath =
                    \block_zoomonline\block_zoomonline_zoom_helper::block_zoomonline_download_video($downloadurl, $accesskey,
                            $videoid);

            if ($tempfilepath && filesize($tempfilepath) >= 10) {
                if ($storagetype === 'aws') {
                    $s3fileurl = $this->block_zoomonline_upload_to_s3($tempfilepath, $videoid);
                    unlink($tempfilepath);
                } else if ($storagetype === 'local') {
                    $localfileurl = $this->block_zoomonline_save_to_local_folder($tempfilepath, $videoid);
                }

                if (isset($s3fileurl) || isset($localfileurl)) {
                    \block_zoomonline\block_zoomonline_zoom_helper::block_zoomonline_delete_zoom_recording($meetingid, $videoid);
                    $fileurl = $storagetype === 'aws' ? $s3fileurl : $localfileurl;
                    if ($embedtype == 'book') {
                        $bkid = $this->block_zoomonline_add_chapter_to_book($bkid, $fileurl, $shareurl, $firstname, $lastname,
                                $recordingdate, $vid);
                    } else if ($embedtype === 'label') {
                        $this->block_zoomonline_add_video_to_moodle($courseid, $videoid, $fileurl, $firstname, $lastname,
                                $recordingdate);
                    }
                }
            }
        }
    }

    /**
     * Formats the recording date.
     *
     * Converts the given string time into a formatted date string in the 'd/m/Y H:i' format.
     * It adjusts the time based on the server's default timezone.
     *
     * @param string $stringtime The original string representing the recording time.
     *
     * @return string The formatted date in 'd/m/Y H:i' format.
     */
    private function block_zoomonline_format_recording_date($stringtime) {
        $timestamp = strtotime($stringtime);
        $date = new DateTime();
        $date->setTimestamp($timestamp);
        $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
        return $date->format('d/m/Y H:i');
    }

    /**
     * Creates or retrieves the 'ONLINE RECORDINGS' section in the course.
     *
     * This method checks if a section named 'ONLINE RECORDINGS' exists in the current course.
     * If it doesn't exist, it creates a new section at the end of the course.
     * If it already exists, it retrieves the section ID.
     *
     * @param int $courseid The ID of the course where the section should be created/retrieved.
     * @return int The section ID of the 'ONLINE RECORDINGS' section.
     */
    private function block_zoomonline_create_section($courseid) {
        global $DB, $CFG;

        require_once("$CFG->dirroot/course/lib.php"); // Ensure Course API is available.

        // Ensure the course ID is valid.
        if (!$courseid || !$DB->record_exists('course', ['id' => $courseid])) {
            debugging("block_zoomonline_create_section: Invalid course ID", DEBUG_DEVELOPER);
            return null;
        }

        $sectionname = 'ONLINE RECORDINGS';

        // Check if the section already exists.
        $existingsections = $DB->get_records('course_sections', ['course' => $courseid, 'name' => $sectionname], '', 'id, section');
        if ($existingsections) {
            // Return the section ID if it exists.
            $existingsection = reset($existingsections);
            return $existingsection->section;
        }

        // Create a new section using course_create_section().
        try {
            $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

            // Add the section to the end of the course (position = 0).
            $newsection = course_create_section($course, 0, false);

            // Update the section name if applicable.
            $DB->update_record('course_sections', [
                    'id' => $newsection->id,
                    'name' => $sectionname,
            ]);

            // Return the newly created section ID.
            return $newsection->section;
        } catch (Exception $e) {
            debugging("block_zoomonline_create_section: Failed to create section for course ID $courseid. Error: " .
                    $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
    }

    /**
     * Creates a new book module or retrieves an existing one for storing recordings.
     *
     * This method checks if a book module with a name starting with the given video ID exists in the course.
     * If it doesn't exist, it creates a new book module in the specified section.
     * If it already exists, it returns the existing book's ID.
     *
     * @param string $vid The video ID, used as the base for the book name.
     * @param int $cid The course ID where the book will be created.
     * @param int $sectionid The section ID where the book will be placed.
     * @return int|null The ID of the created or existing book module, or null if an error occurred.
     * @throws Exception If there's an error during book creation or retrieval.
     */
    private function block_zoomonline_create_book($vid, $cid, $sectionid) {
        global $CFG, $DB;

        try {

            $course = $DB->get_record('course', ['id' => $cid], '*', MUST_EXIST);

            $origname = $vid;

            $vid = $DB->sql_like_escape($vid); // Escape special characters for LIKE clause .

            $bookexists =
                    $DB->get_record_sql("SELECT id FROM {book} WHERE course = ? AND name LIKE ?", [$cid, "$vid%"]);

            if (!$bookexists) {
                $book = new stdClass();
                $book->course = $cid;
                $book->visible = 1;
                $book->section = $sectionid;
                $book->module = $DB->get_field('modules', 'id', ['name' => 'book']);
                $book->modulename = 'book';
                $book->intro = "Recordings for '$course->shortname'";
                $book->introformat = FORMAT_HTML;
                $book->name = $origname;

                require_once($CFG->dirroot . '/mod/label/lib.php');
                require_once($CFG->dirroot . '/course/modlib.php');

                $moduleinstance = add_moduleinfo($book, $course);
                return $moduleinstance->instance;
            } else {
                return $bookexists->id;
            }
        } catch (Exception $ex) {
            return null;
        }
    }

    /**
     * Checks if a chapter with the given recording date already exists in the specified book.
     *
     * @param int $bookid The ID of the book to check.
     * @param string $recordingdate The date of the recording, used as the chapter title.
     * @return bool True if the chapter exists, false otherwise.
     */
    private function block_zoomonline_chapter_exists($bookid, $recordingdate) {
        global $DB;
        $t = $DB->record_exists('book_chapters', ['bookid' => $bookid, 'title' => $recordingdate]);

        return $t;
    }

    /**
     * Uploads a file to Amazon S3 storage.
     *
     * This method uses the AWS S3 SDK to upload a file to the configured S3 bucket.
     * It retrieves AWS credentials and bucket information from the plugin configuration.
     *
     * @param string $filepath The local path of the file to be uploaded.
     * @param string $filename The name to be given to the file in S3 (without .mp4 extension).
     * @return string|null The URL of the uploaded file in S3 if successful, null otherwise.
     * @throws Exception If there's an error during the upload process.
     */
    private function block_zoomonline_upload_to_s3($filepath, $filename) {
        $filename .= '.mp4';
        // Retrieve AWS Credentials from Moodle config
        $awskey = get_config('block_zoomonline', 'aws_key');
        $awssecret = get_config('block_zoomonline', 'aws_secret');
        $bucket = get_config('block_zoomonline', 'aws_bucket');
        $region = get_config('block_zoomonline', 'aws_region');

        // Ensure the file exists before uploading
        if (!file_exists($filepath)) {
            die("S3 Upload Error: File does not exist at $filepath<br>");
        }

        if (!is_readable($filepath)) {
            die("S3 Upload Error: File is not readable at $filepath<br>");
        }

        $filesize = filesize($filepath);
        if ($filesize == 0) {
            die("S3 Upload Error: File is empty at $filepath<br>");
        }

        // Separate the input file creation for debugging
        $inputfile = simple_storage_service::inputfile($filepath);

        if (!$inputfile) {
            die("S3 Upload Error: inputfile() returned false for $filepath<br>");
        }

        try {
            simple_storage_service::setAuth($awskey, $awssecret);

            $uploadsuccess = simple_storage_service::putObject(
                    $inputfile,
                    $bucket,
                    $filename,
                    simple_storage_service::ACL_PUBLIC_READ
            );

            if ($uploadsuccess) {
                return "https://$bucket.s3.$region.amazonaws.com/$filename";
            } else {
                die("S3 Upload failed: Unable to upload $filename to bucket $bucket.<br>");
            }

        } catch (Exception $e) {
            die("S3 Upload Exception: " . $e->getMessage() . "<br>");
        }

    }

    /**
     * Saves a video file to the local Moodle file system.
     *
     * This method handles the storage of a video file in Moodle's file system,
     * creating a new file or returning the URL of an existing file
     *
     * @param string $tempfilepath The temporary path of the file to be saved.
     * @param string $videoid The unique identifier for the video.
     * @return string The URL of the saved or existing file.
     * @throws moodle_exception If there's an error in the block instance, context, or file saving process.
     * @throws coding_exception If the context is invalid or not set to the block level.
     */
    private function block_zoomonline_save_to_local_folder($tempfilepath, $videoid) {
        if (!isset($this->instance->id) || !$this->instance->id) {
            throw new moodle_exception('Block instance ID is not set or invalid.');
        }
        try {
            $context = context_block::instance($this->instance->id);
        } catch (Exception $e) {
            throw new moodle_exception('Invalid context ID specified for block_zoomonline.');
        }
        if (!$context || $context->contextlevel != CONTEXT_BLOCK) {
            throw new coding_exception('Context is not valid or not set to the block level.');
        }
        $fs = get_file_storage();
        $filerecord = [
                'contextid' => $context->id,        // Use the retrieved block context.
                'component' => 'block_zoomonline',  // Component name for your block.
                'filearea' => 'zoom_videos',        // Custom file area name for organization.
                'itemid' => 0,                      // Item ID (use 0 if not needed).
                'filepath' => '/',                  // Filepath (always start with '/').
                'filename' => $videoid . '.mp4',     // The name of the file.
        ];
        $existingfile = $fs->get_file(
                $filerecord['contextid'],
                $filerecord['component'],
                $filerecord['filearea'],
                $filerecord['itemid'],
                $filerecord['filepath'],
                $filerecord['filename']
        );
        if ($existingfile) {
            return moodle_url::make_pluginfile_url(
                    $existingfile->get_contextid(),
                    $existingfile->get_component(),
                    $existingfile->get_filearea(),
                    $existingfile->get_itemid(),
                    $existingfile->get_filepath(),
                    $existingfile->get_filename()
            )->out(false);
        }
        $storedfile = $fs->create_file_from_pathname($filerecord, $tempfilepath);
        if ($storedfile) {
            return moodle_url::make_pluginfile_url(
                    $context->id,
                    'block_zoomonline',
                    'zoom_videos',
                    0,
                    '/',
                    $videoid . '.mp4'
            )->out(false);
        } else {
            throw new moodle_exception('Error saving file to Moodle data.');
        }
    }

    /**
     * Adds a new chapter to a book module with video content.
     *
     * This method creates a new chapter in the specified book, including
     * a video player, recording information, and a link to view the video in Zoom.
     *
     * @param int $bookid The ID of the book to add the chapter to.
     * @param string $s3fileurl The URL of the video file in S3.
     * @param string $shareurl The Zoom share URL for the video.
     * @param string $firstname The first name of the recorder.
     * @param string $lastname The last name of the recorder.
     * @param string $recordingdate The date of the recording.
     * @param string $vid The video ID.
     */
    private function block_zoomonline_add_chapter_to_book($bookid, $s3fileurl, $shareurl, $firstname, $lastname, $recordingdate,
            $vid) {
        global $DB;

        $content = "<video width='300' height='175' oncontextmenu='return false;' controls='controls' controlsList='nodownload'>
                <source src='$s3fileurl' type='video/mp4'>$vid
            </video>
            <p>$firstname $lastname - $recordingdate</p>
            <p><a target='_blank' href='$shareurl'>" . get_string('zoom_play_with_transcript', 'block_zoomonline') . "</a></p>";

        $chapter = (object) [
                'bookid' => $bookid,
                'content' => $content,
                'title' => $recordingdate,
                'hidden' => 1,
        ];
        $DB->insert_record('book_chapters', $chapter);
    }

    /**
     * Adds a video to a Moodle course as a label activity.
     *
     * This function creates a label activity in a specified Moodle course and embeds a video
     * with its metadata, such as the uploader's name and recording date.
     * The label is added to the course's highest section and remains hidden initially.
     *
     * @param int $courseid The ID of the Moodle course to which the video will be added.
     * @param string $videoid The unique identifier for the video.
     * @param string $videourl The URL of the video to be embedded.
     * @param string $firstname The first name of the individual associated with the video.
     * @param string $lastname The last name of the individual associated with the video.
     * @param string $recordingdate The recording date of the video in a human-readable format.
     */
    private function block_zoomonline_add_video_to_moodle($courseid, $videoid, $videourl, $firstname, $lastname, $recordingdate) {
        global $CFG, $DB, $COURSE;
        $COURSE = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $label = new stdClass();
        $label->course = $courseid;
        $label->visible = 0;
        $section = $DB->get_record_sql("SELECT MAX(section) AS section FROM {course_sections} WHERE course = ?", [$courseid]);
        $label->section = $section->section;
        $moduleid = $DB->get_field('modules', 'id', ['name' => 'label']);
        $label->module = $moduleid;
        $label->modulename = 'label';
        $label->intro = "<video width='300' height='175' controls='controls' controlsList='nodownload'>
                        <source src='$videourl' type='video/mp4'>$videoid</video>
                     <p>$firstname $lastname - $recordingdate</p>";
        $label->introformat = FORMAT_HTML;
        $label->name = $videoid;
        require_once($CFG->dirroot . '/mod/label/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');
        add_moduleinfo($label, $COURSE);
    }

    /**
     * Processes attendance for a Zoom meeting.
     *
     * This method retrieves meeting data, checks for existing attendance records,
     * and processes attendance data for participants if it hasn't been done before.
     *
     * @param string $meetingid The Zoom meeting ID.
     * @param int $courseid The Moodle course ID.
     * @return bool False if the meeting data is invalid or attendance has already been processed, true otherwise.
     */
    public function block_zoomonline_process_attendance($meetingid, $courseid) {
        $meetingdata = \block_zoomonline\block_zoomonline_zoom_helper::block_zoomonline_get_meeting_data($meetingid);

        if (is_array($meetingdata) && isset($meetingdata['code']) && $meetingdata['code'] === 3301) {
            return false;
        }
        if (!$meetingdata) {
            return false;
        }
        $meetingstartwtz = isset($meetingdata['start_time']) ? $meetingdata['start_time'] : null;
        $meetingduration = isset($meetingdata['duration']) ? $meetingdata['duration'] : 0;
        $topic = isset($meetingdata['topic']) ? $meetingdata['topic'] : 'Untitled';
        $meetingstartec = $this->block_zoomonline_get_rounded_meeting_start($meetingstartwtz);
        $meetingstart = date('Y/m/d h:i:s', $meetingstartec);

        if (!$this->block_zoomonline_attendance_exists($meetingid, $meetingstart) && $meetingstartec) {

            $attendees = $this->block_zoomonline_get_all_meeting_participants($meetingid, $meetingduration);

            if (empty($attendees)) {
                return false;
            }

            $consolidatedattendees = $this->block_zoomonline_consolidate_attendance_data($attendees);
            $this->block_zoomonline_process_final_attendance($consolidatedattendees, $meetingstartwtz, $meetingduration, $courseid,
                    $topic);
            $this->block_zoomonline_mark_attendance_processed($meetingid, $meetingstart);
        } else {
            return false;
        }
    }

    /**
     * Get the rounded meeting start time.
     *
     * @param string $meetingstartwtz
     * @return int Rounded meeting start timestamp.
     */
    private function block_zoomonline_get_rounded_meeting_start($meetingstartwtz) {
        $meetingstarte = strtotime($meetingstartwtz);
        return $meetingstarte - ($meetingstarte % 60); // Round to nearest minute.
    }

    /**
     * Check if attendance already exists for a meeting.
     *
     * @param string $meetingid
     * @param string $meetingstart
     * @return bool
     */
    private function block_zoomonline_attendance_exists($meetingid, $meetingstart) {
        global $DB;

        // Convert the start date to a UNIX timestamp
        $startdate = strtotime($meetingstart);

        return $DB->record_exists('block_zoomonline_att_check', [
                'zoom_meeting_id' => $meetingid,
                'start_date' => $startdate,
        ]);
    }

    /**
     * Get all meeting participants from Zoom API.
     *
     * @param string $meetingid
     * @param int $meetingduration
     * @return array List of participants.
     */
    private function block_zoomonline_get_all_meeting_participants($meetingid, $meetingduration) {
        $attendees = [];
        $nextpagetoken = "";

        while (true) {
            $data = \block_zoomonline\block_zoomonline_zoom_helper::block_zoomonline_get_participants($meetingid, $nextpagetoken);
            if (!$data) {
                break;
            }
            if (isset($data['participants']) && $meetingduration >= 5) {
                foreach ($data['participants'] as $USER) {
                    $attuser = $this->block_zoomonline_create_att_user_object($USER);
                    if ($attuser) {
                        $attendees[] = $attuser;
                    }
                }
            }
            if (empty($data['next_page_token'])) {
                break;
            } else {
                $nextpagetoken = $data['next_page_token'];
            }
        }
        return $attendees;
    }

    /**
     * Create an attendance user object from Zoom participant data.
     *
     * @param array $USER
     * @return stdClass|null
     */
    private function block_zoomonline_create_att_user_object($USER) {
        $stuid = filter_var($USER['name'], FILTER_SANITIZE_NUMBER_INT);
        if ($stuid > 0) {
            $attuser = new stdClass();
            $attuser->name = $stuid;
            $attuser->join_time = $USER['join_time'];
            $attuser->leave_time = $USER['leave_time'];
            $attuser->duration = $USER['duration'];
            $attuser->email = $USER['user_email'];
            return $attuser;
        }
        return null;
    }

    /**
     * Consolidate multiple attendance records into a single one.
     *
     * @param array $attendees
     * @return array Consolidated attendees.
     */
    private function block_zoomonline_consolidate_attendance_data($attendees) {
        // Get the configuration setting to decide which field to use for matching attendance.
        $useusernamefield = get_config('block_zoomonline', 'username_field');

        $consolidated = [];
        foreach ($attendees as $att) {
            // Use either the attendee's name or email based on the configuration.
            $nametofind = $useusernamefield === 'email' ? $att->email : $att->name;

            if ($nametofind) {
                // Check if the attendee is already processed.
                $existing = $this->block_zoomonline_find_in_array($consolidated, 'name', $nametofind);

                if (!$existing) {
                    // Filter attendees with the same name/email.
                    $groupedattendees = array_filter($attendees, function($attone) use ($nametofind, $useusernamefield) {
                        $fieldtocompare = $useusernamefield === 'email' ? $attone->email : $attone->name;
                        return $fieldtocompare === $nametofind;
                    });

                    // Sort attendees by join_time (ascending).
                    usort($groupedattendees, function($a, $b) {
                        return strtotime($a->join_time) <=> strtotime($b->join_time);
                    });

                    // Prepare consolidated attendance record.
                    $attstud = new stdClass();
                    $attstud->name = $nametofind;
                    $attstud->join_time = $groupedattendees[0]->join_time; // Earliest join_time.
                    $attstud->leave_time = end($groupedattendees)->leave_time; // Latest leave_time.
                    $attstud->duration = array_reduce($groupedattendees, function($carry, $item) {
                        return $carry + $item->duration;
                    }, 0);

                    $consolidated[] = $attstud;
                }
            }
        }

        return $consolidated;
    }

    /**
     * Find an element in an array by a key-value pair.
     *
     * @param array $array
     * @param string $key
     * @param mixed $value
     * @return mixed|null
     */
    private function block_zoomonline_find_in_array($array, $key, $value) {
        foreach ($array as $element) {
            if ($element->$key === $value) {
                return $element;
            }
        }
        return null;
    }

    /**
     * Process final attendance data and update logs.
     *
     * @param array $attendees
     * @param string $meetingstartwtz
     * @param int $meetingduration
     * @param int $courseid
     * @param string $topic
     */
    private function block_zoomonline_process_final_attendance($attendees, $meetingstartwtz, $meetingduration, $courseid, $topic) {
        $length = count($attendees);
        $x = 1;

        foreach ($attendees as $attendee) {
            $close = ($x === $length) ? 1 : 0;
            $this->block_zoomonline_update_att($attendee, $meetingstartwtz, $meetingduration, $courseid, $close, $topic);
            $x++;
        }
    }

    /**
     * Updates attendance records for a student in a Zoom meeting.
     *
     * This method calculates the student's attendance status, retrieves or creates
     * the necessary attendance records, and updates or creates attendance sessions
     * as needed.
     *
     * @param object $finstuatt The student's attendance data.
     * @param string $meetingstart The start time of the meeting.
     * @param int $meetingduration The duration of the meeting in minutes.
     * @param int $zcrsid The Zoom course ID.
     * @param bool $close Whether to close the attendance session.
     * @param string $topic The topic or name of the meeting.
     */
    private function block_zoomonline_update_att($finstuatt, $meetingstart, $meetingduration, $zcrsid, $close, $topic) {
        // Retrieve the configuration setting to determine the field to use for attendance matching.
        $useusernamefield = get_config('block_zoomonline', 'username_field');

        // Get the appropriate identifier for the student (email or name).
        $identifier = $useusernamefield === 'email' ? $finstuatt->email : $finstuatt->name;

        // Fetch the student ID using the chosen identifier.
        $studentid = $this->block_zoomonline_get_studentid_by_username($identifier);

        // If no student ID is found, gracefully exit.
        if (!$studentid) {
            return;
        }

        $status = $this->block_zoomonline_calculate_student_status($finstuatt, $meetingstart, $meetingduration);

        $attendancerecord = $this->block_zoomonline_get_attendance_record($zcrsid);
        if ($attendancerecord) {
            $sessionexists =
                    $this->block_zoomonline_process_attendance_session($attendancerecord, $meetingstart, $meetingduration, $status,
                            $zcrsid, $studentid, $close);
            if (!$sessionexists) {
                $this->block_zoomonline_create_att_sess($attendancerecord->id,
                        $this->block_zoomonline_get_rounded_meeting_starttime($meetingstart),
                        $meetingduration * 60, $status, $zcrsid, $studentid, $topic, false);
            }
        } else {
            $this->block_zoomonline_create_new_attendance_instance($zcrsid, $meetingstart, $meetingduration, $status, $studentid,
                    $topic);
        }
    }

    /**
     * Get the student's ID by their username.
     *
     * @param string $username
     * @return int|null The student's ID or null if not found.
     */
    private function block_zoomonline_get_studentid_by_username($username) {
        global $DB;
        $student = $DB->get_record('user', ['username' => $username]);
        return $student ? $student->id : null;
    }

    /**
     * Calculate the student's status based on their attendance and meeting details.
     *
     * @param object $finstuatt
     * @param string $meetingstart
     * @param int $meetingduration
     * @return string The student's attendance status (P, L).
     */
    private function block_zoomonline_calculate_student_status($finstuatt, $meetingstart, $meetingduration) {
        $status = "P";
        $latetime = get_config('block_zoomonline', 'latetime');
        if ($latetime == 0) {
            $latetime = 900;
        }
        $attendancethreshold = get_config('block_zoomonline', 'attendance_threshold');
        if ($attendancethreshold == 0) {
            $attendancethreshold = 70;
        }
        $earlydeparturethreshold = get_config('block_zoomonline', 'early_departure_threshold');
        if ($earlydeparturethreshold == 0) {
            $earlydeparturethreshold = 900;
        }
        $meetingstarttime = strtotime($meetingstart);
        $meetingstartec = $meetingstarttime - ($meetingstarttime % 60); // Round to nearest minute.
        $studentjointime = strtotime($finstuatt->join_time);
        $studentlate = max(0, $studentjointime - $meetingstartec);
        $duration = $meetingduration * 60;
        $attendedpercentage = ($finstuatt->duration * 100) / $duration;
        if ($attendedpercentage < $attendancethreshold || $studentlate > $latetime) {
            $status = "L"; // Late
        }
        if ($duration > 1800) {
            $sessionendtime = $meetingstartec + $duration;
            $studentendtime = $studentjointime + $finstuatt->duration;
            if (($sessionendtime - $studentendtime) > $earlydeparturethreshold) {
                $status = "L"; // Late if student leaves too early.
            }
        }
        return $status;
    }

    /**
     * Retrieve the attendance record for the course.
     *
     * @param int $zcrsid The course ID.
     * @return object|null The attendance record or null if not found.
     */
    private function block_zoomonline_get_attendance_record($zcrsid) {
        global $DB;
        return $DB->get_record('attendance', ['course' => $zcrsid]);
    }

    /**
     * Process the attendance session, updating or creating a log as necessary.
     *
     * @param object $attendancerecord
     * @param string $meetingstart
     * @param int $meetingduration
     * @param string $status
     * @param int $zcrsid
     * @param int $studentid
     * @param int $close
     * @return bool True if session exists, false otherwise.
     */
    private function block_zoomonline_process_attendance_session($attendancerecord, $meetingstart, $meetingduration, $status,
            $zcrsid,
            $studentid, $close) {
        global $DB;
        $meetingstarttime = $this->block_zoomonline_get_rounded_meeting_starttime($meetingstart);
        $duration = $meetingduration * 60;
        $attsesssql =
                "SELECT attendanceid, id, duration FROM {attendance_sessions} WHERE attendanceid = ? AND sessdate = ? AND (duration = ? OR duration = '3600')";
        $attsess = $DB->get_records_sql($attsesssql, [$attendancerecord->id, $meetingstarttime, $duration]);
        if ($attsess) {
            $attsessid = reset($attsess)->id;
            if (reset($attsess)->duration == '3600' && $duration != 3600) {
                $sessions = $DB->get_records_select(
                        'attendance_sessions',
                        "attendanceid = :attendanceid AND sessdate = :sessdate AND duration = :oldduration",
                        [
                                'attendanceid' => $attendancerecord->id,
                                'sessdate' => $meetingstarttime,
                                'oldduration' => 3600,
                        ]
                );
                if (!empty($sessions)) {
                    foreach ($sessions as $session) {
                        $session->duration = $duration; // Set the new duration.
                        $DB->update_record('attendance_sessions', $session); // Update the record.
                    }
                }
            }
            $stuattsesssql =
                    "SELECT sessionid, studentid, statusid, remarks, id FROM {attendance_log} WHERE sessionid = ? AND studentid = ?";
            $stuattsess = $DB->get_records_sql($stuattsesssql, [$attsessid, $studentid]);
            $attstatus = $DB->get_record('attendance_statuses',
                    ['acronym' => $status, 'attendanceid' => $attendancerecord->id]);
            if (!$stuattsess || $close == 1) {
                $this->block_zoomonline_create_att_log($attsessid, $attstatus->id, $studentid, $meetingstarttime, $zcrsid);
            } else {
                $this->block_zoomonline_update_attendance_log_if_needed($stuattsess[$attsessid], $attstatus->id, $meetingstarttime);
            }
            return true;
        }
        return false;
    }

    /**
     * Get the rounded meeting start time.
     *
     * @param string $starttime
     * @return int Rounded meeting start timestamp.
     */
    private function block_zoomonline_get_rounded_meeting_starttime($starttime) {
        $meetingstarte = strtotime($starttime);
        return $meetingstarte - ($meetingstarte % 60); // Round to nearest minute.
    }

    /**
     * Creates an attendance log entry for a student.
     *
     * This method checks if an attendance log already exists for the given session and student.
     * If no log exists, it creates a new attendance log entry with the provided details.
     *
     * @param int $sessid The ID of the attendance session.
     * @param int $attstatus The status ID representing the student's attendance (e.g., present, absent).
     * @param int $studentid The ID of the student.
     * @param int $sessdate The timestamp of when the attendance was taken.
     * @param int $zcrsid The Zoom course ID (not used in the method, consider removing if unnecessary).
     */
    private function block_zoomonline_create_att_log($sessid, $attstatus, $studentid, $sessdate, $zcrsid) {
        global $DB;
        $existinglog = $DB->record_exists('attendance_log', [
                'sessionid' => $sessid,
                'studentid' => $studentid,
        ]);
        if (!$existinglog) {
            $newattdata = (object) [
                    'sessionid' => $sessid,
                    'studentid' => $studentid,
                    'statusid' => $attstatus,
                    'statusset' => '9,11,12,10',
                    'remarks' => '',
                    'timetaken' => $sessdate,
                    'takenby' => '2',
            ];
            $DB->insert_record('attendance_log', $newattdata);
        }
    }

    /**
     * Update the attendance log if the system previously auto-recorded it.
     *
     * @param object $attendancelog
     * @param int $attstatusid
     * @param int $meetingstarttime
     */
    private function block_zoomonline_update_attendance_log_if_needed($attendancelog, $attstatusid, $meetingstarttime) {
        global $DB;
        if ($attendancelog->remarks == 'system auto recorded') {
            $attendancelog->timetaken = $meetingstarttime;
            $attendancelog->statusid = $attstatusid;
            $attendancelog->remarks = 'Zoom';
            $DB->update_record('attendance_log', $attendancelog);
        }
    }

    /**
     * Creates a new attendance session and optionally logs student attendance.
     *
     * This method generates a new attendance session with the provided details,
     * assigns a group if applicable, ensures attendance statuses exist,
     * and optionally creates an attendance log for a student.
     *
     * @param int $attinstanceid The ID of the attendance instance.
     * @param int $sessdate The timestamp for the session date.
     * @param int $duration The duration of the session in seconds.
     * @param string $status The attendance status acronym (e.g., 'P' for present).
     * @param int $zcrsid The Zoom course ID.
     * @param int $studentid The ID of the student to log attendance for.
     * @param string $topic The topic or description of the session.
     * @param bool $pre Whether this is a pre-session creation (default false).
     */
    private function block_zoomonline_create_att_sess($attinstanceid, $sessdate, $duration, $status, $zcrsid, $studentid, $topic,
            $pre = false) {
        global $DB;

        $password = $this->block_zoomonline_generate_random_password(8);

        $attdata = (object) [
                'attendanceid' => $attinstanceid,
                'sessdate' => $sessdate,
                'duration' => $duration,
                'lasttaken' => $sessdate,
                'lasttakenby' => 2,
                'timemodified' => $sessdate,
                'descriptionformat' => 1,
                'calendarevent' => 0,
                'studentscanmark' => 1,
                'studentpassword' => $password,
                'includeqrcode' => 0,
                'rotateqrcode' => 1,
                'automark' => 2,
                'description' => $topic,
        ];

        $this->block_zoomonline_assign_group_if_exists($attdata, $topic, $zcrsid);
        $sessionid = $DB->insert_record('attendance_sessions', $attdata);
        if ($sessionid) {
            $this->block_zoomonline_ensure_attendance_statuses($attinstanceid, $status);
            if (!$pre) {
                $attstatus = $DB->get_record('attendance_statuses',
                        ['acronym' => $status, 'attendanceid' => $attinstanceid]);
                $this->block_zoomonline_create_att_log($sessionid, $attstatus->id, $studentid, $sessdate, $zcrsid);
            }
        }
    }

    /**
     * Generate a random password of the specified length.
     *
     * @param int $length The length of the password.
     * @return string The generated password.
     */
    private function block_zoomonline_generate_random_password($length) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        return substr(str_shuffle($chars), 0, $length);
    }

    /**
     * Assign group to attendance session if topic contains group information.
     *
     * @param stdClass $attdata The attendance session data object.
     * @param string $topic The topic which may contain group information.
     * @param int $zcrsid The course ID.
     */
    private function block_zoomonline_assign_group_if_exists(&$attdata, $topic, $zcrsid) {
        global $DB;
        $namepieces = explode("_", $topic);
        if (count($namepieces) > 1 && $namepieces[0] === "GID") {
            $groupidnumber = $namepieces[1];
            $group = $DB->get_record_sql("SELECT id FROM {groups} WHERE id = ? AND courseid = ?",
                    [$groupidnumber, $zcrsid]);
            if ($group) {
                $attdata->groupid = $group->id;
            }
        }
    }

    /**
     * Ensure the required attendance statuses exist for the given instance.
     *
     * @param int $attinstanceid The attendance instance ID.
     * @param string $status The status acronym.
     */
    private function block_zoomonline_ensure_attendance_statuses($attinstanceid, $status) {
        global $DB;
        $existingstatus = $DB->get_record('attendance_statuses', ['acronym' => $status, 'attendanceid' => $attinstanceid]);
        if (!$existingstatus) {
            $this->block_zoomonline_create_status($attinstanceid, "P", "Present", "2.00");
            $this->block_zoomonline_create_status($attinstanceid, "A", "Absent", "0.00");
            $this->block_zoomonline_create_status($attinstanceid, "L", "Late", "0.00");
            $this->block_zoomonline_create_status($attinstanceid, "E", "Excused", "2.00");
        }
    }

    /**
     * Creates a new attendance status entry for a given attendance instance.
     *
     * This method adds a new status to the attendance_statuses table with the specified
     * acronym, description, and grade. The 'setunmarked' field is automatically set to 1
     * if the acronym is 'A', indicating an absent status.
     *
     * @param int $attinstanceid The ID of the attendance instance to which the status belongs.
     * @param string $acronym The acronym representing the attendance status (e.g., 'P' for present, 'A' for absent).
     * @param string $desc A description of the attendance status.
     * @param float $grade The grade value associated with this attendance status.
     */
    private function block_zoomonline_create_status($attinstanceid, $acronym, $desc, $grade) {
        global $DB;
        $attsesse = (object) [
                'attendanceid' => $attinstanceid,
                'acronym' => $acronym,
                'description' => $desc,
                'grade' => $grade,
                'visible' => 1,
                'deleted' => 0,
                'setnumber' => 0,
                'setunmarked' => ($acronym === 'A') ? 1 : 0, // Automatically set 'setunmarked' for 'A' acronym.
        ];
        $DB->insert_record('attendance_statuses', $attsesse);
    }

    /**
     * Create a new attendance instance and session for the course.
     *
     * @param int $zcrsid
     * @param string $meetingstart
     * @param int $meetingduration
     * @param string $status
     * @param int $studentid
     * @param string $topic
     */
    private function block_zoomonline_create_new_attendance_instance($zcrsid, $meetingstart, $meetingduration, $status, $studentid,
            $topic) {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/attendance/lib.php'); // Include Attendance module.
        require_once($CFG->dirroot . '/course/modlib.php'); // Include Course API.

        try {
            // Step 1: Validate and retrieve the course.
            $course = $DB->get_record('course', ['id' => $zcrsid], '*', MUST_EXIST);

            // Step 2: Retrieve the module ID for "attendance".
            $moduleid = $DB->get_field('modules', 'id', ['name' => 'attendance']);
            if (!$moduleid) {
                throw new Exception('Error: Could not retrieve module ID for "attendance".');
            }

            // Step 3: Define the attendance module information.
            $modinfo = new stdClass();
            $modinfo->modulename = 'attendance'; // Module name.
            $modinfo->module = $moduleid; // Module ID from the `modules` table.
            $modinfo->name = 'Attendance'; // Instance name.
            $modinfo->intro = 'Attendance module for course.'; // Intro text.
            $modinfo->introformat = FORMAT_HTML; // Intro format.
            $modinfo->section = 3; // Section ID (default to section 3).
            $modinfo->visible = 1; // Make the module visible.
            $modinfo->visibleoncoursepage = 1; // Make module visible on the course page.
            $modinfo->cmidnumber = ''; // Course module ID number (can be left blank).
            $modinfo->groupmode = NOGROUPS; // Set group mode (default: no groups).
            $modinfo->groupingid = 0; // Grouping ID (default: none).

            // Step 4: Add the module to the course using `add_moduleinfo`.
            $moduleinfo = add_moduleinfo($modinfo, $course);

            // Step 5: Verify that the module was created successfully.
            if (!$moduleinfo || !isset($moduleinfo->instance)) {
                throw new Exception("Failed to create Attendance module for course ID: $zcrsid");
            }

            // Retrieve the Attendance module instance ID.
            $attinstanceid = $moduleinfo->instance;

            // Step 6: Create the attendance session for the module.
            $this->block_zoomonline_create_att_sess(
                    $attinstanceid,
                    $this->block_zoomonline_get_rounded_meeting_starttime($meetingstart),
                    $meetingduration * 60, // Convert duration to seconds.
                    $status,
                    $zcrsid,
                    $studentid,
                    $topic,
                    false
            );

        } catch (Exception $e) {
            debugging("Error creating Attendance module: " . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Mark the attendance as processed for a meeting.
     *
     * @param string $meetingid
     * @param string $meetingstart
     */
    private function block_zoomonline_mark_attendance_processed($meetingid, $meetingstart) {
        global $DB;

        // Convert the start date to a UNIX timestamp
        $startdate = strtotime($meetingstart);

        $record = (object) [
                'zoom_meeting_id' => $meetingid,
                'start_date' => $startdate, // Store as UNIX timestamp
                'timemodified' => time(),
        ];

        $DB->insert_record("block_zoomonline_att_check", $record);
    }

    /**
     * Checks the status of a live Zoom meeting and manages attendance sessions.
     *
     * This method verifies if a Zoom meeting has started and retrieves its metrics.
     * It then calculates the rounded start time and checks for an existing attendance
     * instance in the course. If an attendance instance exists, it ensures a session
     * is created or updated. If not, it creates a new attendance module and session.
     *
     * @param string $meetingid The ID of the Zoom meeting to check.
     * @param int $courseid The ID of the Moodle course associated with the meeting.
     */
    public function block_zoomonline_check_live_meeting($meetingid, $courseid) {
        $meetingdata = \block_zoomonline\block_zoomonline_zoom_helper::block_zoomonline_get_meeting_status($meetingid);
        if (!isset($meetingdata['status'])) {
            return;
        }
        if ($meetingdata['status'] !== "started") {
            return;
        }
        $meetingmetrics = \block_zoomonline\block_zoomonline_zoom_helper::block_zoomonline_get_meeting_metrics($meetingid);
        $meetingstarttime = $this->block_zoomonline_get_rounded_meeting_starttime($meetingmetrics['start_time']);
        if (!$meetingstarttime) {
            return;
        }
        $attinstanceid = $this->block_zoomonline_get_attendance_instance($courseid);
        if ($attinstanceid) {
            $this->block_zoomonline_check_or_create_session($attinstanceid, $meetingstarttime, $courseid,
                    $meetingmetrics['topic']);
        } else {
            $attinstanceid = $this->block_zoomonline_create_new_attendance_module($courseid);
            $this->block_zoomonline_check_or_create_session($attinstanceid, $meetingstarttime, $courseid,
                    $meetingmetrics['topic']);
        }
    }

    /**
     * Get the attendance instance for the course.
     *
     * @param int $courseid
     * @return int|null The attendance instance ID or null if not found.
     */
    private function block_zoomonline_get_attendance_instance($courseid) {
        global $DB;
        $attendancerecord = $DB->get_record('attendance', ['course' => $courseid]);
        return $attendancerecord ? $attendancerecord->id : null;
    }

    /**
     * Check if an attendance session exists for a given time, and create it if not.
     *
     * @param int $attinstanceid
     * @param int $meetingstarttime
     * @param int $courseid
     * @param string $topic
     */
    private function block_zoomonline_check_or_create_session($attinstanceid, $meetingstarttime, $courseid, $topic) {
        global $DB;
        $attsessexists = $DB->record_exists('attendance_sessions', [
                'attendanceid' => $attinstanceid,
                'sessdate' => $meetingstarttime,
        ]);
        if (!$attsessexists) {
            $status = "P";
            $this->block_zoomonline_create_att_sess($attinstanceid, $meetingstarttime, 3600, $status, $courseid, "None", $topic,
                    true);
        }
    }

    /**
     * Create a new attendance module for the course.
     *
     * @param int $courseid
     * @return int The created attendance instance ID.
     */
    private function block_zoomonline_create_new_attendance_module($courseid) {
        global $CFG;

        require_once($CFG->dirroot . '/mod/attendance/lib.php'); // Ensure Attendance module is loaded.
        require_once($CFG->dirroot . '/course/modlib.php'); // Ensure Course API is available.

        // Module data for the Attendance instance.
        $modinfo = [
                'modulename' => 'attendance', // The module name (e.g., attendance).
                'section' => 0, // Section ID (0 = default section).
                'course' => $courseid, // Course ID.
                'visible' => 1, // Module is visible.
                'instance' => 0, // Placeholder, filled by create_module.
                'add' => 'attendance', // Module to add.
                'name' => 'Attendance', // Module name in the course.
                'grade' => 100, // Maximum grade for the    module.
                'intro' => '', // Optional intro text for the module.
                'introformat' => FORMAT_HTML, // Format for the intro.
                'showextrauserdetails' => 1, // Additional settings specific to the module.
                'showsessiondetails' => 1,
                'sessiondetailspos' => 'left',
        ];

        $modinfo = create_module((object) $modinfo);

        // Return the instance ID of the newly created Attendance module.
        return $modinfo->instance;
    }

    /**
     * Generates an HTML link for joining a Zoom meeting.
     *
     * This method creates a formatted HTML snippet containing information about
     * the meeting and a link to join it. The appearance and functionality of the
     * link vary based on the user's access rights and the meeting's visibility.
     *
     * @param object $teacher An object containing the teacher's first name and last name.
     * @param object $group An object containing the group's name.
     * @param string $meetingid The Zoom meeting ID.
     * @param int $visibility The visibility status of the meeting (2 for hidden, other values for visible).
     * @return string HTML snippet for the meeting link, or an empty string if the user doesn't have access.
     */
    private function block_zoomonline_generate_meeting_link($teacher, $group, $meetingid, $visibility) {
        $linkroot = "https://app.zoom.us/j/";
        $isvisible = $visibility != 2;
        $blockzoomonlineuserhasaccess = $this->block_zoomonline_user_has_access($teacher);
        $templatedata = [
                'teacher_firstname' => $teacher->firstname,
                'teacher_lastname' => $teacher->lastname,
                'group_name' => $group->name,
                'meeting_id' => $meetingid,
                'zoom_link' => "{$linkroot}{$meetingid}",
                'show_join_button' => $isvisible && $this->block_zoomonline_user_is_in_group($group),
                'eye_disabled' => !$isvisible,
        ];
        if ($blockzoomonlineuserhasaccess) {
            $templatedata['show_eye_icon'] = true;
            $templatedata['toggle_visibility'] = $isvisible ? 2 : 1;
            $templatedata['eye_icon_class'] = $isvisible ? 'fa-eye' : 'fa-eye-slash';
        } else {
            $templatedata['show_eye_icon'] = false;
        }
        $output = $this->page->get_renderer('block_zoomonline');
        return $output->render_from_template('block_zoomonline/meeting_link', $templatedata);
    }

    /**
     * Checks if the current user has access to manage the meeting.
     *
     * @param object $teacher The teacher object to compare against.
     * @return bool True if the user is the teacher or a site admin, false otherwise.
     */
    private function block_zoomonline_user_has_access($teacher) {
        global $USER;
        return $USER->id === $teacher->id || is_siteadmin($USER);
    }

    /**
     * Checks if the current user is a member of the specified group.
     *
     * @param object $group The group object to check membership for.
     * @return bool True if the user is in the group or if the group ID is 0, false otherwise.
     */
    private function block_zoomonline_user_is_in_group($group) {
        global $DB, $USER;

        // Retrieve the setting from the configuration.
        $groupsenabled = get_config('block_zoomonline', 'enable_groups');

        // If groups are disabled, return the default group.
        if (!$groupsenabled) {
            return $group->id == 0;
        }

        // Otherwise, check if the user is in the group.
        return $group->id == 0 || $DB->record_exists('groups_members', [
                        'userid' => $USER->id,
                        'groupid' => $group->id,
                ]);
    }

    /**
     * Generates the CSS and JavaScript for the Zoom meeting links.
     *
     * @return string HTML string containing style and script tags for meeting link functionality.
     */
    private function block_zoomonline_generate_style_and_script() {
        $this->page->requires->js_call_amd('block_zoomonline/live_join', 'init');
        return '';
    }

    /**
     * Specifies the course formats where this block can be used.
     *
     * @return array An associative array of course formats and their allowed status.
     */
    public function applicable_formats() {
        return [
                'all' => false,
                'site' => true,
                'site-index' => true,
                'course-view' => true,
                'course-view-social' => false,
                'mod' => true,
                'mod-quiz' => false,
        ];
    }

    /**
     * Indicates whether multiple instances of this block are allowed on a single page.
     *
     * @return bool Always returns true, allowing multiple instances.
     */
    public function instance_allow_multiple() {
        return true;
    }

    /**
     * Indicates whether this block has a configuration page.
     *
     * @return bool Always returns true, indicating this block has a configuration.
     */
    public function has_config() {
        return true;
    }
}
