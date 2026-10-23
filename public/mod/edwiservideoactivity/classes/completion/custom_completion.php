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

declare(strict_types=1);

namespace mod_edwiservideoactivity\completion;

use core_completion\activity_custom_completion;

/**
 * Custom completion class for Edwiser Video Activity
 *
 * @package   mod_edwiservideoactivity
 * @copyright 2024 Edwiser
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {

    /**
     * Get the completion state for a specific rule
     *
     * @param string $rule The completion rule
     * @return int The completion state
     * @throws \Exception
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        $completionpercent = $this->cm->customdata['customcompletionrules']['completionpercent'] ?? 0;

        // If no custom completion percentage is set, return incomplete.
        if ($completionpercent <= 0) {
            return COMPLETION_INCOMPLETE;
        }

        // Get the highest progress percentage for this user and activity.
        // Use get_field_sql with properly quoted column name using Moodle's DDL generator.
        $generator = $DB->get_manager()->generator;
        $quotedpercent = $generator->getEncQuoted('percent');
        $sql = "SELECT MAX({$quotedpercent})
                  FROM {edwiservideoactivity_progress}
                 WHERE cm_id = :cmid AND user_id = :userid";
        $progress = $DB->get_field_sql($sql, [
            'cmid' => $this->cm->id,
            'userid' => $this->userid
        ], IGNORE_MISSING);

        // If no progress record found, return incomplete.
        if ($progress === false || $progress === null) {
            return COMPLETION_INCOMPLETE;
        }

        return ($progress >= $completionpercent) ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * Fetch the list of custom completion rules that this module defines
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return ['completionpercent'];
    }

    /**
     * Returns an associative array of the descriptions of custom completion rules
     *
     * @return array
     * @throws \coding_exception
     */
    public function get_custom_rule_descriptions(): array {
        $completionpercent = $this->cm->customdata['customcompletionrules']['completionpercent'] ?? 0;
        return [
            'completionpercent' => get_string('completiondetail:completionpercent', 'mod_edwiservideoactivity', $completionpercent),
        ];
    }

    /**
     * Returns an array of all completion rules, in the order they should be displayed to users
     *
     * @return array
     */
    public function get_sort_order(): array {
        return [
            'completionview',
            'completionusegrade',
            'completionpassgrade',
            'completionpercent',
        ];
    }
}
