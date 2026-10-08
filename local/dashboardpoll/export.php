<?php
/**
 * CSV export of a poll's responses (per student) for report viewers.
 *
 * @package   local_dashboardpoll
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$pollid = required_param('pollid', PARAM_INT);

require_login();
if (!local_dashboardpoll_can_view_reports()) {
    throw new required_capability_exception(context_system::instance(),
        'local/dashboardpoll:viewreports', 'nopermissions', '');
}
require_sesskey();

$poll = $DB->get_record('local_dashboardpoll', ['id' => $pollid], '*', MUST_EXIST);

$columns = [
    'userid'   => get_string('col_userid', 'local_dashboardpoll'),
    'fullname' => get_string('col_fullname', 'local_dashboardpoll'),
    'email'    => get_string('col_email', 'local_dashboardpoll'),
    'idnumber' => get_string('col_idnumber', 'local_dashboardpoll'),
    'option'   => get_string('col_option', 'local_dashboardpoll'),
    'time'     => get_string('col_time', 'local_dashboardpoll'),
];

$sql = "SELECT r.id, r.userid, r.timecreated, u.email, u.idnumber, o.optiontext,
               u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
               u.middlename, u.alternatename
          FROM {local_dashboardpoll_responses} r
          JOIN {user} u ON u.id = r.userid
          JOIN {local_dashboardpoll_options} o ON o.id = r.optionid
         WHERE r.pollid = :pollid
      ORDER BY r.timecreated ASC";
$rs = $DB->get_recordset_sql($sql, ['pollid' => $pollid]);

$callback = function($record) {
    return [
        'userid'   => $record->userid,
        'fullname' => fullname($record),
        'email'    => $record->email,
        'idnumber' => $record->idnumber,
        'option'   => $record->optiontext,
        'time'     => userdate($record->timecreated),
    ];
};

\core\dataformat::download_data('poll-' . $pollid . '-responses', 'csv', $columns, $rs, $callback);
$rs->close();
