<?php
// This file is part of VPL for Moodle - http://vpl.dis.ulpgc.es/
//
// VPL for Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// VPL for Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with VPL for Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Background AI grading of a VPL activity.
 *
 * @package    mod_vpl
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_vpl\task;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/vpl/vpl.class.php');

use mod_vpl\ai_grader;

/**
 * Grades one chunk of an activity's submissions, then requeues itself.
 *
 * The work is deliberately split across runs rather than done in one long
 * task. task_adhoc_max_runtime is 600s here, and a cron pod preempted on a
 * spot node orphans its task claim -- a short run loses at most one chunk,
 * and because the pending set is re-derived from the database on every run,
 * repeating a chunk is harmless.
 */
class ai_grade_batch extends \core\task\adhoc_task {

    /** @var int Give up after this many runs that grade nothing at all. */
    const MAX_STALLS = 3;

    /**
     * @return string
     */
    public function get_name() {
        return get_string('aigrading_taskname', 'vpl');
    }

    /**
     * Grade a chunk and requeue while work remains.
     */
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        if (empty($data->jobid)) {
            return;
        }

        $job = $DB->get_record('vpl_ai_grading_job', ['id' => $data->jobid]);
        if (!$job) {
            mtrace("vpl ai grading: job {$data->jobid} no longer exists, nothing to do");
            return;
        }
        if ($job->status === 'cancelled' || $job->status === 'done') {
            mtrace("vpl ai grading: job {$job->id} is {$job->status}, nothing to do");
            return;
        }

        try {
            $vpl = new \mod_vpl($job->cmid);
        } catch (\Throwable $e) {
            $this->finish($job, 'failed', 'Activity no longer available: ' . $e->getMessage());
            return;
        }

        // Nothing here can ever be graded: VPL refuses to write a grade for a
        // Practice (No Grades) activity. Close the job rather than spend three
        // rounds of API calls discovering that.
        if ($vpl->is_practice_no_grades_mode()) {
            $this->finish($job, 'done', get_string('aigrading_nogrademode', 'vpl'));
            mtrace("vpl ai grading: job {$job->id} skipped, activity is in Practice (No Grades) mode");
            return;
        }

        $pending = $this->get_pending($vpl, (bool) $job->regradeall);

        $job->total = count($pending) + $job->processed;
        $job->status = 'running';
        $job->timemodified = time();
        $DB->update_record('vpl_ai_grading_job', $job);

        if (!$pending) {
            $this->finish($job, 'done', '');
            mtrace("vpl ai grading: job {$job->id} complete, {$job->processed} graded, {$job->failed} failed");
            return;
        }

        $chunk = array_slice($pending, 0, ai_grader::get_chunk_size());
        mtrace("vpl ai grading: job {$job->id} grading " . count($chunk) . " of " . count($pending) . " pending");

        try {
            $results = ai_grader::grade_batch($vpl, $chunk);
        } catch (\Throwable $e) {
            // A configuration error (no keys) must stop the job rather than
            // requeue forever.
            $this->finish($job, 'failed', $e->getMessage());
            mtrace("vpl ai grading: job {$job->id} failed: " . $e->getMessage());
            return;
        }

        $graded = 0;
        $failed = 0;
        foreach ($results as $result) {
            if ($result['success']) {
                $graded++;
            } else {
                $failed++;
            }
        }

        $job = $DB->get_record('vpl_ai_grading_job', ['id' => $job->id]);
        if (!$job || $job->status === 'cancelled') {
            return;
        }
        $job->processed += $graded;
        $job->failed += $failed;
        $job->stalls = $graded > 0 ? 0 : $job->stalls + 1;
        $job->timemodified = time();
        $DB->update_record('vpl_ai_grading_job', $job);

        mtrace("vpl ai grading: job {$job->id} chunk done, graded $graded, failed $failed");

        if ($job->stalls >= self::MAX_STALLS) {
            $this->finish($job, 'failed', 'No submission could be graded in ' . self::MAX_STALLS . ' consecutive runs');
            mtrace("vpl ai grading: job {$job->id} stalled, giving up");
            return;
        }

        $this->requeue($job->id);
    }

    /**
     * Submissions still waiting for a grade, newest submission per user.
     *
     * @param \mod_vpl $vpl
     * @param bool $regradeall
     * @return \stdClass[]
     */
    protected function get_pending(\mod_vpl $vpl, bool $regradeall): array {
        $pending = [];
        foreach ($vpl->all_last_user_submission() as $sub) {
            if (!$sub) {
                continue;
            }
            if (!$regradeall && !is_null($sub->grade) && $sub->grade !== '') {
                continue;
            }
            $pending[] = $sub;
        }
        return $pending;
    }

    /**
     * Close the job off.
     *
     * @param \stdClass $job
     * @param string $status
     * @param string $error
     */
    protected function finish(\stdClass $job, string $status, string $error) {
        global $DB;
        $job->status = $status;
        $job->error = $error;
        $job->timemodified = time();
        $DB->update_record('vpl_ai_grading_job', $job);
    }

    /**
     * Queue the next chunk of the same job.
     *
     * @param int $jobid
     */
    protected function requeue(int $jobid) {
        $task = new self();
        $task->set_custom_data(['jobid' => $jobid]);
        $task->set_component('mod_vpl');
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Create a job and queue its first chunk.
     *
     * @param \mod_vpl $vpl
     * @param bool $regradeall
     * @param int $userid who asked for it
     * @return int job id
     */
    public static function queue_job(\mod_vpl $vpl, bool $regradeall, int $userid): int {
        global $DB;

        // A Practice (No Grades) activity cannot hold a grade, so there is
        // nothing for a job to do.
        if ($vpl->is_practice_no_grades_mode()) {
            return 0;
        }

        $job = new \stdClass();
        $job->cmid = $vpl->get_course_module()->id;
        $job->vplid = $vpl->get_instance()->id;
        $job->courseid = $vpl->get_course()->id;
        $job->regradeall = $regradeall ? 1 : 0;
        $job->status = 'queued';
        $job->total = 0;
        $job->processed = 0;
        $job->failed = 0;
        $job->stalls = 0;
        $job->error = '';
        $job->userid = $userid;
        $job->timecreated = time();
        $job->timemodified = time();
        $job->id = $DB->insert_record('vpl_ai_grading_job', $job);

        $task = new self();
        $task->set_custom_data(['jobid' => $job->id]);
        $task->set_component('mod_vpl');
        \core\task\manager::queue_adhoc_task($task);

        return $job->id;
    }

    /**
     * The job currently queued or running for an activity, if any.
     *
     * @param int $cmid
     * @return \stdClass|false
     */
    public static function get_active_job(int $cmid) {
        global $DB;
        return $DB->get_record_select(
            'vpl_ai_grading_job',
            'cmid = ? AND status IN (?, ?)',
            [$cmid, 'queued', 'running'],
            '*',
            IGNORE_MULTIPLE
        );
    }
}
