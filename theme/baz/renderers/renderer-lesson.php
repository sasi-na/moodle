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

require_once($CFG->dirroot . "/mod/lesson/renderer.php");

/**
 * Lesson.
 *
 */
class theme_baz_mod_lesson_renderer extends mod_lesson_renderer {

    /**
     * Returns HTML for a lesson inaccessible message
     *
     * @param string $message
     * @return <type>
     */
    public function lesson_inaccessible($message) {
        global $CFG;
        $output = $this->output->box_start('generalbox boxaligncenter my-2 w-100');
        $output .= $this->output->box_start('center my-2 w-100');
        $output .= $message;
        $output .= $this->output->box('<a class="btn btn-sm btn-secondary" href="' .
            $CFG->wwwroot .
            '/course/view.php?id=' .
            $this->page->course->id .
            '">' .
            get_string('returnto', 'lesson', format_string($this->page->course->fullname, true)) .
            '</a>', ' btn btn-sm btn-secondary');
        $output .= $this->output->box_end();
        $output .= $this->output->box_end();
        return $output;
    }

    /**
     * Returns HTML to prompt the user to log in
     * @param lesson $lesson
     * @param bool $failedattempt
     * @return string
     */
    public function login_prompt(lesson $lesson, $failedattempt = false) {
        global $CFG;
        $output = $this->output->box_start('password-form');
        $output .= $this->output->box_start('generalbox boxaligncenter my-2 w-100');
        $output .= '<form id="password" method="post" action="' .
            $CFG->wwwroot .
            '/mod/lesson/view.php" autocomplete="off">';
        $output .= '<fieldset class="invisiblefieldset center">';
        $output .= '<input type="hidden" name="id" value="' .
            $this->page->cm->id .
            '" />';
        $output .= '<input type="hidden" name="sesskey" value="' .
            sesskey() .
            '" />';
        if ($failedattempt) {
            $output .= $this->output->notification(get_string('loginfail', 'lesson'));
        }
        $output .= get_string('passwordprotectedlesson', 'lesson', format_string($lesson->name)) . '<br /><br />';
        $output .= get_string('enterpassword', 'lesson') .
            " <input type=\"password\" name=\"userpassword\" /><br /><br />";
        $output .= "<input class='btn btn-sm btn-primary' type='submit' value='" .
            get_string('continue', 'lesson') .
            "' />";
        $output .= "<input class='btn btn-sm btn-danger' type='submit' name='backtocourse' value='" .
            get_string('cancel', 'lesson') . "' />";
        $output .= '</fieldset></form>';
        $output .= $this->output->box_end();
        $output .= $this->output->box_end();
        return $output;
    }

    /**
     * Returns HTML to display a continue button
     * @param lesson $lesson
     * @param int $lastpageseen
     * @return string
     */
    public function continue_links(lesson $lesson, $lastpageseenid) {
        global $CFG;
        $output = $this->output->box(get_string('youhaveseen', 'lesson'), 'generalbox boxaligncenter m-0 p-0 w-100');

        $yeslink = html_writer::link(new moodle_url('/mod/lesson/view.php', [
            'id' => $this->page->cm->id,
            'pageid' => $lastpageseenid,
            'startlastseen' => 'yes',
        ]), get_string('yes'), ['class' => 'btn btn-sm btn-primary']);
        $output .= html_writer::tag('span', $yeslink, ['class' => 'lessonbutton']);
        $output .= '&nbsp;';

        $nolink = html_writer::link(new moodle_url('/mod/lesson/view.php', [
            'id' => $this->page->cm->id,
            'pageid' => $lesson->firstpageid,
            'startlastseen' => 'no',
        ]), get_string('no'), ['class' => 'btn btn-sm btn-secondary']);
        $output .= html_writer::tag('span', $nolink, ['class' => 'lessonbutton']);

        return $output;
    }


    /**
     * Returns HTML to display a message
     * @param string $message
     * @param single_button $button
     * @return string
     */
    public function message($message, single_button $button = null) {
        $output = $this->output->box_start('generalbox boxaligncenter wrapper-fw');
        $output .= $message;
        if ($button !== null) {
            $output .= $this->output->box($this->output->render($button), 'lessonbutton');
        }
        $output .= $this->output->box_end();
        return $output;
    }


