<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Registers the proctor review report in the quiz Results menu.
 *
 * @package quiz_proctorreview
 */
function xmldb_quiz_proctorreview_install() {
    global $DB;

    $record = new stdClass();
    $record->name         = 'proctorreview';
    $record->displayorder = '11000';

    $DB->insert_record('quiz_reports', $record);
}
