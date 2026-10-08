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

namespace local_facultyfeedback;

defined('MOODLE_INTERNAL') || die();

/**
 * Aggregates the weekly faculty feedback a course has already collected.
 *
 * DSM runs faculty feedback as one mod_feedback activity per course per week,
 * named like "Feedback - <subject> | <date>(Week-N)". Each of those carries
 * exactly one rated question on a 1-5 Strongly Disagree -> Strongly Agree
 * scale. This class turns that into a single course-level average plus a
 * week-by-week trend.
 *
 * How a rating is identified (verified against prod, 2026-07-29):
 *
 * - A rated question is `feedback_item.typ = 'multichoicerated'`. That single
 *   field is a precise selector: all 2,987 rated questions on the site use it
 *   on an identical 1-5 scale, and everything that must be excluded (Student
 *   Satisfaction Survey, 360-degree feedback, Apply for Change of Elective,
 *   the Learner Engagement session forms) carries *zero* rated questions. No
 *   matching on activity names is needed, which matters because their spelling
 *   drifts constantly ("Feedback - ", "Feedback -  ", "Feedback -| " ...).
 *
 * - `feedback_value.value = '0'` means the question was left unanswered and is
 *   excluded. Site-wide that moves the average from 4.0105 to 4.0408.
 *
 * - `feedback_value.value` is a TEXT column, so aggregation goes through
 *   {@see \moodle_database::sql_cast_char2real()} with the $text flag set.
 *
 * - The course is reached via `{feedback}.course`. Do NOT filter on the indexed
 *   `feedback_value.course_id` — it is 0 on 55,001 rows here, so that query
 *   silently returns nothing.
 *
 * - The week is `{feedback}.timeopen`, which is set on all 2,965 rated
 *   activities. It beats bucketing by response date: it is DSM's own declared
 *   week start, it exists before anyone has answered, and it cannot split one
 *   weekly activity across two ISO weeks (course 939's newest window opens on
 *   Saturday 20 June 2026, which ISO-week bucketing would cut in half).
 *
 * Everything here is read-only; the plugin owns no tables.
 *
 * @package   local_facultyfeedback
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {

    /** @var string The only feedback question type that carries a numeric rating. */
    const RATED_TYPE = 'multichoicerated';

    /** @var int Top of the rating scale. Uniform across every rated item on the site. */
    const SCALE_MAX = 5;

    /** @var int Fallback minimum responses before an average is published. */
    const DEFAULT_MIN_RESPONSES = 5;

    /** @var int Fallback number of weeks kept in the trend. */
    const DEFAULT_TREND_WEEKS = 12;

    /**
     * Ratings counted as promoters for the Net Promoter Score.
     *
     * Deliberately only the top of the scale. Note this is a stricter mapping
     * than a textbook NPS, which is defined on an 0-10 "how likely are you to
     * recommend" question rather than the 1-5 agreement item DSM asks — so the
     * figure is not comparable with published NPS benchmarks.
     *
     * @var int[]
     */
    const NPS_PROMOTERS = [5];

    /**
     * Ratings counted as detractors.
     *
     * Includes 3 ("Neutral" on this scale), so a learner who expressed no
     * opinion counts against the score.
     *
     * @var int[]
     */
    const NPS_DETRACTORS = [1, 2, 3];

    /** @var array<int,array|null> Per-request memo of summary(), keyed by course id. */
    protected static $summaries = [];

    /**
     * Whether the feature is switched on site-wide.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return (bool) get_config('local_facultyfeedback', 'enabled');
    }

    /**
     * Minimum responses before a course's average is published.
     *
     * Below this the field shows its status instead of a number: newly created
     * weekly activities routinely sit at 0-2 responses for days, and "* 1.0"
     * off a single submission is worse than showing nothing. It also keeps a
     * thin week from being de-anonymisable, since mod_feedback here is
     * anonymous by display.
     *
     * @return int always >= 1
     */
    public static function min_responses(): int {
        $value = get_config('local_facultyfeedback', 'minresponses');
        if ($value === false || $value === null || $value === '') {
            $value = self::DEFAULT_MIN_RESPONSES;
        }
        return max(1, (int) $value);
    }

    /**
     * How many of the most recent qualifying weeks the trend keeps.
     *
     * @return int always >= 1
     */
    public static function trend_weeks(): int {
        $value = get_config('local_facultyfeedback', 'trendweeks');
        if ($value === false || $value === null || $value === '') {
            $value = self::DEFAULT_TREND_WEEKS;
        }
        return max(1, (int) $value);
    }

    /**
     * Whether the current user may see this course's feedback.
     *
     * @param int $courseid
     * @return bool
     */
    public static function can_view(int $courseid): bool {
        try {
            return has_capability('local/facultyfeedback:view', \context_course::instance($courseid));
        } catch (\Throwable $e) {
            // Missing/!invalid course context - no badge, no noise.
            return false;
        }
    }

    /**
     * The cached aggregate for a course.
     *
     * @param int $courseid
     * @return array|null null when the course has no rated weekly feedback at all
     */
    public static function summary(int $courseid): ?array {
        if (array_key_exists($courseid, self::$summaries)) {
            return self::$summaries[$courseid];
        }

        $result = null;
        try {
            $cache = \cache::make('local_facultyfeedback', 'summary');
            $cached = $cache->get($courseid);
            if ($cached !== false) {
                // 0 is the stored stand-in for "nothing to show" (a cache miss
                // is false, so null cannot be stored distinguishably).
                return self::$summaries[$courseid] = ($cached === 0 ? null : $cached);
            }
            $result = self::build_summary($courseid);
            $cache->set($courseid, $result === null ? 0 : $result);
        } catch (\Throwable $e) {
            debugging('local_facultyfeedback: summary failed for course ' . $courseid
                . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            $result = null;
        }

        return self::$summaries[$courseid] = $result;
    }

    /**
     * The context the course-header field template needs.
     *
     * Display strings are assembled here rather than in the template, so the
     * mustache stays free of nested {{#str}} JSON (which breaks on a quote in
     * an interpolated value) and so both the field and the pop-up read from one
     * place.
     *
     * @param int $courseid
     * @return array|null null when there is nothing to show for this course
     */
    public static function badge_context(int $courseid): ?array {
        $summary = self::summary($courseid);
        if ($summary === null) {
            return null;
        }

        $awaiting = '';
        if (!$summary['hasenough'] && $summary['openlabel'] !== '') {
            $awaiting = get_string('openawaiting', 'local_facultyfeedback', (object) [
                'date' => $summary['openlabel'],
                'have' => $summary['responses'],
                'need' => $summary['minresponses'],
            ]);
        }

        $summaryline = '';
        if ($summary['hasenough']) {
            $key = ($summary['responses'] === 1) ? 'ratingsummaryone' : 'ratingsummary';
            $summaryline = get_string($key, 'local_facultyfeedback', (object) [
                'scalemax'  => $summary['scalemax'],
                'responses' => $summary['responsesdisplay'],
            ]);
        }

        return [
            'courseid'       => $summary['courseid'],
            'hasenough'      => (bool) $summary['hasenough'],
            'fieldlabel'     => get_string('fieldlabel', 'local_facultyfeedback'),
            'averagedisplay' => $summary['averagedisplay'],
            'summaryline'    => $summaryline,
            'statusline'     => get_string('notenoughresponses', 'local_facultyfeedback'),
            'awaitingline'   => $awaiting,
            'ctalabel'       => get_string('viewfeedback', 'local_facultyfeedback'),
        ];
    }

    /**
     * The context the pop-up template needs.
     *
     * Chart geometry and the answer histogram are built here, not in
     * {@see self::summary()}, so a course page load never pays for them —
     * only opening the pop-up does.
     *
     * @param int $courseid
     * @return array|null null when there is nothing to report
     */
    public static function modal_context(int $courseid): ?array {
        $summary = self::summary($courseid);
        if ($summary === null) {
            return null;
        }

        $chart = self::chart($summary['trend']);
        $histogram = self::histogram_for_course($courseid);

        $summary['chart'] = $chart;
        $summary['histogram'] = $histogram;
        $summary['hastrend'] = ($chart !== null);
        $summary['hashistogram'] = !empty($histogram);
        $summary['hasthinweeks'] = ($summary['thinweeks'] > 0);

        // Whole stars as a string: a mustache section over an integer renders
        // once, not N times, so the repetition has to happen here.
        $summary['starglyphs'] = str_repeat("\u{2605}", self::stars((float) $summary['average']));

        // Net Promoter Score, from the same responses as the average.
        $nps = self::nps($histogram);
        $summary['hasnps'] = ($nps !== null);
        $summary['nps'] = $nps;
        if ($nps !== null) {
            // The buckets are unusual enough that the figure needs its working
            // shown, or +3 beside a 3.99 average just looks wrong.
            $summary['npsexplain'] = get_string('npsexplain', 'local_facultyfeedback', (object) [
                'promoters'  => number_format($nps['promoters']),
                'detractors' => number_format($nps['detractors']),
                'passives'   => number_format($nps['passives']),
                'total'      => number_format($nps['total']),
            ]);
        } else {
            $summary['npsexplain'] = '';
        }

        if ($chart !== null) {
            $summary['trendnote'] = get_string('averagebyweeknote', 'local_facultyfeedback', (object) [
                'ymin'     => $chart['ymin'],
                'ymax'     => $chart['ymax'],
                'scalemax' => $summary['scalemax'],
            ]);
            $summary['chartaria'] = get_string('chartaria', 'local_facultyfeedback', (object) [
                'scalemax' => $summary['scalemax'],
                'first'    => $chart['firstlabel'],
                'last'     => $chart['lastlabel'],
                'lowest'   => $chart['lowest'],
                'highest'  => $chart['highest'],
            ]);
        } else {
            $summary['trendnote'] = '';
            $summary['chartaria'] = '';
        }

        if ($summary['hasthinweeks']) {
            $summary['thinnote'] = get_string('thinweeks', 'local_facultyfeedback', (object) [
                'count' => $summary['thinweeks'],
                'min'   => $summary['minresponses'],
            ]);
        } else {
            $summary['thinnote'] = '';
        }

        // The distribution now spans every week, so its sub-line reports the
        // whole span rather than naming one week.
        $key = ($summary['weeks'] === 1) ? 'ratingdistributionnoteone' : 'ratingdistributionnote';
        $summary['histogramnote'] = get_string($key, 'local_facultyfeedback', (object) [
            'weeks'     => $summary['weeks'],
            'responses' => $summary['responsesdisplay'],
        ]);

        // Assembled here rather than concatenated in the template, so a
        // translation can reorder it.
        $summary['latestweekvalue'] = get_string('latestweekvalue', 'local_facultyfeedback', (object) [
            'label'     => $summary['latestlabel'],
            'average'   => $summary['latestaverage'],
            'responses' => $summary['latestresponses'],
        ]);

        return $summary;
    }

    /**
     * Build the aggregate for a course from scratch.
     *
     * One query covers every week. The overall average is the response-weighted
     * total (sum of ratings / count of ratings), computed here from the
     * per-week sums — a plain average of the week averages would let a
     * 1-response week count as much as a 268-response one.
     *
     * @param int $courseid
     * @return array|null
     */
    protected static function build_summary(int $courseid): ?array {
        global $DB;

        $cast = $DB->sql_cast_char2real('fv.value', true);
        $sql = "SELECT f.id AS feedbackid, f.name, f.timeopen, f.timeclose,
                       COUNT(fv.id) AS responses,
                       SUM($cast) AS ratingsum
                  FROM {feedback} f
                  JOIN {feedback_item} fi ON fi.feedback = f.id AND fi.typ = :ratedtype
                  JOIN {feedback_value} fv ON fv.item = fi.id AND fv.value <> '0'
                  JOIN {feedback_completed} fc ON fc.id = fv.completed
                 WHERE f.course = :courseid
              GROUP BY f.id, f.name, f.timeopen, f.timeclose
              ORDER BY f.timeopen ASC, f.id ASC";
        $rows = $DB->get_records_sql($sql, [
            'ratedtype' => self::RATED_TYPE,
            'courseid'  => $courseid,
        ]);

        if (!$rows) {
            // No answered weekly feedback yet. If the course has a rated
            // activity at all, say so; otherwise this course is not part of the
            // weekly feedback programme and gets nothing.
            $latestactivity = self::latest_activity($courseid);
            if (!$latestactivity) {
                return null;
            }
            return self::zero_state($courseid, $latestactivity);
        }

        $min = self::min_responses();

        $totalresponses = 0;
        $totalsum = 0.0;
        $weeks = [];
        foreach ($rows as $row) {
            $responses = (int) $row->responses;
            if ($responses <= 0) {
                continue;
            }
            $totalresponses += $responses;
            $totalsum += (float) $row->ratingsum;
            // The activity name is deliberately not carried: the week is
            // labelled from timeopen, and those names run to 255 chars each,
            // which would bloat the cached payload for nothing.
            $weeks[] = [
                'feedbackid' => (int) $row->feedbackid,
                'timeopen'   => (int) $row->timeopen,
                'responses'  => $responses,
                'average'    => (float) $row->ratingsum / $responses,
            ];
        }

        if (!$totalresponses) {
            $latestactivity = self::latest_activity($courseid);
            return $latestactivity ? self::zero_state($courseid, $latestactivity) : null;
        }

        $average = $totalsum / $totalresponses;

        // Every response counts toward the headline average. The trend, though,
        // only plots weeks that clear the threshold — a 1-response week would
        // both mislead and be de-anonymisable. Thin weeks are reported, never
        // silently dropped.
        $plotted = array_values(array_filter($weeks, fn($w) => $w['responses'] >= $min));
        $thin = count($weeks) - count($plotted);
        if (count($plotted) > self::trend_weeks()) {
            $plotted = array_slice($plotted, -self::trend_weeks());
        }

        $latestweek = end($weeks);

        // When the course has responses but not yet enough of them, still say
        // which week is collecting and how far off it is — otherwise the field
        // reads "Not enough responses yet" with no hint of what is missing.
        // Only then is the extra query worth issuing; on a healthy course (the
        // common case) this branch never runs.
        $openlabel = '';
        $openneeded = 0;
        if ($totalresponses < $min) {
            $latestactivity = self::latest_activity($courseid);
            $openlabel = self::short_date((int) ($latestactivity->timeopen ?? $latestweek['timeopen']));
            $openneeded = $min;
        }

        return [
            'courseid'         => $courseid,
            'scalemax'         => self::SCALE_MAX,
            'minresponses'     => $min,
            'hasenough'        => $totalresponses >= $min,
            'average'          => round($average, 2),
            'averagedisplay'   => self::format_rating($average),
            'responses'        => $totalresponses,
            'responsesdisplay' => number_format($totalresponses),
            'weeks'            => count($weeks),
            'latestfeedbackid' => $latestweek['feedbackid'],
            'latestlabel'      => self::week_label($latestweek['timeopen']),
            'latestaverage'    => self::format_rating($latestweek['average']),
            'latestresponses'  => $latestweek['responses'],
            'trend'            => $plotted,
            'thinweeks'        => $thin,
            'openlabel'        => $openlabel,
            'openneeded'       => $openneeded,
        ];
    }

    /**
     * Payload for a course that has rated weekly feedback set up but nothing
     * answered yet (or nothing that counts).
     *
     * @param int $courseid
     * @param \stdClass $activity the newest rated activity
     * @return array
     */
    protected static function zero_state(int $courseid, \stdClass $activity): array {
        return [
            'courseid'         => $courseid,
            'scalemax'         => self::SCALE_MAX,
            'minresponses'     => self::min_responses(),
            'hasenough'        => false,
            'average'          => 0.0,
            'averagedisplay'   => '',
            'responses'        => 0,
            'responsesdisplay' => '0',
            'weeks'            => 0,
            'latestfeedbackid' => (int) $activity->id,
            'latestlabel'      => self::week_label((int) $activity->timeopen),
            'latestaverage'    => '',
            'latestresponses'  => 0,
            'trend'            => [],
            'thinweeks'        => 0,
            'openlabel'        => self::short_date((int) $activity->timeopen),
            'openneeded'       => self::min_responses(),
        ];
    }

    /**
     * The newest rated weekly activity in a course, answered or not.
     *
     * Used to tell "this course runs weekly feedback but nobody has answered
     * the current week" apart from "this course has no weekly feedback".
     *
     * @param int $courseid
     * @return \stdClass|null with id, name, timeopen, timeclose
     */
    protected static function latest_activity(int $courseid): ?\stdClass {
        global $DB;

        $sql = "SELECT f.id, f.name, f.timeopen, f.timeclose
                  FROM {feedback} f
                 WHERE f.course = :courseid
                   AND EXISTS (SELECT 1
                                 FROM {feedback_item} fi
                                WHERE fi.feedback = f.id
                                  AND fi.typ = :ratedtype)
              ORDER BY f.timeopen DESC, f.id DESC";
        $records = $DB->get_records_sql($sql, [
            'courseid'  => $courseid,
            'ratedtype' => self::RATED_TYPE,
        ], 0, 1);

        return $records ? reset($records) : null;
    }

    /**
     * The 1-5 answer distribution across every rated week in a course.
     *
     * Course-scoped rather than per-activity on purpose: it selects exactly the
     * rows the headline average sums, so the distribution *is* that average's
     * derivation. On course 939 the counts total 1,811 and weighting them by
     * rating gives 7,218 — the same numerator and denominator behind the 3.99.
     * A single week would have covered only ~11% of the responses.
     *
     * Only fetched when the pop-up is opened, so the course page never pays for
     * it. Rows come back highest rating first.
     *
     * @param int $courseid
     * @return array list of ['rating', 'stars', 'count', 'barwidth', 'share', 'ismode', 'rowlabel']
     */
    public static function histogram_for_course(int $courseid): array {
        global $DB;

        try {
            $sql = "SELECT fv.value, COUNT(fv.id) AS n
                      FROM {feedback} f
                      JOIN {feedback_item} fi ON fi.feedback = f.id AND fi.typ = :ratedtype
                      JOIN {feedback_value} fv ON fv.item = fi.id AND fv.value <> '0'
                      JOIN {feedback_completed} fc ON fc.id = fv.completed
                     WHERE f.course = :courseid
                  GROUP BY fv.value";
            $records = $DB->get_records_sql($sql, [
                'courseid'  => $courseid,
                'ratedtype' => self::RATED_TYPE,
            ]);
        } catch (\Throwable $e) {
            debugging('local_facultyfeedback: histogram failed for course ' . $courseid
                . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }

        $counts = [];
        for ($rating = 1; $rating <= self::SCALE_MAX; $rating++) {
            $counts[$rating] = 0;
        }
        foreach ($records as $record) {
            $rating = (int) $record->value;
            if ($rating >= 1 && $rating <= self::SCALE_MAX) {
                $counts[$rating] = (int) $record->n;
            }
        }

        $widest = max($counts);
        $total = array_sum($counts);
        if ($widest <= 0 || $total <= 0) {
            return [];
        }

        $rows = [];
        for ($rating = self::SCALE_MAX; $rating >= 1; $rating--) {
            $count = $counts[$rating];
            // Two different percentages, deliberately: the bar is scaled to the
            // widest row so the shape stays legible when one rating dominates
            // (4 star takes 54% here), while the printed figure is the share of
            // all respondents, which is what the number should mean to a reader.
            $share = round($count / $total * 100, 1);
            $rows[] = [
                'rating'   => $rating,
                'stars'    => str_repeat("\u{2605}", $rating),
                'count'    => $count,
                'barwidth' => round($count / $widest * 100, 1),
                'share'    => $share,
                'ismode'   => ($count === $widest),
                // Screen readers get the row spelled out; the star glyphs and
                // the bar are decorative.
                'rowlabel' => get_string('histogramrow', 'local_facultyfeedback', (object) [
                    'rating'   => $rating,
                    'scalemax' => self::SCALE_MAX,
                    'count'    => $count,
                    'share'    => $share,
                ]),
            ];
        }

        return $rows;
    }

    /**
     * Net Promoter Score over the same responses the average uses.
     *
     * NPS = %promoters - %detractors, both as a share of *all* responses.
     * Ratings of 4 are passive: they sit in the denominator but in neither
     * percentage, so they dilute the score without moving it either way. On
     * course 939 that is 54% of responses, which is why the score lands near
     * zero (+3) while the mean is a healthy 3.99 — the two measure different
     * things and are shown side by side rather than one instead of the other.
     *
     * @param array $histogram rows from histogram_for_course()
     * @return array|null ['value', 'display', 'promoters', 'passives', 'detractors', 'total'],
     *                    or null when there is nothing to score
     */
    public static function nps(array $histogram): ?array {
        if (!$histogram) {
            return null;
        }

        $promoters = 0;
        $detractors = 0;
        $total = 0;
        foreach ($histogram as $row) {
            $rating = (int) $row['rating'];
            $count = (int) $row['count'];
            $total += $count;
            if (in_array($rating, self::NPS_PROMOTERS, true)) {
                $promoters += $count;
            } else if (in_array($rating, self::NPS_DETRACTORS, true)) {
                $detractors += $count;
            }
        }

        if ($total <= 0) {
            return null;
        }

        // Equivalent to (%promoters - %detractors) but without compounding two
        // roundings; the conventional NPS is a whole number.
        $value = (int) round(($promoters - $detractors) / $total * 100);

        return [
            'value' => $value,
            // Signed, because the sign is the point — but a zero score reads as
            // "0%", not "+0%".
            //
            // The per-cent sign is accurate here rather than decorative: this is
            // a difference of two percentages over the same denominator, so it
            // is itself a share of respondents. On course 939, 440 promoters
            // minus 387 detractors is a net 53 people out of 1,811 = 2.93%.
            // (A textbook NPS is written bare to mark it as an index, but this
            // figure is not a textbook NPS anyway — see the class constants.)
            'display'    => (($value === 0) ? '0' : sprintf('%+d', $value)) . '%',
            'promoters'  => $promoters,
            'passives'   => $total - $promoters - $detractors,
            'detractors' => $detractors,
            'total'      => $total,
        ];
    }

    /**
     * Turn the trend into ready-to-draw SVG geometry.
     *
     * The template stays dumb: all arithmetic happens here. A line (not bars)
     * because this is change-over-time, and on a *focused* y-axis because the
     * real spread is tiny — course 939 runs 3.94 to 4.07, so a zero-baseline
     * bar chart would be eight identical bars. The axis range is always drawn
     * and labelled so the focus is declared, never implied.
     *
     * Weeks are plotted in order rather than on a calendar axis: gaps between
     * feedback weeks are real (course 939 has none between 9 May and 6 Jun) and
     * a time axis would draw a long flat line through a period nobody was asked.
     *
     * @param array $trend rows from summary()['trend']
     * @return array|null null when there is nothing plottable
     */
    public static function chart(array $trend): ?array {
        $count = count($trend);
        if ($count < 1) {
            return null;
        }

        // Canvas. Matches the viewBox in the summary template.
        $left = 34.0;
        $right = 548.0;
        $top = 12.0;
        $bottom = 124.0;

        $values = array_map(fn($week) => (float) $week['average'], $trend);
        $lowest = min($values);
        $highest = max($values);

        // Snap the window out to half-points around the data, clamped to the
        // scale, with a floor on the span so a flat run is not magnified.
        $ymin = max(1.0, floor(($lowest - 0.25) * 2) / 2);
        $ymax = min((float) self::SCALE_MAX, ceil(($highest + 0.25) * 2) / 2);
        if ($ymax - $ymin < 0.5) {
            $ymax = min((float) self::SCALE_MAX, $ymin + 0.5);
            $ymin = max(1.0, $ymax - 0.5);
        }
        $span = $ymax - $ymin;

        $ycoord = function (float $value) use ($top, $bottom, $ymin, $span): float {
            return round($bottom - (($value - $ymin) / $span) * ($bottom - $top), 1);
        };
        $xcoord = function (int $index) use ($left, $right, $count): float {
            if ($count === 1) {
                return round(($left + $right) / 2, 1);
            }
            return round($left + ($right - $left) * ($index / ($count - 1)), 1);
        };

        // Label the first, the highest and the last point only — a number on
        // every point is noise. Ties resolve to the earliest, which is fine.
        $highestindex = array_search($highest, $values, true);

        $points = [];
        $path = [];
        foreach ($trend as $index => $week) {
            $x = $xcoord($index);
            $y = $ycoord((float) $week['average']);
            $path[] = $x . ',' . $y;
            $islast = ($index === $count - 1);
            $points[] = [
                'x'         => $x,
                'y'         => $y,
                'islast'    => $islast,
                'radius'    => $islast ? 5 : 4,
                'label'     => self::format_rating((float) $week['average']),
                'showlabel' => ($index === 0 || $index === $highestindex || $islast),
                // Nudge the label above the point, or below when the point sits
                // near the top of the plot and the label would clip.
                'labely'    => ($y - $top < 14) ? round($y + 15, 1) : round($y - 8, 1),
                'anchor'    => $islast ? 'end' : ($index === 0 ? 'start' : 'middle'),
                'weeklabel' => self::week_label((int) $week['timeopen']),
                'shortdate' => self::short_date((int) $week['timeopen']),
                'responses' => $week['responses'],
            ];
        }

        // Up to five x tick labels, evenly spaced, always including both ends.
        $ticks = [];
        $maxticks = 5;
        $stride = ($count <= $maxticks) ? 1 : (int) ceil(($count - 1) / ($maxticks - 1));
        for ($index = 0; $index < $count; $index += $stride) {
            $ticks[$index] = true;
        }
        $ticks[$count - 1] = true;
        foreach ($points as $index => $point) {
            $points[$index]['showtick'] = isset($ticks[$index]);
        }

        // Horizontal grid at each half-point of the window, labelled at the
        // ends and the middle so the axis reads without crowding.
        $gridlines = [];
        for ($value = $ymin; $value <= $ymax + 0.001; $value += 0.5) {
            $rounded = round($value, 1);
            $gridlines[] = [
                'y'        => $ycoord($rounded),
                'value'    => number_format($rounded, 1),
                'showlabel' => (abs($rounded - $ymin) < 0.001
                    || abs($rounded - $ymax) < 0.001
                    || abs($rounded - ($ymin + $span / 2)) < 0.001),
            ];
        }

        $firstpoint = reset($points);
        $lastpoint = end($points);

        return [
            'left'       => $left,
            'right'      => $right,
            'top'        => $top,
            'bottom'     => $bottom,
            'ymin'       => number_format($ymin, 1),
            'ymax'       => number_format($ymax, 1),
            'area'       => implode(' ', $path) . ' ' . $right . ',' . $bottom . ' ' . $left . ',' . $bottom,
            'line'       => implode(' ', $path),
            'points'     => $points,
            'gridlines'  => $gridlines,
            'single'     => ($count === 1),
            // For the chart's accessible description.
            'firstlabel' => $firstpoint['weeklabel'],
            'lastlabel'  => $lastpoint['weeklabel'],
            'lowest'     => self::format_rating($lowest),
            'highest'    => self::format_rating($highest),
        ];
    }

    /**
     * A rating formatted the way it is shown everywhere: two decimals.
     *
     * @param float $value
     * @return string
     */
    public static function format_rating(float $value): string {
        return number_format($value, 2);
    }

    /**
     * Whole stars for the glyph row beside the headline number.
     *
     * @param float $value
     * @return int 0..SCALE_MAX
     */
    protected static function stars(float $value): int {
        return max(0, min(self::SCALE_MAX, (int) round($value)));
    }

    /**
     * "Week commencing 20 June 2026".
     *
     * @param int $timestamp {feedback}.timeopen
     * @return string
     */
    public static function week_label(int $timestamp): string {
        if ($timestamp <= 0) {
            return get_string('weekunknown', 'local_facultyfeedback');
        }
        return get_string('weekcommencing', 'local_facultyfeedback', self::long_date($timestamp));
    }

    /**
     * Date in the user's own timezone and language, long form.
     *
     * @param int $timestamp
     * @return string
     */
    protected static function long_date(int $timestamp): string {
        return userdate($timestamp, get_string('strftimedate', 'core_langconfig'));
    }

    /**
     * Date in the user's own timezone and language, short form (axis ticks).
     *
     * @param int $timestamp
     * @return string
     */
    protected static function short_date(int $timestamp): string {
        if ($timestamp <= 0) {
            return '';
        }
        return userdate($timestamp, get_string('strftimedateshortmonthabbr', 'core_langconfig'));
    }
}
