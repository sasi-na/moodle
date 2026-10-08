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
 * Admin settings and report page for local_reflectiveassessment.
 *
 * @package   local_reflectiveassessment
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_reflectiveassessment\summary;

// Always defined: admin_externalpage handles visibility, and testing the
// capability here would throw on first install before db/access.php is read.
$ADMIN->add('reports', new admin_externalpage(
    'local_reflectiveassessment_report',
    get_string('reportpage', 'local_reflectiveassessment'),
    new moodle_url('/local/reflectiveassessment/index.php'),
    'local/reflectiveassessment:view'
));

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_reflectiveassessment_settings',
        get_string('settings', 'local_reflectiveassessment'));

    $settings->add(new admin_setting_configtext(
        'local_reflectiveassessment/coursepattern',
        get_string('coursepattern', 'local_reflectiveassessment'),
        get_string('coursepattern_desc', 'local_reflectiveassessment'),
        summary::DEFAULT_COURSE_PATTERN,
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configtext(
        'local_reflectiveassessment/quizpattern',
        get_string('quizpattern', 'local_reflectiveassessment'),
        get_string('quizpattern_desc', 'local_reflectiveassessment'),
        summary::DEFAULT_QUIZ_PATTERN,
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configtext(
        'local_reflectiveassessment/batchcategories',
        get_string('batchcategories', 'local_reflectiveassessment'),
        get_string('batchcategories_desc', 'local_reflectiveassessment'),
        summary::DEFAULT_BATCH_CATEGORIES,
        PARAM_SEQUENCE
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_reflectiveassessment/serviceaccounts',
        get_string('serviceaccounts', 'local_reflectiveassessment'),
        get_string('serviceaccounts_desc', 'local_reflectiveassessment'),
        summary::DEFAULT_SERVICE_ACCOUNTS,
        PARAM_RAW
    ));

    $ADMIN->add('localplugins', $settings);
}
