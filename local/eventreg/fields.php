<?php
/**
 * Question builder for a registration form: list / add / edit / delete / reorder
 * fields. Server-rendered, one field per action. Once the form has responses the
 * structure is locked (no add/delete/reorder/retype) so recorded answers stay valid.
 *
 * @package   local_eventreg
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_eventreg\manager;
use local_eventreg\form_builder;
use local_eventreg\form\field_form;

$formid  = required_param('formid', PARAM_INT);
$action  = optional_param('action', '', PARAM_ALPHA);
$fieldid = optional_param('fieldid', 0, PARAM_INT);

admin_externalpage_setup('local_eventreg_manage');
$context = context_system::instance();
require_capability('local/eventreg:manage', $context);

$form = $DB->get_record('local_eventreg_forms', ['id' => $formid], '*', MUST_EXIST);
$baseurl = new moodle_url('/local/eventreg/fields.php', ['formid' => $formid]);
$PAGE->set_url($baseurl);

$locked = manager::has_submissions($formid);
$types = form_builder::field_types();

// ---- reorder --------------------------------------------------------------
if (in_array($action, ['moveup', 'movedown'], true) && $fieldid && !$locked && confirm_sesskey()) {
    $fields = array_values($DB->get_records('local_eventreg_fields', ['formid' => $formid], 'sortorder ASC, id ASC'));
    foreach ($fields as $i => $f) {
        if ((int) $f->id === $fieldid) {
            $j = $action === 'moveup' ? $i - 1 : $i + 1;
            if ($j >= 0 && $j < count($fields)) {
                // Swap sortorder with the neighbour.
                $a = $fields[$i];
                $b = $fields[$j];
                $DB->set_field('local_eventreg_fields', 'sortorder', $b->sortorder, ['id' => $a->id]);
                $DB->set_field('local_eventreg_fields', 'sortorder', $a->sortorder, ['id' => $b->id]);
                manager::purge_all();
            }
            break;
        }
    }
    redirect($baseurl);
}

// ---- delete a field -------------------------------------------------------
if ($action === 'delete' && $fieldid && !$locked && confirm_sesskey()) {
    $field = $DB->get_record('local_eventreg_fields', ['id' => $fieldid, 'formid' => $formid], '*', MUST_EXIST);
    if (optional_param('confirm', 0, PARAM_BOOL)) {
        $DB->delete_records('local_eventreg_fields', ['id' => $fieldid]);
        manager::purge_all();
        redirect($baseurl, get_string('fielddeleted', 'local_eventreg'));
    }
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($form->title));
    echo $OUTPUT->confirm(
        get_string('confirmdeletefield', 'local_eventreg', shorten_text(form_builder::plain_label($field), 60)),
        new moodle_url($baseurl, ['action' => 'delete', 'fieldid' => $fieldid, 'confirm' => 1, 'sesskey' => sesskey()]),
        $baseurl);
    echo $OUTPUT->footer();
    exit;
}

// ---- add / edit a field ---------------------------------------------------
if ($action === 'add' || $action === 'edit') {
    $field = null;
    if ($action === 'edit' && $fieldid) {
        $field = $DB->get_record('local_eventreg_fields', ['id' => $fieldid, 'formid' => $formid], '*', MUST_EXIST);
    }

    $customdata = ['locked' => $locked];
    if ($field) {
        $customdata['type'] = $field->type;
    }
    $formurl = new moodle_url($baseurl, ['action' => $action] + ($fieldid ? ['fieldid' => $fieldid] : []));
    $mform = new field_form($formurl->out(false), $customdata);

    if ($mform->is_cancelled()) {
        redirect($baseurl);
    } else if ($data = $mform->get_data()) {
        $type = $data->type;

        // Options JSON for choice types.
        $optionsjson = null;
        if (form_builder::is_choice_type($type)) {
            $opts = array_values(array_filter(array_map('trim', explode("\n", (string) $data->options)),
                fn($v) => $v !== ''));
            $optionsjson = json_encode($opts);
        }

        // Settings JSON per type.
        $settings = [];
        if ($type === 'rating') {
            $settings['min'] = (int) $data->ratingmin;
            $settings['max'] = (int) $data->ratingmax;
            $settings['lowlabel'] = trim((string) $data->ratinglow);
            $settings['highlabel'] = trim((string) $data->ratinghigh);
        } else if ($type === 'number') {
            if (trim((string) $data->numbermin) !== '') {
                $settings['min'] = trim((string) $data->numbermin);
            }
            if (trim((string) $data->numbermax) !== '') {
                $settings['max'] = trim((string) $data->numbermax);
            }
        } else if (form_builder::is_file_type($type)) {
            $settings['maxfiles'] = max(1, (int) $data->filemaxfiles);
            $settings['maxbytes'] = (int) $data->filemaxbytes;
            $accept = trim((string) $data->fileaccept);
            $settings['accept'] = $accept === '' ? '*' : $accept;
        }
        $settingsjson = $settings ? json_encode($settings) : null;

        // Editors submit ['text' => ..., 'format' => ...]; unpacked the same way
        // edit.php unpacks the form's own intro editor.
        $record = (object) [
            'formid'   => $formid,
            'type'     => $type,
            'label'          => $data->label['text'] ?? '',
            'labelformat'    => $data->label['format'] ?? FORMAT_HTML,
            'helptext'       => $data->helptext['text'] ?? '',
            'helptextformat' => $data->helptext['format'] ?? FORMAT_HTML,
            // A description asks nothing, so it can never be required however the
            // hidden checkbox happens to post.
            'required' => (!form_builder::is_display_type($type) && !empty($data->required)) ? 1 : 0,
            'options'  => $optionsjson,
            'settings' => $settingsjson,
        ];

        if (!empty($data->id)) {
            $record->id = $data->id;
            // Locked forms keep structure; only wording/required/help change.
            if ($locked) {
                $existing = $DB->get_record('local_eventreg_fields', ['id' => $data->id], '*', MUST_EXIST);
                $record->type = $existing->type;
                $record->options = $existing->options;
                $record->settings = $existing->settings;
            }
            $DB->update_record('local_eventreg_fields', $record);
        } else {
            $max = (int) $DB->get_field_sql(
                'SELECT COALESCE(MAX(sortorder), -1) FROM {local_eventreg_fields} WHERE formid = ?', [$formid]);
            $record->sortorder = $max + 1;
            $DB->insert_record('local_eventreg_fields', $record);
        }
        manager::purge_all();
        redirect($baseurl, get_string('fieldsaved', 'local_eventreg'));
    }

    if ($field) {
        $toform = [
            'id'        => $field->id,
            'formid'    => $formid,
            'type'      => $field->type,
            // Editors want ['text' => ..., 'format' => ...]. A row written before the
            // editor existed carries no format, and form_builder::text_format() resolves
            // that to FORMAT_MOODLE so its real newlines survive the round trip.
            'label'     => [
                'text'   => $field->label,
                'format' => form_builder::text_format($field->labelformat ?? null),
            ],
            'helptext'  => [
                'text'   => (string) $field->helptext,
                'format' => form_builder::text_format($field->helptextformat ?? null),
            ],
            'required'  => (int) $field->required,
        ];
        if (form_builder::is_choice_type($field->type)) {
            $toform['options'] = implode("\n", form_builder::options($field));
        }
        $s = form_builder::settings($field);
        if ($field->type === 'rating') {
            $toform['ratingmin'] = $s['min'] ?? 1;
            $toform['ratingmax'] = $s['max'] ?? 5;
            $toform['ratinglow'] = $s['lowlabel'] ?? '';
            $toform['ratinghigh'] = $s['highlabel'] ?? '';
        } else if ($field->type === 'number') {
            $toform['numbermin'] = $s['min'] ?? '';
            $toform['numbermax'] = $s['max'] ?? '';
        } else if (form_builder::is_file_type($field->type)) {
            $toform['filemaxfiles'] = (int) ($s['maxfiles'] ?? 1);
            $toform['filemaxbytes'] = (int) ($s['maxbytes'] ?? 0);
            $toform['fileaccept'] = $s['accept'] ?? '*';
        }
        $mform->set_data($toform);
    } else {
        $mform->set_data(['formid' => $formid]);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($form->title));
    echo $OUTPUT->heading($action === 'edit'
        ? get_string('editfield', 'local_eventreg')
        : get_string('addfield', 'local_eventreg'), 3);
    $mform->display();
    echo $OUTPUT->footer();
    exit;
}

// ---- list fields ----------------------------------------------------------
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($form->title));
echo $OUTPUT->heading(get_string('fields', 'local_eventreg'), 3);

if ($locked) {
    echo $OUTPUT->notification(get_string('fieldlocked', 'local_eventreg'), 'info');
}

$fields = array_values($DB->get_records('local_eventreg_fields', ['formid' => $formid], 'sortorder ASC, id ASC'));
if (!$fields) {
    echo $OUTPUT->notification(get_string('nofields', 'local_eventreg'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        '#',
        get_string('fieldlabel', 'local_eventreg'),
        get_string('fieldtype', 'local_eventreg'),
        get_string('fieldrequired', 'local_eventreg'),
        get_string('actions', 'local_eventreg'),
    ];
    $table->attributes['class'] = 'generaltable';

    $n = count($fields);
    foreach ($fields as $i => $f) {
        $actions = [];
        $actions[] = html_writer::link(
            new moodle_url($baseurl, ['action' => 'edit', 'fieldid' => $f->id]), get_string('edit'));
        if (!$locked) {
            if ($i > 0) {
                $actions[] = html_writer::link(
                    new moodle_url($baseurl, ['action' => 'moveup', 'fieldid' => $f->id, 'sesskey' => sesskey()]),
                    get_string('moveup', 'local_eventreg'));
            }
            if ($i < $n - 1) {
                $actions[] = html_writer::link(
                    new moodle_url($baseurl, ['action' => 'movedown', 'fieldid' => $f->id, 'sesskey' => sesskey()]),
                    get_string('movedown', 'local_eventreg'));
            }
            $actions[] = html_writer::link(
                new moodle_url($baseurl, ['action' => 'delete', 'fieldid' => $f->id, 'sesskey' => sesskey()]),
                get_string('delete'), ['class' => 'text-danger']);
        }
        $table->data[] = [
            $i + 1,
            shorten_text(form_builder::plain_label($f), 80),
            $types[$f->type] ?? $f->type,
            !empty($f->required) ? get_string('yes') : get_string('no'),
            implode(' &nbsp;·&nbsp; ', $actions),
        ];
    }
    echo html_writer::table($table);
}

$buttons = '';
if (!$locked) {
    $buttons .= $OUTPUT->single_button(
        new moodle_url($baseurl, ['action' => 'add']),
        get_string('addfield', 'local_eventreg'), 'get');
}
$buttons .= ' ' . html_writer::link(new moodle_url('/local/eventreg/manage.php'),
    get_string('back', 'local_eventreg'), ['class' => 'btn btn-link']);
echo html_writer::div($buttons, 'mt-3 d-flex align-items-center');

echo $OUTPUT->footer();
