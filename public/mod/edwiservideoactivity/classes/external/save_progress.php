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
 * Trait for saving video progress.
 *
 * @package    mod_edwiservideoactivity
 * @copyright  2025 Edwiser
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_edwiservideoactivity\external;

use context_module;
use external_function_parameters;
use external_single_structure;
use external_value;

/**
 * Trait implementing the save_progress external function.
 */
trait save_progress {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function save_progress_parameters() {
        return new external_function_parameters([
            'data' => new external_value(PARAM_RAW, 'JSON data containing cmid, currenttime, duration, percent, provider')
        ]);
    }

    /**
     * Save video progress for a user.
     *
     * @param string $data JSON data containing progress information
     * @return string JSON response
     */
    public static function save_progress($data) {
        global $DB, $USER;

        // Validate parameters.
        $params = self::validate_parameters(self::save_progress_parameters(), [
            'data' => $data
        ]);

        // Parse JSON data.
        $progressdata = json_decode($params['data'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return json_encode([
                'success' => false,
                'error' => 'Invalid JSON data'
            ]);
        }

        // Validate course module.
        $cm = get_coursemodule_from_id('edwiservideoactivity', $progressdata['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($progressdata['cmid']);
        self::validate_context($context);
        require_capability('mod/edwiservideoactivity:view', $context);

        // Get activity instance to determine sourcetype.
        $activity = $DB->get_record('edwiservideoactivity', ['id' => $cm->instance], '*', MUST_EXIST);

        // Get or create progress record.
        $progress = $DB->get_record('edwiservideoactivity_progress', [
            'cm_id' => $progressdata['cmid'],
            'user_id' => $USER->id
        ]);

        $now = time();

        // Get properly quoted column name using Moodle's DDL generator for reserved keywords.
        $generator = $DB->get_manager()->generator;
        $quotedpercent = $generator->getEncQuoted('percent');

        if ($progress) {
            // Update existing record using raw SQL to handle reserved keywords.
            $sql = "UPDATE {edwiservideoactivity_progress}
                       SET currenttime = :currenttime,
                           duration = :duration,
                           {$quotedpercent} = :percent,
                           provider = :provider,
                           timemodified = :timemodified
                     WHERE cm_id = :cmid AND user_id = :userid";
            $DB->execute($sql, [
                'currenttime' => $progressdata['currenttime'],
                'duration' => $progressdata['duration'],
                'percent' => $progressdata['percent'],
                'provider' => $progressdata['provider'],
                'timemodified' => $now,
                'cmid' => $progressdata['cmid'],
                'userid' => $USER->id
            ]);
        } else {
            // Create new record using raw SQL to handle reserved keywords.
            $sql = "INSERT INTO {edwiservideoactivity_progress}
                        (cm_id, user_id, currenttime, duration, {$quotedpercent}, videoname, sourcetype, provider,
                         timecreated, timemodified)
                    VALUES (:cmid, :userid, :currenttime, :duration, :percent, :videoname, :sourcetype,
                            :provider, :timecreated, :timemodified)";
            $DB->execute($sql, [
                'cmid' => $progressdata['cmid'],
                'userid' => $USER->id,
                'currenttime' => $progressdata['currenttime'],
                'duration' => $progressdata['duration'],
                'percent' => $progressdata['percent'],
                'videoname' => $cm->name,
                'sourcetype' => $activity->sourcetype,
                'provider' => $progressdata['provider'],
                'timecreated' => $now,
                'timemodified' => $now
            ]);
        }

        return json_encode([
            'success' => true,
            'progress' => $progressdata['percent'],
            'provider' => $progressdata['provider']
        ]);
    }

    /**
     * Returns description of method result value.
     *
     * @return external_value
     */
    public static function save_progress_returns() {
        return new external_value(PARAM_RAW, 'JSON response');
    }
}