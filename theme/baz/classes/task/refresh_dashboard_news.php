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
 * Scheduled task that pre-renders the student dashboard news/podcast cards.
 *
 * @package   theme_baz
 * @copyright 2026 onlinejain.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_baz\task;

use theme_baz\local\dashboard_news;

defined('MOODLE_INTERNAL') || die();

/**
 * Fetches the configured RSS feeds and caches the rendered block HTML in Redis,
 * so /my/ never makes an outbound HTTP request while holding the session lock.
 */
class refresh_dashboard_news extends \core\task\scheduled_task {

    /**
     * @return string
     */
    public function get_name() {
        return get_string('task_refreshdashboardnews', 'theme_baz');
    }

    /**
     * Refresh every configured dashboard feed.
     */
    public function execute() {
        $results = dashboard_news::refresh();

        foreach ($results as $key => $bytes) {
            if ($bytes > 0) {
                mtrace("theme_baz dashboard news '{$key}': cached {$bytes} bytes.");
            } else if ($bytes === 0) {
                mtrace("theme_baz dashboard news '{$key}': no content returned, keeping previous cache.");
            } else {
                mtrace("theme_baz dashboard news '{$key}': refresh failed, keeping previous cache.");
            }
        }
    }
}
