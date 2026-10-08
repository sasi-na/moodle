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
 * The candidate review queue.
 *
 * @package   local_candidature
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/adminlib.php');

use local_candidature\candidature;

require_login();

$context = context_system::instance();
$sitewide = has_capability('local/candidature:reviewall', $context);
$scope = candidature::reviewable_categories();

if ($scope !== true && empty($scope)) {
    throw new required_capability_exception($context, 'local/candidature:review', 'nopermissions', '');
}

$url = new moodle_url('/local/candidature/index.php');
if ($sitewide) {
    admin_externalpage_setup('local_candidature_queue');
} else {
    $PAGE->set_url($url);
    $PAGE->set_context($context);
    $PAGE->set_pagelayout('report');
    $PAGE->set_title(get_string('queue', 'local_candidature'));
    $PAGE->set_heading(get_string('queue', 'local_candidature'));
}

// ---------- actions ----------
$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
if ($action && $id) {
    require_sesskey();
    $rec = $DB->get_record('local_candidature', ['id' => $id], '*', MUST_EXIST);
    if (!candidature::can_decide($rec)) {
        throw new required_capability_exception($context, 'local/candidature:review',
            'nopermissions', '');
    }
    $reason = trim(optional_param('reason', '', PARAM_TEXT));
    switch ($action) {
        case 'approve':
            candidature::approve($id, (int) $USER->id, $reason);
            redirect($url, get_string('done_approved', 'local_candidature'));
        case 'reject':
            candidature::reject($id, (int) $USER->id, $reason);
            redirect($url, get_string('done_rejected', 'local_candidature'));
        case 'reopen':
            candidature::reopen($id, (int) $USER->id);
            redirect($url, get_string('done_reopened', 'local_candidature'));
    }
    redirect($url);
}

// ---------- the list ----------
$filter = optional_param('filter', 'pending', PARAM_ALPHA);
$now = time();
$where = [];
$params = [];

switch ($filter) {
    case 'approved':
        $where[] = 'c.status = :st';  $params['st'] = candidature::APPROVED; break;
    case 'rejected':
        $where[] = 'c.status = :st';  $params['st'] = candidature::REJECTED; break;
    case 'flagged':
        $where[] = 'c.status = :st AND c.flaggedtime IS NOT NULL';
        $params['st'] = candidature::PENDING; break;
    case 'all':
        break;
    default:
        $filter = 'pending';
        $where[] = 'c.status = :st';  $params['st'] = candidature::PENDING;
}

// A Program Manager sees only their own programmes. NULL-category candidates are
// site-wide only, so they are excluded here rather than shown and then refused.
if ($scope !== true) {
    list($insql, $inparams) = $DB->get_in_or_equal($scope, SQL_PARAMS_NAMED, 'cat');
    $where[] = "c.categoryid $insql";
    $params += $inparams;
}

$sql = "SELECT c.*, u.firstname, u.lastname, u.email, cc.name AS categoryname
          FROM {local_candidature} c
          JOIN {user} u ON u.id = c.userid AND u.deleted = 0
     LEFT JOIN {course_categories} cc ON cc.id = c.categoryid"
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' ORDER BY c.submittedtime ASC';
$records = $DB->get_records_sql($sql, $params, 0, 300);

$rows = [];
foreach ($records as $r) {
    $overdue = $r->status === candidature::PENDING && $r->decisiondue && $r->decisiondue < $now;
    $rows[] = [
        'id'         => $r->id,
        'name'       => fullname($r),
        'email'      => $r->email,
        'profileurl' => (new moodle_url('/user/profile.php', ['id' => $r->userid]))->out(false),
        'programme'  => $r->categoryname ? trim(format_string($r->categoryname))
                            : get_string('unassigned', 'local_candidature'),
        'submitted'  => userdate($r->submittedtime, get_string('strftimedatetimeshort', 'langconfig')),
        'waiting'    => format_time($now - $r->submittedtime),
        'status'     => get_string('status_' . $r->status, 'local_candidature'),
        'ispending'  => $r->status === candidature::PENDING,
        'isrejected' => $r->status === candidature::REJECTED,
        'overdue'    => $overdue,
        'flagged'    => !empty($r->flaggedtime),
        'hasreason'  => trim((string) $r->reason) !== '',
        'reason'     => (string) $r->reason,
        'candecide'  => candidature::can_decide($r),
    ];
}

$counts = [];
foreach (['pending', 'flagged', 'approved', 'rejected', 'all'] as $key) {
    $w = [];
    $p = [];
    if ($key === 'pending')  { $w[] = 'status = :s'; $p['s'] = candidature::PENDING; }
    if ($key === 'approved') { $w[] = 'status = :s'; $p['s'] = candidature::APPROVED; }
    if ($key === 'rejected') { $w[] = 'status = :s'; $p['s'] = candidature::REJECTED; }
    if ($key === 'flagged')  { $w[] = 'status = :s AND flaggedtime IS NOT NULL'; $p['s'] = candidature::PENDING; }
    if ($scope !== true) {
        list($cs, $cp) = $DB->get_in_or_equal($scope, SQL_PARAMS_NAMED, 'c');
        $w[] = "categoryid $cs";
        $p += $cp;
    }
    $counts[$key] = $DB->count_records_select('local_candidature',
        $w ? implode(' AND ', $w) : '', $p);
}

$tabs = [];
foreach (['pending', 'flagged', 'approved', 'rejected', 'all'] as $key) {
    $tabs[] = [
        'label'  => get_string('filter_' . $key, 'local_candidature'),
        'url'    => (new moodle_url($url, ['filter' => $key]))->out(false),
        'active' => $filter === $key,
        'count'  => $counts[$key],
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_candidature/queue', [
    'tabs'      => $tabs,
    'rows'      => $rows,
    'hasrows'   => !empty($rows),
    'actionurl' => $url->out(false),
    'sesskey'   => sesskey(),
    'slahours'  => candidature::sla_hours(),
    'sitewide'  => $sitewide,
    'truncated' => count($records) === 300,
]);
echo $OUTPUT->footer();
