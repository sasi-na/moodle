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
 * Extractor for mod_quiz.
 *
 * @package   local_geniai
 * @copyright 2024 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_geniai\extractor;


use cm_info;
use dml_exception;
use local_geniai\analyzer\content_cleaner;
use stdClass;

/**
 * Class quiz_extractor
 */
class quiz_extractor extends generic_extractor {
    /** Maximum number of questions extracted in the first version. */
    const MAX_QUESTIONS = 40;

    /**
     * Whether this extractor supports the provided course module.
     *
     * @param \cm_info $cm Course module info.
     * @return bool
     */
    public function supports(cm_info $cm) {
        return $cm->modname === "quiz";
    }

    /**
     * Extract quiz content and a limited list of questions.
     *
     * @param \cm_info $cm Course module info.
     * @param \stdClass $course Course record.
     * @param int $userid User ID.
     * @return \local_geniai\analyzer\activity_content
     * @throws \dml_exception
     */
    public function extract(cm_info $cm, stdClass $course, $userid) {
        global $DB;

        $content = $this->base_content($cm, $course, $userid);
        $quiz = $DB->get_record("quiz", ["id" => $cm->instance]);

        if ($quiz) {
            $content->intro = content_cleaner::clean_html($quiz->intro);
            $content->metadata["grade"] = $quiz->grade ?? "";
            $content->metadata["sumgrades"] = $quiz->sumgrades ?? "";
            $content->questions = $this->extract_questions((int) $quiz->id);
            // LOCAL CHANGE: quizzes built from random slots ("N random questions from category X") had 0 questions.
            $this->add_random_slot_questions((int) $quiz->id, $content);
        }

        return $content;
    }

    /**
     * Extract questions from old and new Moodle question bank schemas.
     *
     * @param int $quizid Quiz ID.
     * @return array
     * @throws \dml_exception
     */
    private function extract_questions($quizid) {
        global $DB;

        try {
            $columns = $DB->get_columns("quiz_slots");
        } catch (dml_exception) {
            return [];
        }

        if (isset($columns["questionid"])) {
            return $this->extract_questions_legacy($quizid);
        }

        return $this->extract_questions_question_bank($quizid);
    }

    /**
     * Extract questions for Moodle versions where quiz_slots has questionid.
     *
     * @param int $quizid Quiz ID.
     * @return array
     * @throws \dml_exception
     */
    private function extract_questions_legacy($quizid) {
        global $DB;

        $sql = "SELECT q.id, q.name, q.qtype, q.questiontext
                  FROM {quiz_slots} qs
                  JOIN {question} q ON q.id = qs.questionid
                 WHERE qs.quizid = :quizid
              ORDER BY qs.slot";
        $records = $DB->get_records_sql($sql, ["quizid" => $quizid], 0, self::MAX_QUESTIONS);

        return $this->format_question_records($records);
    }

    /**
     * Extract questions for Moodle versions using question bank references.
     *
     * @param int $quizid Quiz ID.
     * @return array
     */
    private function extract_questions_question_bank($quizid) {
        global $DB;

        $sql = "SELECT q.id, q.name, q.qtype, q.questiontext, qs.slot
                  FROM {quiz_slots} qs
                  JOIN {question_references} qr
                    ON qr.itemid = qs.id
                   AND qr.component = :component
                   AND qr.questionarea = :questionarea
                  JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                  JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                  JOIN {question} q ON q.id = qv.questionid
                 WHERE qs.quizid = :quizid
                   AND (qr.version IS NULL OR qr.version = qv.version)
                   AND (qv.status IS NULL OR qv.status <> :draftstatus)
              ORDER BY qs.slot ASC, qv.version DESC";

        try {
            $records = $DB->get_records_sql($sql, [
                "quizid" => $quizid,
                "component" => "mod_quiz",
                "questionarea" => "slot",
                "draftstatus" => "draft",
            ], 0, self::MAX_QUESTIONS);
        } catch (dml_exception) {
            return [];
        }

        return $this->format_question_records($records);
    }

