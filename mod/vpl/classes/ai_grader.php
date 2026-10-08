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
 * Shared AI grading logic for VPL submissions.
 *
 * Prompt construction, the Gemini call and the grade write live here so the
 * interactive endpoint (views/ai_grade_submission.php) and the background task
 * cannot drift apart on how a submission is graded.
 *
 * @package    mod_vpl
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_vpl;

defined('MOODLE_INTERNAL') || die();

/**
 * Grades VPL submissions through the Gemini API.
 */
class ai_grader {

    /** @var string Model used when mod_vpl/aimodel is unset. */
    const DEFAULT_MODEL = 'gemini-2.5-flash';

    /** @var int Simultaneous in-flight API calls when mod_vpl/aiconcurrency is unset. */
    const DEFAULT_CONCURRENCY = 8;

    /** @var int Submissions graded per task run when mod_vpl/aichunksize is unset. */
    const DEFAULT_CHUNK_SIZE = 200;

    /** @var string Endpoint template: model, then key. */
    const ENDPOINT = 'https://generativelanguage.googleapis.com/v1/models/%s:generateContent?key=%s';

    /** @var int Per-request timeout in seconds. */
    const TIMEOUT = 120;

    /**
     * API keys to spread requests across, in the order they were configured.
     *
     * Quota on the Generative Language API is enforced per Google Cloud project,
     * not per key, so several keys only add throughput when they belong to
     * different projects. One key per line.
     *
     * @return string[]
     * @throws \moodle_exception when no key is configured
     */
    public static function get_keys(): array {
        $raw = (string) get_config('mod_vpl', 'aikeys');
        $keys = [];
        foreach (preg_split('/[\r\n,]+/', $raw) as $key) {
            $key = trim($key);
            if ($key !== '') {
                $keys[] = $key;
            }
        }
        if (!$keys) {
            throw new \moodle_exception('aigrading_nokeys', 'vpl');
        }
        return $keys;
    }

    /**
     * @return string Gemini model id
     */
    public static function get_model(): string {
        $model = trim((string) get_config('mod_vpl', 'aimodel'));
        return $model !== '' ? $model : self::DEFAULT_MODEL;
    }

    /**
     * Requests in flight at once. Each one also reads the submission's files
     * from moodledata over NFS, so this is capped well below what the API
     * would allow.
     *
     * @return int
     */
    public static function get_concurrency(): int {
        $n = (int) get_config('mod_vpl', 'aiconcurrency');
        if ($n < 1) {
            $n = self::DEFAULT_CONCURRENCY;
        }
        return min($n, 64);
    }

    /**
     * @return int submissions graded per task run
     */
    public static function get_chunk_size(): int {
        $n = (int) get_config('mod_vpl', 'aichunksize');
        return $n > 0 ? $n : self::DEFAULT_CHUNK_SIZE;
    }

