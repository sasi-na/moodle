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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_coursetutor\tutor;

/**
 * The current user's chat with a tutor.
 *
 * @package   local_coursetutor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class history extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(['tutorid' => new external_value(PARAM_INT, 'Tutor id')]);
    }

    /**
     * Run.
     *
     * @param int $tutorid
     * @return array
     */
    public static function execute(int $tutorid): array {
        global $DB, $USER;
        ['tutorid' => $tutorid] = self::validate_parameters(self::execute_parameters(), ['tutorid' => $tutorid]);
        $t = tutor::get_usable($tutorid);
        self::validate_context(\context_course::instance($t->courseid));
        $rows = array_reverse($DB->get_records('local_coursetutor_msg', ['tutorid' => $t->id, 'userid' => $USER->id],
            'id DESC', 'id, role, content', 0, 40));
        return array_values(array_map(fn($r) => ['role' => $r->role, 'content' => $r->content], $rows));
    }

    /**
     * Returns.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'role' => new external_value(PARAM_ALPHA, 'user or assistant'),
            'content' => new external_value(PARAM_RAW, 'Text'),
        ]));
    }
}
