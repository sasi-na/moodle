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
 * List and revoke share links.
 *
 * @package   local_fileshare
 * @copyright 2026 onlinejain.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_fileshare\manager;

require_login();

$context = context_system::instance();
$manageall = has_capability('local/fileshare:manageall', $context);

$url = new moodle_url('/local/fileshare/manage.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('managelinks', 'local_fileshare'));
$PAGE->set_heading(get_string('managelinks', 'local_fileshare'));

$revoke = optional_param('revoke', 0, PARAM_INT);
if ($revoke) {
    require_sesskey();
    manager::revoke($revoke);
    redirect($url, get_string('linkrevoked', 'local_fileshare'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Own shares unless you can manage every one of them.
$where = $manageall ? '1 = 1' : 'createdby = :userid';
$params = $manageall ? [] : ['userid' => $USER->id];
$shares = $DB->get_records_select('local_fileshare', $where, $params, 'timecreated DESC', '*', 0, 200);

echo $OUTPUT->header();

if (!$shares) {
    // Deliberately no "create" action here: a share is always scoped to one
    // attachment on one quiz attempt (share.php requires qubaid + slot + file),
    // so there is nothing to create from an empty list. Say where links come
    // from instead of leaving a dead end.
    echo $OUTPUT->notification(get_string('nolinks', 'local_fileshare'), 'info');
    echo html_writer::tag('p', get_string('nolinks_where', 'local_fileshare'),
        ['class' => 'text-muted']);
    echo $OUTPUT->footer();
    exit;
}

$labels = [
    'live' => ['badge-success', get_string('state_live', 'local_fileshare')],
    'expiring' => ['badge-warning', get_string('state_expiring', 'local_fileshare')],
    'expired' => ['badge-secondary', get_string('state_expired', 'local_fileshare')],
    'revoked' => ['badge-danger', get_string('state_revoked', 'local_fileshare')],
    'spent' => ['badge-secondary', get_string('state_spent', 'local_fileshare')],
];

$table = new html_table();
$table->head = [
    get_string('file', 'local_fileshare'),
    get_string('student', 'local_fileshare'),
    get_string('recipient', 'local_fileshare'),
    get_string('state', 'local_fileshare'),
    get_string('used', 'local_fileshare'),
    get_string('expires', 'local_fileshare'),
    get_string('lastaccess', 'local_fileshare'),
    '',
];
$table->attributes['class'] = 'generaltable';

foreach ($shares as $share) {
    $state = manager::state($share);
    [$class, $label] = $labels[$state];

    $student = $DB->get_record('user', ['id' => $share->studentid], 'id,firstname,lastname');
    $used = $share->downloads . ' / ' . ($share->maxdownloads > 0 ? $share->maxdownloads : '&infin;');

    $action = '';
    if ($state === 'live' || $state === 'expiring') {
        $action = html_writer::link(
            new moodle_url($url, ['revoke' => $share->id, 'sesskey' => sesskey()]),
            get_string('revoke', 'local_fileshare'),
            ['class' => 'text-danger']);
    }

    $table->data[] = [
        s($share->filename),
        $student ? fullname($student) : '-',
        s((string) $share->recipient),
        html_writer::tag('span', $label, ['class' => 'badge ' . $class]),
        $used,
        userdate($share->validuntil, get_string('strftimedatetimeshort', 'core_langconfig')),
        $share->lastaccess ? userdate($share->lastaccess, get_string('strftimedatetimeshort', 'core_langconfig')) : '-',
        $action,
    ];
}

echo html_writer::tag('p', get_string('managelinks_desc', 'local_fileshare'), ['class' => 'text-muted']);
echo html_writer::table($table);
echo $OUTPUT->footer();
