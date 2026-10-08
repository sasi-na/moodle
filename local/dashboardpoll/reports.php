<?php
/**
 * Read-only list of dashboard polls for report viewers (teachers).
 *
 * Unlike manage.php this uses $PAGE->set_context() rather than
 * admin_externalpage_setup(), so non-admin capability holders can reach it, and
 * it renders View report / Download CSV links ONLY — no create/edit/delete/
 * activate/close controls.
 *
 * @package   local_dashboardpoll
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
$context = context_system::instance();
if (!local_dashboardpoll_can_view_reports()) {
    throw new required_capability_exception($context, 'local/dashboardpoll:viewreports', 'nopermissions', '');
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/dashboardpoll/reports.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('pollresults', 'local_dashboardpoll'));
$PAGE->set_heading(get_string('pollresults', 'local_dashboardpoll'));
$PAGE->navbar->add(get_string('pollresults', 'local_dashboardpoll'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pollresults', 'local_dashboardpoll'));

$polls = $DB->get_records('local_dashboardpoll', null, 'timemodified DESC');
if (!$polls) {
    echo $OUTPUT->notification(get_string('nopolls', 'local_dashboardpoll'), 'info');
} else {
    $counts = $DB->get_records_sql(
        'SELECT pollid, COUNT(*) AS c FROM {local_dashboardpoll_responses} GROUP BY pollid');

    $statusclass = ['draft' => 'badge-secondary', 'active' => 'badge-success', 'closed' => 'badge-dark'];

    $table = new html_table();
    $table->head = [
        get_string('question', 'local_dashboardpoll'),
        get_string('cohort', 'local_dashboardpoll'),
        get_string('status', 'local_dashboardpoll'),
        get_string('responses', 'local_dashboardpoll'),
        get_string('actions', 'local_dashboardpoll'),
    ];
    $table->attributes['class'] = 'generaltable';

    foreach ($polls as $p) {
        $status = html_writer::span(
            get_string('status' . $p->status, 'local_dashboardpoll'),
            'badge ' . ($statusclass[$p->status] ?? 'badge-secondary'));

        $cohortlabel = ($p->cohort === '')
            ? get_string('allcohort', 'local_dashboardpoll')
            : format_string($p->cohort);

        $reporturl = new moodle_url('/local/dashboardpoll/report.php', ['pollid' => $p->id]);
        $exporturl = new moodle_url('/local/dashboardpoll/export.php',
            ['pollid' => $p->id, 'sesskey' => sesskey()]);

        // View report / Download CSV only — no management actions.
        $actions = html_writer::link($reporturl, get_string('viewreport', 'local_dashboardpoll'),
                ['class' => 'btn btn-sm btn-secondary'])
            . ' '
            . html_writer::link($exporturl, get_string('downloadcsv', 'local_dashboardpoll'),
                ['class' => 'btn btn-sm btn-link']);

        $table->data[] = [
            format_string($p->question),
            $cohortlabel,
            $status,
            isset($counts[$p->id]) ? (int) $counts[$p->id]->c : 0,
            $actions,
        ];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
