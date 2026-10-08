<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Strings.
 *
 * @package   local_dttutorgemini
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Tutor AI on Google Gemini';
$string['apikey'] = 'Gemini API key';
$string['apikey_desc'] = 'Google AI Studio key. Leave empty to reuse the key set for GeniAI (Gemini).';
$string['model'] = 'Gemini model';
$string['model_desc'] = 'Falls back to gemini-3.5-flash-lite when the model is busy.';
$string['privacy:metadata'] = 'Tutor AI chat messages are sent to Google Gemini to generate answers and kept in Moodle so the chat history can be shown.';
$string['privacy:metadata:local_dttutorgemini_msg'] = 'Tutor AI chat messages.';
