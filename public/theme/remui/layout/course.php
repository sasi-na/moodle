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
 * A drawer based layout for the remui theme.
 *
 * @package   theme_remui
 * @copyright (c) 2023 WisdmLabs (https://wisdmlabs.com/) <support@wisdmlabs.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG, $COURSE, $USER;


require_once($CFG->dirroot . '/theme/remui/layout/common.php');

if (isset($templatecontext['focusdata']['enabled']) && $templatecontext['focusdata']['enabled']) {
    list(
        $templatecontext['focusdata']['sections'],
        $templatecontext['focusdata']['active']
    ) = \theme_remui\utility::get_focus_mode_sections($COURSE);
}
$coursecontext = context_course::instance($COURSE->id);
if (!is_guest($coursecontext, $USER) && \theme_remui\toolbox::get_setting('enablecoursestats')) {
    $templatecontext['iscoursestatsshow'] = true;
}

$completion = new \completion_info($COURSE);
$templatecontext['completion'] = $completion->is_enabled();

$templatecontext['courseid'] = $COURSE->id;
$templatecontext['iscourseviewpagecontainer'] = true;

if (!empty($templatecontext['iscoursestatsshow'])) {
    $activitycounts = [];
    $activitytypes = [];
    $totalvisibleactivities = 0;
    $completedactivities = 0;
    $inprogressactivities = 0;
    $notstartedactivities = 0;
    $resumeactivityurl = '';
    $showcourseprogress = false;
    $progresspercentage = 0;
    $formattedtimespent = theme_remui_coursehandler::get_formatted_course_time_spent($COURSE->id, $USER->id);
    $stringmanager = get_string_manager();
    $modinfo = get_fast_modinfo($COURSE);
    $completionenabled = $completion->is_enabled();

    if ($completionenabled) {
        $showcourseprogress = true;
        $courseprogress = \core_completion\progress::get_course_progress_percentage($COURSE);
        if (!is_null($courseprogress)) {
            $progresspercentage = floor($courseprogress);
        }
    }

    if (class_exists('\\format_remuiformat\\course_format_data_common_trait')) {
        $courseformathelper = \format_remuiformat\course_format_data_common_trait::getinstance();
        $resumeactivityurl = $courseformathelper->get_activity_to_resume($COURSE);
    }

    foreach ($modinfo->get_cms() as $cm) {
        if (!$cm->uservisible || !$cm->is_visible_on_course_page() || in_array($cm->modname, ['label', 'subsection'], true)) {
            continue;
        }

        $totalvisibleactivities++;

        if (!isset($activitycounts[$cm->modname])) {
            $activitycounts[$cm->modname] = 0;
        }
        $activitycounts[$cm->modname]++;

        if ($completionenabled) {
            $completiondata = $completion->get_data($cm, true);

            if ((int)$completiondata->completionstate !== COMPLETION_INCOMPLETE) {
                $completedactivities++;
            } else if ((int)$completiondata->viewed === COMPLETION_VIEWED) {
                $inprogressactivities++;
            } else {
                $notstartedactivities++;
            }
        }
    }

    arsort($activitycounts);

    foreach ($activitycounts as $modname => $count) {
        $component = 'mod_' . $modname;
        if ($stringmanager->string_exists('modulenameplural', $component)) {
            $label = get_string('modulenameplural', $component);
        } else if ($stringmanager->string_exists('modulename', $component)) {
            $label = get_string('modulename', $component);
        } else {
            $label = ucfirst($modname);
        }

        $activitytypes[] = [
            'label' => $label,
            'count' => $count,
        ];
    }

    $templatecontext['coursesidebar'] = [
        'image' => theme_remui_coursehandler::get_course_image($COURSE),
        'imagetext' => format_string($COURSE->fullname, true, ['context' => $coursecontext]),
        'activitystatstitle' => get_string('activitystatistics', 'theme_remui'),
        'totalactivitylabel' => get_string('totalactivities', 'theme_remui'),
        'totalactivities' => $totalvisibleactivities,
        'hascompletionstats' => $completionenabled && $totalvisibleactivities > 0,
        'hasstatusstats' => ($completionenabled && $totalvisibleactivities > 0) || $formattedtimespent !== '',
        'completedactivitieslabel' => get_string('completed', 'theme_remui'),
        'completedactivities' => $completedactivities,
        'inprogressactivitieslabel' => get_string('inprogress', 'theme_remui'),
        'inprogressactivities' => $inprogressactivities,
        'notstartedactivitieslabel' => get_string('yettostart', 'theme_remui'),
        'notstartedactivities' => $notstartedactivities,
        'timespentlabel' => get_string('timespent', 'theme_remui'),
        'timespent' => $formattedtimespent,
        'hasprogresspanel' => $showcourseprogress || !empty($resumeactivityurl),
        'showcourseprogress' => $showcourseprogress,
        'progresspercentage' => $progresspercentage,
        'resumeactivityurl' => $resumeactivityurl,
        'hasactivitytypes' => !empty($activitytypes),
        'activitytypes' => $activitytypes,
    ];
}

// Must be called before rendering the template.
// This will ease us to add body classes directly to the array.
require_once($CFG->dirroot . '/theme/remui/layout/common_end.php');

echo $OUTPUT->render_from_template('theme_remui/course', $templatecontext);
