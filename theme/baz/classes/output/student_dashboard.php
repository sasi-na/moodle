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
 * Student dashboard exporter for theme_baz.
 *
 * Assembles all widget data for the bespoke student dashboard server-side, so the
 * mustache template stays logic-free. Risky data sources (calendar, Level Up XP)
 * are wrapped so a failure degrades to an empty widget rather than a fatal error.
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
use theme_baz\local\semester;
use theme_baz\local\dashboard_news;
use theme_baz\local\exam_batch;

defined('MOODLE_INTERNAL') || die();

/**
 * Renderable + templatable for the student dashboard.
 */
class student_dashboard implements renderable, templatable {

    /** @var int Modules treated as "live classes" rather than deadlines. */
    const LIVE_MODULES = ['zoom', 'bigbluebuttonbn', 'customteams', 'teams', 'msteams', 'jitsi', 'collaborate'];

    /**
     * @var array Hosts we will hand a learner straight to from the dashboard.
     * Timetabled live classes carry their join link in the event description,
     * which is teacher-authored, so only these hosts are ever linked directly.
     */
    const MEETING_HOSTS = ['teams.microsoft.com', 'teams.live.com', 'meet.google.com', 'zoom.us'];

    /** @var int Course cards shown on the dashboard; the rest sit behind "All N courses". */
    const MAX_COURSE_CARDS = 6;

    /** @var array Resolved course modules, keyed "modname:instance", to keep the month grid cheap. */
    protected $cmcache = [];

    /** @var array Course-cover gradient for even-indexed cards (deep royal blue). */
    const COVER_EVEN = ['#1B4099', '#0D2150'];

    /** @var array Course-cover gradient for odd-indexed cards (sky blue). */
    const COVER_ODD = ['#5B9BFF', '#2563EB'];

    /** @var array Three base brand colours, cycled for card "logos" (dark blue, light blue, gold). */
    const LOGO_CYCLE = ['#1B4099', '#5B9BFF', 'var(--bz-accent)'];

