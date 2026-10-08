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
 * End-semester exam entry point.
 *
 * @package   block_endsemesterexam
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use block_endsemesterexam\local\audience;

/**
 * A single button that takes an entitled candidate to this site's exam portal.
 */
class block_endsemesterexam extends block_base {

    #[\Override]
    public function init(): void {
        $this->title = get_string('pluginname', 'block_endsemesterexam');
    }

    /**
     * Dashboard only.
     *
     * @return array
     */
    #[\Override]
    public function applicable_formats(): array {
        return ['my' => true];
    }

    /**
     * This block has site-level settings (audience gate and exam portal).
     *
     * @return bool
     */
    #[\Override]
    public function has_config(): bool {
        return true;
    }

    #[\Override]
    public function get_content(): stdClass {
        global $USER, $PAGE;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        $PAGE->requires->css('/blocks/endsemesterexam/style.css');

        // Always visible to site administrators, so the configuration can be
        // checked without holding a learner's profile data. The handshake still
        // runs the real gate, so an admin following the link is told plainly
        // that they are not a candidate rather than being handed a session.
        if (is_siteadmin($USER)) {
            return $this->render_exam_button();
        }

        if (!audience::allows((int) $USER->id)) {
            return $this->content;
        }

        return $this->render_exam_button();
    }

    /**
     * The button.
     *
     * Carries no candidate identifier: the exam identifier is derived from the
     * session at redirect time, not passed through the browser. Carries a
     * sesskey because following it starts an authenticated handshake with an
     * external portal.
     *
     * @return stdClass
     */
    protected function render_exam_button(): stdClass {
        $url = new moodle_url('/blocks/endsemesterexam/redirect.php', ['sesskey' => sesskey()]);

        $this->content->text = html_writer::div(
            html_writer::link($url, get_string('endsemesterexam', 'block_endsemesterexam'), [
                'class'  => 'btn btn-endexam',
                'target' => '_blank',
                'rel'    => 'noopener noreferrer',
            ])
        );

        return $this->content;
    }
}
