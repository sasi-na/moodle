<?php
/**
 * Learner view: fill in and submit a registration form (one submission per user).
 * Typically reached from a dashboard banner link.
 *
 * @package   local_eventreg
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require(__DIR__ . '/../../config.php');

use local_eventreg\manager;
use local_eventreg\form_builder;
use local_eventreg\form\submission_form;

$id = required_param('id', PARAM_INT);

require_login();
$context = context_system::instance();
$userid = (int) $USER->id;

$form = $DB->get_record('local_eventreg_forms', ['id' => $id], '*', MUST_EXIST);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/eventreg/view.php', ['id' => $id]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(format_string($form->title));
$PAGE->set_heading(format_string($form->title));

// Gate: form must be open and the user must fall in its audience.
if ($form->status !== 'open') {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($form->title));
    echo $OUTPUT->notification(get_string('formnotopen', 'local_eventreg'), 'info');
    echo $OUTPUT->footer();
    exit;
}
if (!manager::user_in_audience($form, $userid)) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($form->title));
    echo $OUTPUT->notification(get_string('notinaudience', 'local_eventreg'), 'warning');
    echo $OUTPUT->footer();
    exit;
}

$fields = array_values($DB->get_records('local_eventreg_fields', ['formid' => $id], 'sortorder ASC, id ASC'));
$submission = $DB->get_record('local_eventreg_submissions', ['formid' => $id, 'userid' => $userid]);

// ---- already registered: read-only confirmation ---------------------------
if ($submission) {
    // Keyed by fieldid (get_records indexes on the first selected column); the id
    // is carried too because file answers need it to build download links.
    $answers = $DB->get_records('local_eventreg_answers', ['submissionid' => $submission->id], '', 'fieldid, id, value');

    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($form->title));
    echo $OUTPUT->notification(get_string('alreadyregistered', 'local_eventreg'), 'success');
    if (!empty($form->intro)) {
        echo html_writer::div(format_text($form->intro, $form->introformat), 'mb-3');
    }

    echo html_writer::tag('h4', get_string('yourresponse', 'local_eventreg'), ['class' => 'mt-3']);
    $dl = html_writer::start_tag('dl', ['class' => 'row']);
    // "Your response" lists what the learner answered, so the description blocks
    // between the questions have no place in it.
    foreach (form_builder::answerable($fields) as $field) {
        $answer = $answers[$field->id] ?? null;
        $raw = $answer ? $answer->value : '';
        if ($answer && form_builder::is_file_type($field->type)) {
            $display = form_builder::file_links((int) $answer->id, $raw);
        } else {
            $display = s(form_builder::format_value($field, $raw));
        }
        // plain_label(): a definition term wants one line of text, not the label's markup.
        $dl .= html_writer::tag('dt', form_builder::plain_label($field), ['class' => 'col-sm-4']);
        $dl .= html_writer::tag('dd', $display !== '' ? $display : '—', ['class' => 'col-sm-8']);
    }
    $dl .= html_writer::end_tag('dl');
    echo $dl;

    echo $OUTPUT->footer();
    exit;
}

// ---- fill the form --------------------------------------------------------
$mform = new submission_form(
    new moodle_url('/local/eventreg/view.php', ['id' => $id]),
    ['formid' => $id, 'fields' => $fields]);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/eventreg/index.php'));
} else if ($data = $mform->get_data()) {
    $now = time();
    $answerids = [];
    $duplicate = false;
    try {
        $transaction = $DB->start_delegated_transaction();
        $subid = $DB->insert_record('local_eventreg_submissions', (object) [
            'formid'      => $id,
            'userid'      => $userid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        // answerable(): a description field asked nothing, so it gets no answer row
        // rather than an empty one per registration.
        foreach (form_builder::answerable($fields) as $field) {
            $value = form_builder::extract_value($field, $data);
            $answerids[$field->id] = $DB->insert_record('local_eventreg_answers', (object) [
                'submissionid' => $subid,
                'fieldid'      => $field->id,
                'value'        => $value,
            ]);
        }
        $transaction->allow_commit();
    } catch (\dml_write_exception $e) {
        // Unique index caught a concurrent double-submit by the same user.
        // force_transaction_rollback() unwinds without re-throwing, so we can
        // report "already registered" instead of a hard error.
        $DB->force_transaction_rollback();
        $duplicate = true;
    }

    if ($duplicate) {
        redirect(new moodle_url('/local/eventreg/view.php', ['id' => $id]),
            get_string('alreadyregistered', 'local_eventreg'));
    }

    // Attachments move out of the user's draft area only once the answer rows are
    // committed: the file API writes to disk, so it must not run inside the
    // transaction that the double-submit guard may roll back. The answer value
    // then records the filenames that actually landed.
    foreach ($fields as $field) {
        if (!form_builder::is_file_type($field->type)) {
            continue;
        }
        $answerid = $answerids[$field->id];
        $filenames = form_builder::save_answer_files($field, $data, $answerid, $context->id);
        $DB->set_field('local_eventreg_answers', 'value', $filenames, ['id' => $answerid]);
    }

    redirect(new moodle_url('/local/eventreg/view.php', ['id' => $id]),
        get_string('registrationsaved', 'local_eventreg'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($form->title));
if (!empty($form->intro)) {
    echo html_writer::div(format_text($form->intro, $form->introformat), 'mb-3');
}
if (!$fields) {
    echo $OUTPUT->notification(get_string('nofields', 'local_eventreg'), 'info');
} else {
    $mform->display();
}
echo $OUTPUT->footer();
