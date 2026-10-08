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
 * Batch vitals report.
 *
 * Two audiences, one page. A site-wide viewer gets the batch figures exactly as
 * before; a programme coordinator gets only the programmes they hold the
 * category capability on.
 *
 * @package   local_batchvitals
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_batchvitals\access;
use local_batchvitals\vitals;

$selectedid = optional_param('batch', 0, PARAM_INT);

$syscontext = context_system::instance();
$sitewide = access::is_sitewide();
$url = new moodle_url('/local/batchvitals/index.php');

if ($sitewide) {
    // Unchanged from the admin-only version: the page lives in the admin tree.
    admin_externalpage_setup('local_batchvitals_report');
} else {
    require_login();
    if (!access::has_any()) {
        throw new required_capability_exception($syscontext,
            access::CAP_PROGRAMME, 'nopermissions', '');
    }
    $PAGE->set_context($syscontext);
    $PAGE->set_url($url);
    $PAGE->set_pagelayout('report');
    $PAGE->set_title(get_string('reportpage', 'local_batchvitals'));
    $PAGE->set_heading(get_string('reportpage', 'local_batchvitals'));
}

$data = vitals::load();
$days = (int) ($data['activedays'] ?: vitals::active_days());

if ($sitewide) {
    $rows = $data['batches'];
    $scope = get_string('scope_batch', 'local_batchvitals');
} else {
    $rows = access::filter_programmes($data['programmes']);
    $scope = get_string('scope_programme', 'local_batchvitals');
    if (!$rows) {
        // Entitled somewhere, but the nightly sweep has not covered it yet.
        $rows = [];
    }
}

/**
 * Percentage of a total, already formatted, guarding division by zero.
 *
 * @param int $part
 * @param int $total
 * @return string
 */
$pct = function(int $part, int $total): string {
    return $total > 0 ? format_float(100 * $part / $total, 1) . '%' : '—';
};

$options = [];
$selected = null;
foreach ($rows as $r) {
    if (!$selectedid) {
        $selectedid = (int) $r['id'];
    }
    $ison = ((int) $r['id'] === $selectedid);
    if ($ison) {
        $selected = $r;
    }
    $label = $r['name'];
    if (!$sitewide && !empty($r['batchname'])) {
        $label = $r['batchname'] . ' — ' . $r['name'];
    }
    $options[] = ['id' => (int) $r['id'], 'name' => $label, 'selected' => $ison];
}
// An id in the URL outside this user's scope falls back to their first row.
if (!$selected && $rows) {
    $selected = $rows[0];
    $selectedid = (int) $selected['id'];
    foreach ($options as $i => $o) {
        $options[$i]['selected'] = ((int) $o['id'] === $selectedid);
    }
}

$table = [];
foreach ($rows as $r) {
    $table[] = [
        'id' => (int) $r['id'],
        'name' => (!$sitewide && !empty($r['batchname']))
            ? $r['batchname'] . ' — ' . $r['name'] : $r['name'],
        'url' => (new moodle_url($url, ['batch' => $r['id']]))->out(false),
        'learners' => number_format($r['learners']),
        'active' => number_format($r['active']),
        'never' => number_format($r['never']),
        'susp' => number_format($r['susp']),
        'courses' => number_format($r['courses']),
        'programs' => $r['programs'],
        'completion' => $r['recs'] > 0 ? format_float(100 * $r['done'] / $r['recs'], 1) . '%' : '—',
        'current' => ((int) $r['id'] === $selectedid),
    ];
}

$ctx = [
    'hasbatches'  => !empty($rows),
    'sitewide'    => $sitewide,
    'scopelabel'  => $scope,
    // A coordinator with a single programme has nothing to pick or compare.
    'showpicker'  => count($rows) > 1,
    'showtable'   => count($rows) > 1,
    'configureurl' => (new moodle_url('/admin/settings.php',
        ['section' => 'local_batchvitals_settings']))->out(false),
    'canconfigure' => has_capability('moodle/site:config', $syscontext),
    'formurl'     => $url->out(false),
    'refreshurl'  => (new moodle_url('/local/batchvitals/refresh.php'))->out(false),
    'sesskey'     => sesskey(),
    'activedays'  => $days,
    'options'     => $options,
    'rows'        => $table,
    'generatedlabel' => get_string('generated', 'local_batchvitals',
        !empty($data['gen'])
            ? userdate($data['gen'], get_string('strftimedatetimeshort', 'langconfig'))
            : get_string('never', 'local_batchvitals')),
];

