<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_coursetutor;

/**
 * Collects a course's readable learning material as plain text for the tutor.
 *
 * @package   local_coursetutor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content {

    /** Maximum characters of course material sent with each question. */
    const MAXCHARS = 60000;

    /**
     * Course material visible to the given user.
     *
     * @param int $courseid
     * @param int $userid
     * @return string
     */
    public static function for_course(int $courseid, int $userid): string {
        global $DB;
        $course = get_course($courseid);
        $out = [];
        $out[] = '# Course: ' . format_string($course->fullname);
        $out[] = self::clean($course->summary);

        $modinfo = get_fast_modinfo($course, $userid);
        foreach ($modinfo->get_section_info_all() as $section) {
            if (!$section->uservisible) {
                continue;
            }
            $out[] = '## ' . get_section_name($course, $section);
            $out[] = self::clean($section->summary);
            foreach ($modinfo->sections[$section->section] ?? [] as $cmid) {
                $cm = $modinfo->cms[$cmid];
                if (!$cm->uservisible) {
                    continue;
                }
                $line = '### ' . $cm->modfullname . ': ' . format_string($cm->name);
                $intro = $DB->get_field($cm->modname, 'intro', ['id' => $cm->instance], IGNORE_MISSING);
                $body = self::clean((string) $intro);
                if ($cm->modname === 'page') {
                    $body .= "\n" . self::clean((string) $DB->get_field('page', 'content', ['id' => $cm->instance]));
                } else if ($cm->modname === 'book') {
                    $chapters = $DB->get_records('book_chapters', ['bookid' => $cm->instance, 'hidden' => 0], 'pagenum', 'title, content');
                    foreach ($chapters as $ch) {
                        $body .= "\n" . format_string($ch->title) . ': ' . self::clean($ch->content);
                    }
                } else if ($cm->modname === 'label') {
                    $line = '### Note';
                }
                $out[] = $line . "\n" . trim($body);
                if (strlen(implode("\n", $out)) > self::MAXCHARS) {
                    break 2;
                }
            }
        }
        $text = preg_replace("/\n{3,}/", "\n\n", implode("\n\n", array_filter($out, 'strlen')));
        return \core_text::substr($text, 0, self::MAXCHARS);
    }

    /**
     * HTML to plain text.
     *
     * @param string|null $html
     * @return string
     */
    private static function clean(?string $html): string {
        return trim(html_to_text((string) $html, 0, false));
    }
}
