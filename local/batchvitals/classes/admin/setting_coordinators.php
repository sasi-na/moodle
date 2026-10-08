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
 * Read-only overview of who currently holds the coordinator role.
 *
 * @package   local_batchvitals
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_batchvitals\admin;

use local_batchvitals\access;

defined('MOODLE_INTERNAL') || die();

/**
 * Lists every programme coordinator and links to the page that edits them.
 *
 * This is a display-only setting: nothing is stored. The query lives in
 * output_html() rather than the constructor so it runs when this settings page
 * is actually viewed, not on every admin tree build.
 */
class setting_coordinators extends \admin_setting {

    /**
     * @param string $name Internal name.
     */
    public function __construct($name) {
        parent::__construct($name, get_string('coordinators', 'local_batchvitals'), '', '');
    }

    /**
     * Nothing is stored, so there is always a value.
     *
     * @return bool
     */
    public function get_setting() {
        return true;
    }

    /**
     * Read-only: never writes.
     *
     * @param mixed $data
     * @return string Always empty (no error).
     */
    public function write_setting($data) {
        return '';
    }

    /**
     * Render the table.
     *
     * @param mixed $data
     * @param string $query
     * @return string
     */
    public function output_html($data, $query = '') {
        global $OUTPUT;

        $rows = access::coordinators();

        if (!$rows) {
            $html = $OUTPUT->notification(
                get_string('coordinators_none', 'local_batchvitals'), 'info', false);
            return format_admin_setting($this, $this->visiblename, $html, '', false, '', null, $query);
        }

        $table = new \html_table();
        $table->head = [
            get_string('coordinator', 'local_batchvitals'),
            get_string('email'),
            get_string('scope_programme', 'local_batchvitals'),
            get_string('scope_batch', 'local_batchvitals'),
            get_string('since', 'local_batchvitals'),
            '',
        ];
        $table->attributes['class'] = 'generaltable';
        $table->colclasses = ['', '', '', '', '', 'text-right'];

        $people = [];
        foreach ($rows as $r) {
            $people[$r->userid] = true;
            $edit = new \moodle_url('/admin/roles/assign.php', ['contextid' => $r->ctxid]);
            $table->data[] = [
                fullname($r),
                \html_writer::tag('span', s($r->email), ['class' => 'text-muted']),
                s(trim($r->catname)),
                s(trim((string) $r->batchname)),
                $r->timemodified ? userdate($r->timemodified,
                    get_string('strftimedate', 'langconfig')) : '—',
                \html_writer::link($edit, get_string('edit'), ['class' => 'btn btn-sm btn-secondary']),
            ];
        }

        $summary = \html_writer::tag('p',
            get_string('coordinators_summary', 'local_batchvitals',
                (object) ['people' => count($people), 'programmes' => count($rows)]),
            ['class' => 'text-muted']);

        $html = $summary . \html_writer::table($table)
            . \html_writer::tag('p',
                get_string('coordinators_help', 'local_batchvitals'),
                ['class' => 'text-muted small']);

        return format_admin_setting($this, $this->visiblename, $html, '', false, '', null, $query);
    }
}
