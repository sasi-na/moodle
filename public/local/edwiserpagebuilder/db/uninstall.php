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
 * @package   local_edwiserpagebuilder
 * @copyright (c) 2024 WisdmLabs (https://wisdmlabs.com/) <support@wisdmlabs.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/edwiserpagebuilder/lib.php');

/**
 * Custom code to be run on uninstalling the plugin.
 */
function xmldb_local_edwiserpagebuilder_uninstall() {
    global $CFG;

    // Send deactivation analytics data only for free version (non-blocking).
    // Do this first before any cleanup, so we have access to plugin files.
    // Only send if pro version is not allowed (i.e., it's the free version).
    if (!is_epb_pro_allowed()) {
        try {
            require_once($CFG->dirroot . '/local/edwiserpagebuilder/classes/plugin_analytics.php');
            $analytics = new \local_edwiserpagebuilder\plugin_analytics();
            $analytics->send_deactivation_data('Plugin uninstalled');
        } catch (\Exception $e) {
            // Silently fail - don't break uninstallation.
            error_log('[Plugin Analytics] Failed to send deactivation data: ' . $e->getMessage());
        }
    }
}

