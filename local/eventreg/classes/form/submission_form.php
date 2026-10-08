<?php
namespace local_eventreg\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

use moodleform;
use local_eventreg\form_builder;

/**
 * The learner-facing registration form, built dynamically from a form's fields.
 *
 * The field records are passed in via customdata['fields']; each is rendered by
 * form_builder and re-validated server-side there too.
 *
 * @package   local_eventreg
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission_form extends moodleform {

    protected function definition() {
        $mform = $this->_form;
        $fields = $this->_customdata['fields'] ?? [];

        $mform->addElement('hidden', 'id', $this->_customdata['formid'] ?? 0);
        $mform->setType('id', PARAM_INT);

        foreach ($fields as $field) {
            form_builder::add_field($mform, $field);
        }

        $this->add_action_buttons(false, get_string('submitregistration', 'local_eventreg'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        foreach (($this->_customdata['fields'] ?? []) as $field) {
            $errors += form_builder::validate_field($field, $data);
        }
        return $errors;
    }
}
