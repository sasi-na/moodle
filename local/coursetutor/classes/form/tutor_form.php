<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_coursetutor\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Create / edit a tutor.
 *
 * @package   local_coursetutor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tutor_form extends \moodleform {

    /**
     * Definition.
     */
    protected function definition() {
        $m = $this->_form;
        $m->addElement('hidden', 'id');
        $m->setType('id', PARAM_INT);
        $m->addElement('hidden', 'courseid');
        $m->setType('courseid', PARAM_INT);
        $m->addElement('text', 'name', get_string('tutorname', 'local_coursetutor'), ['size' => 50]);
        $m->setType('name', PARAM_TEXT);
        $m->addRule('name', null, 'required');
        $m->addElement('textarea', 'instructions', get_string('instructions', 'local_coursetutor'), ['rows' => 5, 'cols' => 70]);
        $m->setType('instructions', PARAM_TEXT);
        $m->addHelpButton('instructions', 'instructions', 'local_coursetutor');
        $m->addElement('textarea', 'knowledge', get_string('knowledge', 'local_coursetutor'), ['rows' => 8, 'cols' => 70]);
        $m->setType('knowledge', PARAM_TEXT);
        $m->addHelpButton('knowledge', 'knowledge', 'local_coursetutor');
        $m->addElement('advcheckbox', 'usecontent', get_string('usecontent', 'local_coursetutor'));
        $m->setDefault('usecontent', 1);
        $m->addElement('advcheckbox', 'enabled', get_string('enabled', 'local_coursetutor'));
        $m->setDefault('enabled', 1);
        $this->add_action_buttons();
    }
}
