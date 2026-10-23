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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/completionlib.php');

// Inject Moodle version into JS
$PAGE->requires->js_init_code("evfmoodleversion = '" . $CFG->branch . "';");

// Retrieve course format option fields and add them to the $course object.
$format = core_courseformat\base::instance($course);
$course = $format->get_course();
$context = \core\context\course::instance($course->id);

// Make sure section 0 is created.
course_create_sections_if_missing($course, 0);

$renderer = $format->get_renderer($PAGE);

// Get format options and pass display mode to JavaScript
$formatoptions = $format->get_format_options();
$displaymode = $formatoptions['activity_display_mode'] ?? 0;


$PAGE->requires->js_call_amd('format_edwiservideoformat/common', 'init', [$displaymode]);

// Get section parameter for single section display
$section = optional_param('section', 0, PARAM_INT);

// Handle single section display
if ($section && $section > 0) {
    // Single section display - render only the specified section
    $outputclass = $format->get_output_classname('content');
    $widget = new $outputclass($format);
    $widget->set_single_section($section);
    echo $renderer->render_single_section($widget);
} else {
    // All sections display - render all sections
    $outputclass = $format->get_output_classname('content');
    $widget = new $outputclass($format);
    echo $renderer->render($widget);
}

$courseid = required_param('id', PARAM_INT);
$modtype  = optional_param('modtype', '', PARAM_RAW);
$modid    = optional_param('modid', 0, PARAM_INT);
$formatoptions = $format->get_format_options();
if ($formatoptions['activity_display_mode'] == 1 && $modtype != 'edwiservideoactivity') {
    if($modid > 0){
        redirect(new moodle_url("/mod/{$modtype}/view.php", ['id' => $modid]));
    }
}

// Redirect only once per (course, modtype, cmid) within a short TTL.
if ($modtype === 'edwiservideoactivity' && $modid > 0) {

    // Prepare session store.
    if (!isset($SESSION->format_redirected)) {
        $SESSION->format_redirected = [];
    }

    $key = $courseid . ':' . $modtype . ':' . $modid;
    $now = time();
    $ttl = 15; // seconds; adjust if you like

    // Cleanup expired entries.
    foreach ($SESSION->format_redirected as $k => $t) {
        if ($t + $ttl < $now) {
            unset($SESSION->format_redirected[$k]);
        }
    }

    // Only redirect if we haven't done it recently.
    if (empty($SESSION->format_redirected[$key])) {
        // (Optional but safer) verify CM exists & user can view it to avoid bounce-backs.
        try {
            $cm = get_coursemodule_from_id($modtype, $modid, $courseid, false, MUST_EXIST);
            // Only require login/capability check if you want to be extra-safe:
            // require_course_login($courseid, true, $cm);
        } catch (moodle_exception $e) {
            // If CM not found/visible, skip redirect and let course page render.
            $cm = null;
        }

        if ($cm) {
            $SESSION->format_redirected[$key] = $now;
            redirect(new moodle_url("/mod/{$modtype}/view.php", ['id' => $modid]));
        }
    }
}
