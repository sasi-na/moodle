<?php
/**
 * Create / edit a registration form's metadata (title, description, audience).
 *
 * @package   local_eventreg
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_eventreg\manager;
use local_eventreg\form\form_meta_form;

admin_externalpage_setup('local_eventreg_manage');
$context = context_system::instance();
require_capability('local/eventreg:manage', $context);

$id      = optional_param('id', 0, PARAM_INT);
$baseurl = new moodle_url('/local/eventreg/manage.php');
$editurl = new moodle_url('/local/eventreg/edit.php', $id ? ['id' => $id] : []);
$PAGE->set_url($editurl);

$form = null;
if ($id) {
    $form = $DB->get_record('local_eventreg_forms', ['id' => $id], '*', MUST_EXIST);
}

$mform = new form_meta_form($editurl->out(false), ['batches' => manager::batch_choices()]);

if ($mform->is_cancelled()) {
    redirect($baseurl);
} else if ($data = $mform->get_data()) {
    $now = time();
    $mode = (int) $data->audiencemode;
    $tokens = ($mode === manager::AUDIENCE_ALL) ? '' :
        manager::token_encode(isset($data->audiencebatches) ? (array) $data->audiencebatches : []);

    $record = (object) [
        'title'          => $data->title,
        'intro'          => $data->intro['text'] ?? '',
        'introformat'    => $data->intro['format'] ?? FORMAT_HTML,
        'audiencemode'   => $mode,
        'audiencetokens' => $tokens,
        'timemodified'   => $now,
        'usermodified'   => $USER->id,
    ];

    if (!empty($data->id)) {
        $record->id = $data->id;
        $DB->update_record('local_eventreg_forms', $record);
        $formid = (int) $data->id;
    } else {
        $record->status = 'draft';
        $record->timecreated = $now;
        $formid = $DB->insert_record('local_eventreg_forms', $record);
    }

    manager::purge_all();
    // Straight into the question builder for a brand-new form.
    if (empty($data->id)) {
        redirect(new moodle_url('/local/eventreg/fields.php', ['formid' => $formid]),
            get_string('formsaved', 'local_eventreg'));
    }
    redirect($baseurl, get_string('formsaved', 'local_eventreg'));
}

if ($form) {
    $mform->set_data([
        'id'           => $form->id,
        'title'        => $form->title,
        'intro'        => ['text' => $form->intro, 'format' => $form->introformat],
        'audiencemode' => (int) $form->audiencemode,
        'audiencebatches' => manager::token_list($form->audiencetokens),
    ]);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($id
    ? get_string('editform', 'local_eventreg')
    : get_string('createform', 'local_eventreg'));
$mform->display();
echo $OUTPUT->footer();
