<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AI Question Generator: several question types and difficulties in one go, saved to the course question bank.
 *
 * @package   local_coursetutor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_coursetutor\qgen;

require(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
require_login($courseid, false);
$context = context_course::instance($courseid);
require_capability('local/coursetutor:manage', $context);
require_capability('moodle/question:add', $context);
@set_time_limit(300);

$url = new moodle_url('/local/coursetutor/qgen.php', ['courseid' => $courseid]);
$PAGE->set_url($url);
$PAGE->set_title(get_string('qgen', 'local_coursetutor'));
$PAGE->set_heading(format_string(get_course($courseid)->fullname));
$str = fn($k, $a = null) => get_string($k, 'local_coursetutor', $a);

$typeopts = [];
foreach (qgen::TYPES as $t) {
    $typeopts[$t] = $str('qtype_' . $t);
}
$levelopts = ['easy' => $str('level_easy'), 'medium' => $str('level_medium'), 'hard' => $str('level_hard')];
$sessionkey = 'local_coursetutor_qgen_' . $courseid;

// Defaults (or what the teacher sent last time).
$topic = optional_param('topic', '', PARAM_RAW);
$usecontent = optional_param('usecontent', $action === '' ? 1 : 0, PARAM_BOOL);
$types = optional_param_array('qtype', ['truefalse', 'multichoice', 'shortanswer'], PARAM_ALPHA);
$levels = optional_param_array('qlevel', ['easy', 'medium', 'hard'], PARAM_ALPHA);
$counts = optional_param_array('qcount', [3, 3, 4], PARAM_INT);
$rows = qgen::clean_rows($types, $levels, $counts);

$notice = '';
$questions = null;
if ($action === 'generate' && confirm_sesskey()) {
    if (!$rows) {
        $notice = $OUTPUT->notification($str('qgennorows'), 'error');
    } else if (!$usecontent && trim($topic) === '') {
        $notice = $OUTPUT->notification($str('qgennosource'), 'error');
    } else {
        try {
            $questions = qgen::generate($courseid, $topic, (bool) $usecontent, $rows);
            $SESSION->{$sessionkey} = $questions;
            if (!$questions) {
                $notice = $OUTPUT->notification($str('qgenempty'), 'error');
            }
        } catch (Throwable $e) {
            $notice = $OUTPUT->notification($str('aiunavailable') . ' (' . s($e->getMessage()) . ')', 'error');
        }
    }
} else if ($action === 'save' && confirm_sesskey()) {
    // Rebuild every question from the (possibly edited) fields of the preview form.
    $posted = (isset($_POST['q']) && is_array($_POST['q'])) ? clean_param_array($_POST['q'], PARAM_RAW, true) : [];
    $chosen = [];
    $edited = [];
    foreach ($posted as $f) {
        if (!is_array($f)) {
            continue;
        }
        $type = (string) ($f['type'] ?? '');
        $q = ['type' => $type, 'difficulty' => $f['difficulty'] ?? 'medium', 'name' => $f['name'] ?? '',
            'text' => $f['text'] ?? '', 'explanation' => $f['explanation'] ?? ''];
        if ($type === 'truefalse') {
            $q['answer'] = ($f['answer'] ?? '') === 'true';
        } else if ($type === 'multichoice' || $type === 'multiselect') {
            $opts = (array) ($f['options'] ?? []);
            $q['options'] = array_values($opts);
            $map = array_flip(array_keys($opts));
            $raw = $type === 'multichoice' ? [($f['correct'] ?? -1)] : (array) ($f['correctm'] ?? []);
            $q['correct'] = array_values(array_map(fn($k) => $map[$k] ?? -1, $raw));
        } else if ($type === 'shortanswer') {
            $q['answers'] = preg_split('#\s*/\s*#', (string) ($f['answers'] ?? ''));
        } else if ($type === 'matching') {
            $q['pairs'] = [];
            foreach ((array) ($f['left'] ?? []) as $j => $l) {
                $q['pairs'][] = ['left' => $l, 'right' => $f['right'][$j] ?? ''];
            }
        } else if ($type === 'essay') {
            $q['model_answer'] = $f['model_answer'] ?? '';
        }
        $v = qgen::validate($q);
        $edited[] = $v ?: $q;
        if (!empty($f['keep'])) {
            if (!$v) {
                $notice .= $OUTPUT->notification($str('qgeninvalid', s(\core_text::substr($q['text'], 0, 80))), 'error');
                continue;
            }
            $chosen[] = $v;
        }
    }
    $SESSION->{$sessionkey} = array_values(array_filter($edited, fn($x) => !empty($x['text'])));
    $questions = $SESSION->{$sessionkey};
    if ($notice === '' && !$chosen) {
        $notice = $OUTPUT->notification($str('qgennothingchosen'), 'error');
    } else if ($notice === '') {
        try {
            [$n, $catname] = qgen::save_to_bank($courseid, $chosen);
            unset($SESSION->{$sessionkey});
            $bank = new moodle_url('/question/edit.php', ['courseid' => $courseid]);
            redirect($url, $str('qgensaved', (object) ['n' => $n, 'cat' => $catname]) . ' ' . html_writer::link($bank, $str('qgenopenbank')),
                null, \core\output\notification::NOTIFY_SUCCESS);
        } catch (Throwable $e) {
            $notice = $OUTPUT->notification($e->getMessage(), 'error');
        }
    }
} else if ($action === 'delete' && confirm_sesskey()) {
    // Delete saved AI questions (one question or a whole generated set). Questions used in a quiz are hidden instead, like Moodle does.
    $delids = array_filter(array_map('intval', explode(',', optional_param('ids', '', PARAM_SEQUENCE))));
    $done = 0;
    if ($delids) {
        require_once($CFG->libdir . '/questionlib.php');
        [$insql, $inparams] = $DB->get_in_or_equal($delids, SQL_PARAMS_NAMED);
        $inparams['ctx'] = $context->id;
        $entries = $DB->get_fieldset_sql("SELECT DISTINCT qv.questionbankentryid
                                            FROM {question_versions} qv
                                            JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                                            JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                                           WHERE qv.questionid $insql AND qc.contextid = :ctx", $inparams);
        foreach ($entries as $entryid) {
            $versions = $DB->get_records('question_versions', ['questionbankentryid' => $entryid], 'version DESC', 'id, questionid');
            foreach ($versions as $v) {
                if (questions_in_use([$v->questionid])) {
                    $DB->set_field('question_versions', 'status', \core_question\local\bank\question_version_status::QUESTION_STATUS_HIDDEN,
                        ['id' => $v->id]);
                } else {
                    question_delete_question($v->questionid);
                }
            }
            $done++;
        }
    }
    redirect($url, $str('qgendeleted', $done), null, \core\output\notification::NOTIFY_SUCCESS);
} else if ($action === 'discard' && confirm_sesskey()) {
    unset($SESSION->{$sessionkey});
} else if (!empty($SESSION->{$sessionkey})) {
    $questions = $SESSION->{$sessionkey}; // Unsaved questions from earlier stay available.
}

echo $OUTPUT->header();
echo $OUTPUT->heading($str('qgen'));
echo html_writer::tag('p', $str('qgenintro'));
echo $notice;

// ----- Request form -----
$rowhtml = function($t, $l, $c) use ($typeopts, $levelopts) {
    return '<tr class="qgen-row"><td>' . html_writer::select($typeopts, 'qtype[]', $t, false, ['class' => 'custom-select'])
        . '</td><td>' . html_writer::select($levelopts, 'qlevel[]', $l, false, ['class' => 'custom-select'])
        . '</td><td><input type="number" name="qcount[]" min="1" max="20" value="' . (int) $c . '" class="form-control" style="width:90px"></td>'
        . '<td><button type="button" class="btn btn-link text-danger qgen-del" title="Remove">&times;</button></td></tr>';
};
echo '<form method="post" action="' . $url->out() . '" class="card p-3 mb-4" id="qgen-form">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '"><input type="hidden" name="action" value="generate">';
echo '<label class="font-weight-bold" for="qgen-topic">' . $str('qgentopic') . '</label>';
echo '<textarea id="qgen-topic" name="topic" rows="4" class="form-control mb-2" placeholder="' . s($str('qgentopic_ph')) . '">' . s($topic) . '</textarea>';
echo '<div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="usecontent" value="1" id="qgen-uc"' . ($usecontent ? ' checked' : '')
    . '><label class="form-check-label" for="qgen-uc">' . $str('qgenusecontent') . '</label></div>';
echo '<table class="table table-sm" style="max-width:720px"><thead><tr><th>' . $str('qgentype') . '</th><th>' . $str('qgenlevel') . '</th><th>'
    . $str('qgencount') . '</th><th></th></tr></thead><tbody id="qgen-rows">';
foreach ($rows ?: [['type' => 'truefalse', 'level' => 'easy', 'count' => 3]] as $r) {
    echo $rowhtml($r['type'], $r['level'], $r['count']);
}
echo '</tbody></table>';
echo '<div class="mb-3"><button type="button" class="btn btn-secondary btn-sm" id="qgen-add">+ ' . $str('qgenaddrow')
    . '</button> <span class="ml-3 text-muted">' . $str('qgentotal') . ': <strong id="qgen-total"></strong> / ' . qgen::MAX . '</span></div>';
echo '<button type="submit" class="btn btn-primary" id="qgen-go">' . $str('qgengenerate') . '</button>';
echo ' <span class="text-muted ml-2" id="qgen-wait" style="display:none">' . $str('qgenwait') . '</span>';
echo '</form>';
$template = json_encode($rowhtml('multichoice', 'medium', 1));
echo "<script>
(function(){
  var body=document.getElementById('qgen-rows'), total=document.getElementById('qgen-total');
  function sum(){ var s=0; body.querySelectorAll('input[name=\"qcount[]\"]').forEach(function(i){ s+=parseInt(i.value||0,10); }); total.textContent=s; }
  document.getElementById('qgen-add').addEventListener('click', function(){ body.insertAdjacentHTML('beforeend', $template); sum(); });
  body.addEventListener('click', function(e){ if(e.target.classList.contains('qgen-del') && body.rows.length>1){ e.target.closest('tr').remove(); sum(); } });
  body.addEventListener('input', sum); sum();
  document.getElementById('qgen-form').addEventListener('submit', function(){ document.getElementById('qgen-go').disabled=true; document.getElementById('qgen-wait').style.display='inline'; });
})();
</script>";

// ----- Editable preview -----
if ($questions) {
    $summary = [];
    foreach ($questions as $q) {
        $k = ($levelopts[$q['difficulty']] ?? $q['difficulty']) . ' · ' . ($typeopts[$q['type']] ?? $q['type']);
        $summary[$k] = ($summary[$k] ?? 0) + 1;
    }
    echo $OUTPUT->heading($str('qgenpreview', count($questions)), 3);
    echo html_writer::tag('p', implode(' &nbsp;|&nbsp; ', array_map(fn($k, $v) => "<strong>$v</strong> × " . s($k), array_keys($summary), $summary)));
    echo html_writer::tag('p', $str('qgenedithelp'), ['class' => 'text-muted']);
    echo '<form method="post" action="' . $url->out() . '"><input type="hidden" name="sesskey" value="' . sesskey() . '"><input type="hidden" name="action" value="save">';
    $badge = ['easy' => 'success', 'medium' => 'warning', 'hard' => 'danger'];
    $in = fn($name, $val, $extra = '') => '<input type="text" class="form-control form-control-sm" name="' . $name . '" value="' . s($val) . '" ' . $extra . '>';
    foreach ($questions as $i => $q) {
        $p = "q[$i]";
        $type = $q['type'];
        echo '<div class="card mb-3"><div class="card-body">';
        echo '<input type="hidden" name="' . $p . '[type]" value="' . s($type) . '"><input type="hidden" name="' . $p . '[name]" value="' . s($q['name'] ?? '') . '">';
        echo '<div class="d-flex flex-wrap align-items-center mb-2" style="gap:8px"><strong>Q' . ($i + 1) . '</strong>'
            . '<span class="badge badge-info">' . s($typeopts[$type] ?? $type) . '</span>'
            . html_writer::select($levelopts, $p . '[difficulty]', $q['difficulty'], false, ['class' => 'custom-select custom-select-sm', 'style' => 'width:auto'])
            . '<div class="form-check ml-auto"><input class="form-check-input" type="checkbox" name="' . $p . '[keep]" value="1" checked id="k' . $i
            . '"><label class="form-check-label" for="k' . $i . '">' . $str('qgenkeep') . '</label></div></div>';
        echo '<label class="small text-muted mb-0">' . $str('qgenqtext') . '</label><textarea class="form-control mb-2" rows="2" name="' . $p . '[text]">' . s($q['text']) . '</textarea>';
        switch ($type) {
            case 'truefalse':
                echo '<label class="small text-muted mb-0">' . $str('qgencorrect') . '</label>'
                    . html_writer::select(['true' => get_string('true', 'qtype_truefalse'), 'false' => get_string('false', 'qtype_truefalse')],
                        $p . '[answer]', !empty($q['answer']) ? 'true' : 'false', false, ['class' => 'custom-select custom-select-sm mb-2', 'style' => 'width:auto']);
                break;
            case 'multichoice':
            case 'multiselect':
                echo '<label class="small text-muted mb-0">' . $str($type === 'multichoice' ? 'qgenoptions1' : 'qgenoptionsm') . '</label>';
                foreach (($q['options'] ?? []) as $j => $o) {
                    $isright = in_array($j, $q['correct'] ?? [], true);
                    $mark = $type === 'multichoice'
                        ? '<input type="radio" name="' . $p . '[correct]" value="' . $j . '"' . ($isright ? ' checked' : '') . '>'
                        : '<input type="checkbox" name="' . $p . '[correctm][]" value="' . $j . '"' . ($isright ? ' checked' : '') . '>';
                    echo '<div class="input-group input-group-sm mb-1"><div class="input-group-prepend"><span class="input-group-text">' . $mark
                        . '&nbsp;' . chr(97 + $j) . '</span></div>' . $in($p . "[options][$j]", $o) . '</div>';
                }
                break;
            case 'shortanswer':
                echo '<label class="small text-muted mb-0">' . $str('qgenanswers') . '</label>' . $in($p . '[answers]', implode(' / ', $q['answers'] ?? []), 'style="margin-bottom:8px"');
                break;
            case 'matching':
                echo '<label class="small text-muted mb-0">' . $str('qgenpairs') . '</label>';
                foreach (($q['pairs'] ?? []) as $j => $pr) {
                    echo '<div class="d-flex mb-1" style="gap:6px">' . $in($p . "[left][$j]", $pr['left']) . '<span class="pt-1">→</span>' . $in($p . "[right][$j]", $pr['right']) . '</div>';
                }
                break;
            case 'essay':
                echo '<label class="small text-muted mb-0">' . $str('qgenmodel') . '</label><textarea class="form-control mb-2" rows="3" name="' . $p . '[model_answer]">'
                    . s($q['model_answer'] ?? '') . '</textarea>';
                break;
        }
        echo '<label class="small text-muted mb-0 mt-1">' . $str('qgenexplain') . '</label><textarea class="form-control" rows="1" name="' . $p . '[explanation]">'
            . s($q['explanation'] ?? '') . '</textarea>';
        echo '</div></div>';
    }
    echo '<button type="submit" class="btn btn-success">' . $str('qgensave') . '</button> ';
    echo '<button type="submit" class="btn btn-outline-secondary" name="action" value="discard">' . $str('qgendiscard') . '</button>';
    echo '</form>';
}
// ----- History: questions saved earlier from this page (tagged "AI generated"), newest first -----
$histsql = "SELECT q.id, q.name, q.qtype, q.questiontext, q.generalfeedback, q.timecreated, qbe.id AS entryid
              FROM {question} q
              JOIN {question_versions} qv ON qv.questionid = q.id
              JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
              JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
             WHERE qc.contextid = :ctx AND qv.status <> 'hidden'
               AND qv.version = (SELECT MAX(v2.version) FROM {question_versions} v2 WHERE v2.questionbankentryid = qbe.id)
               AND EXISTS (SELECT 1 FROM {question_versions} v3
                             JOIN {tag_instance} ti ON ti.itemid = v3.questionid AND ti.itemtype = 'question' AND ti.component = 'core_question'
                             JOIN {tag} t ON t.id = ti.tagid
                            WHERE v3.questionbankentryid = qbe.id AND t.name = 'ai generated')
          ORDER BY qbe.id DESC";
$hist = $DB->get_records_sql($histsql, ['ctx' => $context->id], 0, 300);
echo '<div class="card p-3 mt-4 mb-4" id="qgen-history">';
echo $OUTPUT->heading($str('qgenhistory', count($hist)), 3);
if (!$hist) {
    echo html_writer::tag('p', $str('qgenhistoryempty'), ['class' => 'text-muted mb-0']);
} else {
    echo html_writer::tag('p', $str('qgenhistoryhelp'), ['class' => 'text-muted']);
    $ids = array_keys($hist);
    [$insql, $inparams] = $DB->get_in_or_equal($ids);
    $answers = [];
    foreach ($DB->get_records_select('question_answers', "question $insql", $inparams, 'id') as $a) {
        $answers[$a->question][] = $a;
    }
    $pairs = [];
    foreach ($DB->get_records_select('qtype_match_subquestions', "questionid $insql", $inparams, 'id') as $m) {
        $pairs[$m->questionid][] = $m;
    }
    $essay = $DB->get_records_select_menu('qtype_essay_options', "questionid $insql", $inparams, '', 'questionid, graderinfo');
    $levelbadge = ['easy' => 'success', 'medium' => 'warning', 'hard' => 'danger'];
    $qtypenames = ['truefalse' => $typeopts['truefalse'], 'shortanswer' => $typeopts['shortanswer'], 'match' => $typeopts['matching'],
        'essay' => $typeopts['essay']];
    // Group by save batch: questions saved in the same minute belong together.
    $batches = [];
    foreach ($hist as $h) {
        $batches[userdate($h->timecreated, get_string('strftimedatetime', 'langconfig'))][] = $h;
    }
    $first = true;
    $bi = count($batches) + 1; // Oldest set is 1, newest has the highest number.
    foreach ($batches as $when => $items) {
        $bi--;
        $items = array_reverse($items); // Same order as generated (Q1 first).
        // Mix of this section, e.g. "3 × Easy · True / False".
        $mix = [];
        foreach ($items as $h) {
            $lv = preg_match('/^\[(Easy|Medium|Hard)\]/i', $h->name, $mm) ? $levelopts[strtolower($mm[1])] : '';
            $tn = $h->qtype === 'multichoice'
                ? (count(array_filter($answers[$h->id] ?? [], fn($a) => $a->fraction > 0)) > 1 ? $typeopts['multiselect'] : $typeopts['multichoice'])
                : ($qtypenames[$h->qtype] ?? $h->qtype);
            $key = trim($lv . ' · ' . $tn, ' ·');
            $mix[$key] = ($mix[$key] ?? 0) + 1;
        }
        $mixtxt = implode(' &nbsp;|&nbsp; ', array_map(fn($k, $v) => '<strong>' . $v . '</strong> × ' . s($k), array_keys($mix), $mix));
        echo '<details class="mb-2 border rounded"' . ($first ? ' open' : '') . '><summary class="p-2" style="cursor:pointer"><strong>'
            . $str('qgensection', $bi) . ' — ' . s($when) . '</strong> &nbsp;<span class="text-muted">' . $str('qgenhistorycount', count($items)) . '</span></summary>';
        echo '<div class="px-2 pb-2"><div class="d-flex flex-wrap align-items-center mb-2" style="gap:8px"><span class="small">' . $mixtxt . '</span>'
            . '<button type="button" class="btn btn-sm btn-primary ml-auto qgen-pvall" data-batch="' . $bi . '">' . $str('qgenshowall') . '</button></div>';
        $setids = implode(',', array_map(fn($h) => $h->id, $items));
        echo '<div class="d-flex flex-wrap align-items-center mb-2" style="gap:6px">'
            . '<form method="get" action="' . (new moodle_url('/local/coursetutor/qgendownload.php'))->out() . '" class="form-inline" style="gap:6px">'
            . '<input type="hidden" name="courseid" value="' . $courseid . '"><input type="hidden" name="ids" value="' . $setids . '">'
            . '<input type="hidden" name="set" value="' . $bi . '">'
            . html_writer::select(['doc' => $str('qgenfmt_doc'), 'pdf' => $str('qgenfmt_pdf'), 'xml' => $str('qgenfmt_xml')], 'format', 'doc', false,
                ['class' => 'custom-select custom-select-sm'])
            . html_writer::select([1 => $str('qgenwithkey'), 0 => $str('qgenpaperonly')], 'key', 1, false, ['class' => 'custom-select custom-select-sm'])
            . '<button type="submit" class="btn btn-sm btn-success">&#8681; ' . $str('qgendownload') . '</button></form>'
            . '<form method="post" action="' . $url->out() . '" class="ml-auto" onsubmit="return confirm(' . s(json_encode($str('qgendeletesetconfirm', count($items)))) . ')">'
            . '<input type="hidden" name="sesskey" value="' . sesskey() . '"><input type="hidden" name="action" value="delete">'
            . '<input type="hidden" name="ids" value="' . $setids . '">'
            . '<button type="submit" class="btn btn-sm btn-outline-danger">&#128465; ' . $str('qgendeleteset') . '</button></form></div>';
        echo '<table class="table table-sm mb-0"><tbody>';
        $first = false;
        foreach ($items as $n => $h) {
            $level = '';
            $name = $h->name;
            if (preg_match('/^\[(Easy|Medium|Hard)\]\s*/i', $name, $mm)) {
                $level = strtolower($mm[1]);
                $name = substr($name, strlen($mm[0]));
            }
            $tname = $h->qtype === 'multichoice'
                ? (count(array_filter($answers[$h->id] ?? [], fn($a) => $a->fraction > 0)) > 1 ? $typeopts['multiselect'] : $typeopts['multichoice'])
                : ($qtypenames[$h->qtype] ?? $h->qtype);
            $previewurl = new moodle_url('/question/bank/previewquestion/preview.php', ['id' => $h->id, 'courseid' => $courseid]);
            $editurl = new moodle_url('/question/bank/editquestion/question.php',
                ['id' => $h->id, 'courseid' => $courseid, 'returnurl' => $url->out_as_local_url(false)]);
            echo '<tr><td style="width:40px">' . ($n + 1) . '</td><td>' . s(shorten_text(strip_tags($h->questiontext), 120)) . '</td>'
                . '<td style="white-space:nowrap"><span class="badge badge-info">' . s($tname) . '</span> '
                . ($level ? '<span class="badge badge-' . $levelbadge[$level] . '">' . $levelopts[$level] . '</span>' : '') . '</td>'
                . '<td style="white-space:nowrap;text-align:right">'
                . '<button type="button" class="btn btn-sm btn-outline-primary qgen-pv qgen-pvb' . $bi . '" data-target="pv' . $h->id . '">' . $str('qgenshow') . '</button> '
                . html_writer::link($previewurl, $str('qgentry'), ['class' => 'btn btn-sm btn-outline-secondary', 'target' => '_blank']) . ' '
                . html_writer::link($editurl, get_string('edit'), ['class' => 'btn btn-sm btn-outline-secondary']) . ' '
                . '<form method="post" action="' . $url->out() . '" style="display:inline" onsubmit="return confirm(' . s(json_encode($str('qgendeleteconfirm'))) . ')">'
                . '<input type="hidden" name="sesskey" value="' . sesskey() . '"><input type="hidden" name="action" value="delete">'
                . '<input type="hidden" name="ids" value="' . $h->id . '">'
                . '<button type="submit" class="btn btn-sm btn-outline-danger" title="' . s($str('qgendelete')) . '">&#128465;</button></form></td></tr>';
            // Inline preview (hidden until "Preview" is clicked).
            $body = '<div class="mb-2">' . format_text($h->questiontext, FORMAT_HTML, ['context' => $context]) . '</div>';
            if ($h->qtype === 'match') {
                $body .= '<ul class="mb-2">';
                foreach ($pairs[$h->id] ?? [] as $m) {
                    $body .= '<li>' . s(strip_tags($m->questiontext)) . ' → <strong>' . s($m->answertext) . '</strong></li>';
                }
                $body .= '</ul>';
            } else if ($h->qtype === 'essay') {
                if (!empty($essay[$h->id])) {
                    $body .= '<div class="mb-2"><em>' . $str('qgenmodel') . ':</em> ' . format_text($essay[$h->id], FORMAT_HTML, ['context' => $context]) . '</div>';
                }
            } else {
                $body .= '<ul class="mb-2" style="list-style:none;padding-left:0">';
                foreach ($answers[$h->id] ?? [] as $k => $a) {
                    $ok = $a->fraction > 0;
                    $label = $h->qtype === 'multichoice' ? chr(97 + $k) . '. ' : '';
                    $body .= '<li class="' . ($ok ? 'text-success font-weight-bold' : '') . '">' . ($ok ? '✔ ' : '○ ') . $label
                        . s(strip_tags($a->answer)) . '</li>';
                }
                $body .= '</ul>';
            }
            if (trim(strip_tags($h->generalfeedback)) !== '') {
                $body .= '<div class="text-muted small"><em>' . $str('qgenexplain') . ':</em> ' . s(strip_tags($h->generalfeedback)) . '</div>';
            }
            echo '<tr id="pv' . $h->id . '" class="qgen-pvrow-' . $bi . '" style="display:none"><td></td><td colspan="3" class="bg-light">' . $body . '</td></tr>';
        }
        echo '</tbody></table></div></details>';
    }
    $hidetxt = json_encode($str('qgenhide'));
    $showtxt = json_encode($str('qgenshow'));
    $showall = json_encode($str('qgenshowall'));
    $hideall = json_encode($str('qgenhideall'));
    echo "<script>
document.getElementById('qgen-history').addEventListener('click', function(e){
  var all = e.target.closest('.qgen-pvall');
  if (all) {
    var n = all.dataset.batch, open = all.textContent === $showall;
    document.querySelectorAll('.qgen-pvrow-' + n).forEach(function(r){ r.style.display = open ? '' : 'none'; });
    document.querySelectorAll('.qgen-pvb' + n).forEach(function(b){ b.textContent = open ? $hidetxt : $showtxt; });
    all.textContent = open ? $hideall : $showall;
    return;
  }
  var b = e.target.closest('.qgen-pv'); if (!b) { return; }
  var r = document.getElementById(b.dataset.target), open = r.style.display === 'none';
  r.style.display = open ? '' : 'none'; b.textContent = open ? $hidetxt : $showtxt;
});
</script>";
}
echo '</div>';
echo $OUTPUT->footer();
