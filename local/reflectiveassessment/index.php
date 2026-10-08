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
 * Reflective Assessment reporting: guide summary, evaluator rollup and
 * per-student detail for a chosen batch and semester.
 *
 * @package   local_reflectiveassessment
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_reflectiveassessment\summary;
use local_reflectiveassessment\evaluators;
use local_reflectiveassessment\detail;

require_login();
$context = context_system::instance();
require_capability('local/reflectiveassessment:view', $context);

/** @var int Student detail rows per page. */
const LOCAL_REFLECTIVEASSESSMENT_PERPAGE = 100;

$view = optional_param('view', 'guides', PARAM_ALPHA);
$periodkey = optional_param('period', '', PARAM_RAW_TRIMMED);
$sort = optional_param('sort', 'submitted', PARAM_ALPHA);
$courseid = optional_param('courseid', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$dataformat = optional_param('dataformat', '', PARAM_ALPHA);

if (!in_array($view, ['guides', 'evaluators', 'students'], true)) {
    $view = 'guides';
}

$periods = summary::get_periods();
// Fall back to the newest batch rather than erroring on a stale bookmark.
if (!isset($periods[$periodkey])) {
    $periodkey = $periods ? array_key_first($periods) : '';
}

$baseurl = new moodle_url('/local/reflectiveassessment/index.php');
$viewparams = ['view' => $view, 'period' => $periodkey];
if ($view === 'guides') {
    $viewparams['sort'] = $sort;
}
if ($view === 'students' && $courseid) {
    $viewparams['courseid'] = $courseid;
}
$pageurl = new moodle_url($baseurl, $viewparams);

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('reportpage', 'local_reflectiveassessment'));
$PAGE->set_heading(get_string('reportpage', 'local_reflectiveassessment'));

if ($view === 'students') {
    \core_php_time_limit::raise(300);
    raise_memory_limit(MEMORY_EXTRA);
}

$report = null;
$builder = null;
if ($periodkey) {
    switch ($view) {
        case 'evaluators':
            $report = evaluators::build($periodkey);
            $builder = evaluators::class;
            break;
        case 'students':
            if ($courseid && !array_key_exists($courseid, summary::get_courses())) {
                $courseid = 0;
            }
            $report = detail::build($periodkey, $courseid);
            $builder = detail::class;
            break;
        default:
            $report = summary::build($periodkey, $sort);
            $builder = summary::class;
    }
}

// Must precede any output: download_data() refuses to run once buffered.
if ($report && $dataformat) {
    \core\session\manager::write_close();
    $rows = new ArrayIterator(array_map([$builder, 'export_row'], $report->rows));
    $filename = clean_filename(get_string('pluginname', 'local_reflectiveassessment')
        . ' - ' . $report->period->label
        . ' - ' . get_string('view_' . $view, 'local_reflectiveassessment'));
    \core\dataformat::download_data($filename, $dataformat, $builder::export_columns(), $rows);
    die();
}

echo $OUTPUT->header();

if (!$periods) {
    echo $OUTPUT->notification(get_string('noperiods', 'local_reflectiveassessment'),
        \core\output\notification::NOTIFY_WARNING);
    echo $OUTPUT->footer();
    die();
}

$tabs = [];
foreach (['guides', 'evaluators', 'students'] as $name) {
    $tabs[] = new tabobject($name,
        new moodle_url($baseurl, ['view' => $name, 'period' => $periodkey]),
        get_string('view_' . $name, 'local_reflectiveassessment'));
}
echo $OUTPUT->tabtree($tabs, $view);

echo html_writer::tag('p', get_string('intro', 'local_reflectiveassessment'), ['class' => 'text-muted']);

$periodoptions = [];
foreach ($periods as $key => $period) {
    $periodoptions[$key] = $period->label;
}

echo html_writer::start_div('d-flex flex-wrap align-items-end gap-3 mb-3');
echo $OUTPUT->single_select(new moodle_url($baseurl, ['view' => $view, 'sort' => $sort]),
    'period', $periodoptions, $periodkey, null, null,
    ['label' => get_string('period', 'local_reflectiveassessment')]);

if ($view === 'guides') {
    $sortoptions = [
        'submitted' => get_string('sort_submitted', 'local_reflectiveassessment'),
        'guide'     => get_string('sort_guide', 'local_reflectiveassessment'),
        'evaluated' => get_string('sort_evaluated', 'local_reflectiveassessment'),
        'awaiting'  => get_string('sort_awaiting', 'local_reflectiveassessment'),
    ];
    echo $OUTPUT->single_select(new moodle_url($baseurl, ['view' => $view, 'period' => $periodkey]),
        'sort', $sortoptions, $sort, null, null,
        ['label' => get_string('sortby', 'local_reflectiveassessment')]);
}

