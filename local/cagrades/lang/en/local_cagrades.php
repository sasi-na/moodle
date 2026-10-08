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
 * Strings for local_cagrades.
 *
 * @package    local_cagrades
 * @copyright  2026 JAIN Online
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Continuous assessment report source';

// Report source and entity.
$string['sourcecagrades'] = 'Continuous assessment grades';
$string['entityca'] = 'Continuous assessment';

// Columns and filters.
$string['ca'] = 'CA {$a}';
$string['camax'] = 'CA {$a} maximum';
$string['cabesttwo'] = 'CA best two total';
$string['cafinal'] = 'CA final mark';
$string['cagraded'] = 'Has any CA grade';
$string['cagradedcount'] = 'CAs graded';
$string['caitemcount'] = 'CA quizzes in course';
$string['caitemnames'] = 'CA quiz names';
$string['capercent'] = 'CA percentage';
$string['catotal'] = 'CA total';
$string['catotalmax'] = 'CA total maximum';
$string['caweighted'] = 'CA {$a} (weighted to 25)';
$string['catotalwithra'] = 'AI Skills total';
$string['catotalwithramax'] = 'AI Skills maximum';
$string['racourse'] = 'Reflective Assessment course';
$string['racoursemultiple'] = '{$a->course} ({$a->count} allocations)';
$string['ragrade'] = 'Reflective Assessment';
$string['ramax'] = 'Reflective Assessment maximum';

// Virtual Labs marking scheme — grade category names built by cli/apply_lab_scheme.php.
$string['labca'] = 'Continuous Assessment - {$a}';
$string['labpractice'] = 'Practice Questions (Graded)';
$string['labsection'] = 'Section {$a}';

$string['privacy:metadata'] = 'The Continuous assessment report source plugin does not store any personal data. ' .
    'It only reads existing grades to display them in custom reports.';
$string['task_hide_past_ca'] = 'Hide previous-semester continuous assessment marks';
