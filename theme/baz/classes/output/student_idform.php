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
 * Bespoke wrapper for the Student e-ID Card request form (student-only).
 *
 * The form itself (upload zones, cascading selects, validation, submission) is
 * the local_studentid plugin's own markup + JS, rendered into region-main. This
 * exporter only supplies the shared navy/gold app shell; the form is restyled
 * via SCSS so its proven upload logic is preserved untouched.
 *
 * @package   theme_baz
 * @copyright 2026 onlinejain.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_baz\output;

use renderable;
use templatable;
use renderer_base;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

/**
 * Renderable + templatable for the bespoke e-ID request-form shell.
 */
class student_idform implements renderable, templatable {

    /**
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $CFG;
        $support = $this->render_support();
        $askjo = \theme_baz\local\askjo::build();
        return [
            'config'        => ['wwwroot' => $CFG->wwwroot],
            'idcardurl'     => (new moodle_url('/local/studentid/index.php'))->out(false),
            'dashboardurl'  => (new moodle_url('/my/'))->out(false),
            'coursesurl'    => (new moodle_url('/my/courses.php'))->out(false),
            'calendarurl'   => (new moodle_url('/calendar/view.php'))->out(false),
            'mygradesurl'   => (new moodle_url('/grade/report/overview/index.php'))->out(false),
            'filesurl'      => (new moodle_url('/user/files.php'))->out(false),
            'support_html'  => $support,
            'hassupport'    => $support !== null,
            'askjo'         => $askjo,
            'hasaskjo'      => !empty($askjo),
        ];
    }

    /**
     * Real support block HTML for the floating help panel.
     */
    protected function render_support(): ?string {
        return theme_baz_support_panel();
    }
}
