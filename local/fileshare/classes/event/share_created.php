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
 * A share link was created.
 *
 * @package   local_fileshare
 * @copyright 2026 onlinejain.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_fileshare\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Someone published a student attachment outside the LMS. Logged so it can be answered for.
 */
class share_created extends \core\event\base {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_fileshare';
    }

    /**
     * Localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_share_created', 'local_fileshare');
    }

    /**
     * Non-localised description for the log.
     *
     * @return string
     */
    public function get_description() {
        $filename = $this->other['filename'] ?? '';
        return "The user with id '{$this->userid}' created a share link (id '{$this->objectid}') for the file " .
            "'{$filename}' submitted by the user with id '{$this->relateduserid}'.";
    }
}
