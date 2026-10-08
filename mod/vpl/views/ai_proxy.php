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
 * Server side proxy for the browser's AI features.
 *
 * The editor used to call Google directly with an API key embedded in the
 * JavaScript, which put a working credential in front of every user who
 * opened the page -- students included, through the hints button. The browser
 * now asks this endpoint instead: the key stays on the server, the caller is
 * checked against the activity's capabilities, and the prompt is built here
 * from a fixed template so the endpoint cannot be driven as a general chatbot.
 *
 * @package    mod_vpl
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(dirname(__FILE__).'/../../../config.php');
require_once($CFG->dirroot.'/mod/vpl/locallib.php');
require_once($CFG->dirroot.'/mod/vpl/vpl.class.php');

use mod_vpl\ai_grader;
use mod_vpl\ai_prompts;

global $USER, $SESSION;

$id = required_param('id', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);

require_sesskey();

$vpl = new mod_vpl($id);
require_login($vpl->get_course(), false, $vpl->get_course_module());

/**
 * Answer with an error in the shape the browser already handles.
 *
 * @param string $message
 * @param int $status
 */
function vpl_ai_proxy_error(string $message, int $status = 400) {
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode(['error' => ['message' => $message]]);
    exit;
}

/**
 * Strip markup and collapse whitespace, as the browser used to do before
 * building the prompt itself.
 *
 * @param string $text
 * @return string
 */
function vpl_ai_proxy_clean(string $text): string {
    return trim(preg_replace('/\s+/', ' ', strip_tags($text)));
}

// Everything below reaches a paid API, so each action is gated on the
// capability that the feature itself requires.
switch ($action) {
    case 'hints':
        // Offered to students working on their own submission.
        $vpl->require_capability(VPL_SUBMIT_CAPABILITY);
        break;
    case 'testcases':
        $vpl->require_capability(VPL_MANAGE_CAPABILITY);
        break;
    case 'idegrading':
    case 'formgrading':
        $vpl->require_capability(VPL_GRADE_CAPABILITY);
        break;
    default:
        vpl_ai_proxy_error('Unknown action');
}

// A per-user ceiling, so one account cannot burn the site's quota. This is a
// dampener rather than an accounting boundary: it lives in the session.
$limit = (int) get_config('mod_vpl', 'airatelimit');
if ($limit > 0) {
    $now = time();
    $calls = isset($SESSION->vpl_ai_calls) ? $SESSION->vpl_ai_calls : [];
    $calls = array_values(array_filter($calls, function($t) use ($now) {
        return $t > $now - HOURSECS;
    }));
    if (count($calls) >= $limit) {
        vpl_ai_proxy_error(get_string('aigrading_ratelimited', 'vpl'), 429);
    }
    $calls[] = $now;
    $SESSION->vpl_ai_calls = $calls;
}

$maxgrade = (int) $vpl->get_grade();
$ispractice = $vpl->is_practice_mode();

switch ($action) {
    case 'hints':
        $description = required_param('description', PARAM_RAW);
        $prompt = ai_prompts::hints(vpl_ai_proxy_clean($description));
        break;
    case 'testcases':
        $description = required_param('description', PARAM_RAW);
        $prompt = ai_prompts::testcases(vpl_ai_proxy_clean($description));
        break;
    case 'idegrading':
        $prompt = ai_prompts::ide_grading(
            optional_param('code', '', PARAM_RAW),
            optional_param('compilation', '', PARAM_RAW),
            optional_param('tests', '', PARAM_RAW),
            $ispractice,
            $maxgrade
        );
        break;
    case 'formgrading':
        $prompt = ai_prompts::form_grading(
            optional_param('code', '', PARAM_RAW),
            optional_param('compilation', '', PARAM_RAW),
            optional_param('tests', '', PARAM_RAW),
            $ispractice,
            $maxgrade
        );
        break;
}

try {
    $result = ai_grader::call_api($prompt);
} catch (moodle_exception $e) {
    vpl_ai_proxy_error($e->getMessage(), 500);
}

// The response is handed back as it came, so the existing browser code that
// reads candidates[0].content.parts[0].text keeps working unchanged.
header('Content-Type: application/json');
http_response_code($result['httpcode'] === 200 ? 200 : 502);
echo $result['httpcode'] === 200
    ? $result['body']
    : json_encode(['error' => ['message' => 'AI API request failed with code: ' . $result['httpcode']]]);
