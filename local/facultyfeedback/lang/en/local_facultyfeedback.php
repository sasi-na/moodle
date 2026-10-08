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
 * Strings for local_facultyfeedback.
 *
 * @package   local_facultyfeedback
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Weekly faculty feedback';

// Capability.
$string['facultyfeedback:view'] = 'See a course\'s weekly feedback rating';

// Settings.
$string['settings'] = 'Weekly faculty feedback';
$string['enabled'] = 'Show weekly feedback to faculty';
$string['enabled_desc'] = 'Adds a "Weekly feedback" figure to the course header, visible only to users who can see a course\'s feedback rating. Learners never see it. Off until you are ready to switch it on.';
$string['minresponses'] = 'Minimum responses';
$string['minresponses_desc'] = 'How many rated responses a course needs before its average is shown. Below this the course header says the feedback is still open instead of publishing a figure — a new weekly feedback often sits at nought or one response for days, and an average built on one answer would both mislead and identify the person who gave it. Weeks below this number are also left off the trend, though every response still counts toward the overall average.';
$string['trendweeks'] = 'Weeks in the trend';
$string['trendweeks_desc'] = 'How many of the most recent qualifying weeks the pop-up plots.';

// Course header field.
$string['fieldlabel'] = 'Weekly feedback';
$string['viewfeedback'] = 'View feedback';
$string['notenoughresponses'] = 'Not enough responses yet';
$string['openawaiting'] = 'Week of {$a->date} is open · {$a->have} of {$a->need} needed';
// Follows the average in the course header, e.g. "3.99" + "/ 5 · 1,811 responses".
$string['ratingsummary'] = '/ {$a->scalemax} · {$a->responses} responses';
$string['ratingsummaryone'] = '/ {$a->scalemax} · 1 response';

// Pop-up.
$string['modaltitle'] = 'Weekly feedback';
$string['outofscale'] = '/ {$a}';
$string['nps'] = 'Net Promoter Score';
$string['npsexplain'] = 'Promoters ({$a->promoters} rated 5) minus detractors ({$a->detractors} rated 1–3), as a share of all {$a->total} responses. The {$a->passives} who rated 4 count as passive and are excluded from both sides.';
$string['overallresponses'] = 'Responses';
$string['feedbackweeks'] = 'Feedback weeks';
$string['latestweek'] = 'Latest week';
$string['latestweekvalue'] = '{$a->label} · {$a->average} from {$a->responses}';
$string['averagebyweek'] = 'Average by feedback week';
$string['averagebyweeknote'] = 'Scale {$a->ymin}–{$a->ymax} of {$a->scalemax}, so week-to-week movement is legible. One point per weekly feedback, labelled by the week it opened.';
$string['ratingdistribution'] = 'Rating distribution';
$string['ratingdistributionnote'] = 'All {$a->weeks} feedback weeks · {$a->responses} responses';
$string['ratingdistributionnoteone'] = 'One feedback week · {$a->responses} responses';
$string['thinweeks'] = '{$a->count} week(s) with fewer than {$a->min} responses are not plotted. Their responses still count toward the overall average.';
$string['viewastable'] = 'View as table';
$string['colweek'] = 'Week commencing';
$string['colaverage'] = 'Average';
$string['colresponses'] = 'Responses';
$string['allweeks'] = 'All weeks';
$string['openlatest'] = 'Open this week\'s feedback';
$string['weekcommencing'] = 'Week commencing {$a}';
$string['weekunknown'] = 'Undated week';
$string['chartaria'] = 'Weekly average rating out of {$a->scalemax}, from {$a->first} to {$a->last}. Lowest {$a->lowest}, highest {$a->highest}.';
$string['histogramrow'] = '{$a->rating} of {$a->scalemax} — {$a->count} responses, {$a->share}% of the total';
$string['nofeedbackyet'] = 'No responses have been recorded yet.';

// Report builder datasource.
$string['sourceweeklyfeedback'] = 'Weekly faculty feedback';
$string['entityweeklyfeedback'] = 'Weekly feedback';
$string['colweekcommencing'] = 'Week commencing';
$string['colweekcloses'] = 'Week closes';
$string['colactivityname'] = 'Feedback activity';
$string['colresponsecount'] = 'Responses';
$string['colresponsecount_help'] = 'How many learners answered the rated question in this week\'s feedback. A zero means the feedback was set up but nobody responded.

To get a course\'s overall average across several weeks, add up "Rating total" and divide by the sum of "Responses". Averaging the "Average" column instead gives the average of the weekly averages, which weights a week with 3 responses the same as one with 300.';
$string['colratingtotal'] = 'Rating total';
$string['colratingtotal_help'] = 'The sum of every rating given in this week, e.g. 785 from 197 responses.

Divide the sum of this column by the sum of "Responses" to get a correct overall average across several weeks.';
$string['colaverageofscale'] = 'Average (of {$a})';
$string['colaverageofscale_help'] = 'This week\'s mean rating. Empty when nobody responded.

Do not average this column to summarise several weeks — see the help on "Rating total".';
$string['colratingcount'] = '{$a} star — responses';
$string['colratingshare'] = '{$a} star — share (%)';
$string['colteachers'] = 'Teachers on the course';
$string['colteachers_help'] = 'Everyone holding a teacher or editing-teacher role on the course, listed for context.

The feedback is collected about the course, not about a named person, and every course here has several teachers — so this column does not attribute the score to any one of them.';
$string['nps_help'] = 'Promoters minus detractors as a share of all responses, where a rating of 5 is a promoter and 1 to 3 are detractors. A rating of 4 is passive: it counts in the total but on neither side. Empty when nobody responded.

This is not comparable with published Net Promoter Score benchmarks, which are based on a 0 to 10 "how likely are you to recommend" question rather than this 1 to 5 agreement question.';

// Errors.
$string['disabled'] = 'Weekly faculty feedback is switched off on this site.';
$string['nofeedback'] = 'This course has no weekly feedback to report.';

// Privacy.
$string['privacy:metadata'] = 'The Weekly faculty feedback plugin stores no personal data of its own. It only reports aggregate counts and averages over feedback responses that mod_feedback already holds, and never identifies a respondent.';
