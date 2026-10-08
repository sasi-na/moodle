<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_coursetutor;

/**
 * Tutor helpers.
 *
 * @package   local_coursetutor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tutor {

    /**
     * Enabled tutors of a course.
     *
     * @param int $courseid
     * @return array
     */
    public static function for_course(int $courseid): array {
        global $DB;
        return $DB->get_records('local_coursetutor', ['courseid' => $courseid, 'enabled' => 1], 'name');
    }

    /**
     * Load a tutor the current user may chat with.
     *
     * @param int $tutorid
     * @return \stdClass
     */
    public static function get_usable(int $tutorid): \stdClass {
        global $DB;
        $t = $DB->get_record('local_coursetutor', ['id' => $tutorid, 'enabled' => 1], '*', MUST_EXIST);
        $context = \context_course::instance($t->courseid);
        require_capability('local/coursetutor:use', $context);
        if (!is_enrolled($context, null, '', true) && !has_capability('moodle/course:view', $context)) {
            throw new \required_capability_exception($context, 'local/coursetutor:use', 'nopermissions', '');
        }
        return $t;
    }

    /**
     * System instruction for a tutor.
     *
     * @param \stdClass $t
     * @return string
     */
    public static function system_prompt(\stdClass $t): string {
        global $USER;
        $course = get_course($t->courseid);
        $p = "You are \"{$t->name}\", an AI tutor for the course \"" . format_string($course->fullname) . "\".\n"
            . "You are talking to " . fullname($USER) . ", a learner. Be clear, kind and encouraging. Explain step by step, "
            . "give hints before full solutions for exercises, and keep answers focused. Answer in the language the learner uses.\n"
            . "Base your answers on the course material below. If a question is about this course's subject but not covered "
            . "by the material, you may explain it briefly with standard subject knowledge. Never invent course rules, deadlines or grades.\n"
            . "SCOPE - STRICT: you only help with the subject of \"" . format_string($course->fullname) . "\". If the learner asks about "
            . "another subject, another course or anything unrelated, do not answer it: say politely in one or two sentences that you can "
            . "only help with this course and that for other subjects they should open that course and ask its tutor. Never give "
            . "information about other courses or subjects, even if the learner insists.\n";
        if (trim((string) $t->instructions) !== '') {
            $p .= "\nTeacher's instructions:\n" . trim($t->instructions) . "\n";
        }
        if (trim((string) $t->knowledge) !== '') {
            $p .= "\nExtra knowledge from the teacher:\n" . trim($t->knowledge) . "\n";
        }
        if ($t->usecontent) {
            $p .= "\n--- COURSE MATERIAL ---\n" . content::for_course($t->courseid, $USER->id) . "\n--- END ---\n";
        }
        return $p;
    }
}
