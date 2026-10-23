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
 * @package   local_edwiserpagebuilder
 * @copyright (c) 2023 WisdmLabs (https://wisdmlabs.com/) <support@wisdmlabs.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Gourav Govande
 */

// @codingStandardsIgnoreLine
require_once('../../config.php');

global $OUTPUT, $PAGE, $USER;

$pageid = optional_param('id', 0, PARAM_INT);

// Throw exception if ID not set.
if (empty($pageid)) {
    throw new \moodle_exception('accesserror', 'local_edwiserpagebuilder');
}

// Initialize the page.
$ph = new \local_edwiserpagebuilder\custom_page_handler("publish", $pageid);

$classes = "epb-publish-" . $pageid;

if ($ph->page->get_pagecontent() != null &&
$ph->page->get_pagecontent() != "" &&
json_decode($ph->page->get_pagecontent())->text != "") {
    $classes = "main-area-bg " . $classes;
}

$PAGE->add_body_class($classes);

$systemcontext = \context_system::instance();

// Set page context.
$PAGE->set_context($systemcontext);

// Handle edit mode toggle for Adaptable theme only.
// Other themes handle edit mode through their own mechanisms.
if ( $PAGE->user_allowed_editing()) {
    $edit = optional_param('edit', null, PARAM_BOOL);
    if ($edit !== null && confirm_sesskey()) {
        $USER->editing = $edit;
        // If editing is turned on, redirect to draft page.
        if ($edit == 1) {
            $refid = $ph->page->get_refid();
            if ($refid != -1) {
                $redirecturl = new moodle_url(
                    '/local/edwiserpagebuilder/pagedraft.php',
                    array('id' => $refid)
                );
                redirect($redirecturl);
            }
        }
        // If editing is turned off, stay on publish page.
        $redirecturl = new moodle_url(
            '/local/edwiserpagebuilder/page.php',
            array('id' => $pageid)
        );
        redirect($redirecturl);
    }
}

// If user is editing & has capability to manage the page,
// Redirect user to draft page.
if ($PAGE->user_is_editing()) {
    if (has_capability('local/edwiserpagebuilder:epb_can_manage_page', $systemcontext)) {
        $editurl = new moodle_url(
            '/local/edwiserpagebuilder/pagedraft.php',
            array('id' => $ph->page->get_refid())
        );
        redirect($editurl);
        die();
    }
}

// Check if user can view the page.
if (!$ph->page->can_view_page()) {
    throw new \moodle_exception('accesserror', 'local_edwiserpagebuilder');
}

// Set page URL.
$PAGE->set_url(new moodle_url('/local/edwiserpagebuilder/page.php', array('id' => $ph->page->get_id())));

// Set page title.
$PAGE->set_title($ph->page->get_pagename());

// Set page heading.
$PAGE->set_heading($ph->page->get_pagename());

// Setting page type and subpage type.
$PAGE->set_pagetype('epb-page-publish');

$PAGE->set_subpage($ph->page->get_id());

// Set pagelayout dynamically which is set in every pageobject.
$PAGE->set_pagelayout($ph->page->get_pagelayout());

// Add content region for Edwiser Page Builder blocks.
// This must be done AFTER set_pagetype() and set_pagelayout() so the region
// is properly registered with the correct page type and after theme initialization.
$PAGE->blocks->add_region('content');

// Set edit button for Adaptable theme only.
// Other themes handle edit buttons through their own mechanisms.
if ($PAGE->user_allowed_editing()) {
    // For publish page, redirect to draft page when editing is toggled.
    // Only show button if draft page exists (refid != -1).
    $refid = $ph->page->get_refid();
    if ($refid != -1) {
        $editurl = new moodle_url(
            '/local/edwiserpagebuilder/pagedraft.php',
            array('id' => $refid)
        );
        $editbutton = $OUTPUT->edit_button($editurl);
        if (!empty($editbutton)) {
            $PAGE->set_button($editbutton);
        }
    }
}

echo $OUTPUT->header();

echo $OUTPUT->custom_block_region('content');

// Render page here.
if ($ph->page->get_pagecontent()) {
    echo format_text(json_decode($ph->page->get_pagecontent())->text);
}

// Now output the footer.
echo $OUTPUT->footer();
