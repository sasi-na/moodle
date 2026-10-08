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
 * Bespoke "Interest Zone" (course category) exporter for theme_baz.
 *
 * Renders the courses in a category as club cards for student-only users. Used
 * for the theme custom-nav "Interest Zone" item, which points at a category
 * (JAIN Online Clubs). Course enrolment/visibility is untouched — cards link to
 * each course's own view page.
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

require_once($GLOBALS['CFG']->dirroot . '/course/lib.php');

/**
 * Renderable + templatable for the bespoke Interest Zone category page.
 */
class student_interestzone implements renderable, templatable {

    /** @var int The category being rendered. */
    protected $categoryid;

    /** @var array Cover gradient for even-indexed cards (deep royal blue) — matches My courses. */
    const COVER_EVEN = ['#1B4099', '#0D2150'];

    /** @var array Cover gradient for odd-indexed cards (sky blue) — matches My courses. */
    const COVER_ODD = ['#5B9BFF', '#2563EB'];

    /**
     * @param int $categoryid
     */
    public function __construct(int $categoryid) {
        $this->categoryid = $categoryid;
    }

    /**
     * A small set of club-ish glyphs cycled across cards (inner SVG markup).
     *
     * @param int $i
     * @return string
     */
    protected function icon(int $i): string {
        $icons = [
            '<path d="M12 2l2.4 5 5.6.8-4 4 1 5.6L12 20l-5 2.4 1-5.6-4-4 5.6-.8z"/>',
            '<path d="M4 19V5a1 1 0 0 1 1-1h10l4 4v11a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1z"/><path d="M8 8h6M8 12h8M8 16h5"/>',
            '<path d="M12 21c-4-3-7-6-7-10a4 4 0 0 1 7-2 4 4 0 0 1 7 2c0 4-3 7-7 10z"/>',
            '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
            '<path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
            '<path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/>',
            '<path d="M8 9l-4 3 4 3M16 9l4 3-4 3M13 6l-2 12"/>',
            '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        ];
        return $icons[$i % count($icons)];
    }

    /**
     * Export context for theme_baz/student-interestzone.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $CFG, $USER;

        $category = \core_course_category::get($this->categoryid, IGNORE_MISSING, true);
        $catname = $category ? format_string($category->get_formatted_name()) : get_string('courses');
        $catdesc = '';
        if ($category && trim(strip_tags((string) $category->description)) !== '') {
            $catdesc = trim(format_text($category->description, $category->descriptionformat,
                ['context' => \context_coursecat::instance($this->categoryid), 'noclean' => true]));
        }

        // Visible courses in this category (respecting the user's view rights).
        $cards = [];
        $i = 0;
        if ($category) {
            $courses = $category->get_courses(['recursive' => false, 'sort' => ['sortorder' => 1]]);
            foreach ($courses as $course) {
                if (!$course->visible && !$course->can_access()) {
                    continue;
                }
                $summary = '';
                if (trim(strip_tags((string) $course->summary)) !== '') {
                    $summary = trim(html_to_text(format_text($course->summary, $course->summaryformat,
                        ['context' => \context_course::instance($course->id), 'noclean' => false]), 0, false));
                }
                $enrolled = is_enrolled(\context_course::instance($course->id), $USER->id, '', true);

                // Course overview image, if any.
                $imageurl = '';
                try {
                    foreach ($course->get_course_overviewfiles() as $f) {
                        if ($f->is_valid_image()) {
                            $imageurl = \moodle_url::make_pluginfile_url(
                                $f->get_contextid(), $f->get_component(), $f->get_filearea(),
                                $f->get_itemid() ?: null, $f->get_filepath(), $f->get_filename())->out(false);
                            break;
                        }
                    }
                } catch (\Throwable $e) {
                    $imageurl = '';
                }

                // Two-colour cover, alternating even/odd (matches My courses).
                $palette = ($i % 2 === 0) ? self::COVER_EVEN : self::COVER_ODD;

                $cards[] = [
                    'id'       => $course->id,
                    'name'     => format_string($course->get_formatted_name()),
                    'desc'     => $summary,
                    'hasdesc'  => $summary !== '',
                    'imageurl' => $imageurl,
                    'hasimage' => $imageurl !== '',
                    'gradfrom' => $palette[0],
                    'gradto'   => $palette[1],
                    'iconsvg'  => $this->icon($i),
                    'url'      => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                    'enrolled' => (bool) $enrolled,
                    'i'        => $i,
                ];
                $i++;
            }

            // Append visible subcategories as category cards. Unlike course cards
            // (which link to a single course), these open the subcategory listing
            // where each contained course is individually self-enrollable — this is
            // how "hub" groupings (e.g. CRCE, Career Readiness) surface on the grid.
            try {
                foreach ($category->get_children() as $child) {
                    $cdesc = '';
                    if (trim(strip_tags((string) $child->description)) !== '') {
                        $cdesc = trim(html_to_text(format_text($child->description,
                            $child->descriptionformat ?? FORMAT_HTML,
                            ['context' => \context_coursecat::instance($child->id), 'noclean' => false]), 0, false));
                    }
                    $palette = ($i % 2 === 0) ? self::COVER_EVEN : self::COVER_ODD;
                    $cards[] = [
                        'id'         => 0,
                        'name'       => format_string($child->get_formatted_name()),
                        'desc'       => $cdesc,
                        'hasdesc'    => $cdesc !== '',
                        'imageurl'   => '',
                        'hasimage'   => false,
                        'gradfrom'   => $palette[0],
                        'gradto'     => $palette[1],
                        'iconsvg'    => $this->icon($i),
                        'url'        => (new moodle_url('/course/index.php', ['categoryid' => $child->id]))->out(false),
                        'enrolled'   => false,
                        'iscategory' => true,
                        'i'          => $i,
                    ];
                    $i++;
                }
            } catch (\Throwable $e) {
                // Non-fatal: fall back to course-only cards.
                debugging('theme_baz interestzone subcategory cards: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        // e-ID card visibility mirrors the other bespoke pages.
        $showeidcard = false;
        if (class_exists('\local_studentid\manager')) {
            try {
                $showeidcard = \local_studentid\manager::is_eligible_batch($USER->id);
            } catch (\Throwable $e) {
                $showeidcard = false;
            }
        }

        return [
            'config'        => ['wwwroot' => $CFG->wwwroot],
            'categoryname'  => $catname,
            'categorydesc'  => $catdesc,
            'hasdesc'       => $catdesc !== '',
            'clubs'         => $cards,
            'hasclubs'      => !empty($cards),
            'clubcount'     => count($cards),
            // Nav.
            'dashboardurl'  => (new moodle_url('/my/'))->out(false),
            'coursesurl'    => (new moodle_url('/my/courses.php'))->out(false),
            'calendarurl'   => (new moodle_url('/calendar/view.php'))->out(false),
            'gradesurl'     => (new moodle_url('/grade/report/overview/index.php'))->out(false),
            'filesurl'      => (new moodle_url('/user/files.php'))->out(false),
            'showeidcard'   => $showeidcard,
            'eidcardurl'    => (new moodle_url('/local/studentid/index.php'))->out(false),
        ] + theme_baz_interestzone_ctx();
    }
}
