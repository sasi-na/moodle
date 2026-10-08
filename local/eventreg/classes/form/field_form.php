<?php
namespace local_eventreg\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

use moodleform;
use local_eventreg\form_builder;

/**
 * Add / edit a single form field (question).
 *
 * The type-dependent extras (choice list for radio/checkbox/select, scale for
 * rating, min/max for number) are all present but shown conditionally via
 * hideIf on the type selector. Once the parent form has responses the type is
 * locked (shown read-only) so recorded answers can never be reinterpreted.
 *
 * @package   local_eventreg
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_form extends moodleform {

    protected function definition() {
        global $CFG;

        $mform = $this->_form;
        $locked = !empty($this->_customdata['locked']);

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'formid', 0);
        $mform->setType('formid', PARAM_INT);

        $types = form_builder::field_types();
        if ($locked) {
            // Type cannot change once responses exist; show it read-only and carry
            // the value in a hidden element so save keeps it.
            $current = $this->_customdata['type'] ?? 'text';
            $mform->addElement('static', 'typeview', get_string('fieldtype', 'local_eventreg'),
                ($types[$current] ?? $current) . ' — ' . get_string('typelocked', 'local_eventreg'));
            $mform->addElement('hidden', 'type', $current);
            $mform->setType('type', PARAM_ALPHA);
        } else {
            $mform->addElement('select', 'type', get_string('fieldtype', 'local_eventreg'), $types);
            $mform->setType('type', PARAM_ALPHA);
        }

        // Editors, matching the form's own description field (form_meta_form): the
        // question text and its help often need line breaks and paragraphs - payment
        // instructions and bank details were being typed into a label as plain text and
        // arriving as one flattened line. No filemanager options, so there is no draft
        // area to post-process on save; the same choice form_meta_form makes for intro.
        //
        // The required rule lives in validation() rather than addRule(): an editor
        // submits an array, and a client-side 'required' rule on it does not fire.
        $mform->addElement('editor', 'label', get_string('fieldlabel', 'local_eventreg'), ['rows' => 4]);
        $mform->setType('label', PARAM_RAW);

        $mform->addElement('editor', 'helptext', get_string('fieldhelp', 'local_eventreg'), ['rows' => 3]);
        $mform->setType('helptext', PARAM_RAW);

        $mform->addElement('advcheckbox', 'required', get_string('fieldrequired', 'local_eventreg'));
        $mform->setType('required', PARAM_INT);
        if (!$locked) {
            // A description asks nothing, so it cannot be required and has no help row
            // of its own - its own body is the content.
            $mform->hideIf('required', 'type', 'eq', 'description');
            $mform->hideIf('helptext', 'type', 'eq', 'description');
        }

        // Choice list — radio / checkbox / select.
        $mform->addElement('textarea', 'options', get_string('fieldoptions', 'local_eventreg'),
            ['rows' => 5, 'style' => 'width:100%;']);
        $mform->setType('options', PARAM_TEXT);
        $mform->addHelpButton('options', 'fieldoptions', 'local_eventreg');
        if (!$locked) {
            $mform->hideIf('options', 'type', 'in', form_builder::non_choice_types());
        }

        // Rating scale.
        $mform->addElement('text', 'ratingmin', get_string('ratingmin', 'local_eventreg'), ['size' => 4]);
        $mform->setType('ratingmin', PARAM_INT);
        $mform->setDefault('ratingmin', 1);
        $mform->addElement('text', 'ratingmax', get_string('ratingmax', 'local_eventreg'), ['size' => 4]);
        $mform->setType('ratingmax', PARAM_INT);
        $mform->setDefault('ratingmax', 5);
        $mform->addElement('text', 'ratinglow', get_string('ratinglow', 'local_eventreg'), ['style' => 'width:100%;max-width:24rem;']);
        $mform->setType('ratinglow', PARAM_TEXT);
        $mform->addElement('text', 'ratinghigh', get_string('ratinghigh', 'local_eventreg'), ['style' => 'width:100%;max-width:24rem;']);
        $mform->setType('ratinghigh', PARAM_TEXT);
        if (!$locked) {
            foreach (['ratingmin', 'ratingmax', 'ratinglow', 'ratinghigh'] as $el) {
                $mform->hideIf($el, 'type', 'neq', 'rating');
            }
        }

        // Number bounds.
        $mform->addElement('text', 'numbermin', get_string('numbermin', 'local_eventreg'), ['size' => 6]);
        $mform->setType('numbermin', PARAM_RAW_TRIMMED);
        $mform->addElement('text', 'numbermax', get_string('numbermax', 'local_eventreg'), ['size' => 6]);
        $mform->setType('numbermax', PARAM_RAW_TRIMMED);
        if (!$locked) {
            $mform->hideIf('numbermin', 'type', 'neq', 'number');
            $mform->hideIf('numbermax', 'type', 'neq', 'number');
        }

        // File upload limits. maxbytes 0 = "site upload limit", which is what
        // get_max_upload_sizes() puts first in the list.
        $mform->addElement('select', 'filemaxfiles', get_string('filemaxfiles', 'local_eventreg'),
            array_combine(range(1, 10), range(1, 10)));
        $mform->setType('filemaxfiles', PARAM_INT);
        $mform->setDefault('filemaxfiles', 1);

        $mform->addElement('select', 'filemaxbytes', get_string('filemaxbytes', 'local_eventreg'),
            get_max_upload_sizes($CFG->maxbytes));
        $mform->setType('filemaxbytes', PARAM_INT);
        $mform->setDefault('filemaxbytes', 0);

        $mform->addElement('filetypes', 'fileaccept', get_string('fileaccept', 'local_eventreg'));
        $mform->setDefault('fileaccept', '*');
        $mform->addHelpButton('fileaccept', 'fileaccept', 'local_eventreg');

        if (!$locked) {
            foreach (['filemaxfiles', 'filemaxbytes', 'fileaccept'] as $el) {
                $mform->hideIf($el, 'type', 'neq', 'file');
            }
        }

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Choice types need at least two options; rating needs a valid range.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $type = $data['type'] ?? '';

        // The label is an editor, so it arrives as ['text' => ..., 'format' => ...] and
        // a client-side required rule on it never fires. Checked on the stripped text so
        // that an empty paragraph the editor leaves behind does not pass as content.
        $labeltext = trim(html_to_text((string) ($data['label']['text'] ?? ''), 0, false));
        if ($labeltext === '') {
            $errors['label'] = get_string('needlabel', 'local_eventreg');
        }

        if (form_builder::is_choice_type($type)) {
            $opts = array_filter(array_map('trim', explode("\n", (string) ($data['options'] ?? ''))),
                fn($v) => $v !== '');
            if (count($opts) < 2) {
                $errors['options'] = get_string('needoptions', 'local_eventreg');
            }
        }

        if ($type === 'rating') {
            if ((int) $data['ratingmin'] >= (int) $data['ratingmax']) {
                $errors['ratingmax'] = get_string('ratingrange', 'local_eventreg');
            }
        }

        return $errors;
    }
}
