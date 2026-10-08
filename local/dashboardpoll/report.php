<?php
/**
 * Percentage report for a single dashboard poll.
 *
 * @package   local_dashboardpoll
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use local_dashboardpoll\manager;

$pollid = required_param('pollid', PARAM_INT);

require_login();
$context = context_system::instance();
if (!local_dashboardpoll_can_view_reports()) {
    throw new required_capability_exception($context, 'local/dashboardpoll:viewreports', 'nopermissions', '');
}

$poll = $DB->get_record('local_dashboardpoll', ['id' => $pollid], '*', MUST_EXIST);

// Managers navigate back to the full management list; report-only viewers
// (teachers) go back to the read-only reports list, which is all they can reach.
$ismanager = has_capability('local/dashboardpoll:manage', $context);
$backurl = new moodle_url($ismanager
    ? '/local/dashboardpoll/manage.php'
    : '/local/dashboardpoll/reports.php');
$backlabel = $ismanager
    ? get_string('managepolls', 'local_dashboardpoll')
    : get_string('pollresults', 'local_dashboardpoll');

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/dashboardpoll/report.php', ['pollid' => $pollid]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('report', 'local_dashboardpoll', format_string($poll->question)));
$PAGE->set_heading($backlabel);
$PAGE->navbar->add($backlabel, $backurl);
$PAGE->navbar->add(format_string(shorten_text($poll->question, 40)));

$agg = manager::aggregate($pollid);
$total = (int) $agg['total'];      // Total selections cast (can exceed voters when multi-select).
$voters = (int) $agg['voters'];    // Distinct respondents — the percentage base.

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($poll->question));

$cohortlabel = ($poll->cohort === '')
    ? get_string('allcohort', 'local_dashboardpoll')
    : format_string($poll->cohort);

// Summary line: cohort, respondent count and (for cohort polls) response rate.
$summary = get_string('cohort', 'local_dashboardpoll') . ': ' . html_writer::tag('strong', $cohortlabel);
$summary .= ' &nbsp;|&nbsp; ' . get_string('totalresponses', 'local_dashboardpoll')
    . ': ' . html_writer::tag('strong', $voters);
if (!empty($poll->multiselect)) {
    // With multiple picks allowed, also show how many selections were cast.
    $summary .= ' &nbsp;|&nbsp; ' . get_string('totalselections', 'local_dashboardpoll')
        . ': ' . html_writer::tag('strong', $total);
}
$cohortsize = manager::cohort_size($poll->cohort);
if ($cohortsize > 0) {
    $rate = round($voters * 100 / $cohortsize);
    $summary .= ' &nbsp;|&nbsp; ' . get_string('responserate', 'local_dashboardpoll')
        . ': ' . html_writer::tag('strong', $rate . '% (' . $voters . '/' . $cohortsize . ')');
}
echo html_writer::div($summary, 'mb-4');

if ($voters === 0) {
    echo $OUTPUT->notification(get_string('noresponses', 'local_dashboardpoll'), 'info');
} else {
    if (!empty($poll->multiselect)) {
        echo html_writer::div(get_string('multiselectnote', 'local_dashboardpoll'), 'text-muted small mb-3');
    }
    foreach ($agg['options'] as $o) {
        // Share of respondents who chose this option.
        $percent = $voters > 0 ? round($o['votes'] * 100 / $voters) : 0;

        $head = html_writer::tag('span', format_string($o['text']))
            . html_writer::tag('span', $percent . '%  '
                . html_writer::tag('small', '(' . get_string('votes', 'local_dashboardpoll', $o['votes']) . ')',
                    ['class' => 'text-muted']),
                ['class' => 'font-weight-bold']);

        $bar = html_writer::div(
            html_writer::div('', 'progress-bar', [
                'role' => 'progressbar',
                'style' => 'width: ' . $percent . '%;',
                'aria-valuenow' => $percent,
                'aria-valuemin' => 0,
                'aria-valuemax' => 100,
            ]),
            'progress', ['style' => 'height: 14px;']);

        echo html_writer::div(
            html_writer::div($head, 'd-flex justify-content-between mb-1') . $bar,
            'mb-3');
    }
}

echo html_writer::div(
    $OUTPUT->single_button(
        new moodle_url('/local/dashboardpoll/export.php', ['pollid' => $pollid, 'sesskey' => sesskey()]),
        get_string('downloadcsv', 'local_dashboardpoll'), 'get')
    . ' '
    . html_writer::link($backurl, $backlabel, ['class' => 'btn btn-link']),
    'mt-4 d-flex align-items-center');

echo $OUTPUT->footer();
