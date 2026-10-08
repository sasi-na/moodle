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
 * MUC cache definitions for local_facultyfeedback.
 *
 * `summary` caches the per-course aggregate (overall average, per-week trend,
 * latest-week figures) keyed by course id. The underlying query costs ~12-14ms
 * on the heaviest course, and the numbers only move when a learner submits a
 * weekly feedback, so a short TTL is plenty: nobody needs a course average to
 * be second-accurate, and a stale-by-15-minutes figure is harmless.
 *
 * There is no invalidation event — the TTL is the whole story. Add one only if
 * a "refresh now" affordance is ever requested.
 *
 * @package   local_facultyfeedback
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [
    'summary' => [
        'mode'               => cache_store::MODE_APPLICATION,
        'simplekeys'         => true,
        'simpledata'         => false,
        'staticacceleration' => true,
        'ttl'                => 900,
    ],
];
