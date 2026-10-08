<?php
/**
 * Manage event registration forms: list, publish/close, duplicate, delete.
 *
 * @package   local_eventreg
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_eventreg\manager;
use local_eventreg\form_builder;

admin_externalpage_setup('local_eventreg_manage');
$context = context_system::instance();
require_capability('local/eventreg:manage', $context);

$action  = optional_param('action', '', PARAM_ALPHA);
$id      = optional_param('id', 0, PARAM_INT);
$baseurl = new moodle_url('/local/eventreg/manage.php');

// ---- status actions -------------------------------------------------------
if (in_array($action, ['publish', 'close'], true) && $id && confirm_sesskey()) {
    $DB->get_record('local_eventreg_forms', ['id' => $id], '*', MUST_EXIST);
    $status = $action === 'publish' ? 'open' : 'closed';
    $DB->update_record('local_eventreg_forms', (object) [
        'id' => $id, 'status' => $status, 'timemodified' => time(), 'usermodified' => $USER->id,
    ]);
    manager::purge_all();
    redirect($baseurl, get_string($action === 'publish' ? 'formpublished' : 'formclosed', 'local_eventreg'));
}

// ---- duplicate ------------------------------------------------------------
if ($action === 'duplicate' && $id && confirm_sesskey()) {
    $form = $DB->get_record('local_eventreg_forms', ['id' => $id], '*', MUST_EXIST);
    $now = time();
    $new = clone $form;
    unset($new->id);
    $new->title = get_string('duplicateform', 'local_eventreg') . ': ' . $form->title;
    $new->status = 'draft';
    $new->timecreated = $now;
    $new->timemodified = $now;
    $new->usermodified = $USER->id;
    $newid = $DB->insert_record('local_eventreg_forms', $new);
    foreach ($DB->get_records('local_eventreg_fields', ['formid' => $id], 'sortorder ASC, id ASC') as $f) {
        unset($f->id);
        $f->formid = $newid;
        $DB->insert_record('local_eventreg_fields', $f);
    }
    manager::purge_all();
    redirect($baseurl, get_string('formduplicated', 'local_eventreg'));
}

// ---- delete (with confirmation) -------------------------------------------
if ($action === 'delete' && $id && confirm_sesskey()) {
    $form = $DB->get_record('local_eventreg_forms', ['id' => $id], '*', MUST_EXIST);

    if (optional_param('confirm', 0, PARAM_BOOL)) {
        $subids = $DB->get_fieldset_select('local_eventreg_submissions', 'id', 'formid = :f', ['f' => $id]);
        if ($subids) {
            list($insql, $params) = $DB->get_in_or_equal($subids, SQL_PARAMS_NAMED);
            // Attachments are keyed by answer id in the file API, so they have to
            // be removed before the answer rows go.
            $answerids = $DB->get_fieldset_select('local_eventreg_answers', 'id', "submissionid $insql", $params);
            $fs = get_file_storage();
            foreach ($answerids as $answerid) {
                $fs->delete_area_files($context->id, form_builder::FILE_COMPONENT,
                    form_builder::FILE_AREA, $answerid);
            }
            $DB->delete_records_select('local_eventreg_answers', "submissionid $insql", $params);
        }
        $DB->delete_records('local_eventreg_submissions', ['formid' => $id]);
        $DB->delete_records('local_eventreg_fields', ['formid' => $id]);
        $DB->delete_records('local_eventreg_forms', ['id' => $id]);
        manager::purge_all();
        redirect($baseurl, get_string('formdeleted', 'local_eventreg'));
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('manageforms', 'local_eventreg'));
    echo $OUTPUT->confirm(
        get_string('confirmdelete', 'local_eventreg', shorten_text(format_string($form->title), 60)),
        new moodle_url($baseurl, ['action' => 'delete', 'id' => $id, 'confirm' => 1, 'sesskey' => sesskey()]),
        $baseurl);
    echo $OUTPUT->footer();
    exit;
}

// ---- render list ----------------------------------------------------------
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manageforms', 'local_eventreg'));

$forms = $DB->get_records('local_eventreg_forms', null, 'timemodified DESC');
if (!$forms) {
    echo $OUTPUT->notification(get_string('noforms', 'local_eventreg'), 'info');
} else {
    $subcounts = $DB->get_records_sql(
        'SELECT formid, COUNT(*) AS c FROM {local_eventreg_submissions} GROUP BY formid');
    $fieldcounts = $DB->get_records_sql(
        'SELECT formid, COUNT(*) AS c FROM {local_eventreg_fields} GROUP BY formid');

    $table = new html_table();
    $table->head = [
        get_string('title', 'local_eventreg'),
        get_string('audiencemode', 'local_eventreg'),
        get_string('fields', 'local_eventreg'),
        get_string('status', 'local_eventreg'),
        get_string('responses', 'local_eventreg'),
        get_string('actions', 'local_eventreg'),
    ];
    $table->attributes['class'] = 'generaltable';

    $statusclass = ['draft' => 'badge-secondary', 'open' => 'badge-success', 'closed' => 'badge-dark'];

    foreach ($forms as $f) {
        $status = html_writer::span(
            get_string('status' . $f->status, 'local_eventreg'),
            'badge ' . ($statusclass[$f->status] ?? 'badge-secondary'));

        $nsubs = isset($subcounts[$f->id]) ? (int) $subcounts[$f->id]->c : 0;
        $nfields = isset($fieldcounts[$f->id]) ? (int) $fieldcounts[$f->id]->c : 0;

        $actions = [];
        $actions[] = html_writer::link(
            new moodle_url('/local/eventreg/edit.php', ['id' => $f->id]), get_string('edit'));
        $actions[] = html_writer::link(
            new moodle_url('/local/eventreg/fields.php', ['formid' => $f->id]),
            get_string('editfields', 'local_eventreg'));
        if ($f->status !== 'open') {
            $actions[] = html_writer::link(
                new moodle_url($baseurl, ['action' => 'publish', 'id' => $f->id, 'sesskey' => sesskey()]),
                get_string('publish', 'local_eventreg'));
        } else {
            $actions[] = html_writer::link(
                new moodle_url($baseurl, ['action' => 'close', 'id' => $f->id, 'sesskey' => sesskey()]),
                get_string('unpublish', 'local_eventreg'));
        }
        $actions[] = html_writer::link(
            new moodle_url('/local/eventreg/report.php', ['formid' => $f->id]),
            get_string('viewreport', 'local_eventreg'));
        $actions[] = html_writer::link(
            new moodle_url($baseurl, ['action' => 'duplicate', 'id' => $f->id, 'sesskey' => sesskey()]),
            get_string('duplicateform', 'local_eventreg'));
        $actions[] = html_writer::link(
            new moodle_url($baseurl, ['action' => 'delete', 'id' => $f->id, 'sesskey' => sesskey()]),
            get_string('delete'), ['class' => 'text-danger']);

        $table->data[] = [
            shorten_text(format_string($f->title), 70),
            manager::audience_label($f),
            $nfields,
            $status,
            $nsubs,
            implode(' &nbsp;·&nbsp; ', $actions),
        ];
    }
    echo html_writer::table($table);
}

echo html_writer::div(
    $OUTPUT->single_button(new moodle_url('/local/eventreg/edit.php'),
        get_string('createform', 'local_eventreg'), 'get'),
    'mt-3');

echo $OUTPUT->footer();