if ($selected) {
    $learners = (int) $selected['learners'];
    $recs = (int) $selected['recs'];
    $comppct = $recs > 0 ? 100 * $selected['done'] / $recs : 0;

    $ctx['selected'] = [
        'id'   => (int) $selected['id'],
        'name' => $selected['name'],
        'parent' => $selected['batchname'] ?? '',
        'hasparent' => !$sitewide && !empty($selected['batchname']),
        'secs' => format_float($selected['secs'], 2),

        'learners'     => number_format($learners),
        'active'       => number_format($selected['active']),
        'activepct'    => $pct((int) $selected['active'], $learners),
        'inactive'     => number_format($selected['inactive']),
        'inactivepct'  => $pct((int) $selected['inactive'], $learners),
        'never'        => number_format($selected['never']),
        'neverpct'     => $pct((int) $selected['never'], $learners),
        'susp'         => number_format($selected['susp']),
        'susppct'      => $pct((int) $selected['susp'], $learners),
        'courses'      => number_format($selected['courses']),
        'programs'     => $selected['programs'],

        // Raw values drive the flex-grow of the stacked bar.
        'growactive'   => (int) $selected['active'],
        'growdormant'  => (int) $selected['dormant'],
        'grownever'    => (int) $selected['never'],
        'dormant'      => number_format($selected['dormant']),
        'dormantpct'   => $pct((int) $selected['dormant'], $learners),

        'hasrecs'      => $recs > 0,
        'thin'         => $recs > 0 && $recs < vitals::THIN_RECORDS,
        'comppct'      => format_float($comppct, 1),
        'compdone'     => number_format($selected['done']),
        'comptotal'    => number_format($recs),
        'ringcolour'   => ($recs > 0 && $recs < vitals::THIN_RECORDS)
            ? 'var(--bv-faint)' : 'var(--bv-good)',
    ];

    // Strings carrying a placeholder are resolved here rather than in the
    // template: number_format() emits commas, and a comma inside a {{#str}}
    // tag is read as an argument separator.
    $sel = &$ctx['selected'];
    $sel['builtlabel'] = get_string('builtin', 'local_batchvitals', $sel['secs']);
    $sel['learnerssub'] = vitals::role_filter()
        ? get_string('learners_sub_students', 'local_batchvitals')
        : get_string('learners_sub', 'local_batchvitals');
    $sel['activelabel'] = get_string('active', 'local_batchvitals', $days);
    $sel['activesub'] = get_string('active_sub', 'local_batchvitals', $days);
    $sel['inactivesub'] = get_string('inactive_sub', 'local_batchvitals', $days);
    $sel['legendactive'] = get_string('legend_active', 'local_batchvitals', $days);
    $sel['structuresub'] = get_string('structure_sub', 'local_batchvitals', $sel['programs']);
    $sel['activepctlabel'] = get_string('ofbatch', 'local_batchvitals', $sel['activepct']);
    $sel['inactivepctlabel'] = get_string('ofbatch', 'local_batchvitals', $sel['inactivepct']);
    $sel['neverpctlabel'] = get_string('ofbatch', 'local_batchvitals', $sel['neverpct']);
    $sel['susppctlabel'] = get_string('ofbatch', 'local_batchvitals', $sel['susppct']);
    $sel['compmeta'] = get_string('completion_meta', 'local_batchvitals', (object) [
        'done' => $sel['compdone'],
        'total' => $sel['comptotal'],
        'courses' => $sel['courses'],
    ]);
    unset($sel);
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_batchvitals/report', $ctx);
echo $OUTPUT->footer();
