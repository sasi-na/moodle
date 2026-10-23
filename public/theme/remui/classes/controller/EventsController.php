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
 * Edwiser RemUI
 *
 * @package   theme_remui
 * @copyright (c) 2023 WisdmLabs (https://wisdmlabs.com/) <support@wisdmlabs.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace theme_remui\controller;

/**
 * Class EventsController will handle the events triggered by Moodle.
 */
class EventsController {

    public static function remui_stats_common($userid, $courseid, $deleteActivity = false, $coursedeleted=false) {
        global $DB;

        $coursehandler = new \theme_remui_coursehandler();
        $course = null;

        if ($userid) {
            if (get_config( "theme_remui", "enabledashboardcoursestats")) {
                $coursehandler->set_dashboard_stats($userid);
            } else {
                set_config("edwdashboardstats", "", "theme_remui");
            }
        }
        if ($courseid) {
            if (get_config( "theme_remui", "enablecoursestats")) {
                if (!$coursedeleted) {
                    $course = $DB->get_record('course', ['id' => $courseid]);
                    if (!$course) {
                        return;
                    }
                    $coursehandler->set_course_stats($course, true);
                }

                if ($deleteActivity && $course) {
                    if (get_config( "theme_remui", "enabledashboardcoursestats")) {
                        $coursehandler->reset_dashboard_stats_for_users_incourse($course);
                    } else {
                        set_config("edwdashboardstats", "", "theme_remui");
                    }
                }
            } else {
                set_config("edwcoursestats", "", "theme_remui");
            }
        }
    }
    public static function user_enrollment_event($eventdata) {

        $data = $eventdata->get_data();

        $userid = $data['relateduserid'];

        EventsController::remui_stats_common($userid, $eventdata->courseid);

        set_user_preference('course_cache_reset', true, $userid);

        // Update Enrollment History Data.
        $pnotification = new \theme_remui\productnotifications();
        $pnotification->update_enrollment_history();
    }

    public static function course_updation_event($eventdata) {
        // Set Global Config to acknowledge to reset the cache.
        // Can reset order is not just for enrolled students.
        // Need to reset the cache of all users as that course get displayed in All Courses Tab.

        $data = $eventdata->get_data();

        EventsController::remui_stats_common($data['relateduserid'], $eventdata->courseid);

        set_config('cache_reset_time', time(), 'theme_remui');
    }

    public static function course_deletion_event($eventdata) {
        // Set Global Config to acknowledge to reset the cache.
        // Can reset order is not just for enrolled students.
        // Need to reset the cache of all users as that course get displayed in All Courses Tab.

        $data = $eventdata->get_data();

        EventsController::remui_stats_common($data['relateduserid'], $eventdata->courseid, false, true);

        set_config('cache_reset_time', time(), 'theme_remui');
    }

    public static function updation_on_create_delete_activity($eventdata) {
        // Set Global Config to acknowledge to reset the cache.
        // Can reset order is not just for enrolled students.
        // Need to reset the cache of all users as that course get displayed in All Courses Tab.

        EventsController::remui_stats_common("", $eventdata->courseid, true);

        set_config('cache_reset_time', time(), 'theme_remui');
    }


    public static function user_loggedin_event($eventdata) {
        global $USER;
        set_user_preference('enable_focus_mode', null, $USER->id);

        set_user_preference('animate_dm_icon', true, $USER->id);

    }

    public static function customfield_updated($eventdata) {
        global $DB;
        $data = $eventdata->get_data();
        $fieldid = $data['objectid'] ?? null;

        if (empty($fieldid)) {
            return;
        }

        // Only handle the skill-level field for courses.
        $field = $DB->get_record('customfield_field', ['id' => $fieldid], 'id, shortname, configdata');
        if (!$field || $field->shortname !== 'edwskilllevel') {
            return;
        }

        $config = json_decode($field->configdata);
        // Keep options as stored (1-based alignment with intvalue).
        $options = preg_split('/\r\n|\n|\r/', $config->options ?? '');
        $defaultindex = 0;

        // Current distribution of intvalues for this field.
        $sql = "SELECT cd.intvalue, COUNT(cd.intvalue) AS count
                FROM {customfield_field} cf
                INNER JOIN {customfield_data} cd
                ON cf.id = cd.fieldid
                WHERE cf.id = :fieldid
                GROUP BY cd.intvalue
                ORDER BY cd.intvalue";

        $result = $DB->get_records_sql($sql, ['fieldid' => $fieldid]);

        // Find intvalues that no longer map to an existing option (excluding 0).
        $invalidskillvalues = [];
        foreach ($result as $leveldata) {
            if ($leveldata->intvalue != 0 && !isset($options[$leveldata->intvalue - 1])) {
                $invalidskillvalues[] = (int)$leveldata->intvalue;
            }
        }

        if (empty($invalidskillvalues)) {
            return;
        }

        // Move affected courses to the default index (0) to avoid invalid lookups.
        foreach ($invalidskillvalues as $invalidvalue) {
            $affectedcourseids = \theme_remui\utility::get_skilllevel_filtered_courseids([$invalidvalue]);
            if (empty($affectedcourseids)) {
                continue;
            }

            list($courseinsql, $courseinparams) = $DB->get_in_or_equal($affectedcourseids, SQL_PARAMS_NAMED);

            $updateparams = array_merge(
                [
                    'fieldid' => $fieldid,
                    'newintvalue' => $defaultindex,
                ],
                $courseinparams
            );

            $updatesql = "UPDATE {customfield_data}
                          SET intvalue = :newintvalue
                          WHERE fieldid = :fieldid
                            AND instanceid $courseinsql";

            $DB->execute($updatesql, $updateparams);
        }
    }
}
