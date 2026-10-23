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
 * Hook callbacks for format_edwiservideoformat
 *
 * @package    format_edwiservideoformat
 * @copyright  Edwiser
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_edwiservideoformat;

use core\hook\output\before_standard_head_html_generation;

/**
 * Hook callbacks for format_edwiservideoformat
 *
 * @package    format_edwiservideoformat
 * @copyright  Edwiser
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hooks {

    /**
     * Add navigation buttons to activity pages when using Video Course Format
     *
     * @param before_standard_head_html_generation $hook
     */
    public static function before_standard_head_html_generation(before_standard_head_html_generation $hook): void {
        global $PAGE, $COURSE, $cm, $CFG;

        // Check if we're on any activity view page with video format.
        if (strpos($PAGE->pagetype, 'mod-') === 0 &&
            strpos($PAGE->pagetype, '-view') !== false &&
            $PAGE->course->format === 'edwiservideoformat') {

            // Get the course format options to check display mode.
            $format = \core_courseformat\base::instance($COURSE);
            $formatoptions = $format->get_format_options();
            $displaymode = $formatoptions['activity_display_mode'] ?? 0;

            // Only inject navigation if display mode is redirect (1), not iframe (0).
            if ($displaymode === 0) {
                return; // Iframe mode - navigation is handled by course format.
            }

            // Display mode is redirect (1) - inject navigation buttons for activity pages.
            // Include the edwiservideoactivity lib to access get_next_prevbtn function.
            require_once($CFG->dirroot . '/mod/edwiservideoactivity/lib.php');

            // Use existing function to get navigation URLs.
            $navbtn = get_next_prevbtn($COURSE, $cm);

            // Convert course format URLs to direct activity URLs.
            $prevurl = null;
            $nexturl = null;

            if (!empty($navbtn['prevurl'])) {
                $prevurl = $navbtn['prevurl'];
                // Convert course format URL to direct activity URL.
                if (strpos($prevurl, 'course/view.php') !== false) {
                    $prevurl = self::convert_to_direct_activity_url($prevurl);
                }
            }

            if (!empty($navbtn['nexturl'])) {
                $nexturl = $navbtn['nexturl'];
                // Convert course format URL to direct activity URL.
                if (strpos($nexturl, 'course/view.php') !== false) {
                    $nexturl = self::convert_to_direct_activity_url($nexturl);
                }
            }

            // Use existing navigation HTML structure from activity.mustache.
            $navhtml = '<div class="edwiservideo-container">
                <div class="video-wrapper">';

            if (!empty($prevurl)) {
                $navhtml .= '<a href="' . $prevurl . '" class="edwactivity-prevbtn"></a>';
            }
            if (!empty($nexturl)) {
                $navhtml .= '<a href="' . $nexturl . '" class="edwactivity-nextbtn"></a>';
            }

            $navhtml .= '</div></div>';

            // Inject existing navigation HTML and add body class.
            $PAGE->requires->js_init_code("
                var navHtml = " . json_encode($navhtml) . ";
                document.body.insertAdjacentHTML('afterbegin', navHtml);
                document.body.classList.add('edwiservideoformat-activity-navigation');
            ");
        }
    }

    /**
     * Convert course format URL to direct activity URL.
     *
     * @param string $url Course format URL (e.g., course/view.php?id=2&modtype=book&modid=2)
     * @return string Direct activity URL (e.g., mod/book/view.php?id=2)
     */
    private static function convert_to_direct_activity_url($url) {
        global $CFG;

        // Parse the URL to extract parameters.
        $parsed = parse_url($url);
        if (!isset($parsed['query'])) {
            return $url; // Return original if no query parameters.
        }

        parse_str($parsed['query'], $params);

        // Check if we have the required parameters.
        if (!isset($params['modtype']) || !isset($params['modid'])) {
            return $url; // Return original if missing required parameters.
        }

        // Build direct activity URL.
        $modtype = $params['modtype'];
        $modid = $params['modid'];

        return $CFG->wwwroot . '/mod/' . $modtype . '/view.php?id=' . $modid;
    }
}
