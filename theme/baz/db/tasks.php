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
 * Scheduled task definitions for theme_baz.
 *
 * @package   theme_baz
 * @copyright 2026 onlinejain.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [

    // Keeps the student dashboard news/podcast cards warm in the shared cache.
    // Runs every 20 minutes; the feeds themselves change far more slowly than that,
    // and the block's own SimplePie cache duration is an hour.
    [
        'classname' => 'theme_baz\task\refresh_dashboard_news',
        'blocking' => 0,
        'minute' => '*/20',
        'hour' => '*',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*',
    ],

    // Precomputes the admin dashboard's 30-day sign-in trend (a heavy logstore
    // query) once a day, off-request, so /my/ never runs it. Off-peak.
    [
        'classname' => 'theme_baz\task\refresh_admin_engagement',
        'blocking' => 0,
        'minute' => '40',
        'hour' => '3',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*',
    ],
];
