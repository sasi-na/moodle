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
 * Capability definitions for local_facultyfeedback.
 *
 * @package   local_facultyfeedback
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // See a course's aggregated weekly feedback rating (the course-header field
    // and the pop-up behind it).
    //
    // CONTEXT_COURSE, not CONTEXT_SYSTEM: the question is always "on *this*
    // course", so a plain has_capability() against the course context is the
    // right check and get_user_capability_course() is not needed (contrast
    // local_dashboardpoll:viewreports, whose polls live in the system context).
    //
    // Deliberately NOT granted to the student archetype, so learners never see
    // the field and the web service refuses them.
    //
    // The site's custom roles inherit via archetype: programhead and
    // adminreadonly from manager, coursecoordinator from editingteacher, lsm
    // from teacher. That is intended.
    //
    // RISK_PERSONAL is not set: nothing user-identifying is exposed, only
    // aggregate counts and averages.
    'local/facultyfeedback:view' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [
            'manager'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
        ],
    ],

];
