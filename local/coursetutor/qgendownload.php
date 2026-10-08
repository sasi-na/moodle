<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Download one generated set of AI questions as Word, PDF or Moodle XML.
 *
 * @package   local_coursetutor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/editlib.php');

$courseid = required_param('courseid', PARAM_INT);
$ids = array_filter(array_map('intval', explode(',', required_param('ids', PARAM_SEQUENCE))));
$setno = optional_param('set', 1, PARAM_INT);
$format = optional_param('format', 'doc', PARAM_ALPHA);
$withkey = optional_param('key', 1, PARAM_BOOL);
require_login($courseid, false);
$context = context_course::instance($courseid);
require_capability('local/coursetutor:manage', $context);
require_capability('moodle/question:add', $context);
$course = get_course($courseid);
$str = fn($k, $a = null) => get_string($k, 'local_coursetutor', $a);
if (!$ids) {
    throw new moodle_exception('qgennothingchosen', 'local_coursetutor');
}

// Only questions of this course's bank.
[$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
$params['ctx'] = $context->id;
$questions = $DB->get_records_sql("SELECT q.id, q.name, q.qtype, q.questiontext, q.generalfeedback, q.timecreated, qbe.questioncategoryid
                                     FROM {question} q
                                     JOIN {question_versions} qv ON qv.questionid = q.id
                                     JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                                     JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                                    WHERE q.id $insql AND qc.contextid = :ctx
                                 ORDER BY q.id", $params);
if (!$questions) {
    throw new moodle_exception('qgennothingchosen', 'local_coursetutor');
}
$when = userdate(min(array_column($questions, 'timecreated')), get_string('strftimedatetime', 'langconfig'));
$filename = clean_filename(($course->shortname ?: 'course') . ' - ' . $str('qgensection', $setno) . ' - '
    . userdate(min(array_column($questions, 'timecreated')), '%Y-%m-%d %H%M'));

// ----- Moodle XML: can be imported into any Moodle question bank. -----
if ($format === 'xml') {
    require_once($CFG->dirroot . '/question/format/xml/format.php');
    $data = [];
    foreach (array_unique(array_column($questions, 'questioncategoryid')) as $catid) {
        $cat = $DB->get_record('question_categories', ['id' => $catid], '*', MUST_EXIST);
        foreach (get_questions_category($cat, true, false, true, true) as $q) {
            if (isset($questions[$q->id])) {
                $data[$q->id] = $q;
            }
        }
    }
    ksort($data);
    $qformat = new qformat_xml();
    $qformat->setCourse($course);
    $qformat->setQuestions(array_values($data));
    $qformat->setCattofile(false);
    $qformat->setContexttofile(false);
    $xml = $qformat->exportprocess(false);
    send_file($xml, $filename . '.xml', 0, 0, true, true, 'text/xml');
}

// ----- Word / PDF: a question paper, optionally followed by an answer key. -----
[$insql, $inparams] = $DB->get_in_or_equal(array_keys($questions));
$answers = [];
foreach ($DB->get_records_select('question_answers', "question $insql", $inparams, 'id') as $a) {
    $answers[$a->question][] = $a;
}
$pairs = [];
foreach ($DB->get_records_select('qtype_match_subquestions', "questionid $insql", $inparams, 'id') as $m) {
    if (trim(strip_tags($m->questiontext)) !== '') {
        $pairs[$m->questionid][] = $m;
    }
}
$essay = $DB->get_records_select_menu('qtype_essay_options', "questionid $insql", $inparams, '', 'questionid, graderinfo');

$levelnames = ['easy' => $str('level_easy'), 'medium' => $str('level_medium'), 'hard' => $str('level_hard')];
$typename = function($q) use ($answers, $str) {
    if ($q->qtype === 'multichoice') {
        $right = count(array_filter($answers[$q->id] ?? [], fn($a) => $a->fraction > 0));
        return $str($right > 1 ? 'qtype_multiselect' : 'qtype_multichoice');
    }
    return $str('qtype_' . ($q->qtype === 'match' ? 'matching' : $q->qtype));
};
$plain = fn($html) => s(trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')));
$pagebreak = $format === 'pdf' ? '<br pagebreak="true"/>' : '<br clear="all" style="page-break-before:always">';

$mix = [];
$paper = '';
$key = '';
$n = 0;
foreach ($questions as $q) {
    $n++;
    $level = '';
    if (preg_match('/^\[(Easy|Medium|Hard)\]/i', $q->name, $mm)) {
        $level = $levelnames[strtolower($mm[1])];
    }
    $tn = $typename($q);
    $k = trim($level . ' · ' . $tn, ' ·');
    $mix[$k] = ($mix[$k] ?? 0) + 1;

    $paper .= '<p style="margin-top:14px"><b>Q' . $n . '.</b> <i style="color:#666">(' . s($k) . ')</i></p>';
    $paper .= '<p>' . $plain($q->questiontext) . '</p>';
    $ans = $answers[$q->id] ?? [];
    $keytxt = '';
    switch ($q->qtype) {
        case 'truefalse':
        case 'multichoice':
            $paper .= '<p style="margin-left:20px">';
            $right = [];
            foreach (array_values($ans) as $j => $a) {
                $letter = chr(97 + $j);
                $paper .= $letter . ') ' . $plain($a->answer) . '<br>';
                if ($a->fraction > 0) {
                    $right[] = $q->qtype === 'truefalse' ? $plain($a->answer) : $letter . ') ' . $plain($a->answer);
                }
            }
            $paper .= '</p>';
            $keytxt = implode('; ', $right);
            break;
        case 'shortanswer':
            $paper .= '<p style="margin-left:20px">' . get_string('answer', 'question') . ': ______________________________</p>';
            $keytxt = implode(' / ', array_map(fn($a) => $plain($a->answer), array_filter($ans, fn($a) => $a->fraction > 0)));
            break;
        case 'match':
            $left = $pairs[$q->id] ?? [];
            $rights = array_values(array_unique(array_map(fn($m) => $m->answertext, $left)));
            $shuffled = $rights;
            mt_srand($q->id);
            shuffle($shuffled);
            if ($shuffled === $rights && count($rights) > 1) {
                $shuffled[] = array_shift($shuffled); // Never print the answers in matching order.
            }
            $paper .= '<table cellpadding="3" style="margin-left:20px"><tr><td valign="top">';
            foreach (array_values($left) as $j => $m) {
                $paper .= ($j + 1) . '. ' . $plain($m->questiontext) . ' &nbsp; ____<br>';
            }
            $paper .= '</td><td valign="top">';
            foreach ($shuffled as $j => $r) {
                $paper .= chr(97 + $j) . ') ' . s($r) . '<br>';
            }
            $paper .= '</td></tr></table>';
            $keyparts = [];
            foreach (array_values($left) as $j => $m) {
                $keyparts[] = ($j + 1) . ' → ' . chr(97 + array_search($m->answertext, $shuffled)) . ') ' . s($m->answertext);
            }
            $keytxt = implode('; ', $keyparts);
            break;
        case 'essay':
            $paper .= '<p style="margin-left:20px">' . str_repeat('______________________________________________________<br>', 4) . '</p>';
            $keytxt = $plain($essay[$q->id] ?? '');
            break;
    }
    $key .= '<p><b>Q' . $n . '.</b> ' . ($keytxt !== '' ? $keytxt : '-');
    if (trim(strip_tags($q->generalfeedback)) !== '') {
        $key .= '<br><i style="color:#555">' . $plain($q->generalfeedback) . '</i>';
    }
    $key .= '</p>';
}

$title = format_string($course->fullname);
$html = '<h1 style="font-size:18pt">' . $title . '</h1>'
    . '<h2 style="font-size:14pt">' . $str('qgensection', $setno) . ' — ' . s($when) . '</h2>'
    . '<p>' . implode(' &nbsp;|&nbsp; ', array_map(fn($k, $v) => '<b>' . $v . '</b> × ' . s($k), array_keys($mix), $mix))
    . ' &nbsp; (' . $str('qgenhistorycount', $n) . ')</p><hr>' . $paper;
if ($withkey) {
    $html .= $pagebreak . '<h2 style="font-size:14pt">' . $str('qgenanswerkey') . '</h2>' . $key;
}

if ($format === 'pdf') {
    require_once($CFG->libdir . '/pdflib.php');
    $pdf = new pdf();
    $pdf->SetTitle($title . ' - ' . $str('qgensection', $setno));
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->SetFont('freesans', '', 11);
    $pdf->AddPage();
    $pdf->writeHTML($html);
    $pdf->Output($filename . '.pdf', 'D');
    die;
}

// Word: an HTML document Word opens directly.
$doc = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word">'
    . '<head><meta charset="utf-8"><title>' . s($title) . '</title>'
    . '<style>body{font-family:Calibri,Arial,sans-serif;font-size:11pt} p{margin:4px 0}</style></head><body>' . $html . '</body></html>';
send_file("\xEF\xBB\xBF" . $doc, $filename . '.doc', 0, 0, true, true, 'application/msword');
