<?php
// This file is part of VPL for Moodle - http://vpl.dis.ulpgc.es/
//
// VPL for Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// VPL for Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with VPL for Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Activity custom completion for mod_vpl.
 *
 * @package mod_vpl
 * @copyright 2026 Jain Online
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_vpl\completion;

use core_completion\activity_custom_completion;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->dirroot . '/mod/vpl/vpl.class.php');

/**
 * Class for defining mod_vpl's custom completion rules and fetching completion statuses.
 */
class custom_completion extends activity_custom_completion {

    /**
     * Fetches the completion state for a given completion rule.
     *
     * @param string $rule The completion rule.
     * @return int The completion state (COMPLETION_COMPLETE / COMPLETION_INCOMPLETE).
     */
    public function get_state(string $rule): int {
        $this->validate_rule($rule);

        // Only rule defined by this module is 'completionsubmit'.
        $vpl = new \mod_vpl($this->cm->id);

        // last_user_submission() already resolves group vs individual submissions
        // and returns false when the user (or their group) has no submission.
        $hassubmission = $vpl->last_user_submission($this->userid) !== false;

        return $hassubmission ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * Fetch the list of custom completion rules that this module defines.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return ['completionsubmit'];
    }

    /**
     * Returns an associative array of the descriptions of custom completion rules.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        return [
            'completionsubmit' => get_string('completionsubmit_desc', 'vpl'),
        ];
    }

    /**
     * Returns an array of all completion rules, in the order they should be displayed to users.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return [
            'completionview',
            'completionsubmit',
            'completionusegrade',
            'completionpassgrade',
        ];
    }
}
