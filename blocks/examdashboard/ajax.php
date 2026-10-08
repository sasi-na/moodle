<?php

require('../../config.php');
require_login();

global $DB, $USER;

$examcode = required_param('exam_code', PARAM_TEXT);

// Get records
$records = $DB->get_records('anveshana_exam_question_marks', [
    'email' => $USER->email,
    'exam_code' => $examcode
], 'question_number ASC');

if (empty($records)) {
    echo "<p>No data available</p>";
    exit;
}

//Check total_marks_scored from first row
$first = reset($records);

if (!empty($first->total_marks_scored) && strtoupper(trim($first->total_marks_scored)) === 'AB') {

    //Show Absent message only
    echo '
        <div class="absent-text">
            ABSENT
        </div>
    ';
    exit;
}

//Else show table

echo '<div style="max-height:400px; overflow-y:auto;">';

echo '<table class="generaltable" style="width:100%;">';
echo '<thead>
        <tr>
            <th>Question No</th>
            <th>Max Marks</th>
            <th>Marks Scored</th>
        </tr>
      </thead>';

echo '<tbody>';

$totalmax = 0;
$totalscored = 0;

foreach ($records as $r) {
    $totalmax += $r->allotted_marks;
    $totalscored += $r->marks_scored;

    echo "<tr>
            <td>{$r->question_number}</td>
            <td>{$r->allotted_marks}</td>
            <td>{$r->marks_scored}</td>
          </tr>";
}

// Total row
echo "<tr style='font-weight:bold; background:#f5f5f5;'>
        <td>Total</td>
        <td>{$totalmax}</td>
        <td>{$totalscored}</td>
      </tr>";

echo '</tbody></table></div>';