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
 * External services for the ZoomOnline block.
 *
 * This file defines external services used by the ZoomOnline block for
 * AJAX and external interaction, including updating meeting visibility.
 *
 * @package    block_zoomonline
 * @category   external
 * @copyright  2024 Ciarán Mac Donncha
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->libdir/externallib.php");

/**
 * External API for the ZoomOnline block.
 *
 * This class defines the external services for the ZoomOnline block,
 * such as updating meeting visibility through AJAX or web services.
 *
 * @package    block_zoomonline
 * @category   external
 * @copyright  2024 Ciarán Mac Donncha
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_zoomonline_external extends external_api {

    /**
     * Define parameters for the visibility update.
     *
     * @return external_function_parameters
     */
    public static function update_visibility_parameters() {
        return new external_function_parameters([
                'meetingid' => new external_value(PARAM_INT, 'Meeting ID'),
                'visible' => new external_value(PARAM_INT, 'Visibility (1 = visible, 2 = hidden)'),
        ]);
    }

    /**
     * Update the visibility of a meeting.
     *
     * @param int $id Meeting ID.
     * @param int $visible Visibility (1 = visible, 2 = hidden).
     * @return array Result message.
     */
    public static function update_visibility($id, $visible) {
        global $DB;

        // Validate the parameters.
        $params = self::validate_parameters(
                self::update_visibility_parameters(),
                ['meetingid' => $id, 'visible' => $visible]
        );

        // Fetch the meeting record to determine the course context.
        $record = $DB->get_record('block_zoomonline_links', ['zoom_meeting_id' => $params['meetingid']], '*', MUST_EXIST);

        // Validate the course context.
        $context = context_course::instance($record->course_id);
        self::validate_context($context);

        // Ensure the user has the required capability.
        require_capability('block/zoomonline:managevisibility', $context);

        // Update the visibility in the database table.
        $record->visible = $params['visible'];
        $DB->update_record('block_zoomonline_links', $record);

        return ['message' => 'Visibility updated successfully.'];
    }


    /**
     * Define the structure of the response.
     *
     * @return external_single_structure
     */
    public static function update_visibility_returns() {
        return new external_single_structure([
                'message' => new external_value(PARAM_TEXT, 'Result message'),
        ]);
    }
}
