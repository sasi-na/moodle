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
 * Bespoke Student e-ID Card page exporter for theme_baz (student-only).
 *
 * Mirrors the data gathering of local/studentid/index.php so the theme can
 * present the e-ID portal in the bespoke navy/gold shell. Action processing
 * (download / update / submit) still happens in index.php before the layout
 * renders; this only re-renders the output.
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
 * Renderable + templatable for the bespoke Student e-ID Card page.
 */
class student_idcard implements renderable, templatable {

    /**
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $USER, $CFG, $DB;
        require_once($CFG->dirroot . '/local/studentid/classes/manager.php');
        $mgr = '\local_studentid\manager';
        $userid = (int) $USER->id;

        $request = $mgr::get_active_request($userid);
        $card = ($request && $request->status === $mgr::STATUS_APPROVED)
            ? $mgr::get_card_for_request($request->id) : null;
        $sd = $mgr::get_student_data($userid);

        // History rows.
        $history = [];
        foreach ($mgr::get_user_requests($userid) as $r) {
            $history[] = [
                'statuslabel' => $mgr::get_status_label($r->status),
                'isapproved'  => $r->status === $mgr::STATUS_APPROVED,
                'isrejected'  => $r->status === $mgr::STATUS_REJECTED,
                'created'     => userdate($r->timecreated, get_string('strftimedate', 'langconfig')),
                'submitted'   => $r->timesubmitted ? userdate($r->timesubmitted, get_string('strftimedate', 'langconfig')) : '—',
                'comment'     => $r->review_comment ?? '',
                'hascomment'  => !empty($r->review_comment),
            ];
        }

        // Action flags (mirror index.php).
        $candraft = $request && $request->status === $mgr::STATUS_DRAFT;
        $cannew = !$request || in_array($request->status, [$mgr::STATUS_REJECTED, $mgr::STATUS_REPLACED], true);
        $isindia = $request && strcasecmp((string) $request->country, 'India') === 0;
        $cansubmit = $candraft
            && !empty($request->photo_path) && !empty($request->aadhaar_front_path)
            && !empty($request->aadhaar_back_path) && !empty($request->country)
            && !empty($request->state) && !empty($request->city)
            && ($isindia
                ? (!empty($request->abc_id_path) && !empty($request->tenth_marks_path))
                : !empty($request->olevel_path));
        $candownload = $card !== null;

        // Stepper.
        $order = [
            $mgr::STATUS_DRAFT => 0, $mgr::STATUS_SUBMITTED => 1,
            $mgr::STATUS_UNDER_REVIEW => 2, $mgr::STATUS_APPROVED => 3,
        ];
        $cur = $request ? ($order[$request->status] ?? -1) : -1;
        if ($card) {
            $cur = 4;
        }
        $labels = [
            get_string('statusdraft', 'local_studentid'),
            get_string('statussubmitted', 'local_studentid'),
            get_string('statusunderreview', 'local_studentid'),
            get_string('statusapproved', 'local_studentid'),
            get_string('step_card_ready', 'local_studentid'),
        ];
        $steps = [];
        foreach ($labels as $i => $lbl) {
            $steps[] = [
                'label' => $lbl,
                'idx'   => $i,
                'done'  => $i < $cur,
                'active' => $i === $cur,
                'todo'  => $i > $cur,
                'last'  => $i === count($labels) - 1,
            ];
        }
        $isrejected = $request && $request->status === $mgr::STATUS_REJECTED;
        $showstepper = $request && !$isrejected;

        // High-level state for the template's main panel.
        $state = 'none';
        if ($card) {
            $state = 'ready';
        } else if ($isrejected) {
            $state = 'rejected';
        } else if ($candraft) {
            $state = 'draft';
        } else if ($request) {
            $state = 'review';
        }

        $statuslabel = $request ? $mgr::get_status_label($request->status) : '';

        // Card details.
        $cardno = $card ? $card->idcard_number : '';
        $downloads = $card ? (int) ($card->download_count ?? 0) : 0;
        $lastdl = ($card && !empty($card->last_downloaded))
            ? userdate($card->last_downloaded, get_string('strftimedate', 'langconfig'))
            : get_string('neverdownloaded', 'local_studentid');
        $issued = ($card && !empty($card->timecreated))
            ? userdate($card->timecreated, get_string('strftimedate', 'langconfig')) : '';

        // Decorative QR grid (deterministic from the card number).
        $qr = [];
        $seed = crc32($cardno !== '' ? $cardno : (string) $userid);
        for ($i = 0; $i < 36; $i++) {
            $qr[] = ['on' => ((($seed >> ($i % 31)) ^ ($i * 7)) % 3 === 0) ? 'on' : ''];
        }

        $support = $this->render_support();
        $askjo = \theme_baz\local\askjo::build();

        return [
            'config'        => ['wwwroot' => $CFG->wwwroot],
            'qr'            => $qr,
            'sesskey'       => sesskey(),
            'state'         => $state,
            'isready'       => $state === 'ready',
            'isrejected'    => $isrejected,
            'isdraft'       => $candraft,
            'isreview'      => $state === 'review',
            'isnone'        => $state === 'none',
            'statuslabel'   => $statuslabel,
            'rejectcomment' => $isrejected ? (string) ($request->review_comment ?? '') : '',

            'showstepper'   => $showstepper,
            'steps'         => $steps,

            // Student / card.
            'name'          => $sd['fullname'],
            'initials'      => $this->initials($sd['fullname']),
            'usn'           => $sd['usn'] !== '' ? $sd['usn'] : '—',
            'program'       => $sd['program'] !== '' ? $sd['program'] : '—',
            'batch'         => $sd['batch'] !== '' ? $sd['batch'] : '—',
            'email'         => $sd['email'],
            'contact'       => $sd['contact'],
            'cardno'        => $cardno !== '' ? $cardno : '—',
            'hasissued'     => $issued !== '',
            'issued'        => $issued,
            'downloads'     => $downloads,
            'lastdownloaded' => $lastdl,

            // Actions.
            'candownload'   => $candownload,
            'downloadurl'   => $candownload
                ? (new moodle_url('/local/studentid/download.php', ['cardid' => $card->id]))->out(false) : '',
            'canupdate'     => $candownload,
            'updateurl'     => (new moodle_url('/local/studentid/index.php'))->out(false),
            'cannew'        => $cannew,
            'requesturl'    => (new moodle_url('/local/studentid/request.php'))->out(false),
            'canedit'       => $candraft,
            'editurl'       => $candraft
                ? (new moodle_url('/local/studentid/request.php', ['id' => $request->id]))->out(false) : '',
            'cansubmit'     => $cansubmit,

            'history'       => $history,
            'hashistory'    => !empty($history),

            // Shell nav.
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
     * Initials from a name.
     */
    protected function initials(string $name): string {
        $words = array_values(array_filter(preg_split('/\s+/', trim($name)),
            fn($w) => $w !== '' && ctype_alpha($w[0])));
        if (!$words) {
            return \core_text::strtoupper(\core_text::substr(trim($name), 0, 2));
        }
        $a = \core_text::strtoupper(\core_text::substr($words[0], 0, 1));
        $b = count($words) > 1 ? \core_text::strtoupper(\core_text::substr(end($words), 0, 1)) : '';
        return $a . $b;
    }

    /**
     * Real support block HTML (LSM / ticket / SOP) for the floating help panel.
     */
    protected function render_support(): ?string {
        return theme_baz_support_panel();
    }
}
