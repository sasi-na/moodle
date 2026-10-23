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
 * Edwiser Video Activity module form
 *
 * @package   mod_edwiservideoactivity
 * @copyright 2024 Edwiser
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

/**
 * Module instance settings form
 *
 * @package   mod_edwiservideoactivity
 * @copyright 2024 Edwiser
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_edwiservideoactivity_mod_form extends moodleform_mod {

    /**
     * Defines forms elements
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;

        // Header with the activity title (set dynamically later).
        $mform->addElement('header', 'mediaheader', get_string('insertmedia', 'edwiservideoactivity'));

        // Activity title.
        $mform->addElement('text', 'name', get_string('activitytitle', 'edwiservideoactivity'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        // Video source dropdown.
        $mform->addElement('select', 'mediasource', get_string('mediasource', 'edwiservideoactivity'), [
            'url'    => get_string('addurl', 'edwiservideoactivity'),
            'embed'  => get_string('embedvideo', 'edwiservideoactivity'),
            'upload' => get_string('uploadfile', 'edwiservideoactivity'),
        ]);
        $mform->setDefault('mediasource', 'url');

        // Text input (for URL).
        $mform->addElement('static', 'mediaurlmessage', '', html_writer::div(
            get_string('mediaurlnotice', 'edwiservideoactivity')));

        $mform->hideIf('mediaurlmessage', 'mediasource', 'neq', 'url');
        $mform->addElement('text', 'mediaurl', get_string('mediaurl', 'edwiservideoactivity'));
        $mform->setType('mediaurl', PARAM_URL);
        $mform->hideIf('mediaurl', 'mediasource', 'neq', 'url');

        // Text input (for embed video).
        $mform->addElement('static', 'embeddmessage', '', html_writer::div(
            get_string('embedurlnotice', 'edwiservideoactivity')));

        $mform->hideIf('embeddmessage', 'mediasource', 'neq', 'embed');
        $mform->addElement('textarea', 'embedcode', get_string('embedvideo', 'edwiservideoactivity'), ['rows' => 5, 'cols' => 80, 'placeholder' => get_string('embedcode', 'edwiservideoactivity'),]);
        $mform->setType('embedcode', PARAM_RAW);
        $mform->hideIf('embedcode', 'mediasource', 'neq', 'embed');

        // Upload video
        $mform->addElement('static', 'uploadbandwidthnotice', '', html_writer::div(
            get_string('uploadbandwidthwarning', 'edwiservideoactivity')));

        $mform->hideIf('uploadbandwidthnotice', 'mediasource', 'neq', 'upload');

        $mform->addElement('filemanager', 'mediafile', get_string('mediafile', 'edwiservideoactivity'), null, [
            'subdirs'        => 0,
            'maxfiles'       => 1,
            'accepted_types' => ['mp4', 'webm', 'm4v', 'mov', '3gp'],
        ]);

        $mform->hideIf('mediafile', 'mediasource', 'neq', 'upload');

        // Overview editor.
        $mform->addElement('header', 'overviewsection', get_string('overview', 'edwiservideoactivity'));
        $mform->addElement('editor', 'introeditor', get_string('overviewcontent', 'edwiservideoactivity'), null, $this->get_editor_options());
        $mform->setType('introeditor', PARAM_RAW);
        $mform->addElement('static', 'overviewsectiondescription', '', html_writer::div(
            get_string('overviewsectiondescription', 'edwiservideoactivity')));

        // Resources file manager.
        $mform->addElement('header', 'resourcessection', get_string('resources', 'edwiservideoactivity'));
        $mform->addElement('filemanager', 'resources', get_string('resourcesfiles', 'edwiservideoactivity'), null, [
            'subdirs'        => 0,
            'maxfiles'       => -1,
            'accepted_types' => '*',
        ]);
        $mform->addElement('static', 'resourcessectiondescription', '', html_writer::div(
            get_string('resourcessectiondescription', 'edwiservideoactivity')));

        // Transcript file manager.
        $mform->addElement('header', 'transcriptsection', get_string('transcript', 'edwiservideoactivity'));
        $mform->addElement('filemanager', 'transcript', get_string('transcriptfiles', 'edwiservideoactivity'), null, [
            'subdirs'        => 0,
            'maxfiles'       => 1,
            'accepted_types' => ['txt'],
        ]);
        $mform->addElement('static', 'transcriptsectiondescription', '', html_writer::div(
            get_string('transcriptsectiondescription', 'edwiservideoactivity')));

        // Standard course module elements.
        $this->standard_coursemodule_elements();

        // Action buttons.
        $this->add_action_buttons();
    }

    private function get_editor_options()
    {
        global $COURSE, $PAGE;
        return [
            'maxfiles' => 99,
            'context'  => $PAGE->context,
        ];
    }

    public function data_preprocessing(&$defaultvalues)
    {
        parent::data_preprocessing($defaultvalues);
        if (empty($this->current) || empty($this->current->coursemodule)) {
            return;
        }

        // Get context_module.
        $cmid          = $this->current->coursemodule;
        $modulecontext = context_module::instance($cmid);

        // Media file (draft area).
        $draftitemid = file_get_submitted_draft_itemid('mediafile');
        file_prepare_draft_area(
            $draftitemid,
            $modulecontext->id,
            'mod_edwiservideoactivity',
            'mediafile',
            0,
            ['subdirs' => 0]
        );
        $defaultvalues['mediafile'] = $draftitemid;

        // Resources file manager (draft area).
        $draftitemid = file_get_submitted_draft_itemid('resources');
        file_prepare_draft_area(
            $draftitemid,
            $modulecontext->id,
            'mod_edwiservideoactivity',
            'resources',
            0,
            ['subdirs' => 0]
        );
        $defaultvalues['resources'] = $draftitemid;

        // Transcript file manager (draft area).
        $draftitemid = file_get_submitted_draft_itemid('transcript');
        file_prepare_draft_area(
            $draftitemid,
            $modulecontext->id,
            'mod_edwiservideoactivity',
            'transcript',
            0,
            ['subdirs' => 0]
        );
        $defaultvalues['transcript'] = $draftitemid;

        // Restore previously saved media source (upload/url).
        if (! empty($this->current->sourcetype) && $this->current->sourcetype == 2) {
            $defaultvalues['mediaurl']    = $this->current->sourcepath;
            $defaultvalues['mediasource'] = 'url';
        } else if(! empty($this->current->sourcetype) && $this->current->sourcetype == 3){
            $defaultvalues['embedcode']    = $this->current->sourcepath;
            $defaultvalues['mediasource'] = 'embed';
        } else {
            $defaultvalues['mediasource'] = 'upload';
        }

        // Restore completion percentage settings
        $defaultvalues["completionpercentenabled"] = !empty($defaultvalues["completionpercent"]) ? 1 : 0;
        if (empty($defaultvalues["completionpercent"])) {
            $defaultvalues["completionpercent"] = 1;
        }
    }

    /**
     * Allows modules to modify the data returned by form get_data().
     * This method is also called in the bulk activity completion form.
     *
     * Only available on moodleform_mod.
     *
     * @param stdClass $data the form data to be modified.
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        if (!empty($data->completionunlocked)) {
            $suffix = $this->get_suffix();
            // Check completion field with suffix (for default completion form) or without (for module settings)
            $completionfield = 'completion' . $suffix;
            $completion = isset($data->$completionfield) ? $data->$completionfield : $data->completion;
            $autocompletion = !empty($completion) && $completion == COMPLETION_TRACKING_AUTOMATIC;

            // Check completionpercentenabled with suffix (for default completion form) or without (for module settings)
            $completionpercentenabledfield = 'completionpercentenabled' . $suffix;
            $completionpercentenabled = isset($data->$completionpercentenabledfield) ?
                $data->$completionpercentenabledfield : ($data->completionpercentenabled ?? 0);

            // Set completionpercent with suffix (for default completion form) or without (for module settings)
            $completionpercentfield = 'completionpercent' . $suffix;
            if (empty($completionpercentenabled) || !$autocompletion) {
                if (isset($data->$completionpercentfield)) {
                    $data->$completionpercentfield = 0;
                } else {
                    $data->completionpercent = 0;
                }
            }
        }
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ($data['mediasource'] === 'upload') {
            $draftitemid = $data['mediafile'];
            $draftinfo = file_get_draft_area_info($draftitemid);
            if (empty($draftinfo['filecount'])) {
                $errors['mediafile'] = get_string('mediafilevalidation','edwiservideoactivity');
            }
        } else if ($data['mediasource'] === 'url') {
            if (empty($data['mediaurl']) || !filter_var($data['mediaurl'], FILTER_VALIDATE_URL)) {
                $errors['mediaurl'] = get_string('mediaurlvalidation','edwiservideoactivity');
            }
        } else if ($data['mediasource'] === 'embed') {
            if (empty($data['embedcode'])) {
                $errors['embedcode'] = get_string('embedcodevalidation','edwiservideoactivity');
            }
        }

        // Validate completion percentage
        $suffix = $this->get_suffix();
        $completionpercentel = 'completionpercent' . $suffix;
        // Check both with and without suffix to handle both module settings and default completion forms
        $completionpercentfield = isset($data[$completionpercentel]) ? $completionpercentel :
                                  (isset($data['completionpercent']) ? 'completionpercent' : null);

        if ($completionpercentfield && isset($data[$completionpercentfield]) && $data[$completionpercentfield] != "") {
            $completionpercentvalue = intval($data[$completionpercentfield]);
            if ($completionpercentvalue < 1) {
                $data[$completionpercentfield] = "";
            }
            if ($completionpercentvalue > 100) {
                $errors[$completionpercentfield] = get_string("completionpercent_error", "edwiservideoactivity");
            }
        }

        return $errors;
    }

    /**
     * Add completion rules to the form
     *
     * @return array Array of form element names that are used for completion
     */
    public function add_completion_rules() {
        $mform = &$this->_form;
        $suffix = $this->get_suffix();

        $completionpercentenabledel = 'completionpercentenabled' . $suffix;
        $completionpercentel = 'completionpercent' . $suffix;
        $completionpercentgroupel = 'completionpercentgroup' . $suffix;

        $group = [
            $mform->createElement(
                'checkbox',
                $completionpercentenabledel,
                '',
                get_string('completionpercent_label', 'edwiservideoactivity')
            ),
            $mform->createElement(
                'text',
                $completionpercentel,
                get_string('completionpercent_label', 'edwiservideoactivity'),
                ['size' => '2']
            ),
            $mform->createElement('html', '%'),
        ];

        $mform->addGroup(
            $group,
            $completionpercentgroupel,
            get_string('completionpercent', 'edwiservideoactivity'),
            [' '],
            false
        );
        $mform->disabledIf($completionpercentel, $completionpercentenabledel, 'notchecked');
        $mform->setDefault($completionpercentel, 0);
        $mform->setType($completionpercentel, PARAM_INT);
        $mform->addHelpButton($completionpercentgroupel, 'completionpercent', 'edwiservideoactivity');

        return [$completionpercentgroupel];
    }

    /**
     * Check if completion rule is enabled
     *
     * @param array $data Form data
     * @return bool True if completion rule is enabled
     */
    public function completion_rule_enabled($data) {
        $suffix = $this->get_suffix();
        $completionpercentel = 'completionpercent' . $suffix;
        return (!empty($data[$completionpercentel]) && $data[$completionpercentel] > 0);
    }

}
global $PAGE;
$PAGE->requires->strings_for_js(array(
    'onlyintegervalueallowed'
), 'mod_edwiservideoactivity');
$PAGE->requires->js_call_amd('mod_edwiservideoactivity/settings', 'init');
