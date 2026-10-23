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
 * Zoom Online block
 *
 * @package    block_zoomonline
 * @copyright  2024 Ciaran Mac Donncha
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

        'block/zoomonline:myaddinstance' => [
                'captype' => 'write',
                'contextlevel' => CONTEXT_SYSTEM,
                'archetypes' => [
                        'user' => CAP_ALLOW,
                ],
                'clonepermissionsfrom' => 'moodle/my:manageblocks',
        ],

        'block/zoomonline:addinstance' => [
                'riskbitmask' => RISK_SPAM | RISK_XSS,
                'captype' => 'write',
                'contextlevel' => CONTEXT_BLOCK,
                'archetypes' => [
                        'editingteacher' => CAP_ALLOW,
                        'manager' => CAP_ALLOW,
                ],
                'clonepermissionsfrom' => 'moodle/site:manageblocks',
        ],

        'block/zoomonline:view' => [
                'captype' => 'read',
                'contextlevel' => CONTEXT_COURSE,
                'archetypes' => [
                        'student' => CAP_ALLOW,
                        'teacher' => CAP_ALLOW,
                        'manager' => CAP_ALLOW,
                ],
        ],

        'block/zoomonline:managevisibility' => [
                'captype' => 'write',
                'contextlevel' => CONTEXT_COURSE, // Set the appropriate context level here.
                'archetypes' => [
                        'manager' => CAP_ALLOW,
                        'editingteacher' => CAP_ALLOW,
                ],
        ],
];