    /**
     * Returns the HTML for displaying the end of lesson page.
     *
     * @param  lesson $lesson lesson instance
     * @param  stdclass $data lesson data to be rendered
     * @return string         HTML contents
     */
    public function display_eol_page(lesson $lesson, $data) {
        // Bespoke student experience only — fall back to core for teachers/others.
        try {
            $bespoke = \theme_baz\local\experience::is_on('activity');
        } catch (\Throwable $e) {
            $bespoke = false;
        }
        if (!$bespoke) {
            return parent::display_eol_page($lesson, $data);
        }
        try {
            return $this->baz_eol_page($lesson, $data);
        } catch (\Throwable $e) {
            debugging('theme_baz lesson eol failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return parent::display_eol_page($lesson, $data);
        }
    }

    /**
     * Bespoke end-of-lesson completion card.
     *
     * @param lesson $lesson
     * @param stdClass $data
     * @return string
     */
    protected function baz_eol_page(lesson $lesson, $data): string {
        $course = $lesson->courserecord;

        // Header (green completion banner).
        $heading = $data->gradelesson
            ? get_string('congratulations', 'lesson')
            : get_string('sd_l_complete', 'theme_baz');
        $icon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 5 5L20 6"/></svg>';
        $top = html_writer::div(
            html_writer::tag('span', $icon, ['class' => 'baz-les-eol-ic'])
            . html_writer::tag('h3', s($heading))
            . html_writer::tag('p', s(get_string('sd_l_complete_sub', 'theme_baz', format_string($lesson->name)))),
            'baz-les-eol-top');

        // Body: the meaningful summary lines from $data.
        $line = function($html) {
            return html_writer::div($html, 'baz-les-eol-line');
        };
        $body = '';
        if ($data->numberofpagesviewed !== false) {
            $body .= $line(get_string('numberofpagesviewed', 'lesson', $data->numberofpagesviewed));
        }
        if ($data->youshouldview !== false) {
            $body .= $line(get_string('youshouldview', 'lesson', $data->youshouldview));
        }
        if ($data->numberofcorrectanswers !== false) {
            $body .= $line(get_string('numberofcorrectanswers', 'lesson', $data->numberofcorrectanswers));
        }
        if ($data->displayscorewithessays !== false) {
            $body .= $line(get_string('displayscorewithessays', 'lesson', $data->displayscorewithessays));
        } else if ($data->displayscorewithoutessays !== false) {
            $body .= $line(get_string('displayscorewithoutessays', 'lesson', $data->displayscorewithoutessays));
        }
        if ($data->yourcurrentgradeisoutof !== false) {
            $body .= $line(get_string('yourcurrentgradeisoutof', 'lesson', $data->yourcurrentgradeisoutof));
        }
        if ($data->welldone !== false) {
            $body .= $line(get_string('welldone', 'lesson'));
        }
        if ($data->notenoughtimespent !== false) {
            $body .= $line(get_string('notenoughtimespent', 'lesson', $data->notenoughtimespent));
        }
        if ($data->eolstudentoutoftimenoanswers !== false) {
            $body .= $line(get_string('eolstudentoutoftimenoanswers', 'lesson'));
        }
        if ($data->progresscompleted !== false) {
            $body .= html_writer::div($this->progress_bar($lesson, $data->progresscompleted), 'baz-les-eol-line');
        }
        if ($data->displayofgrade !== false) {
            $body .= $line(get_string('displayofgrade', 'lesson'));
        }
        if ($data->modattemptsnoteacher !== false) {
            $body .= $line(get_string('modattemptsnoteacher', 'lesson'));
        }
        $bodyhtml = $body !== '' ? html_writer::div($body, 'baz-les-eol-body') : '';

        // Actions.
        $actions = '';
        if ($data->reviewlesson !== false) {
            $actions .= html_writer::link($data->reviewlesson, get_string('reviewlesson', 'lesson'),
                ['class' => 'btn btn-primary']);
        }
        if (has_capability('gradereport/user:view', context_course::instance($course->id))
                && $course->showgrades && $lesson->grade != 0 && !$lesson->practice) {
            $actions .= html_writer::link(new moodle_url('/grade/index.php', ['id' => $course->id]),
                get_string('viewgrades', 'lesson'), ['class' => 'btn btn-outline-primary']);
        }
        $actions .= html_writer::link(new moodle_url('/course/view.php', ['id' => $course->id]),
            get_string('returnto', 'lesson', format_string($course->fullname, true)),
            ['class' => 'btn btn-secondary']);
        $actionshtml = html_writer::div($actions, 'baz-les-eol-actions');

        // The activity link (Next activity), if any, sits below the card.
        $extra = ($data->activitylink !== false) ? html_writer::div($data->activitylink, 'text-center mt-3') : '';

        return html_writer::div($top . $bodyhtml . $actionshtml, 'baz-les-eol') . $extra;
    }
}
