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
 * MS Teams configuration form
 *
 * @package    mod_msteams
 * @copyright  2020 Center for Learning Management (www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once ($CFG->dirroot.'/course/moodleform_mod.php');

class mod_msteams_mod_form extends moodleform_mod {
    /**
     * Build user selector options for a single searchable user picker.
     *
     * @return array
     */
    private function get_user_selector_options(): array {
        return [
            'ajax' => 'core_user/form_user_selector',
            'multiple' => false,
            'valuehtmlcallback' => function($userid): string {
                global $OUTPUT;

                $context = \context_system::instance();
                $record = \core_user::get_user((int)$userid, 'id,firstname,lastname,email', MUST_EXIST);

                $user = (object)[
                    'id' => $record->id,
                    'fullname' => fullname($record, has_capability('moodle/site:viewfullnames', $context)),
                    'extrafields' => [],
                ];

                if (!empty($record->email)) {
                    $user->extrafields[] = (object)[
                        'name' => 'email',
                        'value' => s($record->email),
                    ];
                }

                return $OUTPUT->render_from_template('core_user/form_user_selector_suggestion', $user);
            },
        ];
    }

    public function definition() {
        global $CFG;

        $mform = $this->_form;

        //-------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), array('size'=>'48'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }

        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('date_time_selector', 'startdatetime', get_string('meetingstart', 'msteams'));
        $mform->addHelpButton('startdatetime', 'meetingstart', 'msteams');
        $mform->setDefault('startdatetime', time());
        $mform->addRule('startdatetime', null, 'required', null, 'client');

        $mform->addElement('date_time_selector', 'enddatetime', get_string('meetingend', 'msteams'));
        $mform->addHelpButton('enddatetime', 'meetingend', 'msteams');
        $mform->setDefault('enddatetime', time() + HOURSECS);
        $mform->addRule('enddatetime', null, 'required', null, 'client');

        $mform->addElement('advcheckbox', 'requirelobby', get_string('requirelobby', 'msteams'));
        $mform->addHelpButton('requirelobby', 'requirelobby', 'msteams');
        $mform->setDefault('requirelobby', 0);
        $mform->setType('requirelobby', PARAM_INT);

        $userselectoroptions = $this->get_user_selector_options();
        $mform->addElement('autocomplete', 'presenteruserid', get_string('presenteruserid', 'msteams'), [], $userselectoroptions);
        $mform->addHelpButton('presenteruserid', 'presenteruserid', 'msteams');
        $mform->setType('presenteruserid', PARAM_INT);

        $mform->addElement('autocomplete', 'coorganizeruserid', get_string('coorganizeruserid', 'msteams'), [], $userselectoroptions);
        $mform->addHelpButton('coorganizeruserid', 'coorganizeruserid', 'msteams');
        $mform->setType('coorganizeruserid', PARAM_INT);

        $mform->addElement('text', 'externalurl', get_string('url', 'msteams'), array('readonly' => 'readonly', 'size'=>'48'));
        $mform->addHelpButton('externalurl', 'url', 'msteams');
        $mform->setType('externalurl', PARAM_RAW_TRIMMED);
        $mform->addElement('static', 'externalurlnotice', '', get_string('meetingurlwillbecreated', 'msteams'));

        $this->standard_intro_elements();
        $element = $mform->getElement('introeditor');
        $attributes = $element->getAttributes();
        $attributes['rows'] = 5;
        $element->setAttributes($attributes);

        //-------------------------------------------------------
        $this->standard_coursemodule_elements();

        //-------------------------------------------------------
        $this->add_action_buttons();
    }


    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['startdatetime']) && !empty($data['enddatetime'])
                && (int)$data['enddatetime'] <= (int)$data['startdatetime']) {
            $errors['enddatetime'] = get_string('meetingcreationinvalidtimes', 'msteams');
        }

        foreach (['presenteruserid', 'coorganizeruserid'] as $fieldname) {
            if (!empty($data[$fieldname])) {
                $user = \core_user::get_user((int)$data[$fieldname], 'id,firstname,lastname,email,deleted,suspended', IGNORE_MISSING);
                if (!$user || !empty($user->deleted) || !empty($user->suspended)) {
                    $errors[$fieldname] = get_string('meetingparticipantinvaliduser', 'msteams');
                } else if (trim((string)$user->email) === '') {
                    $errors[$fieldname] = get_string('meetingparticipantmissingemail', 'msteams', fullname($user));
                }
            }
        }

        if (!empty($data['presenteruserid']) && !empty($data['coorganizeruserid'])
                && (int)$data['presenteruserid'] === (int)$data['coorganizeruserid']) {
            $errors['coorganizeruserid'] = get_string('meetingparticipantduplicate', 'msteams');
        }

        return $errors;
    }

}
