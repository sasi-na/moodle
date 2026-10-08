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
 * The support queue.
 *
 * @package    local_helpdesk
 * @copyright  2026 onwards
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use local_helpdesk\sla;
use local_helpdesk\ticket;

require_login();
local_helpdesk_require_enabled();

$context = context_system::instance();
require_capability('local/helpdesk:manage', $context);

$filter = optional_param('filter', 'open', PARAM_ALPHA);
$category = optional_param('category', '', PARAM_ALPHA);
$q = trim(optional_param('q', '', PARAM_TEXT));
$export = optional_param('export', '', PARAM_ALPHA);

$PAGE->set_url(new moodle_url('/local/helpdesk/queue.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('queue', 'local_helpdesk'));
$PAGE->set_heading(get_string('queue', 'local_helpdesk'));

if ($take = optional_param('take', 0, PARAM_INT)) {
    require_sesskey();
    ticket::instance($take)->assign($USER->id);
    redirect($PAGE->url);
}

$openstatuses = [ticket::STATUS_NEW, ticket::STATUS_OPEN, ticket::STATUS_INPROGRESS,
                 ticket::STATUS_ESCALATED];
[$opensql, $openparams] = $DB->get_in_or_equal($openstatuses, SQL_PARAMS_NAMED, 'st');

$now = time();
$where = [];
$params = [];

switch ($filter) {
    case 'nearing':
        // Still open, not yet breached, inside the warning window.
        $where[] = "t.status $opensql AND t.resolutiondue >= :nearfrom AND t.resolutiondue < :nearto";
        $params += $openparams + ['nearfrom' => $now,
                                  'nearto' => $now + (sla::NEARING_HOURS * HOURSECS)];
        break;

    case 'resolvedtoday':
        $where[] = 't.resolvedtime >= :dayfrom';
        $params += ['dayfrom' => usergetmidnight($now)];
        break;

    case 'unassigned':
        $where[] = "t.status $opensql AND t.assigneeid IS NULL";
        $params += $openparams;
        break;
    case 'mine':
        $where[] = "t.status $opensql AND t.assigneeid = :me";
        $params += $openparams + ['me' => $USER->id];
        break;
    case 'breached':
        $where[] = "t.status $opensql AND t.resolutiondue < :now";
        $params += $openparams + ['now' => $now];
        break;
    case 'resolved':
        $where[] = 't.status = :resolved';
        $params += ['resolved' => ticket::STATUS_RESOLVED];
        break;
    case 'closed':
        $where[] = 't.status = :closed';
        $params += ['closed' => ticket::STATUS_CLOSED];
        break;
    case 'all':
        break;
    default:
        $filter = 'open';
        $where[] = "t.status $opensql";
        $params += $openparams;
}

if ($category !== '') {
    $where[] = 't.category = :category';
    $params['category'] = $category;
}

// Search across the three fields the PRD names: ticket number, student name, email.
if ($q !== '') {
    $like = '%' . $DB->sql_like_escape($q) . '%';
    $where[] = '(' . $DB->sql_like('t.reference', ':q1', false) . ' OR '
        . $DB->sql_like($DB->sql_fullname('u.firstname', 'u.lastname'), ':q2', false) . ' OR '
        . $DB->sql_like('u.email', ':q3', false) . ')';
    $params += ['q1' => $like, 'q2' => $like, 'q3' => $like];
}

$params['sortnow'] = $now;
// The user table is joined unconditionally so the search clause above can reach
// the student's name and email. One indexed foreign key; the cost is noise.
$sql = 'SELECT t.* FROM {local_helpdesk_ticket} t
          LEFT JOIN {user} u ON u.id = t.userid'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' ORDER BY CASE WHEN t.resolutiondue < :sortnow THEN 0 ELSE 1 END, t.resolutiondue ASC';
$records = $DB->get_records_sql($sql, $params, 0, 200);

$userids = [];
foreach ($records as $record) {
    foreach ([$record->userid, $record->assigneeid, $record->forwardedto] as $id) {
        if ($id) {
            $userids[(int) $id] = true;
        }
    }
}
$users = $userids ? $DB->get_records_list('user', 'id', array_keys($userids)) : [];
$flags = local_helpdesk_flags(array_keys($records));

$rows = [];
foreach ($records as $record) {
    $rows[] = local_helpdesk_queue_row($record, $users, $flags[$record->id] ?? [], (int) $USER->id);
}

$counts = [
    'unassigned' => $DB->count_records_select('local_helpdesk_ticket',
        "status $opensql AND assigneeid IS NULL", $openparams),
    'mine' => $DB->count_records_select('local_helpdesk_ticket',
        "status $opensql AND assigneeid = :me", $openparams + ['me' => $USER->id]),
    'breached' => $DB->count_records_select('local_helpdesk_ticket',
        "status $opensql AND resolutiondue < :now", $openparams + ['now' => $now]),
    'open' => $DB->count_records_select('local_helpdesk_ticket', "status $opensql", $openparams),
    'nearing' => $DB->count_records_select('local_helpdesk_ticket',
        "status $opensql AND resolutiondue >= :nfrom AND resolutiondue < :nto",
        $openparams + ['nfrom' => $now, 'nto' => $now + (sla::NEARING_HOURS * HOURSECS)]),
    'resolvedtoday' => $DB->count_records_select('local_helpdesk_ticket',
        'resolvedtime >= :dfrom', ['dfrom' => usergetmidnight($now)]),
];

$tabs = [];
foreach (['open', 'unassigned', 'mine', 'nearing', 'breached', 'resolvedtoday',
          'resolved', 'closed', 'all'] as $key) {
    $tabs[] = [
        'key'      => $key,
        'label'    => get_string('filter_' . $key, 'local_helpdesk'),
        'url'      => (new moodle_url($PAGE->url,
            ['filter' => $key, 'category' => $category, 'q' => $q]))->out(false),
        'active'   => $filter === $key,
        'count'    => $counts[$key] ?? null,
        'hascount' => isset($counts[$key]) && $counts[$key] > 0,
    ];
}

$categories = [['value' => '', 'label' => get_string('allcategories', 'local_helpdesk'),
                'selected' => $category === '']];
foreach (\local_helpdesk\form\raise_form::categories() as $key => $label) {
    $categories[] = ['value' => $key, 'label' => $label, 'selected' => $category === $key];
}

// CSV of exactly the rows the current filter/search produced. Sent before any
// output so the download is not corrupted by page HTML.
if ($export === 'csv') {
    require_once($CFG->libdir . '/dataformatlib.php');
    $columns = [
        'reference'  => get_string('col_reference', 'local_helpdesk'),
        'student'    => get_string('col_student', 'local_helpdesk'),
        'email'      => get_string('email'),
        'category'   => get_string('col_category', 'local_helpdesk'),
        'course'     => get_string('ticketcourse', 'local_helpdesk'),
        'channel'    => get_string('col_channel', 'local_helpdesk'),
        'status'     => get_string('col_status', 'local_helpdesk'),
        'assignee'   => get_string('col_assignee', 'local_helpdesk'),
        'created'    => get_string('col_created', 'local_helpdesk'),
        'firstreply' => get_string('col_firstreply', 'local_helpdesk'),
        'resolved'   => get_string('col_resolved', 'local_helpdesk'),
        'ontime'     => get_string('col_ontime', 'local_helpdesk'),
    ];
    $out = [];
    foreach ($records as $r) {
        $student = $users[$r->userid] ?? null;
        $ontime = null;
        if (!empty($r->resolvedtime)) {
            $ontime = $r->resolvedtime <= $r->resolutiondue
                ? get_string('yes') : get_string('no');
        }
        $out[] = (object) [
            'reference'  => $r->reference,
            'student'    => $student ? fullname($student) : '',
            'email'      => $student ? $student->email : '',
            'category'   => $r->category,
            'course'     => local_helpdesk_course_name($r->courseid ?? null),
            'channel'    => $r->channel,
            'status'     => get_string('status_' . $r->status, 'local_helpdesk'),
            'assignee'   => !empty($r->assigneeid) && isset($users[$r->assigneeid])
                ? fullname($users[$r->assigneeid]) : '',
            'created'    => userdate($r->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
            'firstreply' => !empty($r->firstresponsetime)
                ? userdate($r->firstresponsetime, get_string('strftimedatetimeshort', 'langconfig')) : '',
            'resolved'   => !empty($r->resolvedtime)
                ? userdate($r->resolvedtime, get_string('strftimedatetimeshort', 'langconfig')) : '',
            'ontime'     => $ontime ?? '',
        ];
    }
    \core\dataformat::download_data('helpdesk-' . $filter . '-' . userdate(time(), '%Y%m%d'),
        'csv', $columns, $out);
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_helpdesk/queue', [
    'tabs'       => $tabs,
    'categories' => $categories,
    'rows'       => $rows,
    'hasrows'    => !empty($rows),
    'counts'     => $counts,
    'actionurl'  => $PAGE->url->out(false),
    'q'          => $q,
    'filter'     => $filter,
    'exporturl'  => (new moodle_url($PAGE->url, ['filter' => $filter,
        'category' => $category, 'q' => $q, 'export' => 'csv']))->out(false),
    'sesskey'    => sesskey(),
    'truncated'  => count($records) === 200,
]);
echo $OUTPUT->footer();