    /**
     * Export all context for theme_baz/student-dashboard.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $USER, $CFG;

        $userid = (int) $USER->id;
        $now = time();
        $weekend = $now + (7 * DAYSECS);

        // --- Courses (fetched once), scoped to the current (highest) semester. ---
        [$semcourses, $highestsem, $shortnames, $searchcourses] = $this->get_courses($userid);
        [$coursecards, $ringdata] = $this->build_course_cards($semcourses, $userid);

        // --- Calendar: deadlines + live classes for the next 7 days. Reuses the
        // already-fetched enrolment (course ids + shortnames) — no second query. ---
        [$deadlinedays, $liveclassdays, $deadlinecount, $liveclasscount, $upnext, $duedays, $nextlive] =
            $this->get_calendar($userid, $now, $weekend, array_keys($shortnames), $shortnames);

        // --- Greeting. ---
        $userdate = usergetdate($now);
        $hour = (int) $userdate['hours'];
        if ($hour < 12) {
            $greeting = get_string('sd_morning', 'theme_baz');
        } else if ($hour < 17) {
            $greeting = get_string('sd_afternoon', 'theme_baz');
        } else {
            $greeting = get_string('sd_evening', 'theme_baz');
        }

        // --- Recently accessed items. ---
        $recentitems = $this->get_recent_items();

        // --- Fee / Renewals. ---
        $fee = $this->get_fee($userid);

        // --- Level Up XP. ---
        $xp = $this->get_levelup($userid);

        // --- Student e-ID Card (nav). ---
        $showeidcard = false;
        if (class_exists('\local_studentid\manager')) {
            try {
                $showeidcard = \local_studentid\manager::is_eligible_batch($userid);
            } catch (\Throwable $e) {
                $showeidcard = false;
            }
        }

        // --- Mini calendar (current month: due dots + per-day event tooltips). ---
        $calendar = $this->build_mini_calendar($now, $this->get_month_events($userid, array_keys($shortnames), $now));

        // --- Real block-backed widgets (reuse the configured block instances). ---
        // News/podcast cards come from the shared cache filled by
        // theme_baz\task\refresh_dashboard_news. Never render the rss_client block
        // inline: it fetches up to ten third-party feeds over the network during page
        // render, and because this page holds the session lock, a slow feed stalls
        // every other request from the same user until it hits the lock timeout.
        $bnhtml = dashboard_news::get(dashboard_news::KEY_BUSINESS);
        $pchtml = dashboard_news::get(dashboard_news::KEY_PODCAST);
        $businessnewsitems = $bnhtml !== '' ? $this->parse_rss($bnhtml, 4) : [];
        $podcastitems = $pchtml !== '' ? $this->parse_rss($pchtml, 4) : [];
        $support = $this->render_block('support');
        $announcements = $this->render_block('news_items');
        // Ask JO: render the chat directly (log + input control bar) without
        // block_instance(), which would pull sibling content-region blocks onto the
        // page and queue their JS against DOM this layout never renders.
        $askjo = $this->build_askjo();

        // --- Active-day streak + 7-day activity sparkline (real, from the log). ---
        $streak = $this->get_streak($userid, $now);
        $sparkbars = $this->get_week_activity($userid, $now);

        // --- Donut (a single completion arc = avg %, so the ring matches the
        // centre number) + "focus next" course for the A+C progress widget. The
        // 0/in-progress/not-started mix lives in the chips, not the ring. ---
        $donutarcs = $this->build_completion_arc((int) $ringdata['avg']);
        $focus = $this->pick_focus_course($coursecards);

        // --- Client-side search suggestions: enrolled courses + recent items. ---
        $searchitems = $searchcourses;
        foreach ($recentitems as $ri) {
            $searchitems[] = ['label' => $ri['name'], 'sublabel' => $ri['coursename'], 'url' => $ri['url']];
        }

        // --- End-semester exam CTA. The bespoke dashboard renders no generic blocks,
        // so block_endsemesterexam never surfaces here; this exposes the same entry
        // point for the gated audience. ---
        $examctaurl = $this->get_endsem_exam_url($userid);

        // --- RemUI dashboard banner(s) (theme setting), shown at the top. ---
        $banners = $this->get_dashboard_banners();

        $context = [
            'greetingtime'   => $greeting,
            'firstname'      => ucwords(\core_text::strtolower($USER->firstname)),
            'datelabel'      => userdate($now, get_string('strftimedaydate', 'langconfig')),
            'semesternum'    => $highestsem,
            'hassemester'    => $highestsem !== null,
            'deadlinecount'  => $deadlinecount,
            'liveclasscount' => $liveclasscount,

            // Up next (nearest deadline) — JS computes the live countdown from upnextts.
            'hasupnext'      => !empty($upnext),
            'upnext'         => $upnext,

            // KPI strip.
            'kpicourses'     => count($coursecards),
            'kpiprogress'    => $ringdata['avg'],
            'kpideadlines'   => $deadlinecount,
            'kpiliveclasses' => $liveclasscount,

            // Progress widget (A+C): segmented donut + chips + sparkline + focus-next.
            'ringpct'        => $ringdata['avg'],
            'ringcompleted'  => $ringdata['completed'],
            'ringinprogress' => $ringdata['inprogress'],
            'ringnotstarted' => $ringdata['notstarted'],
            'donutarcs'      => $donutarcs,
            'sparkbars'      => $sparkbars,
            'focus'          => $focus,
            'hasfocus'       => !empty($focus),

            // Sections.
            'recentitems'    => $recentitems,
            'hasrecent'      => !empty($recentitems),
            // Only the first few cards are shown inline; "All N courses" carries the
            // true total and goes to My courses. KPIs, the progress ring and the focus
            // card are computed from every card, so they are unaffected by this cap.
            'courses'        => array_slice($coursecards, 0, self::MAX_COURSE_CARDS),
            'hascourses'     => !empty($coursecards),
            'coursecount'    => count($coursecards),
            'deadlinedays'   => $deadlinedays,
            'hasdeadlines'   => !empty($deadlinedays),
            'liveclassdays'  => $liveclassdays,
            'hasliveclasses' => !empty($liveclassdays),
            // The lower section is a fixed two-column grid. Deadlines and live
            // classes are the only things in the left column, and both are
            // common enough to be empty -- a student with a clear week got a
            // reserved 1.7fr of nothing beside the calendar. Told here so the
            // grid can collapse rather than hold the space open.
            'haslowerleft'   => !empty($deadlinedays) || !empty($liveclassdays),

            // Widgets.
            'fee'            => $fee,
            'hasfee'         => !empty($fee),
            'xp'             => $xp,
            'hasxp'          => !empty($xp),
            'calendar'       => $calendar,

            // Real block-backed widgets, rendered in the dashboard's own markup.
            'support_html'      => $support ? $support['html'] : null,
            'hassupport'        => !empty($support),
            'announcements_html' => $announcements ? $announcements['html'] : null,
            'hasannouncements'  => !empty($announcements),
            'businessnews'      => $businessnewsitems,
            'hasbusinessnews'   => !empty($businessnewsitems),
            'podcasts'          => $podcastitems,
            'haspodcasts'       => !empty($podcastitems),

            // Active-day streak.
            'streak'         => $streak,
            'hasstreak'      => $streak > 0,

            // Ask JO chat (log + input control bar) for the toggled panel.
            'askjo'          => $askjo,
            'hasaskjo'       => !empty($askjo),

            // Student e-ID Card.
            'showeidcard'    => $showeidcard,
            'eidcardurl'     => (new moodle_url('/local/studentid/index.php'))->out(false),

            // End-semester exam CTA.
            'showexamcta'    => $examctaurl !== null,
            'examctaurl'     => $examctaurl,

            // Dashboard banner(s) (top of content).
            'banners'        => $banners,
            'hasbanner'      => !empty($banners),
            'bannermulti'    => count($banners) > 1,
            'bannerdelay'    => max(2000, (int) (get_config('theme_baz', 'dashboardbannerdelay') ?: 5000)),

            // Client-side search autosuggest data.
            'searchitemsjson' => json_encode($searchitems),

            // Quick actions.
            'resumeitem'     => !empty($recentitems) ? $recentitems[0] : null,
            'coursesurl'     => (new moodle_url('/my/courses.php'))->out(false),
            'calendarurl'    => (new moodle_url('/calendar/view.php'))->out(false),
            // Subscribing to the calendar from Google/Outlook is core Moodle, but
            // it is only reachable from the classic calendar block, which this
            // layout does not render -- so students lost it. Same setting core
            // checks; empty when the site has calendar export switched off.
            'calendarexporturl' => !empty($CFG->enablecalendarexport)
                ? (new moodle_url('/calendar/export.php'))->out(false)
                : '',
            // "Join next class" goes straight to the nearest live class when that
            // class has a join link, and falls back to the calendar when it does not.
            'joinclassurl'   => $nextlive['url'] ?? (new moodle_url('/calendar/view.php'))->out(false),
            'joinclassexternal' => !empty($nextlive),
        ];

        return $context;
    }

    /**
     * End-semester exam entry URL for the current user, or null if the feature is
     * switched off (theme setting show_endsem_exam, default off) or the user is
     * not in an exam batch.
     *
     * Surfaced as a highlighted button in the dashboard hero, because the
     * redesigned dashboard omits generic blocks and so never renders
     * block_endsemesterexam itself. It links to the same redirect.php the block
     * links to, which hands the candidate to whichever exam portal the site has
     * configured -- that differs per institution and is the block's business,
     * not this theme's.
     *
     * Entitlement is whichever audience the site has actually configured: the
     * theme's own batch audience where that exists (the cohort_batch profile
     * field plus theme_baz/endsem_exam_batches -- see
     * {@see \theme_baz\local\exam_batch}), otherwise the block's.
     *
     * @param int $userid
     * @return string|null
     */
    protected function get_endsem_exam_url(int $userid): ?string {
        global $DB;

        // Off between exam cycles (theme setting, default off).
        if (get_config('theme_baz', 'show_endsem_exam') !== '1') {
            return null;
        }

        $url = (new moodle_url('/blocks/endsemesterexam/redirect.php',
            ['sesskey' => sesskey()]))->out(false);

        // Two ways a site says who sits the exam, and which one applies is
        // decided by which one the site has actually set up.
        //
        // The theme's own audience came first: the cohort_batch profile field,
        // with the batches chosen in theme_baz/endsem_exam_batches. Where a site
        // has both of those, that IS its answer and nothing else may override it
        // -- a site can name several batches at once, and only some of them
        // correspond to a course category, so reading the audience off
        // categories instead silently drops the rest. That is not hypothetical:
        // it removed the button from 22,770 learners across four batches.
        $fieldid = $DB->get_field('user_info_field', 'id', ['shortname' => exam_batch::FIELD]);
        $batches = trim((string) get_config('theme_baz', 'endsem_exam_batches'));
        if ($fieldid && $batches !== '') {
            $batch = $DB->get_field('user_info_data', 'data',
                ['userid' => $userid, 'fieldid' => $fieldid]);
            if ($batch === false || !exam_batch::matches((string) $batch)) {
                return null;
            }
            return $url;
        }

        // No batch audience configured -- the site has no cohort_batch field, or
        // has named no batches. Then entitlement is the block's, which is the
        // only answer such a site has: IIITD, for instance, identifies exam
        // candidates by register number and has no cohort_batch field at all.
        if (class_exists(\block_endsemesterexam\local\audience::class)) {
            return \block_endsemesterexam\local\audience::allows($userid) ? $url : null;
        }

        return null;
    }

