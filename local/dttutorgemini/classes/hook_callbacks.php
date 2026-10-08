<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_dttutorgemini;

/**
 * Hook callbacks.
 *
 * @package   local_dttutorgemini
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Point Tutor AI's AI client at the local Gemini client when a Gemini key is set.
     *
     * @param \core\hook\di_configuration $hook
     */
    public static function di_configuration(\core\hook\di_configuration $hook): void {
        if (client::is_configured()) {
            $hook->add_definition(\local_dttutor\httpclient\ai_client::class, fn() => new client());
        }
    }
}
