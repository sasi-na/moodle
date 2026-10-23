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
 * This is the external method used for fetching all blocks.
 *
 * @package    local_edwiserpagebuilder
 * @since      Moodle 3.4
 * @copyright  2022 Gourav G <gourav.govande@wisdmlabs.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edwiserpagebuilder\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

use external_function_parameters;
use external_value;

trait edwiser_fetchblocks {

    /**
     * Describes the parameters for execute.
     *
     * @return external_function_parameters
     */
    public static function edwiser_fetchblocks_parameters() {
        return new external_function_parameters([
            'limit' => new external_value(PARAM_INT, 'Number of blocks per batch', VALUE_DEFAULT, 20),
            'offset' => new external_value(PARAM_INT, 'Starting position for batch', VALUE_DEFAULT, 0)
        ]);
    }

    /**
     * Execute the block fetching operation with batching support.
     *
     * @param int|false $limit Number of blocks to process per batch, false for all blocks (backward compatible)
     * @param int $offset Starting position for the batch (default: 0)
     * @return string JSON encoded response with batch information
     */
    public static function edwiser_fetchblocks($limit = false, $offset = 0) {
        global $CFG;

        $libpath = $CFG->dirroot . '/local/edwiserpagebuilder/lib.php';

        require_once($libpath);

        $hasfetched = get_config('local_edwiserpagebuilder', 'edwadvancedblockfatchstatus');

        if (!$hasfetched) {
            try {
                $result = local_edwiserpagebuilder_update_block_content($limit, $offset);

                // Ensure result is always defined and has required fields
                if (!$result || !is_array($result)) {
                    $result = [
                        'limit' => $limit ?: 0,
                        'total' => 0,
                        'processed' => 0,
                        'remaining' => 0,
                        'complete' => true,
                        'next_offset' => 0
                    ];
                }

                // Validate result structure and ensure all required fields exist
                if (!isset($result['complete'])) {
                    $result['complete'] = true;
                }
                if (!isset($result['limit'])) {
                    $result['limit'] = $limit ?: 0;
                }
                if (!isset($result['total'])) {
                    $result['total'] = 0;
                }
                if (!isset($result['processed'])) {
                    $result['processed'] = 0;
                }
                if (!isset($result['remaining'])) {
                    $result['remaining'] = 0;
                }
                if (!isset($result['next_offset'])) {
                    $result['next_offset'] = $result['complete'] ? 0 : ($offset + ($limit ?: 0));
                }

                // Check if all blocks are processed
                if ($result['complete'] || $result['remaining'] <= 0) {
                    set_config('edwadvancedblockfatchstatus', true, 'local_edwiserpagebuilder');
                }
            } catch (\Exception $e) {
                $result = [
                    'limit' => $limit ?: 0,
                    'total' => 0,
                    'processed' => 0,
                    'remaining' => 0,
                    'complete' => false,
                    'next_offset' => 0,
                    'error' => 'Exception occurred: ' . $e->getMessage()
                ];
            }
        } else {
            $result = [
                'limit' => $limit ?: 0,
                'total' => 0,
                'processed' => 0,
                'remaining' => 0,
                'complete' => true,
                'next_offset' => 0
            ];
        }

        $response = [
            'status' => $hasfetched,
            'result' => $result
        ];

        // Return JSON encoded response for flexibility
        return json_encode($response);
    }

    /**
     * Describes the execute return value.
     *
     * @return external_value
     */
    public static function edwiser_fetchblocks_returns() {
        return new external_value(PARAM_RAW, 'JSON encoded response with batch information');
    }
}
