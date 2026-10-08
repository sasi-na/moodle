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
 * Strings for local_rpreport.
 *
 * @package   local_rpreport
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Research Project Report';
$string['settings'] = 'Research Project Report settings';
$string['runpage'] = 'Research Project Report — send / download';
$string['taskname'] = 'Email Research Project submission-status report';

$string['recipients'] = 'Recipient email addresses';
$string['recipients_desc'] = 'Email addresses that receive the daily Research Project submission-status workbook. Separate multiple addresses with commas, semicolons or new lines. Addresses need not belong to a Moodle account.';

$string['sendnow'] = 'Generate & send now';
$string['downloadnow'] = 'Download now';
$string['currentrecipients'] = 'Current recipients: {$a}';
$string['sentok'] = 'Report emailed to {$a->sent} of {$a->total} recipient(s).';
$string['sentfailed'] = 'Sending failed for: {$a}';
$string['norecipients'] = 'No valid recipient email addresses are configured.';
$string['norecipients_config'] = 'No recipients configured yet. Add them on the <a href="{$a}">settings page</a> before sending.';

$string['privacy:metadata'] = 'The Research Project Report plugin does not store personal data itself; it compiles submission status from existing course and user records into a workbook that is emailed to configured administrators.';