    /**
     * Fetch the user's enrolment once and return: the current-(highest)-semester
     * courses, the highest semester number, and an id => shortname map of ALL
     * enrolled courses (so the calendar doesn't have to re-query or call
     * get_course() per event).
     *
     * @param int $userid
     * @return array [array $currentcourses, int|null $highestsemester, array $shortnames]
     */
    protected function get_courses(int $userid): array {
        global $DB;

        $enrolled = enrol_get_users_courses($userid, true, ['id', 'fullname', 'shortname', 'category', 'visible']);
        if (!$enrolled) {
            return [[], null, []];
        }

        // Batch the category-name lookups to avoid N+1.
        $catids = array_unique(array_map(function($c) {
            return $c->category;
        }, $enrolled));
        $catnames = $DB->get_records_list('course_categories', 'id', $catids, '', 'id, name');

        $shortnames = [];
        $searchcourses = [];
        foreach ($enrolled as $course) {
            $catname = isset($catnames[$course->category]) ? $catnames[$course->category]->name : '';
            $course->semesternum = semester::semester_of($catname);
            $shortnames[$course->id] = format_string($course->shortname);
            $searchcourses[] = [
                'label'    => format_string($course->fullname),
                'sublabel' => get_string('course'),
                'url'      => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            ];
        }

        // Compute the highest semester once, then filter.
        $highest = semester::highest_semester($enrolled);
        if ($highest === null) {
            $current = array_values($enrolled);
        } else {
            $current = array_values(array_filter($enrolled, function($c) use ($highest) {
                return $c->semesternum === $highest;
            }));
        }

        // Keep only graded courses — those with at least one graded activity
        // (gradetype value=1 or scale=2). This drops admin/info courses such as
        // "Program Information" and "Course Matrix" that carry no gradebook items.
        if ($current) {
            $courseids = array_map(function($c) {
                return $c->id;
            }, $current);
            [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
            $gradedids = $DB->get_fieldset_sql(
                "SELECT DISTINCT courseid
                   FROM {grade_items}
                  WHERE courseid $insql AND itemtype = 'mod' AND gradetype IN (1, 2)",
                $inparams
            );
            if ($gradedids) {
                $gradedset = array_flip($gradedids);
                $current = array_values(array_filter($current, function($c) use ($gradedset) {
                    return isset($gradedset[$c->id]);
                }));
            }
        }

        return [$current, $highest, $shortnames, $searchcourses];
    }

    /**
     * Build the course-card context and the aggregate progress-ring figures.
     *
     * @param array $courses Current-semester courses (with ->semesternum).
     * @param int $userid
     * @return array [array $cards, array $ring]
     */
    protected function build_course_cards(array $courses, int $userid): array {
        $cards = [];
        $completed = $inprogress = $notstarted = 0;
        $progsum = 0;
        $progcount = 0;
        $i = 0;

        foreach ($courses as $course) {
            $pct = null;
            try {
                $full = get_course($course->id);
                $pct = \core_completion\progress::get_course_progress_percentage($full, $userid);
            } catch (\Throwable $e) {
                $pct = null;
            }

            if ($pct === null) {
                $notstarted++;
                $status = 'new';
                $pctint = 0;
            } else {
                $pctint = (int) round($pct);
                $progsum += $pctint;
                $progcount++;
                if ($pctint >= 100) {
                    $completed++;
                    $status = 'done';
                } else if ($pctint > 0) {
                    $inprogress++;
                    $status = 'prog';
                } else {
                    $notstarted++;
                    $status = 'new';
                }
            }

            // Two base cover colours alternated even/odd (deep blue / sky blue) —
            // the cover when the course has no uploaded image.
            $palette = ($i % 2 === 0) ? self::COVER_EVEN : self::COVER_ODD;
            $statusmeta = $this->status_meta($status);
            $courseimg = $this->course_image($course->id);

            $cards[] = [
                'id'         => $course->id,
                'name'       => format_string($course->fullname),
                'abbr'       => $this->abbreviate($course->fullname),
                'progress'   => $pctint,
                'barwidth'   => max($pctint, 2),
                'barcolor'   => $statusmeta['bar'],
                'statusbg'   => $statusmeta['bg'],
                'statusink'  => $statusmeta['ink'],
                'statuslabel' => $statusmeta['label'],
                'gradfrom'   => $palette[0],
                'gradto'     => $palette[1],
                'imageurl'   => $courseimg,
                'hasimage'   => $courseimg !== '',
                'url'        => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            ];
            $i++;
        }

        $ring = [
            'avg'        => $progcount ? (int) round($progsum / $progcount) : 0,
            'completed'  => $completed,
            'inprogress' => $inprogress,
            'notstarted' => $notstarted,
        ];

        return [$cards, $ring];
    }

    /**
     * The course's uploaded overview image URL, or '' when none (card falls back
     * to the gradient + initials cover). File metadata only — cheap per course.
     *
     * @param int $courseid
     * @return string image URL or ''
     */
    protected function course_image(int $courseid): string {
        try {
            $fs = get_file_storage();
            $ctx = \context_course::instance($courseid);
            foreach ($fs->get_area_files($ctx->id, 'course', 'overviewfiles', 0, 'filename', false) as $f) {
                if ($f->is_valid_image()) {
                    return \moodle_url::make_pluginfile_url(
                        $f->get_contextid(), 'course', 'overviewfiles', null,
                        $f->get_filepath(), $f->get_filename())->out(false);
                }
            }
        } catch (\Throwable $e) {
            return '';
        }
        return '';
    }

    /**
     * Visual metadata for a course completion status.
     *
     * @param string $status one of done|prog|new
     * @return array
     */
    protected function status_meta(string $status): array {
        $map = [
            'done' => ['bar' => '#1FA37A', 'bg' => '#E6F6F0', 'ink' => '#1FA37A', 'label' => get_string('sd_completed', 'theme_baz')],
            'prog' => ['bar' => 'var(--bz-accent)', 'bg' => 'var(--bz-accent-bg)', 'ink' => 'var(--bz-accent-text2)', 'label' => get_string('sd_inprogress', 'theme_baz')],
            'new'  => ['bar' => '#C7D2DC', 'bg' => '#EEF2F6', 'ink' => '#5C7184', 'label' => get_string('sd_notstarted', 'theme_baz')],
        ];
        return $map[$status];
    }

    /**
     * A two-letter monogram from a course name, e.g. "Marketing Management" => "MM".
     *
     * @param string $name
     * @return string
     */
    protected function abbreviate(string $name): string {
        $name = trim(preg_replace('/^[0-9.\s]+/', '', $name));
        $words = preg_split('/\s+/', $name);
        $letters = '';
        foreach ($words as $word) {
            if ($word !== '' && ctype_alpha($word[0])) {
                $letters .= \core_text::strtoupper(\core_text::substr($word, 0, 1));
            }
            if (\core_text::strlen($letters) >= 2) {
                break;
            }
        }
        if ($letters === '') {
            $letters = \core_text::strtoupper(\core_text::substr($name, 0, 2));
        }
        return $letters;
    }

    /**
     * Upcoming calendar events for the user, split into deadlines and live classes
     * and grouped by day.
     *
     * @param int $userid
     * @param int $from
     * @param int $to
     * @param array $courseids Enrolled course ids (already fetched).
     * @param array $shortnames id => shortname map (already fetched).
     * @return array [deadlinedays, liveclassdays, deadlinecount, liveclasscount, upnext, duedays, nextlive]
     */
    protected function get_calendar(int $userid, int $from, int $to, array $courseids, array $shortnames): array {
        $deadlines = [];
        $liveclasses = [];
        $duedays = [];
        $upnext = null;
        $nextlive = null;
        // Only collect due-day dots for events that fall in the month on display.
        $displaymonth = userdate($from, '%Y%m');

        try {
            // calendar_get_events($tstart, $tend, $users, $groups, $courses, ...).
            $events = calendar_get_events($from, $to, $userid, false, $courseids ?: false, true, true);

            foreach ($events as $event) {
                $modname = !empty($event->modulename) ? $event->modulename : '';
                $islive = $this->is_live_event($event);

                $coursename = isset($shortnames[$event->courseid]) ? $shortnames[$event->courseid] : '';

                [$url, $isexternal] = $this->event_url($event, $islive);

                $row = [
                    'time'       => userdate($event->timestart, get_string('strftimetime24', 'langconfig')),
                    'daykey'     => userdate($event->timestart, '%Y%m%d'),
                    'daylabel'   => userdate($event->timestart, get_string('strftimedaydate', 'langconfig')),
                    'name'       => $this->clean_event_name(format_string($event->name)),
                    'coursename' => $coursename,
                    'url'        => $url,
                    'external'   => $isexternal,
                    'timestart'  => (int) $event->timestart,
                    'action'     => $islive ? get_string('sd_join', 'theme_baz') : $this->deadline_action($modname),
                ];

                // Track due-day for the mini calendar, but only within the displayed month
                // (the 7-day window can spill into the next month).
                if (userdate($event->timestart, '%Y%m') === $displaymonth) {
                    $duedays[(int) userdate($event->timestart, '%d')] = true;
                }

                if ($islive) {
                    $liveclasses[] = $row;
                    // Events come back ordered by timestart, so the first live class is
                    // the nearest one. Only offer it to "Join next class" when we really
                    // have a meeting link -- otherwise the calendar is more use than a
                    // course page the learner cannot join from.
                    if ($nextlive === null && $isexternal) {
                        $nextlive = ['url' => $url, 'external' => true];
                    }
                } else {
                    $deadlines[] = $row;
                    // First (nearest) deadline becomes "up next".
                    if ($upnext === null) {
                        $upnext = [
                            'name'       => $row['name'],
                            'coursename' => $row['coursename'],
                            'timelabel'  => userdate($event->timestart, get_string('strftimerecent', 'langconfig')),
                            'timestart'  => (int) $event->timestart,
                            'url'        => $row['url'],
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            // Degrade to empty lists on any calendar error, but leave a trail.
            debugging('student_dashboard calendar failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $deadlines = [];
            $liveclasses = [];
            $nextlive = null;
        }

        return [
            $this->group_by_day($deadlines),
            $this->group_by_day($liveclasses),
            count($deadlines),
            count($liveclasses),
            $upnext,
            array_keys($duedays),
            $nextlive,
        ];
    }

    /**
     * Whether a calendar event should be treated as a live class rather than a deadline.
     *
     * A live class is a timed session (it has a duration) or a known live-session
     * module; quiz and assignment "closes" events carry no duration.
     *
     * @param \stdClass $event Event record from calendar_get_events().
     * @return bool
     */
    protected function is_live_event(\stdClass $event): bool {
        $modname = !empty($event->modulename) ? $event->modulename : '';
        return (!empty($event->timeduration) && $event->timeduration > 0)
            || in_array($modname, self::LIVE_MODULES, true);
    }

    /**
     * Where a calendar event should take the learner.
     *
     * Prefers the activity behind the event, then a live class's join link, and
     * falls back to the course (or the calendar day for site-level events).
     *
     * @param \stdClass $event Event record from calendar_get_events().
     * @param bool $islive Whether the event is a live class.
     * @return array [string $url, bool $isexternal] $isexternal marks an off-site meeting.
     */
    protected function event_url(\stdClass $event, bool $islive): array {
        $modname = !empty($event->modulename) ? $event->modulename : '';

        $url = !empty($event->courseid) && $event->courseid != SITEID
            ? (new moodle_url('/course/view.php', ['id' => $event->courseid]))->out(false)
            : (new moodle_url('/calendar/view.php', ['view' => 'day', 'time' => $event->timestart]))->out(false);

        // Deep-link straight to the activity when we can resolve it.
        if ($modname !== '' && !empty($event->instance)) {
            $key = $modname . ':' . $event->instance;
            if (!array_key_exists($key, $this->cmcache)) {
                try {
                    $this->cmcache[$key] = get_coursemodule_from_instance($modname, $event->instance,
                        !empty($event->courseid) ? $event->courseid : 0, false, IGNORE_MISSING) ?: null;
                } catch (\Throwable $e) {
                    // Keep the fallback URL.
                    $this->cmcache[$key] = null;
                }
            }
            if ($this->cmcache[$key]) {
                $url = (new moodle_url('/mod/' . $modname . '/view.php',
                    ['id' => $this->cmcache[$key]->id]))->out(false);
            }
            return [$url, false];
        }

        // Timetabled live classes are plain calendar events with no activity behind
        // them, so there is no cm to deep-link to. The join link lives in the
        // description; without this a "Join" affordance would fall back to the
        // course page and never reach the meeting.
        if ($islive) {
            $meetingurl = $this->live_meeting_url((string) $event->description);
            if ($meetingurl !== '') {
                return [$meetingurl, true];
            }
        }

        return [$url, false];
    }

    /**
     * The meeting join URL carried in a live class's event description, or '' when
     * there is none we are willing to link to.
     *
     * Timetabled live classes are created as plain calendar events, so the join link
     * is only ever present in the description. That description is teacher-authored
     * HTML, so candidates are restricted to MEETING_HOSTS over https rather than
     * linking whatever URL happens to appear first.
     *
     * @param string $description Raw event description HTML.
     * @return string Join URL, or '' if the description carries no usable one.
     */
    protected function live_meeting_url(string $description): string {
        if (trim($description) === '') {
            return '';
        }

        // The link may sit in an href or as bare text, and query strings are commonly
        // entity-encoded (&amp;), which would corrupt the meeting passcode if kept.
        $text = html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (!preg_match_all('~https://[^\s"\'<>]+~i', $text, $matches)) {
            return '';
        }

        foreach ($matches[0] as $candidate) {
            // Trailing sentence punctuation is not part of the URL.
            $candidate = rtrim($candidate, '.,;:)]}');
            $host = strtolower((string) parse_url($candidate, PHP_URL_HOST));
            if ($host === '') {
                continue;
            }
            foreach (self::MEETING_HOSTS as $allowed) {
                if ($host === $allowed || substr($host, -(strlen($allowed) + 1)) === '.' . $allowed) {
                    return $candidate;
                }
            }
        }

        return '';
    }

    /**
     * Group a flat, time-sorted event list into day buckets for mustache.
     *
     * @param array $events
     * @return array
     */
    protected function group_by_day(array $events): array {
        $days = [];
        foreach ($events as $event) {
            $key = $event['daykey'];
            if (!isset($days[$key])) {
                $days[$key] = ['daylabel' => $event['daylabel'], 'events' => []];
            }
            $days[$key]['events'][] = $event;
        }
        return array_values($days);
    }

    /**
     * Recently accessed items, mapped for the "Jump back in" rail.
     *
     * @return array
     */
    protected function get_recent_items(): array {
        $items = [];
        try {
            $records = \block_recentlyaccesseditems\helper::get_recent_items(6);
            $idx = 0;
            foreach ($records as $record) {
                $items[] = [
                    'name'       => format_string($record->name),
                    'coursename' => format_string($record->coursename),
                    'kind'       => get_string('pluginname', 'mod_' . $record->modname),
                    // Logo colour alternates through the 3 brand colours.
                    'iconcolor'  => self::LOGO_CYCLE[$idx % count(self::LOGO_CYCLE)],
                    'iconsvg'    => $this->module_icon_svg($record->modname),
                    'url'        => (new moodle_url('/mod/' . $record->modname . '/view.php', ['id' => $record->cmid]))->out(false),
                ];
                $idx++;
            }
        } catch (\Throwable $e) {
            debugging('student_dashboard recent items failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $items = [];
        }
        return $items;
    }

    /**
     * Deterministic accent colour for a module type.
     *
     * @param string $modname
     * @return string
     */
    protected function module_color(string $modname): string {
        $map = [
            'forum'      => '#E5556E',
            'quiz'       => 'var(--bz-accent)',
            'assign'     => '#406CD2',
            'resource'   => '#5B5BD6',
            'url'        => '#5B5BD6',
            'page'       => '#5B5BD6',
            'lesson'     => '#1FA37A',
        ];
        // Video-ish modules.
        if (in_array($modname, self::LIVE_MODULES, true)) {
            return '#8B5CF6';
        }
        return $map[$modname] ?? '#406CD2';
    }

    /**
     * Fee / Renewals widget data, reusing block_renewals eligibility + payment URL.
     *
     * @param int $userid
     * @return array|null Null when the user is not eligible.
     */
    protected function get_fee(int $userid): ?array {
        global $CFG, $USER;

        // Reuse block_renewals' own eligibility rule as the single source of truth
        // (can_access() reads the global $USER, so only the current user is supported).
        if ($userid != $USER->id) {
            return null;
        }
        $blockfile = $CFG->dirroot . '/blocks/renewals/block_renewals.php';
        if (!file_exists($blockfile)) {
            return null;
        }
        require_once($blockfile);
        if (!class_exists('block_renewals')) {
            return null;
        }
        try {
            $block = new \block_renewals();
            if (!$block->can_access()) {
                return null;
            }
        } catch (\Throwable $e) {
            debugging('student_dashboard fee eligibility failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }

        $config = get_config('block_renewals');
        $url = isset($config->payment_url) ? trim($config->payment_url) : '';
        if ($url === '') {
            $url = 'https://qa.aditya.thekaios.com/login';
        }
        if (strpos($url, 'http') !== 0) {
            $url = (new moodle_url($url))->out(false);
        }

        return [
            'payurl' => $url,
            'title'  => get_string('paynow_title', 'block_renewals'),
            'desc'   => get_string('paynow_description', 'block_renewals'),
            'button' => get_string('paynow_button', 'block_renewals'),
        ];
    }

    /**
     * Level Up XP figures for the user (site-wide world). Returns null on any
     * failure or when XP isn't running site-wide, so the widget simply hides.
     *
     * @param int $userid
     * @return array|null
     */
    protected function get_levelup(int $userid): ?array {
        if (!class_exists('\block_xp\di')) {
            return null;
        }

        try {
            $mode = \block_xp\di::get('config')->get('context');
            // Only resolve a meaningful single figure when XP is site-wide.
            if ((int) $mode !== CONTEXT_SYSTEM) {
                return null;
            }

            $world = \block_xp\di::get('course_world_factory')->get_world(SITEID);
            $state = $world->get_store()->get_state($userid);
            $level = $state->get_level();
            $levelnum = $level->get_level();
            $points = method_exists($state, 'get_xp') ? (int) $state->get_xp() : 0;

            $levelsinfo = $world->get_levels_info();
            $nextpoints = null;
            if ($levelsinfo->get_count() > $levelnum) {
                $nextlevel = $levelsinfo->get_level($levelnum + 1);
                if (method_exists($nextlevel, 'get_xp_required')) {
                    $nextpoints = (int) $nextlevel->get_xp_required();
                }
            }

            // Progress within the current level (0..1) — not progress from level 0.
            $ratio = (int) round($state->get_ratio_in_level() * 100);
            $ratio = max(0, min(100, $ratio));

            $rank = null;
            try {
                $lbfactory = \block_xp\di::get('leaderboard_factory_maker')->get_leaderboard_factory($world);
                $rankobj = $lbfactory->get_leaderboard()->get_rank($userid);
                if ($rankobj !== null) {
                    // get_rank() returns a rank object; pull the integer position.
                    $rank = $rankobj->get_rank();
                }
            } catch (\Throwable $e) {
                $rank = null;
            }

            return [
                'level'      => $levelnum,
                'points'     => $points,
                'nextpoints' => $nextpoints,
                'hasnext'    => $nextpoints !== null,
                'topct'      => $nextpoints !== null ? max(0, $nextpoints - $points) : 0,
                'ratio'      => $ratio,
                'rank'       => $rank,
                'hasrank'    => $rank !== null,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Per-day event map for the displayed month:
     *   [dayOfMonth => ['count'=>n, 'events'=>[['time'=>..,'name'=>..,'url'=>..,'external'=>..], ...]]].
     * Used for the mini-calendar's due dots and the styled hover popover.
     *
     * @param int $userid
     * @param array $courseids
     * @param int $now
     * @return array
     */
    protected function get_month_events(int $userid, array $courseids, int $now): array {
        $map = [];
        try {
            $year = (int) userdate($now, '%Y');
            $month = (int) userdate($now, '%m');
            $daysinmonth = (int) (new \DateTime(sprintf('%04d-%02d-01', $year, $month)))->format('t');
            $start = make_timestamp($year, $month, 1, 0, 0, 0);
            $end = make_timestamp($year, $month, $daysinmonth, 23, 59, 59);
            $events = calendar_get_events($start, $end, $userid, false, $courseids ?: false, true, true);
            foreach ($events as $event) {
                $day = (int) userdate($event->timestart, '%d');
                if (!isset($map[$day])) {
                    $map[$day] = ['count' => 0, 'events' => []];
                }
                $map[$day]['count']++;
                if (count($map[$day]['events']) < 6) {
                    [$eurl, $eexternal] = $this->event_url($event, $this->is_live_event($event));
                    $map[$day]['events'][] = [
                        'time' => userdate($event->timestart, get_string('strftimetime24', 'langconfig')),
                        'name' => $this->clean_event_name(format_string($event->name)),
                        'url' => $eurl,
                        'external' => $eexternal,
                    ];
                }
            }
        } catch (\Throwable $e) {
            debugging('student_dashboard month events: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        return $map;
    }

    /**
     * Build the mini month calendar grid.
     *
     * @param int $now
     * @param array $monthevents Map from get_month_events().
     * @return array
     */
    protected function build_mini_calendar(int $now, array $monthevents): array {
        $today = (int) userdate($now, '%d');
        $monthlabel = userdate($now, '%B %Y');
        $year = (int) userdate($now, '%Y');
        $month = (int) userdate($now, '%m');

        // First weekday of the month (0 = Sunday) in the user's timezone, and the
        // number of days in the month (pure calendar math, timezone-independent).
        $firstdow = (int) userdate(make_timestamp($year, $month, 1), '%w');
        $daysinmonth = (int) (new \DateTime(sprintf('%04d-%02d-01', $year, $month)))->format('t');

        $cells = [];
        for ($i = 0; $i < $firstdow; $i++) {
            $cells[] = ['blank' => true];
        }
        for ($d = 1; $d <= $daysinmonth; $d++) {
            $dayts = make_timestamp($year, $month, $d);
            $datelabel = userdate($dayts, get_string('strftimedaydate', 'langconfig'));
            $hasevents = isset($monthevents[$d]);
            // Column 0-6 (Sun-Sat) so the hover popover can flip away from the edges.
            $col = ($firstdow + ($d - 1)) % 7;
            $cells[] = [
                'blank'     => false,
                'day'       => $d,
                'istoday'   => $d === $today,
                'isdue'     => $hasevents,
                'col'       => $col,
                'url'       => (new moodle_url('/calendar/view.php', ['view' => 'day', 'time' => $dayts]))->out(false),
                'hasevents' => $hasevents,
                'daylabel'  => $datelabel,
                'events'    => $hasevents ? $monthevents[$d]['events'] : [],
            ];
        }

        return [
            'monthlabel' => $monthlabel,
            'cells'      => $cells,
        ];
    }

    /**
     * Action label for a deadline based on its activity type.
     *
     * @param string $modname
     * @return string
     */
    protected function deadline_action(string $modname): string {
        $map = [
            'quiz'     => 'sd_act_quiz',
            'assign'   => 'sd_act_submit',
            'forum'    => 'sd_act_reply',
            'choice'   => 'sd_act_choose',
            'feedback' => 'sd_act_respond',
            'lesson'   => 'sd_act_start',
            'workshop' => 'sd_act_submit',
        ];
        return isset($map[$modname])
            ? get_string($map[$modname], 'theme_baz')
            : get_string('sd_open', 'theme_baz');
    }

    /**
     * Inner SVG markup (stroke-based) for a recent-item icon by module type.
     *
     * @param string $modname
     * @return string
     */
    protected function module_icon_svg(string $modname): string {
        $forum = '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>';
        $quiz = '<path d="M9 11l2.5 2.5L16 9"/><rect x="4" y="4" width="16" height="16" rx="2"/>';
        $assign = '<path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5M8 13h8M8 17h5"/>';
        $doc = '<path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h5"/>';
        $video = '<rect x="3" y="5" width="18" height="13" rx="2"/><path d="m10 9 5 3-5 3z" fill="currentColor" stroke="none"/>';
        $book = '<path d="M4 5a2 2 0 0 1 2-2h12v18H6a2 2 0 0 1-2-2z"/><path d="M9 3v18"/>';
        $map = [
            'forum'        => $forum,
            'hsuforum'     => $forum,
            'quiz'         => $quiz,
            'assign'       => $assign,
            'workshop'     => $assign,
            'lesson'       => $book,
            'book'         => $book,
            'resource'     => $doc,
            'page'         => $doc,
            'url'          => $doc,
            'folder'       => $doc,
            'glossary'     => $doc,
            'scorm'        => $video,
            'h5pactivity'  => $video,
            'vpl'          => '<path d="M8 9l-4 3 4 3M16 9l4 3-4 3M13 6l-2 12"/>',
        ];
        if (in_array($modname, self::LIVE_MODULES, true)) {
            return $video;
        }
        return $map[$modname] ?? $doc;
    }

    /**
     * Build the Ask JO chat (openai_chat) directly: the block splits its chat log
     * (->text) and input control bar (->footer), and we need both. Rendering via
     * block_instance() would attach sibling content-region blocks to the page and
     * queue their JS against absent DOM, so we mirror block_openai_chat::get_content()
     * here using the existing instance id.
     *
     * @return array|null ['log','control'] or null when unavailable.
     */
    protected function build_askjo(): ?array {
        global $DB, $PAGE, $OUTPUT;
        try {
            $instance = $DB->get_record_select(
                'block_instances',
                'blockname = :bn AND ' . $DB->sql_like('pagetypepattern', ':ptp'),
                ['bn' => 'openai_chat', 'ptp' => 'my-index%'],
                '*',
                IGNORE_MULTIPLE
            );
            if (!$instance || empty(get_config('block_openai_chat', 'apikey'))) {
                return null;
            }

            $assistantname = get_config('block_openai_chat', 'assistantname')
                ?: get_string('defaultassistantname', 'block_openai_chat');
            $username = get_config('block_openai_chat', 'username')
                ?: get_string('defaultusername', 'block_openai_chat');

            $PAGE->requires->js_call_amd('block_openai_chat/lib', 'init', [[
                'blockId' => (int) $instance->id,
                'api_type' => get_config('block_openai_chat', 'type') ?: 'chat',
                'persistConvo' => get_config('block_openai_chat', 'persistconvo'),
            ]]);

            $log = '<script>var assistantName = ' . json_encode($assistantname) . '; var userName = '
                . json_encode($username) . ';</script>'
                . '<style>.openai_message.user:before{content:' . json_encode($username) . '}'
                . '.openai_message.bot:before{content:' . json_encode($assistantname) . '}</style>'
                . '<div id="openai_chat_log" role="log"></div>';

            $control = $OUTPUT->render_from_template('block_openai_chat/control_bar', [
                'logging_enabled' => get_config('block_openai_chat', 'logging'),
                'is_edit_mode'    => false,
                'pix_popout'      => '/blocks/openai_chat/pix/arrow-up-right-from-square.svg',
                'pix_arrow_right' => '/blocks/openai_chat/pix/arrow-right.svg',
                'pix_refresh'     => '/blocks/openai_chat/pix/refresh.svg',
            ]);

            return ['blockid' => (int) $instance->id, 'log' => $log, 'control' => $control];
        } catch (\Throwable $e) {
            debugging('student_dashboard build_askjo: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
    }

    /**
     * Render the content HTML of the first dashboard block matching a name (and
     * optional config-title substring), reusing the real configured instance.
     *
     * @param string $blockname
     * @param string|null $titlecontains Substring to match against the block's configured title.
     * @return array|null ['title','html','footer'] or null when nothing renders.
     */
    protected function render_block(string $blockname, ?string $titlecontains = null): ?array {
        global $DB, $PAGE, $CFG;
        require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
        try {
            $instances = $DB->get_records_select(
                'block_instances',
                'blockname = :bn AND ' . $DB->sql_like('pagetypepattern', ':ptp'),
                ['bn' => $blockname, 'ptp' => 'my-index%']
            );
            foreach ($instances as $instance) {
                $block = block_instance($blockname, $instance, $PAGE);
                if (!$block) {
                    continue;
                }
                if ($titlecontains !== null) {
                    $title = isset($block->config->title) ? (string) $block->config->title : '';
                    if (stripos($title, $titlecontains) === false) {
                        continue;
                    }
                }
                $content = $block->get_content();
                if ($content && trim((string) $content->text) !== '') {
                    return [
                        'title'  => $block->get_title(),
                        'html'   => $content->text,
                        'footer' => isset($content->footer) ? $content->footer : '',
                    ];
                }
            }
        } catch (\Throwable $e) {
            debugging('student_dashboard render_block ' . $blockname . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        return null;
    }

    /**
     * Parse an rss_client block's HTML into structured items so they can be
     * rendered in the dashboard's own card markup. The block emits, per feed:
     *   <div class="title">Source</div><ul class="list"><li>..<a href>Title</a>..</li></ul>
     *
     * @param string $html
     * @param int $limit
     * @return array of ['source','title','url','abbr','color']
     */
    protected function parse_rss(string $html, int $limit = 4): array {
        $items = [];
        if (trim($html) === '') {
            return $items;
        }
        $palette = self::LOGO_CYCLE; // dark blue / light blue / gold, cycled.
        try {
            $doc = new \DOMDocument();
            libxml_use_internal_errors(true);
            $doc->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>');
            libxml_clear_errors();
            $xpath = new \DOMXPath($doc);
            $nodes = $xpath->query('//div[contains(@class,"title")] | //ul[contains(@class,"list")]//a[@href]');
            $source = '';
            $coloridx = 0;
            foreach ($nodes as $node) {
                if ($node->nodeName === 'div') {
                    $source = trim($node->textContent);
                    $coloridx = count($items) % count($palette);
                } else {
                    $title = trim($node->textContent);
                    if ($title === '') {
                        continue;
                    }
                    $items[] = [
                        'source' => $source,
                        'title'  => $title,
                        'url'    => $node->getAttribute('href'),
                        'abbr'   => $this->source_abbr($source),
                        'color'  => $palette[$coloridx % count($palette)],
                    ];
                    if (count($items) >= $limit) {
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            debugging('student_dashboard parse_rss: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        return $items;
    }

    /**
     * Short uppercase abbreviation of a feed source, e.g. "MIT Sloan ..." => "MIT".
     *
     * @param string $source
     * @return string
     */
    protected function source_abbr(string $source): string {
        $source = trim($source);
        if ($source === '') {
            return 'RSS';
        }
        $words = preg_split('/\s+/', $source);
        if (count($words) === 1) {
            return \core_text::strtoupper(\core_text::substr($source, 0, 3));
        }
        $abbr = '';
        foreach ($words as $word) {
            if ($word !== '' && ctype_alpha($word[0])) {
                $abbr .= \core_text::strtoupper($word[0]);
            }
            if (\core_text::strlen($abbr) >= 3) {
                break;
            }
        }
        return $abbr !== '' ? $abbr : \core_text::strtoupper(\core_text::substr($source, 0, 3));
    }

    /**
     * Consecutive-day activity streak ending today (or yesterday) from the log.
     *
     * @param int $userid
     * @param int $now
     * @return int Number of consecutive active days (0 if none).
     */
    protected function get_streak(int $userid, int $now): int {
        global $DB;
        try {
            $since = $now - (60 * DAYSECS);
            $records = $DB->get_records_sql(
                "SELECT DISTINCT FLOOR(timecreated / 86400) AS dayid
                   FROM {logstore_standard_log}
                  WHERE userid = :userid AND timecreated >= :since",
                ['userid' => $userid, 'since' => $since]
            );
            if (!$records) {
                return 0;
            }
            $days = [];
            foreach ($records as $r) {
                $days[(int) $r->dayid] = true;
            }
            $today = (int) floor($now / 86400);
            $start = isset($days[$today]) ? $today : (isset($days[$today - 1]) ? $today - 1 : null);
            if ($start === null) {
                return 0;
            }
            $streak = 0;
            for ($d = $start; isset($days[$d]); $d--) {
                $streak++;
            }
            return $streak;
        } catch (\Throwable $e) {
            debugging('student_dashboard streak: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return 0;
        }
    }

    /**
     * Per-day activity bars for the last 7 days (oldest → today), normalised to
     * the busiest day. Days with no logged activity render as a dim baseline bar.
     *
     * @param int $userid
     * @param int $now
     * @return array of ['height' => int (8..100), 'dim' => bool]
     */
    protected function get_week_activity(int $userid, int $now): array {
        global $DB;
        $today = (int) floor($now / 86400);
        $counts = [];
        try {
            $since = ($today - 6) * 86400;
            $records = $DB->get_records_sql(
                "SELECT FLOOR(timecreated / 86400) AS dayid, COUNT(*) AS cnt
                   FROM {logstore_standard_log}
                  WHERE userid = :userid AND timecreated >= :since
               GROUP BY FLOOR(timecreated / 86400)",
                ['userid' => $userid, 'since' => $since]
            );
            foreach ($records as $r) {
                $counts[(int) $r->dayid] = (int) $r->cnt;
            }
        } catch (\Throwable $e) {
            debugging('student_dashboard week activity: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        $max = $counts ? max($counts) : 0;
        $bars = [];
        for ($i = 6; $i >= 0; $i--) {
            $c = $counts[$today - $i] ?? 0;
            if ($c > 0 && $max > 0) {
                $height = max(18, (int) round(($c / $max) * 100));
            } else {
                $height = 8;
            }
            $bars[] = ['height' => $height, 'dim' => $c === 0];
        }
        return $bars;
    }

    /**
     * Build the donut's single completion arc, sized to the average completion
     * percentage so the ring matches the centre figure (an r=58 / circumference
     * ≈ 364.4 ring). 0% returns no arc (just the track); the course-state mix is
     * shown by the chips, not the ring.
     *
     * @param int $pct Average completion 0-100.
     * @return array of ['color','dash','offset']
     */
    protected function build_completion_arc(int $pct): array {
        $pct = max(0, min(100, $pct));
        if ($pct <= 0) {
            return [];
        }
        $circumference = 2 * M_PI * 58; // ≈ 364.42
        $visible = ($pct / 100) * $circumference;
        return [[
            'color'  => '#5B9BFF', // light blue completion arc.
            'dash'   => round($visible, 2) . ' ' . round($circumference - $visible, 2),
            'offset' => 0,
        ]];
    }

    /**
     * Read the RemUI dashboard banner(s) from theme settings so the bespoke
     * dashboard shows the same top banner as the standard one. Delegates to the
     * shared theme helper, which honours the display toggle, per-slide cohort
     * audience rules and the configured display order.
     *
     * @return array of ['img','link'|null,'haslink','alt']
     */
    protected function get_dashboard_banners(): array {
        global $CFG;
        try {
            require_once($CFG->dirroot . '/theme/baz/lib.php');
            // The shared helper honours the display toggle, per-slide cohort
            // audience rules and the configured display order.
            return theme_baz_get_dashboard_banners();
        } catch (\Throwable $e) {
            debugging('student_dashboard banners: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }

    /**
     * Pick the course to nudge the student toward next: the least-progressed card
     * (ties broken toward not-started). Returns null when there are no courses.
     *
     * @param array $cards Course cards from build_course_cards().
     * @return array|null ['name','url','statuslabel']
     */
    protected function pick_focus_course(array $cards): ?array {
        $focus = null;
        foreach ($cards as $card) {
            if ($focus === null || $card['progress'] < $focus['progress']) {
                $focus = $card;
            }
        }
        if ($focus === null) {
            return null;
        }
        return [
            'name'        => $focus['name'],
            'url'         => $focus['url'],
            'statuslabel' => $focus['statuslabel'],
        ];
    }

    /**
     * Strip Moodle's auto-appended event action suffix ("closes", "opens",
     * "is due", …) so the dashboard shows the clean activity name.
     *
     * @param string $name
     * @return string
     */
    protected function clean_event_name(string $name): string {
        $name = trim($name);
        $cleaned = preg_replace(
            '/\s+(closes|opens|is due|due|is overdue|opened|closed)$/i', '', $name);
        $cleaned = trim((string) $cleaned);
        // Never return an empty string if the whole name was a suffix word.
        return $cleaned !== '' ? $cleaned : $name;
    }
}
