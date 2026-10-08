<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Install: create the shared secret used between Tutor AI and the local Gemini endpoint.
 *
 * @package   local_dttutorgemini
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Install hook.
 */
function xmldb_local_dttutorgemini_install() {
    set_config('secret', bin2hex(random_bytes(24)), 'local_dttutorgemini');
    return true;
}
