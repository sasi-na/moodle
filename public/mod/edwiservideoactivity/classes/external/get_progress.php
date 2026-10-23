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
 * Trait for getting video progress.
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
 * Trait implementing the get_progress external function.
 */
trait get_progress {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function get_progress_parameters() {
        return new external_function_parameters([
            'data' => new external_value(PARAM_RAW, 'JSON data containing cmid')
        ]);
    }

    /**
     * Get video progress for a user.
     *
     * @param string $data JSON data containing cmid
     * @return string JSON response
     */
    public static function get_progress($data) {
        global $DB, $USER;

        // Validate parameters.
        $params = self::validate_parameters(self::get_progress_parameters(), [
            'data' => $data
        ]);

        // Parse JSON data.
        $requestdata = json_decode($params['data'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return json_encode([
                'success' => false,
                'error' => 'Invalid JSON data'
            ]);
        }

        // Validate course module.
        $cm = get_coursemodule_from_id('edwiservideoactivity', $requestdata['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($requestdata['cmid']);
        self::validate_context($context);
        require_capability('mod/edwiservideoactivity:view', $context);

        // Get progress record.
        $progress = $DB->get_record('edwiservideoactivity_progress', [
            'cm_id' => $requestdata['cmid'],
            'user_id' => $USER->id
        ]);

        if ($progress) {
            return json_encode([
                'progress' => [
                    'currenttime' => (int)$progress->currenttime,
                    'duration' => (int)$progress->duration,
                    'percent' => (int)$progress->percent,
                    'provider' => $progress->provider
                ]
            ]);
        }

        return json_encode([
            'progress' => null
        ]);
    }

    /**
     * Returns description of method result value.
     *
     * @return external_value
     */
    public static function get_progress_returns() {
        return new external_value(PARAM_RAW, 'JSON response');
    }
}

