<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

use mod_customteams\account_map;

class mod_customteams_mod_form extends moodleform_mod {

    function definition() {
        global $COURSE;

        $mform = $this->_form;

        $courseid = !empty($this->current->course) ? $this->current->course : $COURSE->id;

        // Meeting name.
        $mform->addElement('text', 'name', get_string('meetingname', 'customteams'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addHelpButton('name', 'meetingname', 'customteams');

        // Start time.
        $mform->addElement('date_time_selector', 'timestart', get_string('starttime', 'customteams'));
        $mform->addRule('timestart', null, 'required', null, 'client');

        // End time.
        $mform->addElement('date_time_selector', 'timeend', get_string('endtime', 'customteams'));
        $mform->addRule('timeend', null, 'required', null, 'client');

        // Organizer account.
        //
        // The list is scoped to the course's own batch and programme, derived
        // from the category tree, so a lecturer sees only the handful of shared
        // accounts that belong to their cohort rather than all 99.
        list($batch, $program) = account_map::resolve_course($courseid);
        $options = account_map::candidates($courseid);

        if (empty($options)) {
            // No mapping and no fallback pool: say which key failed to resolve,
            // because "batch not matched" and "programme not matched" need
            // different people to fix them.
            $mform->addElement('static', 'organizerwarning',
                get_string('organizer', 'customteams'),
                $this->no_accounts_message($batch, $program));

            $mform->addElement('hidden', 'organizer', '');
            $mform->setType('organizer', PARAM_TEXT);

        } else {
            $mform->addElement('select', 'organizer',
                get_string('organizer', 'customteams'),
                ['' => get_string('choosedots')] + $options);

            $mform->setType('organizer', PARAM_TEXT);
            $mform->addRule('organizer', get_string('errornoorganizer', 'customteams'),
                'required', null, 'client');
            $mform->addHelpButton('organizer', 'organizer', 'customteams');

            // One candidate is the common case for the smaller programmes --
            // preselect so the lecturer just saves.
            if (count($options) === 1) {
                $mform->setDefault('organizer', key($options));
            }

            // Show what the course resolved to, so a wrong category is visible
            // here rather than after a meeting lands on the wrong cohort.
            if ($batch !== null || $program !== null) {
                $mform->addElement('static', 'resolvedcohort',
                    get_string('resolvedcohort', 'customteams'),
                    get_string('resolvedcohortvalue', 'customteams', (object) [
                        'batch'   => $batch !== null ? s($batch) : get_string('unknown', 'customteams'),
                        'program' => $program !== null ? s($program) : get_string('unknown', 'customteams'),
                    ]));
            }
        }

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Message for a course that resolved to nothing usable.
     */
    private function no_accounts_message($batch, $program) {
        if ($batch === null) {
            return get_string('errornobatch', 'customteams');
        }

        if ($program === null) {
            return get_string('errornoprogram', 'customteams', s($batch));
        }

        return get_string('errornoaccounts', 'customteams', (object) [
            'batch'   => s($batch),
            'program' => s($program),
        ]);
    }

    function validation($data, $files) {
        global $COURSE;

        $errors = parent::validation($data, $files);

        if ($data['timeend'] <= $data['timestart']) {
            $errors['timeend'] = get_string('endtimebeforestart', 'customteams');
        }

        if (empty($data['organizer'])) {
            $errors['organizer'] = get_string('errornoorganizer', 'customteams');
            return $errors;
        }

        // Check the submitted account against the list this course is actually
        // allowed to use. The select is the only UI that sets this, but the
        // value arrives by POST, and the Graph app holds Calendars.ReadWrite
        // over every mailbox in the tenant -- so without this check any user
        // who can add an activity could create and delete calendar events on
        // an arbitrary staff mailbox by editing the form value.
        $courseid = !empty($this->current->course) ? $this->current->course : $COURSE->id;

        if (!array_key_exists($data['organizer'], account_map::candidates($courseid))) {
            $errors['organizer'] = get_string('errorbadorganizer', 'customteams');
            return $errors;
        }

        // Double-booking check.
        //
        // These accounts are shared -- one mailbox serves several batch and
        // programme pairs -- so the realistic failure is two lecturers booking
        // the same account for the same slot. Graph would accept both and the
        // clash would only surface in the calendar, so it is caught here.
        // Reads Moodle's own table: every meeting is created through this
        // module, so the local record is authoritative and costs one indexed
        // query rather than a Graph round trip.
        $instanceid = !empty($data['instance']) ? (int) $data['instance'] : 0;

        $clashes = account_map::clashes(
            $data['organizer'],
            $data['timestart'],
            $data['timeend'],
            $instanceid
        );

        if (!empty($clashes)) {
            $clash = reset($clashes);
            $errors['organizer'] = get_string('errorclash', 'customteams', (object) [
                'name'  => format_string($clash->name),
                'start' => userdate($clash->timestart),
                'end'   => userdate($clash->timeend),
            ]);
        }

        return $errors;
    }
}
