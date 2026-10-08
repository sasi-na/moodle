<?php
/**
 * Manage Zoom class meetings for a course: list upcoming scheduler-created
 * meetings, schedule a new one, and edit / delete existing ones. Editing a
 * meeting updates it on Zoom and deleting it removes it from Zoom too (via the
 * calendar event observers).
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/zoomattendance/lib.php');
require_once($CFG->dirroot . '/calendar/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', 'list', PARAM_ALPHA);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('local/zoomattendance:schedulemeeting', $context);

$baseurl = new moodle_url('/local/zoomattendance/schedule.php', ['courseid' => $courseid]);

$PAGE->set_url(new moodle_url('/local/zoomattendance/schedule.php',
    ['courseid' => $courseid, 'action' => $action]));
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('schedulemeeting', 'local_zoomattendance'));
$PAGE->set_heading(format_string($course->fullname));

// Host options for the create form / dropdown.
$hostscfg = (string)get_config('local_zoomattendance', 'zoom_host_emails');
$hostlist = array_values(array_filter(array_map('trim', explode(',', $hostscfg))));
$hosts = array_combine($hostlist, $hostlist);

/**
 * Load a scheduler-managed meeting event, scoped to this course. Returns false
 * if the event isn't one of ours (prevents acting on arbitrary event ids).
 */
$get_managed = function (int $eventid) use ($DB, $courseid) {
    $sql = "SELECT e.id, e.name, e.timestart, e.timeduration, j.meeting_id
              FROM {event} e
              JOIN {local_zoom_event_join} j ON j.event_id = e.id
             WHERE e.id = :eid AND e.courseid = :cid AND j.managed = 1";
    return $DB->get_record_sql($sql, ['eid' => $eventid, 'cid' => $courseid]);
};

// ----------------------------------------------------------------- NEW -----
if ($action === 'new') {

    if (empty($hosts)) {
        echo $OUTPUT->header();
        echo $OUTPUT->notification(get_string('nohostsconfigured', 'local_zoomattendance'),
            \core\output\notification::NOTIFY_ERROR);
        echo $OUTPUT->footer();
        exit;
    }

    $mform = new \local_zoomattendance\form\schedule_form(
        new moodle_url('/local/zoomattendance/schedule.php', ['courseid' => $courseid, 'action' => 'new']),
        ['course' => $course, 'hosts' => $hosts, 'mode' => 'new']);

    if ($mform->is_cancelled()) {
        redirect($baseurl);
    } else if ($data = $mform->get_data()) {

        $host = (string)$data->host;
        if (!isset($hosts[$host])) {
            throw new moodle_exception('invalidhost', 'local_zoomattendance');
        }
        $desc = is_array($data->description) ? ($data->description['text'] ?? '') : (string)$data->description;

        try {
            \local_zoomattendance\helper\meeting_creator::create(
                $courseid, trim($data->name), (int)$data->timestart,
                (int)$data->duration, $desc, $host);
            redirect($baseurl, get_string('meetingcreated', 'local_zoomattendance'), null,
                \core\output\notification::NOTIFY_SUCCESS);
        } catch (\moodle_exception $e) {
            \core\notification::error(get_string('meetingcreatefailed', 'local_zoomattendance')
                . ' ' . $e->getMessage());
        }
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('schedulemeeting', 'local_zoomattendance'));
    $mform->display();
    echo $OUTPUT->footer();
    exit;
}

