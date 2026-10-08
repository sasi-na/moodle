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

namespace local_facultyfeedback\privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for local_facultyfeedback.
 *
 * A null provider: the plugin owns no tables and writes nothing. It reads
 * mod_feedback's existing responses and reports only aggregates — counts and
 * averages — so no respondent is ever identified. mod_feedback remains the
 * data controller for those responses and declares them in its own provider.
 *
 * @package   local_facultyfeedback
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\null_provider {

    /**
     * Reason this plugin stores nothing.
     *
     * @return string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