    /**
     * Build the grading prompt for one submission.
     *
     * @param \mod_vpl $vpl
     * @param \mod_vpl_submission_CE $submission
     * @return array [prompt, studentcode, executionresults]
     */
    public static function build_prompt(\mod_vpl $vpl, \mod_vpl_submission_CE $submission): array {
        $files = $submission->get_submitted_fgm()->getallfiles();
        $studentcode = '';
        foreach ($files as $filename => $filedata) {
            $studentcode .= "// File: $filename\n";
            $studentcode .= $filedata . "\n\n";
        }

        $ce = $submission->getCE();
        $compilation = isset($ce['compilation']) ? $ce['compilation'] : 'No compilation results';
        $execution = isset($ce['execution']) ? $ce['execution'] : 'No execution results';
        $maxgrade = $vpl->get_grade();

        if ($vpl->is_practice_mode()) {
            $prompt = "Please grade this programming assignment and provide a numeric grade out of {$maxgrade} points.

**GRADING RULES for PRACTICE:**
1. If the student has submitted any code (at least one line), give at least 1 mark.
2. If at least one test case has passed, give the FULL marks ({$maxgrade}).

**STUDENT CODE:**
{$studentcode}

**COMPILATION RESULTS:**
{$compilation}

**TEST CASE RESULTS:**
{$execution}

**INSTRUCTIONS:**
1. Analyze the code for logic correctness and provide feedback.
2. If any code is present, ensure the final grade is at least 1.
3. If at least one test case passed in the TEST CASE RESULTS, the final grade MUST be {$maxgrade}.
4. Provide specific feedback for the student.
5. **IMPORTANT: End your response with \"FINAL GRADE: X/{$maxgrade}\" where X is the numeric grade based on the above rules**

Please provide detailed feedback followed by the final numeric grade.";
        } else {
            $logic = floor($maxgrade * 0.7);
            $exec = ceil($maxgrade * 0.3);
            $prompt = "Please grade this programming assignment and provide a numeric grade out of {$maxgrade} points.

**GRADING CRITERIA (Total: {$maxgrade} Points)**

1. **Logic and Correctness ({$logic} Points)**
   - Code completeness and implementation
   - Memory allocation and management
   - Code readability and structure
   - Performance and efficiency

2. **Execution and Output ({$exec} Points)**
   - Correct output and functionality
   - Handles edge cases properly
   - No runtime errors

**STUDENT CODE:**
{$studentcode}

**COMPILATION RESULTS:**
{$compilation}

**TEST CASE RESULTS:**
{$execution}

**INSTRUCTIONS:**
1. Analyze the code for logic correctness, completeness, readability, and performance
2. Check compilation and test results for execution quality
3. Provide specific feedback for each criteria
4. Give constructive suggestions for improvement
5. **IMPORTANT: End your response with \"FINAL GRADE: X/{$maxgrade}\" where X is the numeric grade**

Please provide detailed feedback followed by the final numeric grade.";
        }

        return [$prompt, $studentcode, $execution];
    }

    /**
     * @param string $prompt
     * @return string JSON request body
     */
    public static function request_body(string $prompt): string {
        return json_encode([
            'contents' => [[
                'parts' => [[
                    'text' => $prompt,
                ]],
            ]],
        ]);
    }

