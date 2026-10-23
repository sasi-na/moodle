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

namespace mod_msteams;

defined('MOODLE_INTERNAL') || die();

/**
 * Microsoft Graph REST mapping for MS Teams meetings.
 *
 * @package    mod_msteams
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class graph_rest extends \core\oauth2\rest {
    #[\Override]
    public function get_api_functions() {
        return [
            'create_online_meeting' => [
                'endpoint' => 'https://graph.microsoft.com/v1.0/me/onlineMeetings',
                'method' => 'post',
                'args' => [],
                'response' => 'json',
            ],
        ];
    }
}