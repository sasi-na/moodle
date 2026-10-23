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
 * Hook to add _typeof polyfill for jspdf.min.js
 *
 * @package     local_edwiserreports
 * @copyright   2024
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edwiserreports\hook;

use core\hook\output\before_standard_head_html_generation;

/**
 * Hook callback to add _typeof polyfill for jspdf.min.js
 */
class before_standard_head_html {
    
    /**
     * Execute the hook callback
     * 
     * @param before_standard_head_html_generation $hook
     */
    public static function execute(before_standard_head_html_generation $hook): void {
        global $PAGE;
        
        // Only add polyfill on pages that might use jspdf
        $urlpath = $PAGE->url->get_path();
        if (strpos($urlpath, '/local/edwiserreports/') !== false) {
            $PAGE->requires->js_init_code('
                if (typeof window._typeof === "undefined") {
                    window._typeof = function(obj) {
                        return obj && typeof Symbol !== "undefined" && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj;
                    };
                }
                if (typeof globalThis !== "undefined" && typeof globalThis._typeof === "undefined") {
                    globalThis._typeof = window._typeof;
                }
            ', true);
        }
    }
}
