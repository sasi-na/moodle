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
 * Bespoke student assignment renderer for theme_baz.
 *
 * For student-only users (experience::is_on('activity')) the submission-status,
 * feedback and submit-confirmation output is re-presented as the navy/gold
 * card design. Everything else — the submit/edit/remove action buttons, the
 * edit form, all validation and routing — is left to core Moodle, so no
 * assessment logic is reimplemented. Any failure falls back to the standard
 * renderer. Teachers/graders always get standard Moodle.
 *
 * @package   theme_baz
 * @copyright 2026 onlinejain.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/assign/classes/output/renderer.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

/**
 * Assignment renderer.
 */
class theme_baz_mod_assign_renderer extends \mod_assign\output\renderer {

    /**
     * Whether the bespoke student experience is active for this request.
     *
     * @return bool
     */
    protected function baz_is_bespoke(): bool {
        try {
            return \theme_baz\local\experience::is_on('activity');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Inline SVG icon markup for a banner state.
     *
     * @param string $name
     * @return string
     */
    protected function baz_icon(string $name): string {
        $paths = [
            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 8v4l3 2"/>',
            'pencil' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
            'check' => '<path d="M20 6 9 17l-5-5"/>',
            'badge' => '<path d="m5 12 5 5L20 6"/>',
            'alert' => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
            'lock' => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        ];
        $inner = $paths[$name] ?? $paths['clock'];
        return '<svg viewBox="0 0 24 24" aria-hidden="true">' . $inner . '</svg>';
    }

    /**
     * Build one detail-grid cell.
     *
     * @param string $k label
     * @param string $v value (already escaped/safe)
     * @param string $vclass optional value modifier class
     * @return string
     */
    protected function baz_cell(string $k, string $v, string $vclass = ''): string {
        if ($v === '') {
            return '';
        }
        return \html_writer::div(
            \html_writer::div(s($k), 'baz-asg-k') .
            \html_writer::div($v, 'baz-asg-v' . ($vclass ? ' ' . $vclass : '')),
            'baz-asg-cell'
        );
    }

    /**
     * Bespoke override of the student submission-status card.
     *
     * @param \mod_assign\output\assign_submission_status $status
     * @return string
     */
    public function render_assign_submission_status(\mod_assign\output\assign_submission_status $status) {
        if (!$this->baz_is_bespoke() || $status->view == \mod_assign\output\assign_submission_status::GRADER_VIEW) {
            return parent::render_assign_submission_status($status);
        }
        try {
            return $this->baz_submission_status($status);
        } catch (\Throwable $e) {
            debugging('theme_baz assign submission_status failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return parent::render_assign_submission_status($status);
        }
    }

    /**
     * Compose the bespoke submission-status card.
     *
     * @param \mod_assign\output\assign_submission_status $status
     * @return string
     */
    protected function baz_submission_status(\mod_assign\output\assign_submission_status $status): string {
        $submission = $status->teamsubmission ? $status->teamsubmission : $status->submission;
        $sstatus = ($submission && $submission->status != ASSIGN_SUBMISSION_STATUS_NEW) ? $submission->status : '';
        $graded = ($status->gradingstatus === ASSIGN_GRADING_STATUS_GRADED
            || $status->gradingstatus === ASSIGN_MARKING_WORKFLOW_STATE_RELEASED);

        $duedate = $status->duedate;
        if (!empty($status->extensionduedate)) {
            $duedate = max($status->duedate, $status->extensionduedate);
        }

        // Reuse core's exact time-remaining wording + class.
        $timeremaining = '';
        $trclass = '';
        if ($status->duedate > 0 || ($submission && !empty($submission->timestarted))) {
            [$timeremaining, $trclass] = $this->get_time_remaining($status);
        }
        $overdue = ($trclass === 'overdue');
        // Submissions are closed when the assignment takes no online submissions,
        // or the cut-off date has passed (submissionsenabled reflects the plugin
        // setting, not the time window).
        $cutoffpassed = (!empty($status->cutoffdate) && $status->cutoffdate < time());
        $closed = (!$status->submissionsenabled || $cutoffpassed);

        // Determine the banner state.
        if ($graded) {
            $state = 'graded'; $icon = 'badge'; $label = get_string('graded', 'assign');
            $msg = get_string('sd_a_graded_msg', 'theme_baz');
            $sub = get_string('sd_a_graded_sub', 'theme_baz');
        } else if ($sstatus === ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
            $state = 'sub'; $icon = 'check'; $label = get_string('submissionstatus_submitted', 'assign');
            $msg = get_string('submissionstatus_submitted', 'assign');
            $sub = $timeremaining;
        } else if ($sstatus === ASSIGN_SUBMISSION_STATUS_DRAFT) {
            $state = 'draft'; $icon = 'pencil'; $label = get_string('submissionstatus_draft', 'assign');
            $msg = get_string('sd_a_draft_msg', 'theme_baz');
            $sub = $duedate > 0 ? get_string('sd_a_draft_sub', 'theme_baz', userdate($duedate)) : '';
        } else if ($closed) {
            $state = 'closed'; $icon = 'lock'; $label = get_string('sd_a_closed', 'theme_baz');
            $msg = get_string('submissionslocked', 'assign');
            $sub = ($status->cutoffdate ? get_string('sd_a_closed_sub', 'theme_baz', userdate($status->cutoffdate)) : '');
        } else if ($overdue) {
            $state = 'late'; $icon = 'alert'; $label = get_string('sd_a_overdue', 'theme_baz');
            $msg = $timeremaining;
            $sub = ($status->cutoffdate ? get_string('sd_a_late_sub', 'theme_baz', userdate($status->cutoffdate)) : '');
        } else {
            $state = 'todo'; $icon = 'clock'; $label = get_string('sd_a_todo', 'theme_baz');
            $msg = get_string('sd_a_todo_msg', 'theme_baz');
            $sub = $duedate > 0 ? get_string('sd_a_todo_sub', 'theme_baz', userdate($duedate)) : '';
        }

        // Banner.
        $bannermain = \html_writer::div(s($label), 'baz-asg-bstate')
            . \html_writer::div(s($msg), 'baz-asg-bmsg')
            . ($sub !== '' ? \html_writer::div(s($sub), 'baz-asg-bsub') : '');
        $banner = \html_writer::div(
            \html_writer::tag('span', $this->baz_icon($icon), ['class' => 'baz-asg-bi'])
            . \html_writer::div($bannermain, 'baz-asg-bmain'),
            'baz-asg-banner b-' . $state
        );

        // Detail grid.
        $cells = '';
        // Team / attempt rows (kept for parity when applicable).
        if ($status->teamsubmissionenabled && $status->submissiongroup) {
            $cells .= $this->baz_cell(get_string('submissionteam', 'assign'),
                s(format_string($status->submissiongroup->name, false, ['context' => $status->context])));
        }
        if (($status->maxattempts > 1 || $status->maxattempts == ASSIGN_UNLIMITED_ATTEMPTS) && $submission) {
            $attempt = $submission->attemptnumber + 1;
            $cells .= $this->baz_cell(get_string('attemptnumber', 'assign'), s((string)$attempt));
        }

        if ($duedate > 0) {
            $cells .= $this->baz_cell(get_string('duedate', 'assign'), s(userdate($duedate)),
                $overdue ? 'bad' : '');
        }
        if ($timeremaining !== '') {
            $trmod = '';
            if ($trclass === 'overdue') {
                $trmod = 'bad';
            } else if ($trclass === 'latesubmission') {
                $trmod = 'bad';
            } else if ($trclass === 'earlysubmission') {
                $trmod = 'ok';
            } else if ($trclass === 'timeremaining') {
                $trmod = 'warn';
            }
            $cells .= $this->baz_cell(get_string('timeremaining', 'assign'), s($timeremaining), $trmod);
        }

        // Submission status text.
        if ($sstatus !== '') {
            $substr = get_string('submissionstatus_' . $sstatus, 'assign');
            $svclass = ($sstatus === ASSIGN_SUBMISSION_STATUS_SUBMITTED) ? 'ok'
                : (($sstatus === ASSIGN_SUBMISSION_STATUS_DRAFT) ? 'blue' : '');
        } else {
            $substr = $status->submissionsenabled
                ? get_string('nosubmissionyet', 'assign')
                : get_string('noonlinesubmissions', 'assign');
            $svclass = $overdue ? 'bad' : '';
        }
        $cells .= $this->baz_cell(get_string('submissionstatus', 'assign'), s($substr), $svclass);

        // Grading status text.
        if ($status->gradingstatus === ASSIGN_GRADING_STATUS_GRADED
                || $status->gradingstatus === ASSIGN_GRADING_STATUS_NOT_GRADED) {
            $gradetext = get_string($status->gradingstatus, 'assign');
        } else {
            $gradetext = get_string('markingworkflowstate' . $status->gradingstatus, 'assign');
        }
        $cells .= $this->baz_cell(get_string('gradingstatus', 'assign'), s($gradetext), $graded ? 'ok' : '');

        // Cut-off date, when it adds information (submission still open / overdue).
        if (!empty($status->cutoffdate) && ($overdue || !$submission)) {
            $cells .= $this->baz_cell(get_string('cutoffdate', 'assign'), s(userdate($status->cutoffdate)),
                $overdue ? 'warn' : '');
        }

        // Last modified.
        if ($submission && $submission->status != ASSIGN_SUBMISSION_STATUS_NEW) {
            $cells .= $this->baz_cell(get_string('timemodified', 'assign'), s(userdate($submission->timemodified)));
        }

        $grid = $cells !== '' ? \html_writer::div($cells, 'baz-asg-grid') : '';

        // Submission plugin content (files / online text) — reuse core rendering.
        $blocks = '';
        if ($submission && (!$status->teamsubmission || $status->submissiongroup != false
                || !$status->preventsubmissionnotingroup)) {
            foreach ($status->submissionplugins as $plugin) {
                $showsummary = !$plugin->is_empty($submission) || !$plugin->allow_submissions();
                if ($plugin->is_enabled() && $plugin->is_visible() && $plugin->has_user_summary() && $showsummary) {
                    $pluginsubmission = new \assign_submission_plugin_submission(
                        $plugin, $submission, \assign_submission_plugin_submission::SUMMARY,
                        $status->coursemoduleid, $status->returnaction, $status->returnparams);
                    $content = $this->render($pluginsubmission);
                    if (trim($content) !== '') {
                        $blocks .= \html_writer::div(
                            \html_writer::div(s($plugin->get_name()), 'baz-asg-blk-h')
                            . \html_writer::div($content, 'baz-asg-blk-body'),
                            'baz-asg-blk');
                    }
                }
            }
        }

        // Grading criteria preview (rubric/marking guide) if present.
        $extra = '';
        if (!empty($status->gradingcontrollerpreview)) {
            $extra = \html_writer::div(
                \html_writer::div(s(get_string('gradingmethodpreview', 'assign')), 'baz-asg-blk-h')
                . \html_writer::div($status->gradingcontrollerpreview, 'baz-asg-blk-body'),
                'baz-asg-blk');
        }

        return \html_writer::div($banner . $grid . $blocks . $extra, 'submissionstatustable baz-asg');
    }

    /**
     * Bespoke override of the feedback panel.
     *
     * @param \assign_feedback_status $status
     * @return string
     */
    public function render_assign_feedback_status(\assign_feedback_status $status) {
        if (!$this->baz_is_bespoke()) {
            return parent::render_assign_feedback_status($status);
        }
        try {
            return $this->baz_feedback_status($status);
        } catch (\Throwable $e) {
            debugging('theme_baz assign feedback_status failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return parent::render_assign_feedback_status($status);
        }
    }

    /**
     * Compose the bespoke feedback panel.
     *
     * @param \assign_feedback_status $status
     * @return string
     */
    protected function baz_feedback_status(\assign_feedback_status $status): string {
        // A clean "Feedback" section header (the green "graded" banner already
        // sits on the submission-status card above — avoid duplicating it).
        $head = \html_writer::div(
            \html_writer::tag('span', $this->baz_icon('badge'), ['class' => 'baz-asg-fb-ic'])
            . \html_writer::tag('span', s(get_string('feedback', 'assign')), ['class' => 'baz-asg-fb-title']),
            'baz-asg-fb-head');

        // Grade + meta.
        $meta = '';
        if (isset($status->gradefordisplay)) {
            $meta .= \html_writer::div($status->gradefordisplay, 'baz-asg-grade-val');
        }
        $metalines = '';
        if (isset($status->gradeddate)) {
            $metalines .= \html_writer::div(
                s(get_string('gradedon', 'assign')) . ' <b>' . s(userdate($status->gradeddate)) . '</b>', 'baz-asg-fb-line');
        }
        if ($status->grader) {
            $gname = fullname($status->grader, $status->canviewfullnames);
            $metalines .= \html_writer::div(
                s(get_string('gradedby', 'assign')) . ' '
                . $this->output->user_picture($status->grader, ['size' => 24, 'class' => 'baz-asg-fb-av'])
                . ' <b>' . s($gname) . '</b>', 'baz-asg-fb-line');
        }
        $gradeblock = '';
        if ($meta !== '' || $metalines !== '') {
            $gradeblock = \html_writer::div($meta . \html_writer::div($metalines, 'baz-asg-fb-meta'), 'baz-asg-fb-grade');
        }

        // Feedback plugin content (comments / files / annotated pdf).
        $plugins = '';
        foreach ($status->feedbackplugins as $plugin) {
            if ($plugin->is_enabled() && $plugin->is_visible() && $plugin->has_user_summary()
                    && !empty($status->grade) && !$plugin->is_empty($status->grade)) {
                $pluginfeedback = new \assign_feedback_plugin_feedback(
                    $plugin, $status->grade, \assign_feedback_plugin_feedback::SUMMARY,
                    $status->coursemoduleid, $status->returnaction, $status->returnparams);
                $content = $this->render($pluginfeedback);
                if (trim($content) !== '') {
                    $plugins .= \html_writer::div(
                        \html_writer::div(s($plugin->get_name()), 'baz-asg-fb-h')
                        . \html_writer::div($content, 'baz-asg-fb-c'),
                        'baz-asg-fb-item');
                }
            }
        }

        $breakdown = '';
        if (!empty($status->gradingcontrollergrade)) {
            $breakdown = \html_writer::div(
                \html_writer::div(s(get_string('gradebreakdown', 'assign')), 'baz-asg-fb-h')
                . \html_writer::div($status->gradingcontrollergrade, 'baz-asg-fb-c'),
                'baz-asg-fb-item');
        }

        $panel = \html_writer::div($gradeblock . $plugins . $breakdown, 'baz-asg-fb');
        return \html_writer::div($head . $panel, 'feedback baz-asg');
    }

    /**
     * Bespoke override of the submit-for-grading confirmation page.
     *
     * @param \assign_submit_for_grading_page $page
     * @return string
     */
    public function render_assign_submit_for_grading_page($page) {
        if (!$this->baz_is_bespoke()) {
            return parent::render_assign_submit_for_grading_page($page);
        }
        try {
            $cancelurl = new \moodle_url('/mod/assign/view.php', ['id' => $page->coursemoduleid]);
            $inner = \html_writer::tag('span', $this->baz_icon('alert'), ['class' => 'baz-asg-confirm-ic']);
            $inner .= \html_writer::tag('h3', s(get_string('confirmsubmissionheading', 'assign')), ['class' => 'baz-asg-confirm-h']);

            if (count($page->notifications)) {
                $inner .= \html_writer::tag('p', s(get_string('submissionnotready', 'assign')), ['class' => 'baz-asg-confirm-p']);
                foreach ($page->notifications as $notification) {
                    $inner .= $this->output->notification($notification);
                }
                $inner .= $this->output->continue_button($cancelurl);
            } else {
                $inner .= $this->moodleform($page->confirmform);
            }
            return \html_writer::div(
                \html_writer::div($inner, 'baz-asg-confirm'),
                'submitforgrading baz-asg');
        } catch (\Throwable $e) {
            debugging('theme_baz assign confirm failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return parent::render_assign_submit_for_grading_page($page);
        }
    }

    /**
     * Wrap the edit-submission form so the bespoke CSS can restyle it, without
     * touching the form itself.
     *
     * @param \assign_form $form
     * @return string
     */
    public function render_assign_form(\assign_form $form) {
        $html = parent::render_assign_form($form);
        if (!$this->baz_is_bespoke()) {
            return $html;
        }
        return \html_writer::div($html, 'baz-asg-form');
    }
}
