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
 * Backwards-compatible ChatGPT facade.
 *
 * @package   local_geniai
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_geniai\gpt;

use local_geniai\controller;

/**
 * Keeps existing callers working while the implementation lives in a controller subplugin.
 */
class chatgpt {
    /**
     * Delegate to the selected GeniAI controller.
     *
     * @param array $messages
     * @param string $replacemodel
     * @return array
     */
    public static function completions($messages, $replacemodel = "") {
        return controller::completions(array_values($messages), $replacemodel);
    }
}
