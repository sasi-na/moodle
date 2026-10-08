<?php

require('../../config.php');

require_login();

global $DB, $PAGE, $OUTPUT;

/*
|--------------------------------------------------------------------------
| Params
|--------------------------------------------------------------------------
*/

$courseid = required_param(
    'courseid',
    PARAM_INT
);

/*
|--------------------------------------------------------------------------
| Course + capability
|--------------------------------------------------------------------------
*/

$course = get_course($courseid);

$context = context_course::instance(
    $courseid
);

require_capability(
    'block/liverecordingstats:viewstats',
    $context
);

/*
|--------------------------------------------------------------------------
| Page setup
|--------------------------------------------------------------------------
*/

$PAGE->set_context($context);

$PAGE->set_course($course);

$PAGE->set_url(
    new moodle_url(
        '/blocks/liverecordingstats/index.php',
        [
            'courseid' => $courseid
        ]
    )
);

$PAGE->set_title(
    'Recorded Session Stats'
);

$PAGE->set_heading(
    $course->fullname
);

$PAGE->requires->css(
    '/blocks/liverecordingstats/styles.css'
);

/*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/

echo $OUTPUT->header();

/*
|--------------------------------------------------------------------------
| Fetch recorded sessions
|--------------------------------------------------------------------------
|
| Mapping:
|
| local_zoom_event_map.meeting_id
|     ↓
| local_zoom_recording_summary.zoommeetingid
|     ↓
| event.id
|--------------------------------------------------------------------------
*/

$sql = "
SELECT

    e.id AS eventid,

    e.name AS sessiontitle,

    FROM_UNIXTIME(
        e.timestart,
        '%d %b %Y'
    ) AS sessiondate,

    lzem.meeting_id AS zoommeetingid,

    COUNT(
        DISTINCT lzrs.id
    ) AS summarycount

FROM
    {local_zoom_event_map} lzem

JOIN
    {event} e
    ON e.id = lzem.event_id

LEFT JOIN
    {local_zoom_recording_summary} lzrs
    ON lzrs.zoommeetingid =
       lzem.meeting_id

    AND lzrs.courseid = :courseid

WHERE
    e.courseid = :courseid2
    AND lzem.skipped = 0

GROUP BY

    e.id,

    e.name,

    e.timestart,

    lzem.meeting_id

ORDER BY
    e.timestart DESC
";

$records = $DB->get_records_sql(
    $sql,
    [
        'courseid' => $courseid,
        'courseid2' => $courseid
    ]
);

/*
|--------------------------------------------------------------------------
| Title
|--------------------------------------------------------------------------
*/

echo html_writer::tag(
    'h3',
    'Recorded Session Stats',
    [
        'class' =>
            'videotracker-page-title'
    ]
);

/*
|--------------------------------------------------------------------------
| Table start
|--------------------------------------------------------------------------
*/

echo '
<div class="videotracker-table-wrapper">

<table class="videotracker-table">

    <thead>

        <tr>

            <th>
                Live Session Title
            </th>

            <th>
                Date
            </th>

            <th>
                Summary Submitted
            </th>

            <th>
                Download
            </th>

        </tr>

    </thead>

    <tbody>
';

/*
|--------------------------------------------------------------------------
| Empty state
|--------------------------------------------------------------------------
*/

if (empty($records)) {

    echo '
    <tr>

        <td
            colspan="4"
            class="videotracker-empty"
        >

            No recorded session stats found.

        </td>

    </tr>
    ';
}

/*
|--------------------------------------------------------------------------
| Rows
|--------------------------------------------------------------------------
*/

foreach ($records as $record) {

    $downloadurl = new moodle_url(
        '/blocks/liverecordingstats/download.php',
        [
            'courseid' => $courseid,

            'zoommeetingid' =>
                $record->zoommeetingid
        ]
    );

    echo '
    <tr>

        <td>

            <div class="videotracker-session-title">

                ' .

                format_string(
                    $record->sessiontitle
                ) .

            '

            </div>

        </td>

        <td>

            ' .

            s(
                $record->sessiondate
            ) .

        '

        </td>

        <td>

            <span class="videotracker-summary-count">

                ' .

                (int)$record->summarycount .

            '

            </span>

        </td>

        <td>

            <a
                class="
                    btn
                    btn-primary
                    videotracker-download-btn
                "
                href="' .

                    $downloadurl .

                '">

                Download Excel

            </a>

        </td>

    </tr>
    ';
}

/*
|--------------------------------------------------------------------------
| Table end
|--------------------------------------------------------------------------
*/

echo '

    </tbody>

</table>

</div>
';

/*
|--------------------------------------------------------------------------
| Footer
|--------------------------------------------------------------------------
*/

echo $OUTPUT->footer();