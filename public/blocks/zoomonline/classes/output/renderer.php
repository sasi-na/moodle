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
 * Renderer for block_zoomonline.
 *
 * @package    block_zoomonline
 * @copyright  2024 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_zoomonline\output;

defined('MOODLE_INTERNAL') || die;

/**
 * Class renderer for block_zoomonline.
 *
 * This class is responsible for rendering the output for templates used
 * in the Zoom Online block. It extends the Moodle plugin renderer base.
 *
 * @package    block_zoomonline
 * @copyright  2024 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {

    /**
     * Render the meeting link template.
     *
     * @param array $data The template data.
     * @return string Rendered HTML.
     */
    public function render_meeting_link($data) {
        return $this->render_from_template('block_zoomonline/meeting_link', $data);
    }
}
