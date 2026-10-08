<?php

require('../../config.php');

require_login();

global $DB;

/*
|--------------------------------------------------------------------------
| Params
|--------------------------------------------------------------------------
*/

$courseid = required_param(
    'courseid',
    PARAM_INT
);

$zoommeetingid = required_param(
    'zoommeetingid',
    PARAM_TEXT
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
| Fetch session title
|--------------------------------------------------------------------------
*/

$sql = "
SELECT

    e.name AS sessiontitle

FROM
    {local_zoom_event_map} lzem

JOIN
    {event} e
    ON e.id = lzem.event_id

WHERE
    lzem.meeting_id = :meetingid

LIMIT 1
";

$session = $DB->get_record_sql(
    $sql,
    [
        'meetingid' => $zoommeetingid
    ]
);

$sessiontitle = 'Recorded Session';

if (!empty($session->sessiontitle)) {

    $sessiontitle =
        format_string(
            $session->sessiontitle
        );
}

/*
|--------------------------------------------------------------------------
| Fetch submissions
|--------------------------------------------------------------------------
*/

$sql = "
SELECT

    u.email AS useremail,

    lzrs.summary,

    lzrs.wordcount,

    lzrs.submittedat

FROM
    {local_zoom_recording_summary} lzrs

JOIN
    {user} u
    ON u.id = lzrs.userid

WHERE
    lzrs.courseid = :courseid

AND
    lzrs.zoommeetingid = :meetingid

ORDER BY
    lzrs.submittedat DESC
";

$records = $DB->get_records_sql(
    $sql,
    [
        'courseid' => $courseid,
        'meetingid' => $zoommeetingid
    ]
);

/*
|--------------------------------------------------------------------------
| File name
|--------------------------------------------------------------------------
*/

$filename =
    clean_filename(
        $sessiontitle
    ) .
    '_summary_report.xls';

/*
|--------------------------------------------------------------------------
| Headers
|--------------------------------------------------------------------------
*/

header(
    'Content-Type: application/vnd.ms-excel'
);

header(
    'Content-Disposition: attachment; filename="' .
    $filename .
    '"'
);

header(
    'Pragma: no-cache'
);

header(
    'Expires: 0'
);

/*
|--------------------------------------------------------------------------
| Excel table
|--------------------------------------------------------------------------
*/

echo '
<table border="1">

<tr>

    <th>
        Session Name
    </th>

    <th>
        User Email
    </th>

    <th>
        Word Count
    </th>

    <th>
        Date Of Submission
    </th>

    <th>
        Summary
    </th>

</tr>
';

/*
|--------------------------------------------------------------------------
| Rows
|--------------------------------------------------------------------------
*/

foreach ($records as $record) {

    echo '
    <tr>

        <td>' .

            s($sessiontitle) .

        '</td>

        <td>' .

            s($record->useremail) .

        '</td>

        <td>' .

            (int)$record->wordcount .

        '</td>

        <td>' .

            userdate(
                $record->submittedat,
                '%d %b %Y %I:%M %p'
            ) .

        '</td>

        <td>' .

            s($record->summary) .

        '</td>

    </tr>
    ';
}

echo '</table>';

exit;