// ---------------------------------------------------------------- EDIT -----
if ($action === 'edit') {

    $eventid = required_param('eventid', PARAM_INT);
    $meeting = $get_managed($eventid);
    if (!$meeting) {
        redirect($baseurl, get_string('invalidmeeting', 'local_zoomattendance'), null,
            \core\output\notification::NOTIFY_ERROR);
    }

    $mform = new \local_zoomattendance\form\schedule_form(
        new moodle_url('/local/zoomattendance/schedule.php',
            ['courseid' => $courseid, 'action' => 'edit', 'eventid' => $eventid]),
        ['course' => $course, 'mode' => 'edit', 'eventid' => $eventid]);
    $mform->set_data([
        'name' => $meeting->name,
        'timestart' => (int)$meeting->timestart,
        'duration' => (int)$meeting->timeduration,
    ]);

    if ($mform->is_cancelled()) {
        redirect($baseurl);
    } else if ($data = $mform->get_data()) {
        // Updating the event fires the observer, which PATCHes the Zoom meeting.
        $ev = \calendar_event::load($eventid);
        $ev->update((object)[
            'name' => trim($data->name),
            'timestart' => (int)$data->timestart,
            'timeduration' => (int)$data->duration,
        ], false);
        redirect($baseurl, get_string('meetingupdated', 'local_zoomattendance'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('editmeeting', 'local_zoomattendance'));
    $mform->display();
    echo $OUTPUT->footer();
    exit;
}

// -------------------------------------------------------------- DELETE -----
if ($action === 'delete') {

    $eventid = required_param('eventid', PARAM_INT);
    $meeting = $get_managed($eventid);
    if (!$meeting) {
        redirect($baseurl, get_string('invalidmeeting', 'local_zoomattendance'), null,
            \core\output\notification::NOTIFY_ERROR);
    }

    if (optional_param('confirm', 0, PARAM_BOOL)) {
        require_sesskey();
        // Deleting the event fires the observer, which deletes the Zoom meeting.
        $ev = \calendar_event::load($eventid);
        $ev->delete();
        redirect($baseurl, get_string('meetingdeleted', 'local_zoomattendance'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    echo $OUTPUT->header();
    $confirmurl = new moodle_url('/local/zoomattendance/schedule.php', [
        'courseid' => $courseid, 'action' => 'delete', 'eventid' => $eventid,
        'confirm' => 1, 'sesskey' => sesskey(),
    ]);
    echo $OUTPUT->confirm(
        get_string('confirmdeletemeeting', 'local_zoomattendance')
            . '<br><br><strong>' . format_string($meeting->name) . '</strong>',
        $confirmurl, $baseurl);
    echo $OUTPUT->footer();
    exit;
}

// ---------------------------------------------------------------- LIST -----
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('schedulemeeting', 'local_zoomattendance'));

if (empty($hosts)) {
    echo $OUTPUT->notification(get_string('nohostsconfigured', 'local_zoomattendance'),
        \core\output\notification::NOTIFY_ERROR);
} else {
    echo $OUTPUT->single_button(
        new moodle_url('/local/zoomattendance/schedule.php', ['courseid' => $courseid, 'action' => 'new']),
        get_string('schedulenew', 'local_zoomattendance'), 'get');
}

$sql = "SELECT e.id, e.name, e.timestart, e.timeduration
          FROM {event} e
          JOIN {local_zoom_event_join} j ON j.event_id = e.id
         WHERE e.courseid = :cid AND j.managed = 1 AND e.timestart > :now
      ORDER BY e.timestart ASC";
$rows = $DB->get_records_sql($sql, ['cid' => $courseid, 'now' => time()]);

if (empty($rows)) {
    echo $OUTPUT->notification(get_string('schedulednomeetings', 'local_zoomattendance'),
        \core\output\notification::NOTIFY_INFO);
} else {
    $table = new html_table();
    $table->head = [
        get_string('eventtitle', 'local_zoomattendance'),
        get_string('date'),
        get_string('time', 'local_zoomattendance'),
        get_string('duration', 'local_zoomattendance'),
        get_string('actions', 'local_zoomattendance'),
    ];
    foreach ($rows as $r) {
        $editurl = new moodle_url('/local/zoomattendance/schedule.php',
            ['courseid' => $courseid, 'action' => 'edit', 'eventid' => $r->id]);
        $delurl = new moodle_url('/local/zoomattendance/schedule.php',
            ['courseid' => $courseid, 'action' => 'delete', 'eventid' => $r->id]);
        $actions = $OUTPUT->action_link($editurl, get_string('updatemeeting', 'local_zoomattendance'))
            . ' &nbsp;|&nbsp; '
            . $OUTPUT->action_link($delurl, get_string('deletemeeting', 'local_zoomattendance'));
        $table->data[] = [
            format_string($r->name),
            userdate($r->timestart, get_string('strftimedate', 'langconfig')),
            userdate($r->timestart, get_string('strftimetime', 'langconfig')),
            $r->timeduration > 0 ? format_time($r->timeduration) : '-',
            $actions,
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
