<?php
namespace local_dashboardpoll\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

use moodleform;

/**
 * Create / edit form for a dashboard poll.
 *
 * Options are a repeated text element, except when the poll already has
 * responses: then they are shown read-only and left untouched on save, so
 * recorded answers never point at a deleted option.
 *
 * @package   local_dashboardpoll
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class poll_form extends moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $cohorts = $this->_customdata['cohorts'] ?? [];
        $lockedoptions = $this->_customdata['lockedoptions'] ?? null;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('textarea', 'question', get_string('question', 'local_dashboardpoll'),
            ['rows' => 3, 'style' => 'width: 100%;']);
        $mform->setType('question', PARAM_TEXT);
        $mform->addRule('question', get_string('needquestion', 'local_dashboardpoll'), 'required', null, 'client');
        $mform->addHelpButton('question', 'question', 'local_dashboardpoll');

        $cohortoptions = ['' => get_string('allcohort', 'local_dashboardpoll')];
        foreach ($cohorts as $c) {
            $cohortoptions[$c] = $c;
        }
        $mform->addElement('select', 'cohort', get_string('cohort', 'local_dashboardpoll'), $cohortoptions);
        $mform->addHelpButton('cohort', 'cohort', 'local_dashboardpoll');

        // Multiple selections (per poll) and how many picks are required. Once a
        // poll has responses these are locked too (shown read-only), so the
        // required count can never drift from already-recorded answers.
        if (is_array($lockedoptions)) {
            $meta = $this->_customdata['lockedmeta'] ?? ['multiselect' => 0, 'maxchoices' => 1];
            $desc = !empty($meta['multiselect'])
                ? get_string('multiselectlockedmulti', 'local_dashboardpoll', (int) $meta['maxchoices'])
                : get_string('multiselectlockedsingle', 'local_dashboardpoll');
            $mform->addElement('static', 'multiselectview',
                get_string('multiselect', 'local_dashboardpoll'), $desc);
        } else {
            $mform->addElement('advcheckbox', 'multiselect', get_string('multiselect', 'local_dashboardpoll'),
                get_string('multiselect_label', 'local_dashboardpoll'));
            $mform->setType('multiselect', PARAM_INT);
            $mform->addHelpButton('multiselect', 'multiselect', 'local_dashboardpoll');

            // Captured raw (not PARAM_INT) so non-numeric input can be rejected
            // rather than silently coerced to 0.
            $mform->addElement('text', 'maxchoices', get_string('maxchoices', 'local_dashboardpoll'),
                ['size' => 4]);
            $mform->setType('maxchoices', PARAM_RAW_TRIMMED);
            $mform->setDefault('maxchoices', 1);
            $mform->addHelpButton('maxchoices', 'maxchoices', 'local_dashboardpoll');
            $mform->hideIf('maxchoices', 'multiselect', 'notchecked');
        }

        // What students see after voting. Display-only, so it stays editable even
        // once the poll has responses. Bitmask: 0 = thank-you only, 1 = percentages,
        // 2 = counts, 3 = both. New polls default from the global "show vote counts".
        $displayopts = [
            0 => get_string('resultsdisplay_none', 'local_dashboardpoll'),
            1 => get_string('resultsdisplay_percent', 'local_dashboardpoll'),
            2 => get_string('resultsdisplay_count', 'local_dashboardpoll'),
            3 => get_string('resultsdisplay_both', 'local_dashboardpoll'),
        ];
        $mform->addElement('select', 'resultsdisplay',
            get_string('resultsdisplay', 'local_dashboardpoll'), $displayopts);
        $mform->setType('resultsdisplay', PARAM_INT);
        $mform->setDefault('resultsdisplay',
            get_config('local_dashboardpoll', 'showvotecounts') ? 3 : 1);
        $mform->addHelpButton('resultsdisplay', 'resultsdisplay', 'local_dashboardpoll');

        if (is_array($lockedoptions)) {
            // Poll has responses: show options read-only.
            $items = '';
            foreach ($lockedoptions as $text) {
                $items .= \html_writer::tag('li', format_string($text));
            }
            $mform->addElement('static', 'optionsview', get_string('options', 'local_dashboardpoll'),
                \html_writer::tag('ul', $items));
            $mform->addElement('static', 'optionslockednote', '',
                \html_writer::tag('em', get_string('optionslocked', 'local_dashboardpoll')));
        } else {
            $count = $this->_customdata['optioncount'] ?? 4;
            $repeatel = [
                $mform->createElement('text', 'optiontext', get_string('option', 'local_dashboardpoll'),
                    ['style' => 'width: 90%;']),
            ];
            $repeatopts = ['optiontext' => ['type' => PARAM_TEXT]];
            $this->repeat_elements($repeatel, $count, $repeatopts, 'option_repeats', 'option_add',
                2, get_string('addoptions', 'local_dashboardpoll'), true);
        }

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Require a question and at least two non-empty options (unless locked).
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!array_key_exists('optiontext', $data)) {
            // Options are locked (poll has responses) — nothing to validate here.
            return $errors;
        }

        $opts = array_filter(array_map('trim', $data['optiontext']), fn($v) => $v !== '');
        if (count($opts) < 2) {
            $errors['optiontext[1]'] = get_string('needtwooptions', 'local_dashboardpoll');
        }

        // The required number of selections only applies to multi-select polls.
        // It must be a whole number from 1 up to the number of options.
        if (!empty($data['multiselect'])) {
            $raw = trim((string) ($data['maxchoices'] ?? ''));
            if ($raw === '' || !ctype_digit($raw)) {
                $errors['maxchoices'] = get_string('maxchoicesnumeric', 'local_dashboardpoll');
            } else {
                $max = (int) $raw;
                if ($max < 1) {
                    $errors['maxchoices'] = get_string('maxchoicesmin', 'local_dashboardpoll');
                } else if ($max > count($opts)) {
                    $errors['maxchoices'] = get_string('maxchoicestoohigh', 'local_dashboardpoll', count($opts));
                }
            }
        }
        return $errors;
    }
}
