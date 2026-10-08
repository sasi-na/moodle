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
 * Hands an entitled candidate to this site's exam portal, signed in.
 *
 * The flow is the same for every site -- check entitlement, run the portal's
 * handshake, redirect -- and only the handshake differs. That part lives in the
 * configured {@see \block_endsemesterexam\local\provider\provider}, so this
 * script stays the same whichever portal a site uses.
 *
 * @package   block_endsemesterexam
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->libdir . '/filelib.php'); // The Moodle \curl wrapper is not autoloaded.

use block_endsemesterexam\local\audience;
use block_endsemesterexam\local\config;

require_login();
require_sesskey();

global $USER, $PAGE;

$PAGE->set_url('/blocks/endsemesterexam/redirect.php');
$PAGE->set_context(context_system::instance());

$dashboard = new moodle_url('/my/');

// The exam identifier is derived from the session, never accepted from the
// request. It used to arrive as a `register_no` GET parameter, so any learner
// could obtain an exam session for somebody else's register number by editing
// the URL -- the number was the only thing identifying the candidate.
//
// A null identifier is NOT refused here. This script is reached from a button,
// and the surface that drew the button has already decided who may see it --
// which on a site using the theme's batch audience is a wider and different
// question than this block's own gate answers. Refusing here anyway meant the
// button appeared for 22,770 learners and then turned every one of them away.
//
// Entitlement is ultimately the provider's to enforce: it is the system that
// knows who is actually sitting an exam, and it rejects candidates it does not
// recognise. A provider that needs an identifier and is handed none says so.
$identifier = audience::identifier((int) $USER->id) ?? '';

try {
    $examurl = config::provider()->exam_url($identifier, $USER);
} catch (moodle_exception $e) {
    // The provider has already distinguished "we are not set up" from "the
    // handshake failed" and from "this candidate is unknown"; show that, with
    // the detail left for the site's logs rather than put in front of a learner
    // about to sit an exam.
    debugging('block_endsemesterexam: ' . $e->getMessage() . ' ' . (string) $e->debuginfo, DEBUG_NORMAL);
    redirect($dashboard, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
}

// Deliberately not wrapped in moodle_url: providers return signed URLs whose
// query strings must arrive byte-for-byte, and moodle_url would re-encode them.
redirect($examurl);
