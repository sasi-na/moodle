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
 *
 * @package   theme_baz
 * @copyright 2022 - 2024 Marcin Czaja (https://rosea.io)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . "/mod/quiz/renderer.php");

/**
 * Quiz.
 *
 */
class theme_baz_mod_quiz_renderer extends mod_quiz\output\renderer {
    /**
     * Outputs a box.
     *
     * @param string $contents The contents of the box
     * @param string $classes A space-separated list of CSS classes
     * @param string $id An optional ID
     * @param array $attributes An array of other attributes to give the box.
     * @return string the HTML to output.
     */
    public function space_box($contents, $classes = 'generalbox', $id = null, $attributes = []) {
        return $this->space_box_start($classes, $id, $attributes) . $contents . $this->space_box_end();
    }

    /**
     * Outputs the opening section of a box.
     *
     * @param string $classes A space-separated list of CSS classes
     * @param string $id An optional ID
     * @param array $attributes An array of other attributes to give the box.
     * @return string the HTML to output.
     */
    public function space_box_start($classes = 'generalbox', $id = null, $attributes = []) {
        $this->opencontainers->push('box', html_writer::end_tag('div'));
        $attributes['id'] = $id;
        $attributes['class'] = 'box ' . renderer_base::prepare_classes($classes);
        return html_writer::start_tag('div', $attributes);
    }

    /**
     * Outputs the closing section of a box.
     *
     * @return string the HTML to output.
     */
    public function space_box_end() {
        return $this->opencontainers->pop('box');
    }

    /**
     * Output the page information
     *
     * @param object $quiz the quiz settings.
     * @param object $cm the course_module object.
     * @param context $context the quiz context.
     * @param array $messages any access messages that should be described.
     * @param bool $quizhasquestions does quiz has questions added.
     * @return string HTML to output.
     */
    public function view_information($quiz, $cm, $context, $messages, bool $quizhasquestions = false) {
        $output = '';

        $bespoke = false;
        try {
            $bespoke = \theme_baz\local\experience::is_on('activity');
        } catch (\Throwable $e) {
            $bespoke = false;
        }

        // Output any access messages — as navy/gold chips for students, else the box.
        if ($messages) {
            if ($bespoke) {
                $chips = '';
                foreach ($messages as $msg) {
                    $text = trim(html_to_text((string) $msg, 0, false));
                    if ($text === '') {
                        continue;
                    }
                    $parts = explode(':', $text, 2);
                    if (count($parts) === 2 && trim($parts[1]) !== '') {
                        $chips .= html_writer::div(
                            html_writer::div(s(trim($parts[0])), 'sa-chip-k') .
                            html_writer::div(s(trim($parts[1])), 'sa-chip-v'),
                            'sa-chip');
                    } else {
                        $chips .= html_writer::div(s($text), 'sa-chip sa-chip-wide');
                    }
                }
                if ($chips !== '') {
                    $output .= html_writer::div($chips, 'sa-chips');
                }
            } else {
                $output .= $this->box($this->access_messages($messages), 'rui-quizinfo quizinfo mt-3');
            }
        }

        // Show number of attempts summary to those who can view reports.
        if (has_capability('mod/quiz:viewreports', $context)) {
            if (
                $strattemptnum = $this->quiz_attempt_summary_link_to_reports(
                    $quiz,
                    $cm,
                    $context
                )
            ) {
                $output .= html_writer::tag(
                    'div',
                    $strattemptnum,
                    ['class' => 'rui-quizattemptcounts quizattemptcounts my-4']
                );
            }
        }

        if (has_any_capability(['mod/quiz:manageoverrides', 'mod/quiz:viewoverrides'], $context)) {
            if ($overrideinfo = $this->quiz_override_summary_links($quiz, $cm)) {
                $output .= html_writer::tag('div', $overrideinfo, ['class' => 'rui-quizattemptcounts quizattemptcounts my-4']);
            }
        }

        return $output;
    }

    /**
     * Grade + overall-feedback block — rendered as a navy grade banner for students.
     *
     * @param object $quiz
     * @param context $context
     * @param object $cm
     * @param \mod_quiz\output\view_page $viewobj
     * @return string
     */
    public function view_result_info($quiz, $context, $cm, $viewobj) {
        $bespoke = false;
        try {
            $bespoke = \theme_baz\local\experience::is_on('activity');
        } catch (\Throwable $e) {
            $bespoke = false;
        }
        if (!$bespoke) {
            return parent::view_result_info($quiz, $context, $cm, $viewobj);
        }
        if (!$viewobj->numattempts && !$viewobj->gradecolumn && is_null($viewobj->mygrade)) {
            return '';
        }

        $banner = '';
        if ($viewobj->overallstats) {
            $num = quiz_format_grade($quiz, $viewobj->mygrade);
            $max = quiz_format_grade($quiz, $quiz->grade);
            $lbl = ($viewobj->moreattempts ? quiz_get_grading_option_name($quiz->grademethod) . ' · ' : '') . '/ ' . $max;
            $banner = html_writer::div(
                html_writer::div($num, 'sa-grade-num') .
                html_writer::div($lbl, 'sa-grade-lbl'),
                'sa-grade-main');
        }

        $feedback = '';
        if ($viewobj->mygradeoverridden) {
            $feedback .= html_writer::tag('p', get_string('overriddennotice', 'grades'), ['class' => 'overriddennotice']);
        }
        if ($viewobj->gradebookfeedback) {
            $feedback .= html_writer::div($viewobj->gradebookfeedback, 'quizteacherfeedback');
        }
        if ($viewobj->feedbackcolumn) {
            $feedback .= html_writer::div(
                quiz_feedback_for_grade($viewobj->mygrade, $quiz, $context), 'quizgradefeedback');
        }

        if ($banner === '' && $feedback === '') {
            return '';
        }
        $fb = $feedback !== '' ? html_writer::div($feedback, 'sa-grade-fb') : '';
        return html_writer::div($banner . $fb, 'sa-gradebanner');
    }

    /**
     * Whether the bespoke student activity experience is active.
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
     * The quiz view page. For student-only users this renders the bespoke
     * navy/gold layout (hero + info chips + availability timeline + attempts);
     * everyone else gets the stock view.
     *
     * @param stdClass $course
     * @param stdClass $quiz
     * @param stdClass $cm
     * @param context_module $context
     * @param \mod_quiz\output\view_page $viewobj
     * @return string
     */
    public function view_page($course, $quiz, $cm, $context, $viewobj) {
        if (!$this->baz_is_bespoke()) {
            return parent::view_page($course, $quiz, $cm, $context, $viewobj);
        }
        return $this->baz_view_page($course, $quiz, $cm, $context, $viewobj);
    }

    /**
     * Bespoke student quiz view page.
     *
     * @param stdClass $course
     * @param stdClass $quiz
     * @param stdClass $cm
     * @param context_module $context
     * @param \mod_quiz\output\view_page $viewobj
     * @return string
     */
    protected function baz_view_page($course, $quiz, $cm, $context, $viewobj) {
        $out = '';

        /* ---- Hero: icon + course + title + status + primary CTA ---- */
        $icon = html_writer::div(
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" ' .
            'stroke-linecap="round" stroke-linejoin="round" width="26" height="26">' .
            '<path d="M9 11l3 3L22 4"/>' .
            '<path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
            'sa-qh-icon');

        $coursepill = html_writer::div(
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12">' .
            '<path d="M4 5h16v14H4z"/><path d="M4 9h16"/></svg>' .
            s(format_string($course->fullname)),
            'sa-qh-course');
        $title = html_writer::tag('h1', format_string($quiz->name), ['class' => 'sa-qh-title']);
        $status = $this->baz_quiz_status($quiz, $viewobj);
        $meta = html_writer::div($coursepill . $title . $status, 'sa-qh-meta');

        $cta = '';
        if ($viewobj->buttontext) {
            $btn = $this->start_attempt_button(
                $viewobj->buttontext, $viewobj->startattempturl,
                $viewobj->preflightcheckform, $viewobj->popuprequired, $viewobj->popupoptions);
            $sub = '';
            if (!empty($quiz->timeclose) && $quiz->timeclose > time()) {
                $sub = html_writer::div(
                    get_string('sd_q_closeson', 'theme_baz',
                        userdate($quiz->timeclose, get_string('strftimedatetimeshort'))),
                    'sa-qh-ctasub');
            }
            $cta = html_writer::div($btn . $sub, 'sa-qh-cta');
        }

        $out .= html_writer::div($icon . $meta . $cta, 'sa-qhero');

        /* ---- Description / instructions card (the activity-header holding the
           intro is hidden on the quiz view, so re-render the intro here). ---- */
        $intro = format_module_intro('quiz', $quiz, $cm->id);
        if (trim(html_to_text($intro, 0, false)) !== '') {
            $out .= html_writer::div(
                html_writer::div(get_string('sd_instructions', 'theme_baz'), 'sa-desc-label') .
                html_writer::div($intro, 'no-overflow'),
                'sa-adesc');
        }

        /* ---- Access-rule chips (attempts allowed, time limit, …) ---- */
        $out .= $this->view_information($quiz, $cm, $context, $viewobj->infomessages);

        /* ---- Availability timeline ---- */
        $out .= $this->baz_quiz_timeline($quiz);

        /* ---- Grade banner + attempts summary ---- */
        $out .= $this->view_result_info($quiz, $context, $cm, $viewobj);
        $out .= $this->render($viewobj->attemptslist);

        /* ---- Prevent messages / back-to-course ---- */
        $btns = $this->view_page_buttons($viewobj);
        if (trim($btns) !== '') {
            $out .= html_writer::div($btns, 'sa-qbtns');
        }

        return html_writer::div($out, 'sa-quizview');
    }

    /**
     * Status line for the bespoke quiz hero ("Not yet attempted", attempts used).
     *
     * @param stdClass $quiz
     * @param \mod_quiz\output\view_page $viewobj
     * @return string
     */
    protected function baz_quiz_status($quiz, $viewobj) {
        $state = 'todo';
        $label = get_string('sd_q_notattempted', 'theme_baz');
        if (!empty($viewobj->unfinished)) {
            $state = 'progress';
            $label = get_string('sd_q_inprogress', 'theme_baz');
        } else if (!empty($viewobj->numattempts)) {
            $state = 'done';
            $label = get_string('sd_q_submitted', 'theme_baz');
        }

        $text = $label;
        if (!empty($quiz->attempts) && $quiz->attempts > 0) {
            $text .= ' · ' . get_string('sd_q_attemptsofmax', 'theme_baz',
                ['used' => (int) $viewobj->numattempts, 'max' => (int) $quiz->attempts]);
        } else if (!empty($viewobj->numattempts)) {
            $text .= ' · ' . get_string('sd_q_attemptsdone', 'theme_baz', (int) $viewobj->numattempts);
        }

        return html_writer::div(
            html_writer::span('', 'sa-qh-pip') . s($text),
            'sa-qh-status is-' . $state);
    }

    /**
     * Availability timeline (opened → closes) for the bespoke quiz hero.
     *
     * @param stdClass $quiz
     * @return string
     */
    protected function baz_quiz_timeline($quiz) {
        $open = (int) ($quiz->timeopen ?? 0);
        $close = (int) ($quiz->timeclose ?? 0);
        if (!$open && !$close) {
            return '';
        }

        $node = function($labelkey, $ts, $right = false) {
            return html_writer::div(
                html_writer::div(get_string($labelkey, 'theme_baz'), 'sa-tl-lbl') .
                html_writer::div(userdate($ts, get_string('strftimedaydate')), 'sa-tl-val') .
                html_writer::div(userdate($ts, get_string('strftimetime')), 'sa-tl-sub'),
                'sa-tl-node' . ($right ? ' sa-tl-node--r' : ''));
        };

        $body = '';
        if ($open && $close) {
            $now = time();
            $pct = 50;
            if ($close > $open) {
                $pct = max(0, min(100, (int) round(($now - $open) / ($close - $open) * 100)));
            }
            $track = html_writer::div(
                html_writer::div('', 'sa-tl-mk', ['style' => 'left:' . $pct . '%']),
                'sa-tl-track');
            $body = $node('sd_q_opened', $open) . $track . $node('sd_q_closes', $close, true);
        } else if ($open) {
            $body = $node('sd_q_opened', $open);
        } else {
            $body = $node('sd_q_closes', $close, true);
        }

        $head = html_writer::tag('p', get_string('sd_q_availability', 'theme_baz'), ['class' => 'sa-panel-ttl']);
        return html_writer::div($head . html_writer::div($body, 'sa-tl'), 'sa-panel sa-qtimeline');
    }

    /**
     * Bespoke compact quiz header for attempt / summary / review pages (which
     * have no hero). Gold quiz tile + mode chip + title + progress bar; the
     * attempt page also embeds the countdown timer + a Back link.
     *
     * @param quiz_attempt $attemptobj
     * @param string $mode 'attempt' | 'summary' | 'review'
     * @param int $page current page (attempt only)
     * @return string
     */
    protected function baz_quiz_header($attemptobj, string $mode, $page = 0) {
        try {
            return $this->baz_quiz_header_inner($attemptobj, $mode, $page);
        } catch (\Throwable $e) {
            debugging('theme_baz baz_quiz_header: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return '';
        }
    }

    /**
     * Build the bespoke quiz header (see baz_quiz_header, which wraps this in a
     * try/catch so a failure can never break the student-facing attempt page).
     */
    protected function baz_quiz_header_inner($attemptobj, string $mode, $page = 0) {
        $title = format_string($attemptobj->get_quiz_name());
        $chip = get_string('sd_q_chip_' . $mode, 'theme_baz');

        $tile = html_writer::div(
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" ' .
            'stroke-linecap="round" stroke-linejoin="round" width="22" height="22">' .
            '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
            'sa-qah-ic');
        $meta = html_writer::div(
            html_writer::span($chip, 'sa-qah-chip') .
            html_writer::tag('h1', $title, ['class' => 'sa-qah-title']),
            'sa-qah-meta');

        $right = '';
        if ($mode === 'review') {
            $gradehtml = $this->baz_quiz_grade_chip($attemptobj);
            if ($gradehtml !== '') {
                $right .= $gradehtml;
            }
        } else {
            [$answered, $total, $qnum] = $this->baz_quiz_progress($attemptobj, $page);
            if ($total > 0) {
                $pct = (int) round($answered / $total * 100);
                $bar = html_writer::div(
                    html_writer::div('', 'sa-qah-barfill', ['style' => 'width:' . $pct . '%']),
                    'sa-qah-bar');
                if ($mode === 'attempt') {
                    $ptxt = get_string('sd_q_qprogress', 'theme_baz',
                        ['qnum' => $qnum, 'total' => $total, 'answered' => $answered]);
                } else {
                    $ptxt = get_string('sd_q_answered', 'theme_baz',
                        ['answered' => $answered, 'total' => $total]);
                }
                $right .= html_writer::div($bar . html_writer::span($ptxt, 'sa-qah-ptxt'), 'sa-qah-prog');
            }
        }
        if ($mode === 'attempt' || $mode === 'summary') {
            // Timer sits inside the header (matches the design). It is the last
            // thing built, so if anything above throws, baz_quiz_header returns ''
            // and attempt_page/summary_page render the timer standalone instead —
            // the timer is never lost on a timed quiz.
            $right .= $this->countdown_timer($attemptobj, time());
        }
        if ($mode === 'attempt') {
            $right .= html_writer::link($attemptobj->view_url(), get_string('back'),
                ['class' => 'sa-qah-back']);
        }

        $rightdiv = $right !== '' ? html_writer::div($right, 'sa-qah-right') : '';
        return html_writer::div($tile . $meta . $rightdiv, 'sa-qah');
    }

    /**
     * Count answered vs total real questions, plus the first question number on
     * the given page.
     *
     * @param quiz_attempt $attemptobj
     * @param int $page
     * @return array [answered, total, firstquestionnumber]
     */
    protected function baz_quiz_progress($attemptobj, $page = 0) {
        $total = 0;
        $answered = 0;
        foreach ($attemptobj->get_slots() as $slot) {
            if (!$attemptobj->is_real_question($slot)) {
                continue;
            }
            $total++;
            $cls = $attemptobj->get_question_state_class($slot, false);
            if ($cls !== '' && $cls !== 'notyetanswered') {
                $answered++;
            }
        }
        $qnum = 1;
        try {
            $pageslots = $attemptobj->get_slots($page);
            foreach ($pageslots as $slot) {
                if ($attemptobj->is_real_question($slot)) {
                    $qnum = $attemptobj->get_question_number($slot);
                    break;
                }
            }
        } catch (\Throwable $e) {
            $qnum = 1;
        }
        return [$answered, $total, $qnum];
    }

    /**
     * Grade chip for the review header ("X / Y"), or '' if not gradable.
     *
     * @param quiz_attempt $attemptobj
     * @return string
     */
    protected function baz_quiz_grade_chip($attemptobj) {
        $quiz = $attemptobj->get_quiz();
        if (empty($quiz->grade) || (float) $quiz->grade == 0.0) {
            return '';
        }
        $attempt = $attemptobj->get_attempt();
        if (!isset($attempt->sumgrades) || is_null($attempt->sumgrades)) {
            return '';
        }
        $grade = quiz_rescale_grade($attempt->sumgrades, $quiz, false);
        $num = quiz_format_grade($quiz, $grade);
        $max = quiz_format_grade($quiz, $quiz->grade);
        return html_writer::div(
            html_writer::span($num, 'sa-qah-gnum') .
            html_writer::span('/ ' . $max, 'sa-qah-gmax'),
            'sa-qah-grade');
    }

    /**
     * The quiz attempt page — bespoke header (with timer + back) for students.
     */
    public function attempt_page($attemptobj, $page, $accessmanager, $messages, $slots, $id, $nextpage) {
        if (!$this->baz_is_bespoke()) {
            return parent::attempt_page($attemptobj, $page, $accessmanager, $messages, $slots, $id, $nextpage);
        }
        $output = '';
        $output .= $this->header();
        $hdr = $this->baz_quiz_header($attemptobj, 'attempt', $page);
        $output .= $hdr;
        if ($hdr === '') {
            // Header failed to build — render the timer standalone so it is never
            // lost on a timed quiz.
            $output .= $this->countdown_timer($attemptobj, time());
        }
        $output .= $this->quiz_notices($messages);
        $output .= $this->attempt_form($attemptobj, $page, $slots, $id, $nextpage);
        $output .= $this->footer();
        return $output;
    }

    /**
     * The pre-submit summary page — bespoke header for students.
     */
    public function summary_page($attemptobj, $displayoptions) {
        if (!$this->baz_is_bespoke()) {
            return parent::summary_page($attemptobj, $displayoptions);
        }
        $output = '';
        $output .= $this->header();
        $hdr = $this->baz_quiz_header($attemptobj, 'summary');
        $output .= $hdr;
        if ($hdr === '') {
            $output .= $this->countdown_timer($attemptobj, time());
        }
        $output .= $this->summary_table($attemptobj, $displayoptions);
        $output .= $this->summary_page_controls($attemptobj);
        $output .= $this->footer();
        return $output;
    }

    /**
     * The post-submit review page — bespoke header for students.
     */
    public function review_page(quiz_attempt $attemptobj, $slots, $page, $showall,
            $lastpage, $displayoptions, $summarydata) {
        if (is_array($summarydata)) {
            $summarydata = $this->filter_review_summary_table($summarydata, $page);
            $summarydata = \mod_quiz\output\attempt_summary_information::create_from_legacy_array($summarydata);
        }
        if (!$this->baz_is_bespoke()) {
            return parent::review_page($attemptobj, $slots, $page, $showall, $lastpage, $displayoptions, $summarydata);
        }
        $output = '';
        $output .= $this->header();
        $output .= $this->baz_quiz_header($attemptobj, 'review');
        $output .= $this->review_attempt_summary($summarydata, $page);
        $output .= $this->review_form($page, $showall, $displayoptions,
            $this->questions($attemptobj, true, $slots, $page, $showall, $displayoptions),
            $attemptobj);
        $output .= $this->review_next_navigation($attemptobj, $page, $lastpage, $showall);
        $output .= $this->footer();
        return $output;
    }

    /**
     * Generates the table of summarydata
     *
     * @param quiz_attempt $attemptobj
     * @param mod_quiz_display_options $displayoptions
     */
    public function summary_table($attemptobj, $displayoptions) {
        // Prepare the summary table header.
        $table = new html_table();
        $table->attributes['class'] = 'generaltable quizsummaryofattempt';
        $table->head = [get_string('question', 'quiz'), get_string('status', 'quiz')];
        $table->align = ['left', 'left'];
        $table->size = ['', ''];
        $markscolumn = $displayoptions->marks >= question_display_options::MARK_AND_MAX;
        if ($markscolumn) {
            $table->head[] = get_string('marks', 'quiz');
            $table->align[] = 'left';
            $table->size[] = '';
        }
        $tablewidth = count($table->align);
        $table->data = [];

        // Get the summary info for each question.
        $slots = $attemptobj->get_slots();
        foreach ($slots as $slot) {
            // Add a section headings if we need one here.
            $heading = $attemptobj->get_heading_before_slot($slot);

            if ($heading !== null) {
                // There is a heading here.
                $rowclasses = 'quizsummaryheading';
                if ($heading) {
                    $heading = format_string($heading);
                } else if (count($attemptobj->get_quizobj()->get_sections()) > 1) {
                    // If this is the start of an unnamed section, and the quiz has more
                    // than one section, then add a default heading.
                    $heading = get_string('sectionnoname', 'quiz');
                    $rowclasses .= ' dimmed_text';
                }
                $cell = new html_table_cell(format_string($heading));
                $cell->header = true;
                $cell->colspan = $tablewidth;
                $table->data[] = [$cell];
                $table->rowclasses[] = $rowclasses;
            }

            // Don't display information items.
            if (!$attemptobj->is_real_question($slot)) {
                continue;
            }

            $flag = '';

            // Real question, show it.
            if ($attemptobj->is_question_flagged($slot)) {
                // Quiz has custom JS manipulating these image tags - so we can't use the pix_icon method here.
                $flag = '<svg class="ml-2"
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >
                <path d="M4.75 5.75V19.25"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round">
                </path>
                <path d="M4.75 15.25V5.75C4.75 5.75 6 4.75 9 4.75C12 4.75 13.5 6.25 16 6.25C18.5
                6.25 19.25 5.75 19.25 5.75L15.75 10.5L19.25 15.25C19.25 15.25 18.5 16.25 16
                16.25C13.5 16.25 11.5 14.25 9 14.25C6.5 14.25 4.75 15.25 4.75 15.25Z"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"></path>
                </svg>';
            }
            if ($attemptobj->can_navigate_to($slot)) {
                $row = [
                    html_writer::link(
                        $attemptobj->attempt_url($slot),
                        $attemptobj->get_question_number($slot) . $flag
                    ),
                    $attemptobj->get_question_status($slot, $displayoptions->correctness),
                ];
            } else {
                $row = [
                    $attemptobj->get_question_number($slot) . $flag,
                    $attemptobj->get_question_status($slot, $displayoptions->correctness),
                ];
            }
            if ($markscolumn) {
                $row[] = $attemptobj->get_question_mark($slot);
            }
            $table->data[] = $row;
            $table->rowclasses[] = 'quizsummary' . $slot . ' ' . $attemptobj->get_question_state_class(
                $slot,
                $displayoptions->correctness
            );
        }

        // Print the summary table.
        $output = html_writer::table($table);

        return $output;
    }
}

require_once($CFG->dirroot . "/question/engine/renderer.php");

/**
 * theme_baz_core_question_renderer
 */
class theme_baz_core_question_renderer extends core_question_renderer {
    /**
     * Generate the information bit of the question display that contains the
     * metadata like the question number, current state, and mark.
     * @param question_attempt $qa the question attempt to display.
     * @param qbehaviour_renderer $behaviouroutput the renderer to output the behaviour
     *      specific parts.
     * @param qtype_renderer $qtoutput the renderer to output the question type
     *      specific parts.
     * @param question_display_options $options controls what should and should not be displayed.
     * @param string|null $number The question number to display. 'i' is a special
     *      value that gets displayed as Information. Null means no number is displayed.
     * @return HTML fragment.
     */
    protected function info(
        question_attempt $qa,
        qbehaviour_renderer $behaviouroutput,
        qtype_renderer $qtoutput,
        question_display_options $options,
        $number
    ) {
        $output = '';
        $output .= '<div class="d-flex align-items-center flex-wrap mb-sm-2 mb-md-0">' .
            $this->number($number) .
            '<div class="d-inline-flex align-items-center flex-wrap">' .
            $this->status($qa, $behaviouroutput, $options) .
            $this->mark_summary($qa, $behaviouroutput, $options) .
            '</div></div>';
        $output .= '<div>' .
            $this->question_flag($qa, $options->flags) .
            $this->edit_question_link($qa, $options) .
            '</div>';
        return $output;
    }

    /**
     * Generate the display of the question number.
     * @param string|null $number The question number to display. 'i' is a special
     *      value that gets displayed as Information. Null means no number is displayed.
     * @return HTML fragment.
     */
    protected function number($number) {
        if (trim($number) === '') {
            return '';
        }
        $numbertext = '';
        if (trim($number) === 'i') {
            $numbertext = get_string('information', 'question');
        } else {
            $numbertext = get_string(
                'questionx',
                'question',
                html_writer::tag('span', $number, ['class' => 'rui-qno'])
            );
        }
        return html_writer::tag('h4', $numbertext, ['class' => 'h3 w-100 mb-2']);
    }


    /**
     * Generate the display of the status line that gives the current state of
     * the question.
     * @param question_attempt $qa the question attempt to display.
     * @param qbehaviour_renderer $behaviouroutput the renderer to output the behaviour
     *      specific parts.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return HTML fragment.
     */
    protected function status(
        question_attempt $qa,
        qbehaviour_renderer $behaviouroutput,
        question_display_options $options
    ) {
        return html_writer::tag(
            'div',
            $qa->get_state_string($options->correctness),
            ['class' => 'state mr-2 my-2']
        );
    }

    /**
     * Render the question flag, assuming $flagsoption allows it.
     *
     * @param question_attempt $qa the question attempt to display.
     * @param int $flagsoption the option that says whether flags should be displayed.
     */
    protected function question_flag(question_attempt $qa, $flagsoption) {
        global $CFG;

        $divattributes = ['class' => 'questionflag mx-1 d-none'];

        switch ($flagsoption) {
            case question_display_options::VISIBLE:
                $flagcontent = $this->get_flag_html($qa->is_flagged());
                break;

            case question_display_options::EDITABLE:
                $id = $qa->get_flag_field_name();
                $checkboxattributes = [
                    'type' => 'checkbox',
                    'id' => $id . 'checkbox',
                    'name' => $id,
                    'value' => 1,
                ];
                if ($qa->is_flagged()) {
                    $checkboxattributes['checked'] = 'checked';
                }
                $postdata = question_flags::get_postdata($qa);

                $flagcontent = html_writer::empty_tag(
                    'input',
                    ['type' => 'hidden', 'name' => $id, 'value' => 0]
                ) .
                    html_writer::empty_tag('input', $checkboxattributes) .
                    html_writer::empty_tag(
                        'input',
                        ['type' => 'hidden', 'value' => $postdata, 'class' => 'questionflagpostdata']
                    ) .
                    html_writer::tag(
                        'label',
                        $this->get_flag_html($qa->is_flagged(), $id . 'img'),
                        ['id' => $id . 'label', 'for' => $id . 'checkbox']
                    ) . "\n";

                $divattributes = [
                    'class' => 'questionflag mb-sm-2 mb-md-0 mx-md-2 editable d-inline-flex',
                    'aria-atomic' => 'true',
                    'aria-relevant' => 'text',
                    'aria-live' => 'assertive',
                ];

                break;

            default:
                $flagcontent = '';
        }

        return html_writer::nonempty_tag('div', $flagcontent, $divattributes);
    }

    /**
     * Generate the display of the edit question link.
     *
     * @param question_attempt $qa The question attempt to display.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return string
     */
    protected function edit_question_link(question_attempt $qa, question_display_options $options) {
        if (empty($options->editquestionparams)) {
            return '';
        }

        $params = $options->editquestionparams;
        if ($params['returnurl'] instanceof moodle_url) {
            $params['returnurl'] = $params['returnurl']->out_as_local_url(false);
        }
        $params['id'] = $qa->get_question_id();
        $editurl = new moodle_url('/question/bank/editquestion/question.php', $params);

        return html_writer::tag('div', html_writer::link(
            $editurl, $this->pix_icon('t/edit', get_string('edit'), '', ['class' => 'iconsmall']) .
            get_string('editquestion', 'question'),
                ['class' => 'btn btn-sm btn-secondary ml-2']),
            ['class' => 'editquestion']);
    }
}

