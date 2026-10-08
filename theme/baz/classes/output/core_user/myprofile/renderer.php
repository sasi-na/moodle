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
 * myprofile renderer.
 *
 * @package    theme_baz
 * @copyright  Copyright © 2021 onwards Marcin Czaja
 * @author     G J Barnard - {@link http://moodle.org/user/profile.php?id=442195}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

namespace theme_baz\output\core_user\myprofile;

defined('MOODLE_INTERNAL') || die;

use core_user\output\myprofile\category;
use core_user\output\myprofile\node;
use core_user\output\myprofile\tree;
use html_writer;

require_once($CFG->dirroot . '/user/lib.php');

/**
 * myprofile renderer.
 * @copyright Copyright (c) 2017 Manoj Solanki (Coventry University)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \core_user\output\myprofile\renderer {

    /**
     * Profile categories a student has no use for.
     *
     * These are administrative or self-service surfaces aimed at staff and at
     * people managing their own account settings, and on the bespoke student
     * profile they are noise:
     *
     *   reports            core (lib/myprofilelib.php) -- Browser sessions,
     *                      Grades overview, activity/log reports
     *   miscellaneous      core -- Blog entries, forum posts, learning plans
     *   mobile             tool_mobile -- the app QR-code / access key
     *   privacyandpolicies tool_policy + tool_dataprivacy -- both register a
     *                      category under this SAME name, so one entry covers
     *                      the policy agreements and the data-request links
     *
     * Deliberately NOT listed: `contact` (the point of the page),
     * `coursedetails`, `administration` and `loginactivity`. Nothing was asked
     * of those and `administration` is already capability-gated by core.
     */
    protected const STUDENT_HIDDEN_CATEGORIES = [
        'reports',
        'miscellaneous',
        'mobile',
        'privacyandpolicies',
    ];

    /**
     * Is the viewer someone we trim the profile page for?
     *
     * Keyed on the VIEWER, not the profile being viewed, and reusing the same
     * predicate as the rest of the student experience -- so this follows the
     * `student_view_profile` toggle and the student master switch, and staff
     * looking at a learner's profile still see everything.
     *
     * Fails open: if the gate cannot be evaluated for any reason, the full
     * classic set of categories is rendered rather than a page missing sections.
     *
     * @return bool
     */
    protected function trim_for_student(): bool {
        global $USER;

        static $trim = null;
        if ($trim !== null) {
            return $trim;
        }
        try {
            $trim = \theme_baz\local\experience::is_on('profile', (int) $USER->id);
        } catch (\Throwable $e) {
            $trim = false;
        }
        return $trim;
    }

    /**
     * Render a category.
     *
     * @param category $category
     *
     * @return string
     */
    public function render_category(category $category) {
        if (in_array($category->name, self::STUDENT_HIDDEN_CATEGORIES, true)
                && $this->trim_for_student()) {
            return '';
        }

        $classes = $category->classes;
        if (empty($classes)) {
            $return = \html_writer::start_tag(
                'section',
                array('class' => 'node_category card d-inline-block w-100')
            );
            $return .= \html_writer::start_tag('div', array('class' => 'card-body'));
        } else {
            $return = \html_writer::start_tag(
                'section',
                array('class' => 'node_category card d-inline-block w-100' . $classes)
            );
            $return .= \html_writer::start_tag('div', array('class' => 'card-body'));
        }
        $return .= \html_writer::tag('h5', $category->title, array('class' => 'card-title'));
        $nodes = $category->nodes;
        if (empty($nodes)) {
            // No nodes, nothing to render.
            return '';
        }
        $nodes = $this->position_fullname_node($category, $nodes);
        $nodes = $this->relabel_nodes($category, $nodes);
        $return .= \html_writer::start_tag('ul');
        foreach ($nodes as $node) {
            $return .= $this->render($node);
        }
        $return .= \html_writer::end_tag('ul');
        $return .= \html_writer::end_tag('div');
        $return .= \html_writer::end_tag('section');
        return $return;
    }

    /**
     * Apply this site's preferred capitalisation to node titles.
     *
     * Only 'Email address' -> 'Email Address' for now. It is done here rather
     * than in a lang file because the title comes from core's `email` string,
     * which a theme cannot override, and node::$title is private with only a
     * __get -- so the node is REPLACED with an identical one carrying the new
     * title rather than mutated. The replacement copies every constructor
     * argument, and `after` is passed through so ordering is unaffected.
     *
     * Titles are matched by node NAME, never by comparing the rendered English,
     * so this keeps working if the site language changes (it simply stops
     * applying, which is the right outcome -- the override is an English
     * capitalisation preference).
     *
     * @param category $category
     * @param node[] $nodes
     * @return node[]
     */
    protected function relabel_nodes(category $category, array $nodes): array {
        if ($category->name !== 'contact' || !isset($nodes['email'])) {
            return $nodes;
        }

        $email = $nodes['email'];
        $nodes['email'] = new node(
            $email->parentcat,
            $email->name,
            get_string('profile_emailaddress', 'theme_baz'),
            $email->after,
            $email->url,
            $email->content,
            $email->icon,
            $email->classes
        );

        return $nodes;
    }

    /**
     * Move theme_baz's "Full name" node directly above the Email row.
     *
     * The node itself is added by theme_baz_myprofile_navigation(). Plugin callbacks
     * run after core (\core_user\output\myprofile\manager::build_tree()) and the node
     * API only supports 'after', never 'before', so the ordering has to happen here.
     * Nodes are keyed by name, so this is a key-ordered rebuild of a local copy; the
     * tree is not mutated. Email is absent when hidden by maildisplay/hiddenfields,
     * in which case Full name simply goes first.
     *
     * @param category $category
     * @param node[] $nodes nodes of the category, already sorted by core
     * @return node[] nodes in display order
     */
    protected function position_fullname_node(category $category, array $nodes): array {
        if ($category->name !== 'contact' || !isset($nodes['fullname'])) {
            return $nodes;
        }

        $fullname = $nodes['fullname'];
        unset($nodes['fullname']);

        if (!isset($nodes['email'])) {
            return array('fullname' => $fullname) + $nodes;
        }

        $reordered = array();
        foreach ($nodes as $name => $node) {
            if ($name === 'email') {
                $reordered['fullname'] = $fullname;
            }
            $reordered[$name] = $node;
        }

        return $reordered;
    }
}
