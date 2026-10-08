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
 * @package    block_quickmail
 * @copyright  2008 onwards Louisiana State University
 * @copyright  2008 onwards Chad Mazilly, Robert Russo, Jason Peak, Dave Elliott, Adam Zapletal, Philip Cali
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_systemreports\tasks;

defined('MOODLE_INTERNAL') || die();

class custom_quiz_time_update extends \core\task\scheduled_task {
    /*
     * This tasks kicks off the sending of a specific message to all of it's recipients
     * Note: this will in turn kick off subsequent scheduled tasks for each individual recipient delivery
     *
     * Required custom data: message_id
     */

    public function get_name() {
        return get_string('custom_quiz_time_update', 'local_systemreports');
    }

    public function execute() {
        global $DB;

        $maxquizattemptid = 0;
        $sql = "SELECT * FROM {local_timetrack} ORDER BY qid DESC LIMIT 1";
        $timequizattemptid = $DB->get_record_sql($sql);
        if ($timequizattemptid && $timequizattemptid->qid > 0) {
            $maxquizattemptid = $timequizattemptid->qid;
        }

        $attempts = "SELECT a.*, cm.id as cmid, cm.course FROM {quiz_attempts} a
                        JOIN {course_modules} cm ON a.quiz = cm.instance
                        WHERE cm.module = 18 AND a.id > $maxquizattemptid AND a.timefinish > 0 ORDER BY a.id LIMIT 0, 1000";

        $records = $DB->get_records_sql($attempts);

        foreach ($records as $value) {
            $obj = new \stdClass();

            $obj->userid = $value->userid;
            $obj->cmid = $value->cmid;
            $obj->courseid = $value->course;
            $obj->timespent = $value->timefinish - $value->timestart;
            $obj->timecreated = $value->timefinish;
            $obj->qid = $value->id;

            $DB->insert_record('local_timetrack', $obj);
        }
    }
}