    /**
     * Convert question records to prompt-safe arrays.
     *
     * @param array $records Question records.
     * @return array
     */
    private function format_question_records(array $records) {
        $questions = [];

        foreach ($records as $record) {
            $questions[] = [
                "name" => content_cleaner::normalize_text(format_string($record->name)),
                "qtype" => isset($record->qtype) ? $record->qtype : "",
                "questiontext" => content_cleaner::clean_html($record->questiontext),
            ];
        }

        return $questions;
    }

    /**
     * LOCAL CHANGE: add a sample of the questions random slots draw from, plus slot/category metadata.
     *
     * @param int $quizid
     * @param \local_geniai\analyzer\activity_content $content
     */
    private function add_random_slot_questions($quizid, $content) {
        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists("question_set_references")) {
            return;
        }
        try {
            $refs = $DB->get_records_sql("SELECT qsr.id, qsr.filtercondition
                                            FROM {question_set_references} qsr
                                            JOIN {quiz_slots} qs ON qs.id = qsr.itemid
                                           WHERE qs.quizid = :quizid AND qsr.component = 'mod_quiz' AND qsr.questionarea = 'slot'",
                ["quizid" => $quizid]);
            $content->metadata["total_slots"] = $DB->count_records("quiz_slots", ["quizid" => $quizid]);
            $content->metadata["random_slots"] = count($refs);
            if (!$refs) {
                return;
            }
            $catslots = [];
            foreach ($refs as $ref) {
                $filter = json_decode((string) $ref->filtercondition, true);
                foreach ((array) ($filter["filter"]["category"]["values"] ?? []) as $catid) {
                    $catslots[(int) $catid] = ($catslots[(int) $catid] ?? 0) + 1;
                }
                if (isset($filter["questioncategoryid"])) {
                    $catslots[(int) $filter["questioncategoryid"]] = ($catslots[(int) $filter["questioncategoryid"]] ?? 0) + 1;
                }
            }
            $catslots = array_filter($catslots, fn($n, $id) => $id > 0, ARRAY_FILTER_USE_BOTH);
            if (!$catslots) {
                return;
            }
            $catnames = [];
            $room = max(0, self::MAX_QUESTIONS - count($content->questions));
            $perCat = max(5, (int) ceil($room / count($catslots)));
            foreach ($catslots as $catid => $nslots) {
                $cat = $DB->get_record("question_categories", ["id" => $catid], "id, name");
                $pool = $DB->count_records_sql("SELECT COUNT(DISTINCT qbe.id) FROM {question_bank_entries} qbe
                                                 WHERE qbe.questioncategoryid = ?", [$catid]);
                $catnames[] = ($cat ? format_string($cat->name) : "category $catid") . " ({$nslots} slots drawn from {$pool} questions)";
                if ($room <= 0) {
                    continue;
                }
                $records = $DB->get_records_sql("SELECT q.id, q.name, q.qtype, q.questiontext
                                                   FROM {question_bank_entries} qbe
                                                   JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                                                   JOIN {question} q ON q.id = qv.questionid
                                                  WHERE qbe.questioncategoryid = :cat AND qv.status = 'ready'
                                                    AND q.qtype <> 'random' AND q.parent = 0
                                                    AND qv.version = (SELECT MAX(v2.version) FROM {question_versions} v2
                                                                       WHERE v2.questionbankentryid = qbe.id)
                                               ORDER BY q.id", ["cat" => $catid], 0, min($perCat, $room));
                $add = $this->format_question_records($records);
                $content->questions = array_merge($content->questions, $add);
                $room -= count($add);
            }
            $content->metadata["random_question_categories"] = implode("; ", $catnames);
            $content->metadata["question_sample_note"] = "Random slots: the items listed are a sample of the question pool each attempt draws from.";
        } catch (\Throwable $e) {
            debugging("geniai quiz random slots: " . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
