<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Tutor AI on Google Gemini: runs local_dttutor without the DataCurso service.
 *
 * @package   local_dttutorgemini
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_dttutorgemini';
$plugin->version = 2026100500;
$plugin->requires = 2024042200;
$plugin->maturity = MATURITY_STABLE;
$plugin->release = '1.0.0';
$plugin->dependencies = ['local_dttutor' => 2026091106];