    /**
     * Pull the model's text out of a Gemini response.
     *
     * @param string $raw
     * @return string
     * @throws \Exception on a response without a candidate
     */
    public static function extract_text(string $raw): string {
        $data = json_decode($raw, true);
        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            throw new \Exception('Invalid AI API response');
        }
        return $data['candidates'][0]['content']['parts'][0]['text'];
    }

    /**
     * Turn the model's answer into a grade, applying the deterministic practice
     * mode rules and clamping to the activity's maximum.
     *
     * @param \mod_vpl $vpl
     * @param string $text model response
     * @param string $studentcode
     * @param string $execution test case output
     * @return array [grade, text] with the text rewritten if a rule overrode the model
     * @throws \Exception when no grade can be read and no rule applies
     */
    public static function resolve_grade(\mod_vpl $vpl, string $text, string $studentcode, string $execution): array {
        $maxgrade = $vpl->get_grade();
        preg_match('/FINAL GRADE:\s*(\d+(?:\.\d+)?)/i', $text, $matches);
        $grade = isset($matches[1]) ? floatval($matches[1]) : null;

        if ($vpl->is_practice_mode()) {
            $testresults = $vpl->parse_test_case_results($execution);
            if ($testresults['passed'] > 0) {
                $grade = $maxgrade;
            } else if (trim($studentcode) !== '') {
                $grade = 1;
            } else {
                $grade = 0;
            }
            if (isset($matches[1]) && floatval($matches[1]) != $grade) {
                $text = preg_replace('/FINAL GRADE:\s*(\d+(?:\.\d+)?)/i', "FINAL GRADE: $grade", $text);
            }
        }

        if ($grade === null) {
            throw new \Exception('Could not extract grade from AI response');
        }
        $grade = max(0, min($grade, $maxgrade));

        return [$grade, $text];
    }

    /**
     * Write the grade and feedback, and fire the matching grading event.
     *
     * @param \mod_vpl $vpl
     * @param \mod_vpl_submission_CE $submission
     * @param \stdClass $subinstance submission record as it was BEFORE this grade
     * @param float $grade
     * @param string $feedback
     * @param int $userid
     * @return bool
     * @throws \Exception when the grade cannot be saved
     */
    public static function apply_grade(\mod_vpl $vpl, \mod_vpl_submission_CE $submission,
            \stdClass $subinstance, float $grade, string $feedback, int $userid): bool {
        $maxgrade = $vpl->get_grade();

        $gradeinfo = new \stdClass();
        $gradeinfo->grade = $grade;
        $comment = "\n=== AI GRADING ANALYSIS ===\n" . $feedback . "\n";
        $comment .= "\n#AI Grade: " . $grade . "/" . $maxgrade . "\n";
        $comment .= "=== END AI ANALYSIS ===\n";
        $gradeinfo->comments = $comment;

        if (!$submission->set_grade($gradeinfo, false)) {
            throw new \Exception('Failed to save grade to database');
        }

        $params = [
            'objectid' => $submission->get_instance()->id,
            'context' => $vpl->get_context(),
            'relateduserid' => $userid,
            'other' => [
                'vplid' => $vpl->get_instance()->id,
            ],
        ];
        if ($subinstance->grader != 0 && $subinstance->dategraded > 0) {
            $event = \mod_vpl\event\submission_grade_updated::create($params);
        } else {
            $event = \mod_vpl\event\submission_graded::create($params);
        }
        $event->trigger();

        return true;
    }

    /**
     * Grade one submission. Used by the interactive endpoint.
     *
     * @param \mod_vpl $vpl
     * @param \stdClass $subinstance
     * @return float the grade written
     */
    public static function grade_one(\mod_vpl $vpl, \stdClass $subinstance): float {
        $results = self::grade_batch($vpl, [$subinstance]);
        $result = reset($results);
        if (!$result['success']) {
            throw new \Exception($result['error']);
        }
        return $result['grade'];
    }

    /**
     * One Gemini call, returning the response untouched.
     *
     * Used by the browser-facing proxy, where the caller wants the raw body to
     * hand back to JavaScript. Keys are tried in turn: a key that reports a
     * rate limit falls through to the next one rather than failing the call.
     *
     * @param string $prompt
     * @return array ['httpcode' => int, 'body' => string]
     */
    public static function call_api(string $prompt): array {
        $keys = self::get_keys();
        $model = self::get_model();
        $body = self::request_body($prompt);

        $last = ['httpcode' => 0, 'body' => ''];
        foreach ($keys as $key) {
            $ch = curl_init(sprintf(self::ENDPOINT, $model, $key));
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT => self::TIMEOUT,
            ]);
            $raw = (string) curl_exec($ch);
            $httpcode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $last = ['httpcode' => $httpcode, 'body' => $raw];
            if ($httpcode !== 429 && $httpcode !== 403) {
                return $last;
            }
        }
        return $last;
    }

    /**
     * Grade a set of submissions, keeping several API calls in flight at once.
     *
     * Keys are used round-robin. A key that reports a rate limit is dropped for
     * the rest of the run and its submission goes back on the queue for another
     * key; when every key is rate limited the remaining submissions are left
     * untouched, to be picked up by the next run.
     *
     * @param \mod_vpl $vpl
     * @param \stdClass[] $subinstances submission records
     * @return array submission id => ['success' => bool, 'grade' => float, 'error' => string]
     */
    public static function grade_batch(\mod_vpl $vpl, array $subinstances): array {
        global $CFG;
        require_once($CFG->dirroot . '/mod/vpl/locallib.php');
        require_once($CFG->dirroot . '/mod/vpl/vpl.class.php');
        require_once($CFG->dirroot . '/mod/vpl/vpl_submission_CE.class.php');

        // VPL's set_grade() refuses a Practice (No Grades) activity outright, so
        // every call for one would be paid for and then thrown away on the write.
        if ($vpl->is_practice_no_grades_mode()) {
            $results = [];
            foreach ($subinstances as $subinstance) {
                $results[$subinstance->id] = [
                    'success' => false,
                    'grade' => null,
                    'error' => get_string('aigrading_nogrademode', 'vpl'),
                ];
            }
            return $results;
        }

        $keys = self::get_keys();
        $model = self::get_model();
        $queue = array_values($subinstances);
        $concurrency = max(1, min(self::get_concurrency(), count($queue)));

        $results = [];
        $blocked = [];
        $keyindex = 0;
        $next = 0;
        $inflight = [];
        $exhausted = false;

        $mh = curl_multi_init();

        while ($inflight || (!$exhausted && $next < count($queue))) {
            // Fill the window.
            while (!$exhausted && count($inflight) < $concurrency && $next < count($queue)) {
                $subinstance = $queue[$next];
                $key = self::next_key($keys, $blocked, $keyindex);
                if ($key === null) {
                    // Every key is rate limited; stop starting new work.
                    $exhausted = true;
                    break;
                }
                $next++;
                try {
                    $submission = new \mod_vpl_submission_CE($vpl, $subinstance);
                    list($prompt, $studentcode, $execution) = self::build_prompt($vpl, $submission);
                } catch (\Throwable $e) {
                    $results[$subinstance->id] = [
                        'success' => false,
                        'grade' => null,
                        'error' => $e->getMessage(),
                    ];
                    continue;
                }

                $ch = curl_init(sprintf(self::ENDPOINT, $model, $key));
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => self::request_body($prompt),
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_TIMEOUT => self::TIMEOUT,
                ]);
                curl_multi_add_handle($mh, $ch);
                $inflight[spl_object_id($ch)] = [
                    'ch' => $ch,
                    'sub' => $subinstance,
                    'submission' => $submission,
                    'studentcode' => $studentcode,
                    'execution' => $execution,
                    'key' => $key,
                ];
            }

            if (!$inflight) {
                break;
            }

            do {
                $status = curl_multi_exec($mh, $running);
            } while ($status === CURLM_CALL_MULTI_PERFORM);
            if ($running && curl_multi_select($mh, 1.0) === -1) {
                usleep(10000);
            }

            while ($done = curl_multi_info_read($mh)) {
                $ch = $done['handle'];
                $id = spl_object_id($ch);
                if (!isset($inflight[$id])) {
                    continue;
                }
                $ctx = $inflight[$id];
                unset($inflight[$id]);

                $raw = (string) curl_multi_getcontent($ch);
                $httpcode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);

                // A rate limited or exhausted key retires for this run and its
                // submission goes back to the queue for a different key.
                if ($httpcode === 429 || $httpcode === 403) {
                    $blocked[$ctx['key']] = true;
                    $queue[] = $ctx['sub'];
                    continue;
                }

                $subid = $ctx['sub']->id;
                if ($httpcode !== 200) {
                    $results[$subid] = [
                        'success' => false,
                        'grade' => null,
                        'error' => 'AI API request failed with code: ' . $httpcode,
                    ];
                    continue;
                }

                try {
                    $text = self::extract_text($raw);
                    list($grade, $text) = self::resolve_grade($vpl, $text, $ctx['studentcode'], $ctx['execution']);
                    $userid = $ctx['sub']->userid;
                    self::apply_grade($vpl, $ctx['submission'], $ctx['sub'], $grade, $text, $userid);
                    $results[$subid] = ['success' => true, 'grade' => $grade, 'error' => ''];
                } catch (\Throwable $e) {
                    $results[$subid] = [
                        'success' => false,
                        'grade' => null,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }

        // Anything still in flight when every key retired is abandoned cleanly.
        foreach ($inflight as $ctx) {
            curl_multi_remove_handle($mh, $ctx['ch']);
            curl_close($ctx['ch']);
        }
        curl_multi_close($mh);

        return $results;
    }

    /**
     * Next usable key, skipping the ones that retired this run.
     *
     * @param string[] $keys
     * @param array $blocked key => true
     * @param int $keyindex round-robin cursor, advanced by reference
     * @return string|null null when every key is blocked
     */
    protected static function next_key(array $keys, array $blocked, int &$keyindex): ?string {
        $count = count($keys);
        for ($i = 0; $i < $count; $i++) {
            $key = $keys[$keyindex % $count];
            $keyindex++;
            if (!isset($blocked[$key])) {
                return $key;
            }
        }
        return null;
    }
}
