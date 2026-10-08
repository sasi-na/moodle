<?php
namespace local_zoomattendance\form;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * Schedule / edit a Zoom class meeting. In 'new' mode it mirrors the core
 * calendar event fields (title, date, duration, description) plus a Zoom host
 * selector; in 'edit' mode it offers only title / date / duration (the host
 * can't move and the description carries the generated join link).
 */
class schedule_form extends \moodleform {

    protected function definition() {
        $mform = $this->_form;
        $course = $this->_customdata['course'];
        $mode = $this->_customdata['mode'] ?? 'new';

        $mform->addElement('hidden', 'courseid', $course->id);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'action', $mode);
        $mform->setType('action', PARAM_ALPHA);

        if ($mode === 'edit') {
            $mform->addElement('hidden', 'eventid', $this->_customdata['eventid']);
            $mform->setType('eventid', PARAM_INT);
        }

        $mform->addElement('static', 'coursename', get_string('course'),
            format_string($course->fullname));

        $mform->addElement('text', 'name', get_string('eventtitle', 'local_zoomattendance'),
            ['size' => 50]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 250), 'maxlength', 250, 'client');

        $mform->addElement('date_time_selector', 'timestart', get_string('date'));

        $mform->addElement('duration', 'duration',
            get_string('duration', 'local_zoomattendance'),
            ['defaultunit' => MINSECS, 'optional' => false]);
        $mform->setDefault('duration', HOURSECS);

        if ($mode === 'new') {
            $mform->addElement('editor', 'description',
                get_string('eventdescription', 'local_zoomattendance'));
            $mform->setType('description', PARAM_RAW);

            $mform->addElement('select', 'host',
                get_string('zoomhost', 'local_zoomattendance'), $this->_customdata['hosts']);
        }

        $label = $mode === 'edit'
            ? get_string('savechanges')
            : get_string('schedulemeeting', 'local_zoomattendance');
        $this->add_action_buttons(true, $label);
    }
}
