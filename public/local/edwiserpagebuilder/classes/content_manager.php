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
 * @package   local_edwiserpagebuilder
 * @copyright (c) 2022 WisdmLabs (https://wisdmlabs.com/) <support@wisdmlabs.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Gourav Govande
 */

namespace local_edwiserpagebuilder;

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once($CFG->dirroot . "/local/edwiserpagebuilder/lib.php");
define_cdn_constants();

use stdClass;
use context_course;
/**
 * content_manager class handles everything related to block contents.
 */
class content_manager {

    public function get_json_file_data($url) {
        global $CFG;

        require_once($CFG->libdir . "/filelib.php");

        try {
            $c = new \curl;
            $html = $c->get($url);

        } catch (\Exception $e) {
            echo $e;
            exit;
        }

        // Encode and then deccode is jugad for one issue we face while updating the blocks.
        return json_decode($html);

    }

    /**
     * Get block content from proxy API
     *
     * @param string $blockname Name of the block
     * @return object|null Block content object or null on failure
     */
    public function get_block_content_from_proxy($blockname) {
        global $CFG;

        require_once($CFG->libdir . '/filelib.php');

        // Resolve license payload via helper (internally cached per request).
        $cachedlicensepayload = local_edwiserpagebuilder_get_proxy_license_payload();

        // Prepare the POST data.
        $postdata = [
            'blockname' => $blockname,
            'license' => $cachedlicensepayload['license'],
            'item_name' => $cachedlicensepayload['item_name'],
            'siteurl' => $CFG->wwwroot,
            'current_version' => $cachedlicensepayload['current_version'],
        ];

        $jsonpayload = json_encode($postdata);

        // Create new curl instance with security bypass for faster requests.
        $curl = new \curl([
            'cache' => false, // Disable caching for API requests.
        ]);

        // Optimized curl options for fast HTTPS proxy API requests.
        $options = [
            'CURLOPT_TIMEOUT' => 8, // Reduced timeout for faster failure detection.
            'CURLOPT_CONNECTTIMEOUT' => 3, // Faster connection timeout.
            'CURLOPT_FOLLOWLOCATION' => false, // API endpoint shouldn't redirect.
            'CURLOPT_HTTP_VERSION' => CURL_HTTP_VERSION_1_1,
            'CURLOPT_HTTPHEADER' => [
                'Content-Type: application/json',
                'Connection: close', // Prevent connection reuse issues.
            ],
            'CURLOPT_ENCODING' => '', // Enable automatic decompression (gzip, deflate).
            'CURLOPT_SSL_VERIFYPEER' => false, // Skip SSL verification for speed (simple API).
            'CURLOPT_SSL_VERIFYHOST' => false, // Skip host verification for speed.
            'CURLOPT_FRESH_CONNECT' => true, // Fresh connection per request.
        ];

        // Execute POST request using Moodle's curl class.
        $response = $curl->post(BLOCKS_PROXY_API_URL, $jsonpayload, $options);
        $info = $curl->get_info();
        $errno = $curl->get_errno();
        $error = $curl->error;
        $httpcode = !empty($info['http_code']) ? (int)$info['http_code'] : 0;

        // Check for successful response.
        if ($errno === 0 && $response !== false && $httpcode >= 200 && $httpcode < 300) {
            $decoded = json_decode($response, false);
            if ($decoded !== null && !empty($decoded) && !isset($decoded->error)) {
                return isset($decoded->data) ? $decoded->data : $decoded;
            }
            // Invalid JSON or error in response.
            if ($decoded === null) {
                debugging("Proxy API returned invalid JSON for block '{$blockname}': " .
                    json_last_error_msg(), DEBUG_DEVELOPER);
            }
        } else {
            debugging("Proxy API error for block '{$blockname}': HTTP {$httpcode}, cURL errno: {$errno}, Error: {$error}", DEBUG_DEVELOPER);
        }

        return null;
    }

    /**
     * Update the block content with optional batching support.
     *
     * @param int|false $limit Number of blocks to process per batch, false for all blocks
     * @param int $offset Starting position for batch processing
     * @return array|false Array with batch information or false on error
     */
    public function update_block_content($limit = false, $offset = 0) {

        // Here we Update all the blocks content.
        $data = $this->get_json_file_data(BLOCKS_LIST_URL);
        // $pages = $this->get_json_file_data(PAGE_LIST_URL);

        if (isset($pages) && $pages) {
            $data->blocks = array_merge($data->blocks, $pages->pages);
        }

        if (is_array($data) || is_object($data)) {
            foreach ($data as $key => $value) {
                if ($key == "cardlayouts") {
                    // Here we update the layouts list.
                    $this->make_entry_by_data($value, true);
                    continue;
                }
                // Here we update the blocks list.
                $result = $this->make_entry_by_data($value, false, $limit, $offset);

                // If we have a result and it's not complete, return it
                if ($result && isset($result['complete']) && !$result['complete']) {
                    return $result;
                }
            }
        }
        // If we reach here, all blocks are processed
        return [
            'limit' => $limit,
            'total' => 0,
            'processed' => 0,
            'remaining' => 0,
            'complete' => true,
            'next_offset' => 0
        ];
    }

    /**
     * Process blocks data with optional batching support.
     *
     * @param array $blocks Array of block objects to process
     * @param bool $islayout Whether these are layout blocks
     * @param int|false $limit Number of blocks to process per batch, false for all blocks
     * @param int $offset Starting position for batch processing
     * @return array Array with batch information including completion status
     */
    public function make_entry_by_data($blocks, $islayout=false, $limit = false, $offset = 0) {
        $bm = new block_handler();
        $pm = new page_manager();
        $reftable = $bm->get_block_table_name();
        $makeentrycall = "make_entry";

        if ($islayout) {
            $reftable = $bm->get_cl_table_name();
            $makeentrycall = "make_entry_layout";
        }

        $existingblocks = $bm->get_record_from_table($reftable, array(), "title,id,version,updateavailable");
        $totalblocks = count($blocks);
        $count = 0;
        $processed = 0;

        // Apply offset if limit is set
        $startindex = $limit ? $offset : 0;
        $endindex = $limit ? min($offset + $limit, $totalblocks) : $totalblocks;

        // Store all block titles for this batch for later deprecation check
        $batchblocktitles = [];

        for ($i = $startindex; $i < $endindex; $i++) {
            $block = $blocks[$i];

            // Store block title for later deprecation check
            $batchblocktitles[] = $block->title;

            // To change the location for.
            // $contenturl = BLOCKS_CONTENT_URL;
            // if (isset($block->type) && $block->type == "page") {
            //     $contenturl = BLOCKS_CONTENT_URL . "pages/";
            // }

            // Check if block should be processed (version check for existing blocks)
            $shouldprocess = false;
            if (isset($existingblocks[$block->title])) {
                if ($existingblocks[$block->title]->updateavailable) {
                    continue; // Skip if already marked for update.
                }
                if (($block->version > $existingblocks[$block->title]->version)) {
                    $shouldprocess = true;
                }
            } else {
                // New block - always process
                $shouldprocess = true;
            }

            // Process block if needed using common helper function
            if ($shouldprocess) {
                $this->process_single_block($block, $islayout, false, $bm, $pm, $reftable);
            }
            $count++;
            $processed++;
        }
        // Store batch block titles for final deprecation check
        if ($limit) {
            $batchkey = "batch_blocks_{$reftable}_{$offset}";
            set_config($batchkey, json_encode($batchblocktitles), 'local_edwiserpagebuilder');
        } else {
            // No batching - store all block titles for immediate deprecation check
            $allblockskey = "all_blocks_{$reftable}";
            set_config($allblockskey, json_encode($batchblocktitles), 'local_edwiserpagebuilder');
        }

        // Only process deprecated blocks in final batch or when no batching
        if (!$limit || ($offset + $limit >= $totalblocks)) {
            $this->processfinaldeprecation($reftable, $bm, $islayout);
        }

        // Return batch information
        if ($limit) {
            // Calculate actual processed count for this batch
            $actualprocessed = min($limit, $totalblocks - $offset);
            $remaining = max(0, $totalblocks - ($offset + $actualprocessed));
            $iscomplete = ($offset + $actualprocessed) >= $totalblocks;

            return [
                'limit' => $limit,
                'total' => $totalblocks,
                'processed' => $actualprocessed,
                'remaining' => $remaining,
                'complete' => $iscomplete,
                'next_offset' => $offset + $actualprocessed
            ];
        } else {
            // No limit - all blocks processed
            return [
                'limit' => 0,
                'total' => $totalblocks,
                'processed' => $totalblocks,
                'remaining' => 0,
                'complete' => true,
                'next_offset' => 0
            ];
        }
    }

    /**
     * Process final deprecation check with complete information from all batches
     * This method is called only once when all blocks have been processed
     */
    private function processfinaldeprecation($reftable, $bm, $islayout) {
        // Get all batch block titles for this table
        $allbatchtitles = [];

        // First, try to get all blocks from non-batching mode
        $allblockskey = "all_blocks_{$reftable}";
        $allblocksconfig = get_config('local_edwiserpagebuilder', $allblockskey);

        if ($allblocksconfig) {
            // Non-batching mode: use the stored all blocks
            $allbatchtitles = json_decode($allblocksconfig, true);
            // Clean up the all blocks config
            unset_config($allblockskey, 'local_edwiserpagebuilder');
        } else {
            // Batching mode: collect from all batch configs
            $tableprefix = "batch_blocks_{$reftable}_";
            $allconfigs = get_config('local_edwiserpagebuilder');

            foreach ($allconfigs as $key => $value) {
                if (strpos($key, $tableprefix) === 0) {
                    $batchtitles = json_decode($value, true);
                    if (is_array($batchtitles)) {
                        $allbatchtitles = array_merge($allbatchtitles, $batchtitles);
                    }
                }
            }

            // Clean up batch configs for this table
            foreach ($allconfigs as $key => $value) {
                if (strpos($key, $tableprefix) === 0) {
                    unset_config($key, 'local_edwiserpagebuilder');
                }
            }
        }

        // Get existing blocks from database
        $existingblocks = $bm->get_record_from_table($reftable, array(), "title,id,version,updateavailable");

        // Find truly deprecated blocks (exist in database but not in any batch)
        foreach ($existingblocks as $title => $blockdata) {
            if (!in_array($title, $allbatchtitles)) {
                $bm->deprecate_block($reftable, $blockdata);
            }
        }
    }

    public function update_block_content_by_name($blockname, $islayout = false) {
        $bm = new block_handler();
        if ($blockname != "") {
            // Here we update the block content by block name.
            // $content = $this->get_json_file_data(BLOCKS_CONTENT_URL . $blockname . ".json");
            // Fetch block content from proxy API
            $content = $this->get_block_content_from_proxy($blockname);

            if ($content) {
                // Encrypting the content
                $content->content = json_encode($content->content);
                return $bm->update_block_content($content, $islayout);// true to update the content.
            } else {
                return get_string("unabletofetchjson", "local_edwiserpagebuilder");
            }
        } else {
            return get_string("provideproperblockname", "local_edwiserpagebuilder");
        }
    }

    /**
     * Update pro blocks content with optional batching support.
     * This method bypasses version check and updates all locked (pro) blocks.
     * 
     * Strategy: First fetch ALL locked blocks upfront to create a fixed list,
     * then process them in batches. This prevents issues where locked field
     * changes during updates.
     *
     * @param int|false $limit Number of blocks to process per batch, false for all blocks
     * @param int $offset Starting position for batch processing
     * @return array|false Array with batch information or false on error
     */
    public function update_pro_block_content($limit = false, $offset = 0) {
        $bm = new block_handler();

        // If offset is 0, this is the first call - fetch and cache locked blocks
        // If offset > 0, retrieve cached list from previous call
        if ($offset == 0) {
            // First call: Get ALL locked blocks from database upfront
            // This creates a fixed list that won't change during updates
            $reftable = $bm->get_block_table_name();
            $lockedblocks = $bm->get_record_from_table($reftable, array('locked' => 1), "title,id,version,updateavailable");
            $problockstitles = array_keys($lockedblocks);

            // If no locked blocks found, return early
            if (empty($problockstitles)) {
                return $this->get_empty_batch_response($limit);
            }

            // Cache the locked blocks titles list for subsequent calls
            // Use a temporary config that will be cleaned up after completion
            set_config('update_pro_blocks_titles_cache', json_encode($problockstitles), 'local_edwiserpagebuilder');
            set_config('update_pro_blocks_cache_time', time(), 'local_edwiserpagebuilder');
        } else {
            // Subsequent calls: Retrieve cached list from first call
            $cachedtitles = get_config('local_edwiserpagebuilder', 'update_pro_blocks_titles_cache');
            
            // Check if cache exists and is not too old (max 1 hour)
            $cachetime = get_config('local_edwiserpagebuilder', 'update_pro_blocks_cache_time');
            if (!$cachedtitles || !$cachetime || (time() - $cachetime > 3600)) {
                // Cache expired or missing - treat as new request
                return $this->update_pro_block_content($limit, 0);
            }
            
            $problockstitles = json_decode($cachedtitles, true);
            
            if (empty($problockstitles)) {
                // No cached blocks, clear cache and return
                $this->clear_pro_blocks_cache();
                return $this->get_empty_batch_response($limit);
            }
        }

        // Fetch blocks list from remote source
        $data = $this->get_json_file_data(BLOCKS_LIST_URL);
        if (!$data) {
            // Clean up cache on error
            if ($offset == 0) {
                $this->clear_pro_blocks_cache();
            }
            return $this->get_empty_batch_response($limit);
        }

        // Filter blocks to only include locked (pro) blocks from our fixed cached list
        $problocks = [];
        if (isset($data->blocks) && is_array($data->blocks)) {
            foreach ($data->blocks as $block) {
                // Check if this block is in our cached locked blocks list
                if (in_array($block->title, $problockstitles)) {
                    $problocks[] = $block;
                }
            }
        }

        // Process layouts if available
        // Get layouts that belong to locked blocks from cached list
        $prolayouts = [];
        if (isset($data->cardlayouts) && is_array($data->cardlayouts)) {
            $problockstitleslower = array_map('strtolower', $problockstitles);

            foreach ($data->cardlayouts as $layout) {
                // Check if layout belongs to a pro block
                if (isset($layout->belongsto) && in_array(strtolower($layout->belongsto), $problockstitleslower)) {
                    $prolayouts[] = $layout;
                }
            }
        }

        // Combine blocks and layouts into single array for unified batching
        $allproitems = array_merge($problocks, $prolayouts);
        $totalproitems = count($allproitems);
        
        if ($totalproitems == 0) {
            // Clean up cache if no items found
            $this->clear_pro_blocks_cache();
            return $this->get_empty_batch_response($limit);
        }

        // Process all items (blocks + layouts) in a unified batch
        // We need to track which items are blocks vs layouts
        $blockscount = count($problocks);
        
        // Split items based on offset and limit
        if ($limit) {
            $startindex = min($offset, $totalproitems);
            $endindex = min($offset + $limit, $totalproitems);
        } else {
            $startindex = 0;
            $endindex = $totalproitems;
        }

        $processed = 0;
        $itemsprocessed = array_slice($allproitems, $startindex, $endindex - $startindex);

        // Process each item
        foreach ($itemsprocessed as $index => $item) {
            $actualindex = $startindex + $index;
            $islayout = ($actualindex >= $blockscount);
            
            $result = $this->make_entry_by_data_pro_blocks(array($item), $islayout, false, 0);
            if ($result && $result['processed'] > 0) {
                $processed++;
            }
        }

        // Calculate completion status
        $iscomplete = ($endindex >= $totalproitems);
        $nextoffset = $iscomplete ? 0 : $endindex;

        // Clean up cache when all processing is complete
        if ($iscomplete) {
            $this->clear_pro_blocks_cache();
        }

        return [
            'limit' => $limit ?: 0,
            'total' => $totalproitems,
            'processed' => $processed,
            'remaining' => max(0, $totalproitems - $endindex),
            'complete' => $iscomplete,
            'next_offset' => $nextoffset
        ];
    }

    /**
     * Process a single block/layout item (common helper for both regular and pro blocks).
     *
     * @param object $block Block/layout object to process
     * @param bool $islayout Whether this is a layout block
     * @param bool $bypassversion Whether to bypass version check
     * @param block_handler $bm Block handler instance
     * @param page_manager $pm Page manager instance
     * @param string $reftable Reference table name
     * @return bool True if processed successfully, false otherwise
     */
    private function process_single_block($block, $islayout, $bypassversion, $bm, $pm, $reftable) {
        // Fetch block content from proxy API
        $content = $this->get_block_content_from_proxy($block->title);
        if (!$content) {
            return false;
        }

        // Encrypting the content
        $content->content = json_encode($content->content);

        try {
            // Use make_entry/make_entry_layout with optional bypassversion parameter
            if ($islayout) {
                $recordid = $bm->make_entry_layout($content, true, $bypassversion);
            } else {
                $recordid = $bm->make_entry($content, $bypassversion);
            }

            if ($recordid !== null && isset($content->type) && $content->type == "page") {
                $pm->update_page_content($recordid, $content);
            }

            return ($recordid !== null || ($islayout && $recordid === true));
        } catch (\Exception $ex) {
            // Log error but continue
            $blocktype = $bypassversion ? 'pro block' : 'block';
            debugging("Error updating {$blocktype} {$block->title}: " . $ex->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Process pro blocks data (bypasses version check).
     * This method processes a single block or layout item.
     *
     * @param array $blocks Array with single block/layout object to process
     * @param bool $islayout Whether this is a layout block
     * @param int|false $limit Not used (kept for compatibility)
     * @param int $offset Not used (kept for compatibility)
     * @return array Array with batch information including completion status
     */
    public function make_entry_by_data_pro_blocks($blocks, $islayout = false, $limit = false, $offset = 0) {
        $bm = new block_handler();
        $pm = new page_manager();
        $reftable = $bm->get_block_table_name();

        if ($islayout) {
            $reftable = $bm->get_cl_table_name();
        }

        $processed = 0;
        $totalblocks = count($blocks);

        if ($totalblocks == 0) {
            return [
                'limit' => 0,
                'total' => 0,
                'processed' => 0,
                'remaining' => 0,
                'complete' => true,
                'next_offset' => 0
            ];
        }

        // Process each block using common helper function
        foreach ($blocks as $block) {
            if ($this->process_single_block($block, $islayout, true, $bm, $pm, $reftable)) {
                $processed++;
            }
        }

        return [
            'limit' => 0,
            'total' => $totalblocks,
            'processed' => $processed,
            'remaining' => 0,
            'complete' => true,
            'next_offset' => 0
        ];
    }
    /**
     * Get empty batch response (helper to avoid duplication).
     *
     * @param int|false $limit Number of blocks per batch
     * @return array Empty batch response array
     */
    private function get_empty_batch_response($limit = false) {
        return [
            'limit' => $limit ?: 0,
            'total' => 0,
            'processed' => 0,
            'remaining' => 0,
            'complete' => true,
            'next_offset' => 0
        ];
    }

    /**
     * Clear pro blocks cache (helper to avoid duplication).
     */
    private function clear_pro_blocks_cache() {
        unset_config('update_pro_blocks_titles_cache', 'local_edwiserpagebuilder');
        unset_config('update_pro_blocks_cache_time', 'local_edwiserpagebuilder');
    }

    public function can_edit_systemlevel_modules() {
        $context = context_course::instance(1); // System level course.
        if (has_capability('moodle/course:manageactivities', $context)) {
            return true;
        }

        return false;
    }

    public function generate_add_block_modal() {
        global $PAGE, $CFG, $OUTPUT;

        require_once($CFG->libdir . '/blocklib.php');

        $blockslist = [];
        $layoutlist = [];
        if (check_plugin_available("block_edwiseradvancedblock")) {
            $bm = new block_handler();
            $blocks = $bm->fetch_blocks_list(array("type" => "block")); // Fetching Edwiser Blocks

            $templatecontext['edwpageurl'] = strstr($PAGE->url->out(false), "?");
            $templatecontext['can_fetch_blocks'] = true;
            foreach ($blocks as $key => $block) {
                $obj = new stdClass();
                $obj->id = $block->id;
                $actionurl = $PAGE->url->out(false, array('bui_addblock' => '', 'sesskey' => sesskey()));
                $obj->url = strstr($actionurl, "?");// removes string upto substring i.e. "?"
                $obj->name = "edwiseradvancedblock";
                $obj->section = $block->title;
                $obj->title = $block->label;
                $obj->additionalclass = "isblock";
                $obj->thumbnail = str_replace("{{>cdnurl}}", CDNIMAGES, $block->thumbnail);
                $obj->updateavailable = $block->updateavailable;
                $obj->visible = $block->visible;
                if ($block->updateavailable || !$block->visible) {
                    $obj->hasextrabutton = true;
                }
                $blockslist[] = $obj;
            }

            if ($this->can_edit_systemlevel_modules() && check_plugin_available("mod_page")) {
                $templatecontext['can_fetch_pages'] = true;
            }
        }

        $bm = new \block_manager($PAGE);
        $bm->load_blocks(); // Loading all block plugins
        $coreblocks = $bm->get_addable_blocks();

        $blockslist = array_merge($blockslist, $coreblocks); // Fetching other block plugins

        foreach ($blockslist as $key => $block) {
            $actionurl = $PAGE->url->out(false, array('bui_addblock' => '', 'sesskey' => sesskey()));
            $block->url = strstr($actionurl, "?");// removes string upto substring i.e. "?"

            if (!isset($block->thumbnail)) {
                $block->thumbnail = $OUTPUT->image_url('default', 'local_edwiserpagebuilder');
            }

            // Remove edwiseradvancedblock from list
            if (!isset($block->section) && $block->name == "edwiseradvancedblock") {
                unset($blockslist[$key]);
            }

            if (!isset($block->section) && $block->name == "remuiblck") {
                $block->section = " ";
                $block->thumbnail = $OUTPUT->image_url('edwiser', 'local_edwiserpagebuilder');
            }
        }

        $templatecontext['blocks'] = array_values($blockslist);

        return $OUTPUT->render_from_template('local_edwiserpagebuilder/custom_modal', $templatecontext);
    }

    public function create_floating_add_a_block_button() {
        global $OUTPUT;

        $context['buttons']['ele_id'] = 'epbaddblockbutton';
        $context['buttons']['bgcolor'] = '#11c26d';
        $context['buttons']['title'] = get_string('addblock', 'core');
        $context['buttons']['icon'] = 'fa fa-plus';

        return $OUTPUT->render_from_template('local_edwiserpagebuilder/floating_buttons', $context);
    }
}
