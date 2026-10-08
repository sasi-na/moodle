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

namespace block_zoomonline\task;
/**
 * Task class to process Zoom meetings.
 *
 * @package    block_zoomonline
 * @copyright  2024 Ciaran Mac Donncha
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_meetings extends \core\task\scheduled_task {

    /**
     * Returns the name of the scheduled task.
     *
     * This method provides a human-readable name for the task, which is used
     * in the Moodle task scheduling interface and logs.
     *
     * @return string The name of the task: "Process Zoom Meetings".
     */
    public function get_name() {
        return get_string('process_zoom_meetings', 'block_zoomonline');
    }

    /**
     * Executes the scheduled task to process Zoom meetings.
     *
     * This method performs the following operations:
     * 1. Retrieves all Zoom meetings from the database.
     * 2. For each meeting:
     *    - Checks if the associated course has ended.
     *    - Processes recordings for the meeting.
     *    - If attendance tracking is enabled and the Attendance module is installed:
     *      - Processes attendance for the meeting.
     *      - Checks the live meeting status.
     * 3. Logs the number of meetings processed and the time taken.
     *
     * @throws Exception If there's an error during the processing of meetings.
     */
    public function execute() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
        require_once($CFG->dirroot . '/blocks/zoomonline/block_zoomonline.php');

        $blockzoomonline = new \block_zoomonline();
        $starttime = microtime(true);
        $counter = 0;

        mtrace("Starting to process Zoom meetings...");

        try {
            // Fetch all Zoom meetings once.
            $meetings = $DB->get_records('block_zoomonline_links');
            if (!$meetings) {
                mtrace("No meetings found to process.");
                return;
            }

            // Get current timestamp for date comparisons.
            $nowts = time();
            // Fetch all course end dates at once to minimize queries inside the loop.
            $courseenddates = $DB->get_records_sql_menu("SELECT id, enddate FROM {course}");

            foreach ($meetings as $meet) {
                $zoomid = $meet->zoom_meeting_id;
                $crsid = $meet->course_id;
                $lecid = $meet->lecturer_id;

                // Skip processing if the course has ended.
                if (isset($courseenddates[$crsid]) && $courseenddates[$crsid] < $nowts) {
                    mtrace("Skipping meeting $zoomid due to course end date.");
                    continue;
                }

                mtrace("Processing meeting: $zoomid (Course ID: $crsid)");
                try {
                    // Process recordings for the meeting.
                    $blockzoomonline->block_zoomonline_process_recordings($zoomid, null, null, $crsid, $lecid);
                } catch (Exception $ex) {
                    mtrace("Error processing recordings for meeting $zoomid: " . $ex->getMessage());
                }

                // Check if the attendance tracking setting is enabled.
                if (get_config('block_zoomonline', 'useattendance')) {
                    // Check if the Attendance module is installed.
                    $installedmodules = \core_plugin_manager::instance()->get_plugins_of_type('mod');

                    if (array_key_exists('attendance', $installedmodules)) {
                        // The Attendance module is installed, proceed with attendance processing.
                        try {
                            // Process attendance for the meeting.
                            $blockzoomonline->block_zoomonline_process_attendance($zoomid, $crsid);
                        } catch (Exception $ex) {
                            mtrace("Error processing attendance for meeting $zoomid: " . $ex->getMessage());
                        }

                        try {
                            // Check live meeting status.
                            $blockzoomonline->block_zoomonline_check_live_meeting($zoomid, $crsid);
                        } catch (Exception $ex) {
                            mtrace("Error checking live meeting for $zoomid: " . $ex->getMessage());
                        }
                    } else {
                        // The Attendance module is not installed, skip attendance processing.
                        echo "Attendance module is not installed. Skipping attendance processing.";
                        // You could log this event or take an alternative action if necessary.
                    }
                }

                $counter++;
            }
        } catch (Exception $ex) {
            mtrace("General error during meeting processing: " . $ex->getMessage());
        }

        $elapsed = round(microtime(true) - $starttime, 2);
        mtrace("$counter meetings processed (took $elapsed seconds).");
    }
}
