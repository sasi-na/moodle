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
 * Strings for local_batchvitals.
 *
 * @package   local_batchvitals
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Batch vitals';
$string['reportpage'] = 'Batch vitals';
$string['settings'] = 'Batch vitals';
$string['batchvitals:view'] = 'View batch vitals';
$string['batchvitals:viewprogramme'] = 'View batch vitals for a programme';

$string['navlink'] = 'Batch stats';
$string['scope_batch'] = 'Batch';
$string['scope_programme'] = 'Programme';

$string['coordinators'] = 'Programme coordinators';
$string['coordinator'] = 'Coordinator';
$string['since'] = 'Assigned';
$string['coordinators_none'] = 'Nobody has been given programme access yet. Assign the "Programme stats viewer" role on a programme category to add a coordinator.';
$string['coordinators_summary'] = '{$a->people} coordinator(s) across {$a->programmes} programme(s).';
$string['coordinators_help'] = 'Add or remove a coordinator from the programme category itself: Users > Permissions > Assign roles. Changes take effect on their next page load.';

$string['rolename'] = 'Programme stats viewer';
$string['roledescription'] = 'Allows a programme coordinator to view the Batch vitals figures for the course category they are assigned to, and nothing else.';

$string['batchcategories'] = 'Batch categories';
$string['batchcategories_desc'] = 'Comma-separated course category ids to report on, in the order they should appear. Each is treated as a batch and counted over its whole subtree. Leave empty to disable the report.';
$string['activedays'] = 'Active window (days)';
$string['activedays_desc'] = 'A learner counts as active if they have signed in within this many days.';
$string['rolefilter'] = 'Count students only';
$string['rolefilter_desc'] = 'Restrict headcounts to users holding a student role in the batch\'s courses, excluding staff enrolments. More accurate, but roughly four times slower to compute. Measured on this site the correction is under one percent.';

$string['task_refresh'] = 'Refresh batch vitals cache';

$string['nobatches'] = 'No batch categories are configured yet.';
$string['nobatches_help'] = 'Set the category ids in the plugin settings, then run the refresh task or use Refresh now.';
$string['configure'] = 'Configure batches';
$string['nodata'] = 'No figures cached yet for this batch. Use Refresh now, or wait for tonight\'s scheduled task.';

$string['batch'] = 'Batch';
$string['refreshnow'] = 'Refresh now';
$string['refreshed'] = 'Refreshed {$a->name} in {$a->secs}s.';
$string['refreshfailed'] = 'Could not refresh that batch.';
$string['generated'] = 'Cached {$a}';
$string['never'] = 'not yet built';
$string['builtin'] = 'built in {$a}s';

$string['learners'] = 'Learners';
$string['learners_sub'] = 'distinct enrolled accounts';
$string['learners_sub_students'] = 'distinct students enrolled';
$string['active'] = 'Active · {$a}d';
$string['active_sub'] = 'signed in within {$a} days';
$string['inactive'] = 'Inactive';
$string['inactive_sub'] = 'no sign-in in {$a} days';
$string['neverloggedin'] = 'Never logged in';
$string['neverloggedin_sub'] = 'no sign-in, ever';
$string['suspended'] = 'Suspended';
$string['suspended_sub'] = 'blocked accounts';
$string['structure'] = 'Structure';
$string['structure_sub'] = '{$a} programmes · courses';
$string['ofbatch'] = '{$a} of batch';

$string['engagement'] = 'Learner engagement';
$string['engagement_sub'] = 'Every enrolled account split by last-access recency.';
$string['legend_active'] = 'Active — signed in within {$a} days';
$string['legend_dormant'] = 'Dormant — logged in once, but not recently';
$string['legend_never'] = 'Never logged in';

$string['completion'] = 'Activity completion';
$string['completion_sub'] = 'Share of tracked activity records marked complete.';
$string['completion_meta'] = '{$a->done} of {$a->total} tracked records across {$a->courses} courses.';
$string['completion_thin'] = 'Thin sample — this batch has very few tracked records left, so the percentage is not comparable with current batches.';
$string['completion_none'] = 'No completion-tracked activity records in this batch.';

$string['allbatches'] = 'All batches';
$string['col_batch'] = 'Batch';
$string['col_learners'] = 'Learners';
$string['col_active'] = 'Active';
$string['col_never'] = 'Never';
$string['col_susp'] = 'Susp.';
$string['col_courses'] = 'Courses';
$string['col_programs'] = 'Progs';
$string['col_completion'] = 'Compl.';
$string['privacy:metadata'] = 'The Batch vitals plugin stores only aggregate counts and no personal data.';
