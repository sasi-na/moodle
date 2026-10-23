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
 * @package   theme_remui
 * @copyright (c) 2023 WisdmLabs (https://wisdmlabs.com/) <support@wisdmlabs.com>
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 * @author    Your Name
 */

namespace theme_remui\external;

defined('MOODLE_INTERNAL') || die;

use external_function_parameters;
use external_value;
use external_single_structure;
use context_system;

trait do_personalization_action {
    /**
     * Describes the parameters for do_personalization_action
     * @return external_function_parameters
     */
    public static function do_personalization_action_parameters() {
        return new external_function_parameters(
            array(
                'action' => new external_value(PARAM_TEXT, 'Action Type (get_footer_design, apply_footer_design, reset_footer_design)'),
                'config' => new external_value(PARAM_RAW, 'Configuration data for the action')
            )
        );
    }

    /**
     * Handle personalization actions
     * @param string $action Action type to perform
     * @param string $config Configuration data as JSON string
     * @return string JSON response
     */
    public static function do_personalization_action($action, $config) {
        global $PAGE;
        $PAGE->set_context(context_system::instance());

        try {
            // Decode config if it's JSON
            $configdata = json_decode($config, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $configdata = $config; // Use as-is if not JSON
            }

            $personalizer = new \theme_remui\personalizerapihandler();
            $return = $personalizer->perform_action($action, $configdata);
            return json_encode($return);

        } catch (\Exception $e) {
            return json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'action' => $action
            ]);
        }
    }

    /**
     * Describes the do_personalization_action_returns value
     * @return external_value
     */
    public static function do_personalization_action_returns() {
        return new external_value(PARAM_RAW, 'JSON response');
    }
}