if ($view === 'students') {
    $courseoptions = [0 => get_string('allcourses', 'local_reflectiveassessment')];
    foreach (summary::build($periodkey, 'guide')->rows as $row) {
        $courseoptions[$row->courseid] = $row->coursecode . ' — ' . $row->guide;
    }
    echo $OUTPUT->single_select(new moodle_url($baseurl, ['view' => $view, 'period' => $periodkey]),
        'courseid', $courseoptions, $courseid, null, null,
        ['label' => get_string('filtercourse', 'local_reflectiveassessment')]);
}
echo html_writer::end_div();

if (!$report->rows) {
    echo $OUTPUT->notification(get_string('nodata', 'local_reflectiveassessment'),
        \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    die();
}

$table = new html_table();
$table->attributes['class'] = 'generaltable table-sm';
$table->head = $builder::export_columns();

if ($view === 'guides') {
    $shared = count(array_filter($report->rows, function($row) {
        return $row->sharedmarker;
    }));
    if ($shared) {
        echo $OUTPUT->notification(get_string('sharedmarkernotice', 'local_reflectiveassessment', $shared),
            \core\output\notification::NOTIFY_WARNING);
    }
    $deleted = count(array_filter($report->rows, function($row) {
        return $row->guidestatus === summary::GUIDE_DELETED;
    }));
    if ($deleted) {
        echo $OUTPUT->notification(get_string('deletedguidenotice', 'local_reflectiveassessment', $deleted),
            \core\output\notification::NOTIFY_INFO);
    }

    $table->align = [
        'left', 'left', 'left', 'left', 'left', 'left', 'left',
        'right', 'right', 'right', 'right', 'right', 'right', 'right',
        'right', 'right', 'right', 'right', 'left', 'left',
    ];

    foreach ($report->rows as $row) {
        $guide = $row->guide;
        if ($row->guidestatus === summary::GUIDE_DELETED || $row->guidestatus === summary::GUIDE_UNRESOLVED) {
            $guide .= ' ' . html_writer::tag('span', get_string('guidestatus_' . $row->guidestatus,
                'local_reflectiveassessment'), ['class' => 'badge bg-secondary badge-secondary']);
        }

        $tablerow = new html_table_row([
            $row->batch,
            $row->program,
            $row->semester,
            $guide,
            $row->guideemail,
            get_string('guidestatus_' . $row->guidestatus, 'local_reflectiveassessment'),
            html_writer::link(new moodle_url('/course/view.php', ['id' => $row->courseid]), $row->coursecode),
            $row->allocated,
            $row->submitted,
            $row->answered,
            $row->evaluated,
            $row->awaiting ? html_writer::tag('strong', $row->awaiting, ['class' => 'text-danger']) : 0,
            $row->blank,
            $row->notattempted,
            $row->pctsubmitted === null ? '' : $row->pctsubmitted . '%',
            $row->pctevaluated === null ? '' : $row->pctevaluated . '%',
            $row->avgmark === null ? '' : format_float($row->avgmark, 1),
            $row->maxmark === null ? '' : format_float($row->maxmark, 0),
            s($row->evaluatedby),
            $row->lastevaluated ? userdate($row->lastevaluated, get_string('strftimedate', 'langconfig')) : '',
        ]);
        if ($row->pctevaluated !== null && $row->pctevaluated < 50) {
            $tablerow->attributes['class'] = 'table-warning';
        }
        $table->data[] = $tablerow;
    }

    $totals = $report->totals;
    $totalsrow = new html_table_row([
        html_writer::tag('strong', get_string('totals', 'local_reflectiveassessment')),
        '', '', '', '', '',
        $totals->courses,
        html_writer::tag('strong', $totals->allocated),
        html_writer::tag('strong', $totals->submitted),
        html_writer::tag('strong', $totals->answered),
        html_writer::tag('strong', $totals->evaluated),
        html_writer::tag('strong', $totals->awaiting),
        html_writer::tag('strong', $totals->blank),
        html_writer::tag('strong', $totals->notattempted),
        $totals->pctsubmitted === null ? '' : html_writer::tag('strong', $totals->pctsubmitted . '%'),
        $totals->pctevaluated === null ? '' : html_writer::tag('strong', $totals->pctevaluated . '%'),
        $totals->avgmark === null ? '' : html_writer::tag('strong', format_float($totals->avgmark, 1)),
        '', '', '',
    ]);
    $totalsrow->attributes['class'] = 'table-active';
    $table->data[] = $totalsrow;

} else if ($view === 'evaluators') {
    echo html_writer::tag('p', get_string('evaluatorcounts', 'local_reflectiveassessment',
        $report->totals), ['class' => 'lead']);
    if ($report->totals->sharedgraded) {
        echo $OUTPUT->notification(get_string('sharedgradednotice', 'local_reflectiveassessment',
            $report->totals->sharedgraded), \core\output\notification::NOTIFY_WARNING);
    }

    $table->align = ['left', 'left', 'left', 'left', 'right', 'right', 'left', 'right', 'left', 'left'];

    foreach ($report->rows as $row) {
        $name = $row->status === summary::GUIDE_ACTIVE ? $row->evaluator
            : $row->evaluator . ' ' . html_writer::tag('span',
                get_string('guidestatus_' . $row->status, 'local_reflectiveassessment'),
                ['class' => 'badge bg-secondary badge-secondary']);

        $tablerow = new html_table_row([
            $name,
            $row->email,
            get_string('guidestatus_' . $row->status, 'local_reflectiveassessment'),
            $row->shared ? get_string('yes') : get_string('no'),
            html_writer::tag('strong', $row->graded),
            $row->courses,
            s($row->coursecodes),
            $row->avgmark === null ? '' : format_float($row->avgmark, 1),
            $row->first ? userdate($row->first, get_string('strftimedate', 'langconfig')) : '',
            $row->last ? userdate($row->last, get_string('strftimedate', 'langconfig')) : '',
        ]);
        if ($row->shared) {
            $tablerow->attributes['class'] = 'table-warning';
        }
        $table->data[] = $tablerow;
    }

} else {
    echo html_writer::tag('p', get_string('detailcounts', 'local_reflectiveassessment',
        $report->counts), ['class' => 'lead']);
    echo html_writer::tag('p', get_string('markblanknote', 'local_reflectiveassessment'),
        ['class' => 'text-muted']);

    $table->align = [
        'left', 'left', 'left', 'left', 'left', 'left', 'left', 'left',
        'left', 'left', 'left', 'left', 'left', 'right', 'right', 'left', 'left',
    ];

    $total = count($report->rows);
    $from = $page * LOCAL_REFLECTIVEASSESSMENT_PERPAGE;
    $slice = array_slice($report->rows, $from, LOCAL_REFLECTIVEASSESSMENT_PERPAGE);

    $badges = [
        'evaluated' => 'bg-success badge-success',
        'awaiting' => 'bg-danger badge-danger',
        'blank' => 'bg-warning text-dark badge-warning',
        detail::STATUS_NOTATTEMPTED => 'bg-secondary badge-secondary',
    ];

    foreach ($slice as $row) {
        $datefmt = get_string('strftimedate', 'langconfig');
        $table->data[] = new html_table_row([
            $row->batch,
            $row->program,
            $row->semester,
            $row->guide,
            $row->guideemail,
            html_writer::link(new moodle_url('/course/view.php', ['id' => $row->courseid]), $row->coursecode),
            html_writer::link(new moodle_url('/user/profile.php', ['id' => $row->userid]), $row->studentname),
            $row->email,
            $row->usn,
            $row->enrolmentno,
            $row->programreg,
            html_writer::tag('span', get_string('status_' . $row->status, 'local_reflectiveassessment'),
                ['class' => 'badge ' . $badges[$row->status]]),
            $row->submittedon ? userdate($row->submittedon, $datefmt) : '',
            $row->mark === null ? '' : format_float($row->mark, 0),
            $row->maxmark === null ? '' : format_float($row->maxmark, 0),
            $row->evaluatedby,
            $row->evaluatedon ? userdate($row->evaluatedon, $datefmt) : '',
        ]);
    }

    echo html_writer::tag('p', get_string('showingrows', 'local_reflectiveassessment', (object) [
        'from' => $total ? $from + 1 : 0,
        'to' => min($from + LOCAL_REFLECTIVEASSESSMENT_PERPAGE, $total),
        'total' => $total,
    ]), ['class' => 'text-muted']);
    echo $OUTPUT->paging_bar($total, $page, LOCAL_REFLECTIVEASSESSMENT_PERPAGE, $pageurl);
}

echo html_writer::start_div('table-responsive');
echo html_writer::table($table);
echo html_writer::end_div();

if ($view === 'students') {
    echo $OUTPUT->paging_bar(count($report->rows), $page, LOCAL_REFLECTIVEASSESSMENT_PERPAGE, $pageurl);
}

echo html_writer::tag('p', get_string('generated', 'local_reflectiveassessment',
    userdate($report->generated)), ['class' => 'text-muted']);

echo $OUTPUT->download_dataformat_selector(get_string('download', 'local_reflectiveassessment'),
    $baseurl->out(false), 'dataformat', $viewparams);

echo $OUTPUT->footer();
