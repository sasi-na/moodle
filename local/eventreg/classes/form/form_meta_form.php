<?php
namespace local_eventreg\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

use moodleform;
use local_eventreg\manager;

/**
 * Create / edit form for a registration form's metadata (title, description and
 * batch audience). Questions are managed separately on fields.php.
 *
 * @package   local_eventreg
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class form_meta_form extends moodleform {

    protected function definition() {
        $mform = $this->_form;
        $choices = $this->_customdata['batches'] ?? [];

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'title', get_string('title', 'local_eventreg'),
            ['style' => 'width:100%;max-width:40rem;']);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', get_string('needtitle', 'local_eventreg'), 'required', null, 'client');

        $mform->addElement('editor', 'intro', get_string('intro', 'local_eventreg'), ['rows' => 5]);
        $mform->setType('intro', PARAM_RAW);

        // Audience: mode + batch multiselect (only used when mode != 0).
        $modes = [
            manager::AUDIENCE_ALL    => get_string('audiencemode_all', 'local_eventreg'),
            manager::AUDIENCE_ONLY   => get_string('audiencemode_only', 'local_eventreg'),
            manager::AUDIENCE_EXCEPT => get_string('audiencemode_except', 'local_eventreg'),
        ];
        $mform->addElement('select', 'audiencemode', get_string('audiencemode', 'local_eventreg'), $modes);
        $mform->setType('audiencemode', PARAM_INT);
        $mform->addHelpButton('audiencemode', 'audiencemode', 'local_eventreg');

        if (empty($choices)) {
            $mform->addElement('static', 'nobatches', '',
                get_string('nobatches', 'local_eventreg'));
        } else {
            $select = $mform->addElement('select', 'audiencebatches',
                get_string('audiencebatches', 'local_eventreg'), $choices);
            $select->setMultiple(true);
            $mform->setType('audiencebatches', PARAM_RAW); // Values are base64 tokens.
            $mform->addHelpButton('audiencebatches', 'audiencebatches', 'local_eventreg');
            $mform->hideIf('audiencebatches', 'audiencemode', 'eq', manager::AUDIENCE_ALL);
        }

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
