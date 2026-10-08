<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_coursetutor;

/**
 * AI question generator: mixed types/difficulties in one request, saved to the course question bank.
 *
 * @package   local_coursetutor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qgen {

    /** Supported question types => label string id. */
    const TYPES = ['truefalse', 'multichoice', 'multiselect', 'shortanswer', 'matching', 'essay'];

    /** Difficulties. */
    const LEVELS = ['easy', 'medium', 'hard'];

    /** Maximum questions in one generation. */
    const MAX = 40;

    /**
     * Clean the requested rows.
     *
     * @param array $types
     * @param array $levels
     * @param array $counts
     * @return array [['type' => , 'level' => , 'count' => ], ...]
     */
    public static function clean_rows(array $types, array $levels, array $counts): array {
        $rows = [];
        $total = 0;
        foreach ($types as $i => $t) {
            $t = clean_param($t, PARAM_ALPHA);
            $l = clean_param($levels[$i] ?? '', PARAM_ALPHA);
            $c = (int) ($counts[$i] ?? 0);
            if (!in_array($t, self::TYPES, true) || !in_array($l, self::LEVELS, true) || $c < 1) {
                continue;
            }
            $c = min($c, self::MAX - $total);
            if ($c < 1) {
                break;
            }
            $rows[] = ['type' => $t, 'level' => $l, 'count' => $c];
            $total += $c;
        }
        return $rows;
    }

    /**
     * Ask Gemini for the questions.
     *
     * @param int $courseid
     * @param string $topic Teacher's topic / pasted content (may be empty).
     * @param bool $usecontent Include the course material.
     * @param array $rows From clean_rows().
     * @return array List of question arrays (validated).
     */
    public static function generate(int $courseid, string $topic, bool $usecontent, array $rows): array {
        global $USER;
        $course = get_course($courseid);
        $plan = [];
        foreach ($rows as $i => $r) {
            $plan[] = ($i + 1) . ". {$r['count']} question(s) of type \"{$r['type']}\" at difficulty \"{$r['level']}\"";
        }
        $schema = <<<TXT
Return ONLY a JSON object: {"questions": [ ... ]}. Each question object:
- "type": one of truefalse | multichoice | multiselect | shortanswer | matching | essay (exactly as requested)
- "difficulty": easy | medium | hard (exactly as requested)
- "name": short title (max 8 words)
- "text": the question text (plain text)
- "explanation": 1-2 sentences why the answer is correct
- truefalse: "answer": true or false
- multichoice: "options": 4 strings, "correct": [index of the ONE correct option, 0-based]
- multiselect: "options": 5 strings, "correct": [indexes of the 2 or 3 correct options]
- shortanswer: "answers": 1-3 accepted short answers (1-3 words each)
- matching: "pairs": 4 objects {"left": "...", "right": "..."}
- essay: "model_answer": a model answer / marking guide (3-6 sentences)
TXT;
        $system = "You are an expert examiner writing assessment questions for the course \"" . format_string($course->fullname)
            . "\". Questions must be accurate, unambiguous, and only about this course's subject. Easy = recall of basic facts; "
            . "medium = understanding/application; hard = analysis, multi-step reasoning or case-based. Do not repeat questions. "
            . "Write in English unless the topic is in another language.";
        $prompt = "Create exactly these questions, in this order:\n" . implode("\n", $plan) . "\n\n";
        if (trim($topic) !== '') {
            $prompt .= "TOPIC / CONTENT FROM THE TEACHER:\n" . trim($topic) . "\n\n";
        }
        if ($usecontent) {
            $prompt .= "COURSE MATERIAL:\n" . \core_text::substr(content::for_course($courseid, $USER->id), 0, 40000) . "\n\n";
        }
        $prompt .= $schema;
        $total = array_sum(array_column($rows, 'count'));
        $raw = gemini::generate($system, [['role' => 'user', 'content' => $prompt]], true, min(16000, 800 + $total * 450));
        $raw = trim(preg_replace('/^```(json)?|```$/m', '', $raw));
        $data = json_decode($raw, true);
        $list = $data['questions'] ?? (is_array($data) && isset($data[0]) ? $data : []);
        $out = [];
        foreach ($list as $q) {
            if ($v = self::validate($q)) {
                $out[] = $v;
            }
        }
        return $out;
    }

    /**
     * Validate / normalise one question from the AI.
     *
     * @param mixed $q
     * @return array|null
     */
    public static function validate($q): ?array {
        if (!is_array($q)) {
            return null;
        }
        $type = strtolower((string) ($q['type'] ?? ''));
        $level = strtolower((string) ($q['difficulty'] ?? 'medium'));
        $text = trim((string) ($q['text'] ?? ''));
        if (!in_array($type, self::TYPES, true) || $text === '') {
            return null;
        }
        $v = ['type' => $type, 'difficulty' => in_array($level, self::LEVELS, true) ? $level : 'medium', 'text' => $text,
            'name' => trim((string) ($q['name'] ?? '')) ?: \core_text::substr($text, 0, 60),
            'explanation' => trim((string) ($q['explanation'] ?? ''))];
        switch ($type) {
            case 'truefalse':
                $a = $q['answer'] ?? null;
                if (is_string($a)) {
                    $a = in_array(strtolower($a), ['true', 'yes', '1'], true);
                }
                if (!is_bool($a)) {
                    return null;
                }
                $v['answer'] = $a;
                break;
            case 'multichoice':
            case 'multiselect':
                $opts = array_values(array_filter(array_map('trim', array_map('strval', (array) ($q['options'] ?? [])))));
                $correct = array_values(array_unique(array_filter(array_map('intval', (array) ($q['correct'] ?? [])),
                    fn($i) => $i >= 0 && $i < count($opts))));
                if (count($opts) < 2 || !$correct || ($type === 'multichoice' && count($correct) !== 1)
                        || ($type === 'multiselect' && count($correct) >= count($opts))) {
                    return null;
                }
                $v['options'] = $opts;
                $v['correct'] = $correct;
                break;
            case 'shortanswer':
                $ans = array_values(array_filter(array_map('trim', array_map('strval', (array) ($q['answers'] ?? [])))));
                if (!$ans) {
                    return null;
                }
                $v['answers'] = array_slice($ans, 0, 5);
                break;
            case 'matching':
                $pairs = [];
                foreach ((array) ($q['pairs'] ?? []) as $p) {
                    $l = trim((string) ($p['left'] ?? ''));
                    $r = trim((string) ($p['right'] ?? ''));
                    if ($l !== '' && $r !== '') {
                        $pairs[] = ['left' => $l, 'right' => $r];
                    }
                }
                if (count($pairs) < 3) {
                    return null;
                }
                $v['pairs'] = $pairs;
                break;
            case 'essay':
                $v['model_answer'] = trim((string) ($q['model_answer'] ?? ''));
                break;
        }
        return $v;
    }

    /**
     * Moodle XML for the questions.
     *
     * @param array $questions
     * @return string
     */
    public static function to_xml(array $questions): string {
        $c = fn($t) => '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', (string) $t) . ']]>';
        $html = fn($t) => '<text>' . $c(nl2br(s($t))) . '</text>';
        $fraction = function(float $f): string {
            $allowed = [100, 90, 83.33333, 80, 75, 70, 66.66667, 60, 50, 40, 33.33333, 30, 25, 20, 16.66667, 14.28571, 12.5, 11.11111, 10, 5, 0];
            $abs = abs($f);
            $best = 0;
            foreach ($allowed as $a) {
                if (abs($a - $abs) < abs($best - $abs)) {
                    $best = $a;
                }
            }
            return ($f < 0 ? '-' : '') . $best;
        };
        $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n<quiz>\n";
        foreach ($questions as $q) {
            $label = ucfirst($q['difficulty']);
            $type = $q['type'] === 'multiselect' ? 'multichoice' : $q['type'];
            $x .= "<question type=\"{$type}\">\n<name><text>" . $c("[{$label}] " . \core_text::substr($q['name'], 0, 200)) . "</text></name>\n";
            $x .= "<questiontext format=\"html\">" . $html($q['text']) . "</questiontext>\n";
            $x .= "<generalfeedback format=\"html\">" . $html($q['explanation']) . "</generalfeedback>\n";
            $x .= "<defaultgrade>1</defaultgrade><penalty>0.3333333</penalty><hidden>0</hidden>\n";
            switch ($q['type']) {
                case 'truefalse':
                    $x .= '<answer fraction="' . ($q['answer'] ? 100 : 0) . '" format="moodle_auto_format"><text>true</text><feedback format="html"><text></text></feedback></answer>' . "\n";
                    $x .= '<answer fraction="' . ($q['answer'] ? 0 : 100) . '" format="moodle_auto_format"><text>false</text><feedback format="html"><text></text></feedback></answer>' . "\n";
                    break;
                case 'multichoice':
                case 'multiselect':
                    $multi = $q['type'] === 'multiselect';
                    $nright = count($q['correct']);
                    $nwrong = count($q['options']) - $nright;
                    $x .= '<single>' . ($multi ? 'false' : 'true') . '</single><shuffleanswers>true</shuffleanswers><answernumbering>abc</answernumbering>' . "\n";
                    foreach ($q['options'] as $i => $o) {
                        $right = in_array($i, $q['correct'], true);
                        $f = $multi ? ($right ? 100 / $nright : -100 / max(1, $nwrong)) : ($right ? 100 : 0);
                        $x .= '<answer fraction="' . $fraction($f) . '" format="html">' . $html($o) . '<feedback format="html"><text></text></feedback></answer>' . "\n";
                    }
                    break;
                case 'shortanswer':
                    $x .= "<usecase>0</usecase>\n";
                    foreach ($q['answers'] as $a) {
                        $x .= '<answer fraction="100" format="moodle_auto_format"><text>' . $c($a) . '</text><feedback format="html"><text></text></feedback></answer>' . "\n";
                    }
                    break;
                case 'matching':
                    $x .= "<shuffleanswers>true</shuffleanswers>\n";
                    foreach ($q['pairs'] as $p) {
                        $x .= '<subquestion format="html">' . $html($p['left']) . '<answer><text>' . $c($p['right']) . "</text></answer></subquestion>\n";
                    }
                    break;
                case 'essay':
                    $x .= "<responseformat>editor</responseformat><responserequired>1</responserequired><responsefieldlines>10</responsefieldlines>"
                        . "<attachments>0</attachments><attachmentsrequired>0</attachmentsrequired>\n";
                    $x .= '<graderinfo format="html">' . $html($q['model_answer']) . "</graderinfo>\n<responsetemplate format=\"html\"><text></text></responsetemplate>\n";
                    break;
            }
            $x .= "<tags><tag><text>{$label}</text></tag><tag><text>AI generated</text></tag></tags>\n</question>\n";
        }
        return $x . "</quiz>\n";
    }

    /**
     * Save questions into the course's default question bank category.
     *
     * @param int $courseid
     * @param array $questions
     * @return array [int count saved, string category name]
     */
    public static function save_to_bank(int $courseid, array $questions): array {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        require_once($CFG->dirroot . '/question/format.php');
        require_once($CFG->dirroot . '/question/format/xml/format.php');
        $course = get_course($courseid);
        $context = \context_course::instance($courseid);
        $category = question_make_default_categories([$context]);
        $file = make_request_directory() . '/ai_questions.xml';
        file_put_contents($file, self::to_xml($questions));

        $contexts = new \core_question\local\bank\question_edit_contexts($context);
        $qformat = new \qformat_xml();
        $qformat->setCategory($category);
        $qformat->setContexts($contexts->having_one_edit_tab_cap('import'));
        $qformat->setCourse($course);
        $qformat->setFilename($file);
        $qformat->setRealfilename('ai_questions.xml');
        $qformat->setMatchgrades('nearest');
        $qformat->setCatfromfile(false);
        $qformat->setContextfromfile(false);
        $qformat->setStoponerror(true);
        $qformat->set_display_progress(false);
        ob_start();
        $ok = $qformat->importpreprocess() && $qformat->importprocess() && $qformat->importpostprocess();
        $log = ob_get_clean();
        if (!$ok) {
            throw new \moodle_exception('qgenimportfailed', 'local_coursetutor', '', null, strip_tags($log));
        }
        return [count($qformat->questionids ?? $questions), format_string($category->name)];
    }
}
