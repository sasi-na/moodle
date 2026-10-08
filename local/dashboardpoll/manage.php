<?php
/**
 * Manage dashboard polls: list, create/edit, activate, close.
 *
 * @package   local_dashboardpoll
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_dashboardpoll\manager;
use local_dashboardpoll\form\poll_form;

admin_externalpage_setup('local_dashboardpoll_manage');
$context = context_system::instance();
require_capability('local/dashboardpoll:manage', $context);

$action = optional_param('action', '', PARAM_ALPHA);
$id     = optional_param('id', 0, PARAM_INT);
$baseurl = new moodle_url('/local/dashboardpoll/manage.php');

// ---- simple status actions ------------------------------------------------
if (in_array($action, ['activate', 'close'], true) && $id && confirm_sesskey()) {
    $poll = $DB->get_record('local_dashboardpoll', ['id' => $id], '*', MUST_EXIST);

    if ($action === 'activate') {
        // Only one active poll per audience: close any other active poll with the
        // same cohort first.
        $DB->set_field_select('local_dashboardpoll', 'status', 'closed',
            'cohort = :c AND id <> :id AND status = :st',
            ['c' => $poll->cohort, 'id' => $id, 'st' => 'active']);
        $DB->update_record('local_dashboardpoll', (object) [
            'id' => $id, 'status' => 'active', 'timemodified' => time(), 'usermodified' => $USER->id,
        ]);
        manager::purge_all();
        redirect($baseurl, get_string('pollactivated', 'local_dashboardpoll'));
    }

    $DB->update_record('local_dashboardpoll', (object) [
        'id' => $id, 'status' => 'closed', 'timemodified' => time(), 'usermodified' => $USER->id,
    ]);
    manager::purge_all();
    redirect($baseurl, get_string('pollclosed', 'local_dashboardpoll'));
}

// ---- delete (with confirmation) -------------------------------------------
if ($action === 'delete' && $id && confirm_sesskey()) {
    $poll = $DB->get_record('local_dashboardpoll', ['id' => $id], '*', MUST_EXIST);

    if (optional_param('confirm', 0, PARAM_BOOL)) {
        $DB->delete_records('local_dashboardpoll_responses', ['pollid' => $id]);
        $DB->delete_records('local_dashboardpoll_options', ['pollid' => $id]);
        $DB->delete_records('local_dashboardpoll', ['id' => $id]);
        manager::purge_all();
        redirect($baseurl, get_string('polldeleted', 'local_dashboardpoll'));
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('managepolls', 'local_dashboardpoll'));
    echo $OUTPUT->confirm(
        get_string('confirmdelete', 'local_dashboardpoll', shorten_text(format_string($poll->question), 60)),
        new moodle_url($baseurl, ['action' => 'delete', 'id' => $id, 'confirm' => 1, 'sesskey' => sesskey()]),
        $baseurl);
    echo $OUTPUT->footer();
    exit;
}

// ---- create / edit form ---------------------------------------------------
$showform = ($action === 'edit');     // create (no id) or edit (with id).
$editing  = ($showform && $id);       // editing an existing poll specifically.
$customdata = ['cohorts' => manager::cohort_values()];
$existingoptions = [];

if ($editing) {
    $poll = $DB->get_record('local_dashboardpoll', ['id' => $id], '*', MUST_EXIST);
    $existingoptions = $DB->get_records('local_dashboardpoll_options', ['pollid' => $id], 'sortorder ASC, id ASC');
    $hasresponses = $DB->record_exists('local_dashboardpoll_responses', ['pollid' => $id]);
    if ($hasresponses) {
        $customdata['lockedoptions'] = array_map(fn($o) => $o->optiontext, array_values($existingoptions));
        $customdata['lockedmeta'] = [
            'multiselect' => (int) $poll->multiselect,
            'maxchoices'  => (int) $poll->maxchoices,
        ];
    } else {
        $customdata['optioncount'] = max(2, count($existingoptions));
    }
}

// The form action must keep action=edit (and id when editing) so the
// "add options" no-submit button redraws the form instead of falling back to
// the poll list.
$formurl = new moodle_url($baseurl, ['action' => 'edit'] + ($id ? ['id' => $id] : []));
$mform = new poll_form($formurl->out(false), $customdata);

if ($mform->is_cancelled()) {
    redirect($baseurl);
} else if ($data = $mform->get_data()) {
    $now = time();

    // The multiselect/maxchoices fields are only present when editable (not on a
    // poll that already has responses). When disabled, the required count resets
    // to 1; when enabled, it is the validated 1..options value.
    $hasselectfields = isset($data->multiselect);
    if ($hasselectfields) {
        $multiselect = !empty($data->multiselect) ? 1 : 0;
        $maxchoices  = $multiselect ? max(1, (int) $data->maxchoices) : 1;
    }

    // What students see after voting (0..3 bitmask), always editable.
    $resultsdisplay = min(3, max(0, (int) ($data->resultsdisplay ?? 1)));

    if (!empty($data->id)) {
        $record = (object) [
            'id'             => $data->id,
            'question'       => $data->question,
            'cohort'         => $data->cohort,
            'resultsdisplay' => $resultsdisplay,
            'timemodified'   => $now,
            'usermodified'   => $USER->id,
        ];
        if ($hasselectfields) {
            // Locked polls keep their stored selection settings untouched.
            $record->multiselect = $multiselect;
            $record->maxchoices  = $maxchoices;
        }
        $DB->update_record('local_dashboardpoll', $record);
        $pollid = (int) $data->id;
    } else {
        $pollid = $DB->insert_record('local_dashboardpoll', (object) [
            'question'       => $data->question,
            'cohort'         => $data->cohort,
            'multiselect'    => $multiselect,
            'maxchoices'     => $maxchoices,
            'resultsdisplay' => $resultsdisplay,
            'status'         => 'draft',
            'timecreated'    => $now,
            'timemodified'   => $now,
            'usermodified'   => $USER->id,
        ]);
    }

    // Only (re)write options when they were editable (no responses yet).
    if (isset($data->optiontext)) {
        $DB->delete_records('local_dashboardpoll_options', ['pollid' => $pollid]);
        $sortorder = 0;
        foreach ($data->optiontext as $text) {
            $text = trim($text);
            if ($text === '') {
                continue;
            }
            $DB->insert_record('local_dashboardpoll_options', (object) [
                'pollid'    => $pollid,
                'optiontext' => $text,
                'sortorder' => $sortorder++,
            ]);
        }
    }

    manager::purge_all();
    redirect($baseurl, get_string('pollsaved', 'local_dashboardpoll'));
}

// Prefill the form when editing.
if ($editing) {
    $toform = [
        'id' => $poll->id,
        'question' => $poll->question,
        'cohort' => $poll->cohort,
        'multiselect' => (int) $poll->multiselect,
        'maxchoices' => (int) $poll->maxchoices,
        'resultsdisplay' => (int) $poll->resultsdisplay,
    ];
    if (!isset($customdata['lockedoptions'])) {
        $toform['optiontext'] = array_values(array_map(fn($o) => $o->optiontext, $existingoptions));
    }
    $mform->set_data($toform);
}

// ---- render ---------------------------------------------------------------
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managepolls', 'local_dashboardpoll'));

if (!$showform) {
    // Existing polls table.
    $polls = $DB->get_records('local_dashboardpoll', null, 'timemodified DESC');
    if (!$polls) {
        echo $OUTPUT->notification(get_string('nopolls', 'local_dashboardpoll'), 'info');
    } else {
        $counts = $DB->get_records_sql(
            'SELECT pollid, COUNT(*) AS c FROM {local_dashboardpoll_responses} GROUP BY pollid');

        $table = new html_table();
        $table->head = [
            get_string('question', 'local_dashboardpoll'),
            get_string('cohort', 'local_dashboardpoll'),
            get_string('status', 'local_dashboardpoll'),
            get_string('responses', 'local_dashboardpoll'),
            get_string('resultscol', 'local_dashboardpoll'),
            get_string('actions', 'local_dashboardpoll'),
        ];
        $table->attributes['class'] = 'generaltable';

        // Labels for each poll's "after voting, show" mode (resultsdisplay bitmask).
        $displaylabels = [
            0 => get_string('resultsdisplay_none', 'local_dashboardpoll'),
            1 => get_string('resultsdisplay_percent', 'local_dashboardpoll'),
            2 => get_string('resultsdisplay_count', 'local_dashboardpoll'),
            3 => get_string('resultsdisplay_both', 'local_dashboardpoll'),
        ];

        foreach ($polls as $p) {
            $statusclass = ['draft' => 'badge-secondary', 'active' => 'badge-success', 'closed' => 'badge-dark'];
            $status = html_writer::span(
                get_string('status' . $p->status, 'local_dashboardpoll'),
                'badge ' . ($statusclass[$p->status] ?? 'badge-secondary'));

            $cohortlabel = ($p->cohort === '')
                ? get_string('allcohort', 'local_dashboardpoll')
                : format_string($p->cohort);

            $nvotes = isset($counts[$p->id]) ? (int) $counts[$p->id]->c : 0;

            $actions = [];
            $actions[] = html_writer::link(
                new moodle_url($baseurl, ['action' => 'edit', 'id' => $p->id]),
                get_string('edit'));
            if ($p->status !== 'active') {
                $actions[] = html_writer::link(
                    new moodle_url($baseurl, ['action' => 'activate', 'id' => $p->id, 'sesskey' => sesskey()]),
                    get_string('activate', 'local_dashboardpoll'));
            } else {
                $actions[] = html_writer::link(
                    new moodle_url($baseurl, ['action' => 'close', 'id' => $p->id, 'sesskey' => sesskey()]),
                    get_string('close', 'local_dashboardpoll'));
            }
            $actions[] = html_writer::link(
                new moodle_url('/local/dashboardpoll/report.php', ['pollid' => $p->id]),
                get_string('viewreport', 'local_dashboardpoll'));
            $actions[] = html_writer::link(
                new moodle_url('/local/dashboardpoll/export.php', ['pollid' => $p->id, 'sesskey' => sesskey()]),
                get_string('downloadcsv', 'local_dashboardpoll'));
            $actions[] = html_writer::link(
                new moodle_url($baseurl, ['action' => 'delete', 'id' => $p->id, 'sesskey' => sesskey()]),
                get_string('delete'),
                ['class' => 'text-danger']);

            $table->data[] = [
                shorten_text(format_string($p->question), 80),
                $cohortlabel,
                $status,
                $nvotes,
                $displaylabels[(int) $p->resultsdisplay] ?? '-',
                implode(' &nbsp;·&nbsp; ', $actions),
            ];
        }
        echo html_writer::table($table);
    }

    echo html_writer::div(
        $OUTPUT->single_button(new moodle_url($baseurl, ['action' => 'edit']),
            get_string('createpoll', 'local_dashboardpoll'), 'get'),
        'mt-3');
} else {
    echo $OUTPUT->heading(
        $id ? get_string('editpoll', 'local_dashboardpoll') : get_string('createpoll', 'local_dashboardpoll'), 3);
    $mform->display();

    // Reset the required-selections field to 1 the moment multi-select is turned
    // off, so a stale number is never left in the (then hidden) box.
    $PAGE->requires->js_amd_inline("
        require([], function() {
            var cb = document.getElementById('id_multiselect');
            var mx = document.getElementById('id_maxchoices');
            if (cb && mx) {
                cb.addEventListener('change', function() {
                    if (!cb.checked) {
                        mx.value = '1';
                    }
                });
            }
        });
    ");
}

echo $OUTPUT->footer();
