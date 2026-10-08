<?php
/**
 * Compatibility entry point for the exam runner. The runner lives in start.php;
 * this redirects there preserving the quiz course-module id.
 *
 * @package   local_exams
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');

require_login();

$cmid = required_param('cmid', PARAM_INT);
redirect(new moodle_url('/local/exams/start.php', ['cmid' => $cmid]));
