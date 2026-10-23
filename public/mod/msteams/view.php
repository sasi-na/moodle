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
 * msteams module main user interface
 *
 * @package    mod_msteams
 * @copyright  2020 Robert Schrenk
 *             based on 2009 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once("$CFG->dirroot/mod/msteams/lib.php");
require_once("$CFG->dirroot/mod/url/locallib.php");
require_once($CFG->libdir . '/completionlib.php');

$id       = optional_param('id', 0, PARAM_INT);        // Course module ID
$u        = optional_param('u', 0, PARAM_INT);         // teams instance id
$redirect = optional_param('redirect', 0, PARAM_BOOL);
$forceview = optional_param('forceview', 0, PARAM_BOOL);

if ($u) {  // Two ways to specify the module
    $msteam = $DB->get_record('msteams', array('id'=>$u), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('msteams', $msteam->id, $msteam->course, false, MUST_EXIST);

} else {
    $cm = get_coursemodule_from_id('msteams', $id, 0, false, MUST_EXIST);
    $msteam = $DB->get_record('msteams', array('id'=>$cm->instance), '*', MUST_EXIST);
}

$course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/msteams:view', $context);

// Completion and trigger events.
msteams_view($msteam, $course, $cm, $context);

$PAGE->set_url('/mod/msteams/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($msteam->name));
$PAGE->set_heading(format_string($course->fullname));

// Make sure URL exists before generating output - some older sites may contain empty urls
// Do not use PARAM_URL here, it is too strict and does not support general URIs!
$exturl = trim($msteam->externalurl);
if (empty($exturl) or $exturl === 'http://') {
    url_print_header($msteam, $cm, $course);
    url_print_heading($msteam, $cm, $course);
    url_print_intro($msteam, $cm, $course);
    notice(get_string('invalidstoredurl', 'msteams'), new moodle_url('/course/view.php', array('id'=>$cm->course)));
    die;
}
unset($exturl);

$fullurl = new moodle_url(url_get_full_url($msteam, $cm, $course));
$status = get_string('meetingstatusscheduled', 'msteams');
$now = time();
if (!empty($msteam->startdatetime) && !empty($msteam->enddatetime)) {
    if ($now >= (int)$msteam->startdatetime && $now <= (int)$msteam->enddatetime) {
        $status = get_string('meetingstatusinprogress', 'msteams');
    } else if ($now > (int)$msteam->enddatetime) {
        $status = get_string('meetingstatusended', 'msteams');
    }
} else if (empty($msteam->startdatetime) && empty($msteam->enddatetime)) {
    $status = get_string('meetingstatusavailable', 'msteams');
}

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_view msteams-meeting-details';
$table->align = ['left', 'left'];
$table->size = ['35%', '65%'];

$statusrow = new html_table_row();
$statusrow->cells = [
    new html_table_cell(get_string('meetingstatus', 'msteams')),
    new html_table_cell($status),
];
$statusrow->cells[0]->header = true;
$table->data[] = $statusrow;

if (!empty($msteam->startdatetime)) {
    $startrow = new html_table_row();
    $startrow->cells = [
        new html_table_cell(get_string('meetingstart', 'msteams')),
        new html_table_cell(userdate((int)$msteam->startdatetime)),
    ];
    $startrow->cells[0]->header = true;
    $table->data[] = $startrow;
}

if (!empty($msteam->enddatetime)) {
    $endrow = new html_table_row();
    $endrow->cells = [
        new html_table_cell(get_string('meetingend', 'msteams')),
        new html_table_cell(userdate((int)$msteam->enddatetime)),
    ];
    $endrow->cells[0]->header = true;
    $table->data[] = $endrow;
}

$lobbyrow = new html_table_row();
$lobbyrow->cells = [
    new html_table_cell(get_string('lobbysetting', 'msteams')),
    new html_table_cell(!empty($msteam->requirelobby)
        ? get_string('lobbyrequired', 'msteams')
        : get_string('lobbyopen', 'msteams')),
];
$lobbyrow->cells[0]->header = true;
$table->data[] = $lobbyrow;

if (!empty($msteam->presenteruserid)) {
    $presenterrow = new html_table_row();
    $presenterrow->cells = [
        new html_table_cell(get_string('presenteruserid', 'msteams')),
        new html_table_cell(s(msteams_format_selected_user((int)$msteam->presenteruserid))),
    ];
    $presenterrow->cells[0]->header = true;
    $table->data[] = $presenterrow;
}

if (!empty($msteam->coorganizeruserid)) {
    $coorganizerrow = new html_table_row();
    $coorganizerrow->cells = [
        new html_table_cell(get_string('coorganizeruserid', 'msteams')),
        new html_table_cell(s(msteams_format_selected_user((int)$msteam->coorganizeruserid))),
    ];
    $coorganizerrow->cells[0]->header = true;
    $table->data[] = $coorganizerrow;
}

if (!empty($msteam->meetingid)) {
    $meetingidrow = new html_table_row();
    $meetingidrow->cells = [
        new html_table_cell(get_string('meetingidlabel', 'msteams')),
        new html_table_cell(s($msteam->meetingid)),
    ];
    $meetingidrow->cells[0]->header = true;
    $table->data[] = $meetingidrow;
}

$updatedrow = new html_table_row();
$updatedrow->cells = [
    new html_table_cell(get_string('lastmodified')),
    new html_table_cell(userdate((int)$msteam->timemodified)),
];
$updatedrow->cells[0]->header = true;
$table->data[] = $updatedrow;

echo $OUTPUT->header();

if ($msteam->intro) {
    echo $OUTPUT->box(format_module_intro('msteams', $msteam, $cm->id), 'generalbox mod_introbox', 'intro');
}

echo html_writer::start_div('card msteams-meeting-card');
echo html_writer::div(get_string('meetingdetailstitle', 'msteams'), 'card-header');
echo html_writer::start_div('card-body');
echo html_writer::div(
    html_writer::link($fullurl, get_string('joinmeeting', 'msteams'), [
        'class' => 'btn btn-primary',
        'target' => '_blank',
        'rel' => 'noopener',
    ]),
    'msteams-meeting-actions mb-4 text-center'
);
echo html_writer::table($table);
echo html_writer::div(get_string('meetingidentitynotice', 'msteams'), 'alert alert-info mb-0');
echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();
