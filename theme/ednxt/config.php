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
 * Theme config: everything (layouts, renderers, SCSS, settings) comes from Baz.
 * Only the name and parent change; the dashboards check for this theme name.
 *
 * @package   theme_ednxt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Load Baz's own configuration (layouts, renderer factory, icon system, SCSS ...).
require(__DIR__ . '/../baz/config.php');

$THEME->name = 'ednxt';
$THEME->parents = ['baz'];
$THEME->sheets = [];
// Baz's pre/extra SCSS callbacks run from the parent config already; setting
// them here too would add their SCSS twice.
$THEME->prescsscallback = null;
$THEME->extrascsscallback = 'theme_ednxt_get_extra_scss'; // Top bar colours + custom CSS only.
