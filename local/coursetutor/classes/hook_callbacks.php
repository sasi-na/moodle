<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_coursetutor;

/**
 * Hook callbacks.
 *
 * @package   local_coursetutor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /**
     * Add the tutor drawer to course pages.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     */
    public static function before_footer(\core\hook\output\before_footer_html_generation $hook): void {
        global $COURSE, $PAGE;
        try {
            if (!get_config('local_aidock', 'coursetutorondock') || during_initial_install() || !isloggedin() || isguestuser() || empty($COURSE->id) || $COURSE->id <= 1) {
                return;
            }
            if (in_array($PAGE->pagelayout, ['embedded', 'popup', 'frametop', 'maintenance', 'print', 'secure', 'redirect', 'login'], true)
                    || strpos((string) $PAGE->pagetype, 'mod-quiz-attempt') === 0
                    || strpos((string) $PAGE->pagetype, 'local-coursetutor') === 0) {
                return;
            }
            $context = \context_course::instance($COURSE->id);
            if (!has_capability('local/coursetutor:use', $context)) {
                return;
            }
            $hook->add_html(ui::panel(tutor::for_course((int) $COURSE->id), (int) $COURSE->id, false));
        } catch (\Throwable $e) {
            debugging('local_coursetutor: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
