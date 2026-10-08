<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_coursetutor\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_coursetutor\gemini;
use local_coursetutor\tutor;

/**
 * Send a message to a tutor.
 *
 * @package   local_coursetutor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'tutorid' => new external_value(PARAM_INT, 'Tutor id'),
            'message' => new external_value(PARAM_RAW, 'Learner message'),
        ]);
    }

    /**
     * Run.
     *
     * @param int $tutorid
     * @param string $message
     * @return array
     */
    public static function execute(int $tutorid, string $message): array {
        global $DB, $USER;
        ['tutorid' => $tutorid, 'message' => $message] = self::validate_parameters(self::execute_parameters(),
            ['tutorid' => $tutorid, 'message' => $message]);
        $t = tutor::get_usable($tutorid);
        self::validate_context(\context_course::instance($t->courseid));

        $message = trim(\core_text::substr(clean_param($message, PARAM_NOTAGS), 0, 4000));
        if ($message === '') {
            return ['ok' => false, 'answer' => '', 'error' => get_string('emptymessage', 'local_coursetutor')];
        }
        if (!gemini::is_configured()) {
            return ['ok' => false, 'answer' => '', 'error' => get_string('notconfigured', 'local_coursetutor')];
        }

        // Last 12 turns of this learner's chat with this tutor.
        $history = array_reverse($DB->get_records('local_coursetutor_msg', ['tutorid' => $t->id, 'userid' => $USER->id],
            'id DESC', 'id, role, content', 0, 12));
        $turns = array_map(fn($r) => ['role' => $r->role, 'content' => $r->content], $history);
        $turns[] = ['role' => 'user', 'content' => $message];

        try {
            $answer = gemini::generate(tutor::system_prompt($t), $turns);
        } catch (\Throwable $e) {
            return ['ok' => false, 'answer' => '', 'error' => get_string('aiunavailable', 'local_coursetutor')];
        }

        $now = time();
        $DB->insert_record('local_coursetutor_msg', (object) ['tutorid' => $t->id, 'userid' => $USER->id,
            'role' => 'user', 'content' => $message, 'timecreated' => $now]);
        $DB->insert_record('local_coursetutor_msg', (object) ['tutorid' => $t->id, 'userid' => $USER->id,
            'role' => 'assistant', 'content' => $answer, 'timecreated' => $now]);

        return ['ok' => true, 'answer' => $answer, 'error' => ''];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'ok' => new external_value(PARAM_BOOL, 'Success'),
            'answer' => new external_value(PARAM_RAW, 'Tutor answer (plain text / light markdown)'),
            'error' => new external_value(PARAM_TEXT, 'Error message'),
        ]);
    }
}
