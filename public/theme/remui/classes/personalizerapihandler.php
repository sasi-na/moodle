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
 * Personalizer class for handling footer design actions
 *
 * @package   theme_remui
 * @copyright (c) 2023 WisdmLabs (https://wisdmlabs.com/) <support@wisdmlabs.com>
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

namespace theme_remui;

defined('MOODLE_INTERNAL') || die;

/**
 * Personalizer class
 */
class personalizerapihandler {

    /**
     * Base URL for footer assets
     */
    private $baseurl = 'https://qastaticcdn.edwiser.org/theme_remuiassets//footerassets/';

    /**
     * Performs the specified action using the provided configuration.
     *
     * @param string $action The action to perform.
     * @param mixed $config The configuration data required for the action.
     * @return mixed The result of the action, or an error message if the action function does not exist.
     */
    public function perform_action($action, $config) {
        $functionname = "action_".$action;

        // Check if the function exists before calling it.
        if (method_exists($this, $functionname)) {
            // Call the function dynamically.
            return call_user_func(array($this, $functionname), $config);
        } else {
            // Handle the case when the function doesn't exist.
            return "Function $functionname does not exist.";
        }
    }

    /**
     * Get footer design from external server
     * @param array $config Configuration with design identifier
     * @return array Response with footer design data
     */
    public function action_get_footer_design_dummy_data($config) {
        if (!isset($config['design'])) {
            return [
                'success' => false,
                'error' => 'Design parameter is required'
            ];
        }

        $design = $config['design'];
        $url = $this->baseurl . $design . '.json';

        try {
            $response = $this->fetch_external_json($url);

            if ($response === false) {
                return [
                    'success' => false,
                    'error' => 'Failed to fetch footer design from external server',
                    'url' => $url
                ];
            }

            return [
                'success' => true,
                'design' => $design,
                'data' => $response
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error fetching footer design: ' . $e->getMessage(),
                'design' => $design
            ];
        }
    }

    /**
     * Fetch footer configuration settings based on selected footer design
     * @param array $config Configuration data containing flayout
     * @return array Response with footer settings data
     */
    public function action_get_footer_settings($config) {
        global $DB;

        try {
            // Get flayout from config.
            $flayout = isset($config['flayout']) ? $config['flayout'] : null;

            if (!$flayout) {
                return [
                    'success' => false,
                    'error' => 'Footer layout (flayout) is required'
                ];
            }

            // Load design-specific settings from JSON file.
            $designsettings = $this->load_footer_design_settings($flayout);

            if (!$designsettings) {
                return [
                    'success' => false,
                    'error' => 'Footer design settings not found for: ' . $flayout
                ];
            }

            // Extract setting keys from design configuration.
            $requiredsettings = array_keys($designsettings['settings']);

            // Query database for only the required settings.
            $dbsettings = [];
            if (!empty($requiredsettings)) {
                $placeholders = str_repeat('?,', count($requiredsettings) - 1) . '?';
                $footersettings = $DB->get_records_sql(
                    "SELECT name, value FROM {config_plugins}
                     WHERE plugin = 'theme_remui'
                     AND name IN ($placeholders)",
                    $requiredsettings
                );

                foreach ($footersettings as $setting) {
                    $dbsettings[$setting->name] = $setting->value;
                }
            }

            // Merge design settings with database values.
            $mergedsettings = $this->merge_design_with_db_settings($designsettings, $dbsettings);

            // Clean up JSON strings for menu settings to replace escaped quotes with single quotes
            foreach ($mergedsettings as $key => $value) {
                if (strpos($key, 'menu') !== false && is_string($value)) {
                    // First, try to decode the JSON to see if it's double-encoded
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        // It's valid JSON, re-encode with single quotes
                        $mergedsettings[$key] = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        // Replace double quotes with single quotes
                        $mergedsettings[$key] = str_replace('"', "'", $mergedsettings[$key]);
                    } else {
                        // It's not valid JSON, just replace escaped quotes with single quotes
                        $mergedsettings[$key] = str_replace('\"', "'", $value);
                    }
                }
            }
            return [
                'success' => true,
                'settings' => $mergedsettings,
                'count' => count($mergedsettings),
                'flayout' => $flayout,
                'design_name' => $designsettings['name'],
                'design_description' => $designsettings['description']
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error fetching footer settings: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Load footer design settings from JSON file
     * @param string $flayout Footer layout identifier
     * @return array|false Design settings or false if not found
     */
    private function load_footer_design_settings($flayout) {
        global $CFG;

        $jsonfile = $CFG->dirroot . '/theme/remui/json_files/footer_design_settings.json';

        if (!file_exists($jsonfile)) {
            return false;
        }

        $jsoncontent = file_get_contents($jsonfile);
        $designs = json_decode($jsoncontent, true);

        if (!$designs || !isset($designs[$flayout])) {
            return false;
        }

        return $designs[$flayout];
    }

    /**
     * Merge design settings with database values
     * @param array $designsettings Design configuration from JSON
     * @param array $dbsettings Database settings
     * @return array Merged settings with database values taking priority
     */
    private function merge_design_with_db_settings($designsettings, $dbsettings) {
        $mergedsettings = [];

        // Start with design settings as base structure.
        foreach ($designsettings['settings'] as $settingname => $langkey) {
            // Use database value if available, otherwise use empty string.
            $mergedsettings[$settingname] = isset($dbsettings[$settingname]) ? $dbsettings[$settingname] : '';
        }

        // Add any additional database settings not in design config.
        foreach ($dbsettings as $settingname => $value) {
            if (!isset($mergedsettings[$settingname])) {
                $mergedsettings[$settingname] = $value;
            }
        }

        return $mergedsettings;
    }

    /**
     * Fetch JSON from external URL
     * @param string $url External URL to fetch
     * @return array|false JSON data or false on failure
     */
    private function fetch_external_json($url) {
        global $CFG;

        require_once($CFG->libdir . "/filelib.php");

        try {
            $c = new \curl;
            $html = $c->get($url);
            // Encode and then decode is jugad for one issue we face while updating the blocks.
            return $html;

        } catch (\Exception $e) {
            return false;
        }
    }

}
