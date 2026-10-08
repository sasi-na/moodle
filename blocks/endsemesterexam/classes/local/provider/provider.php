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
 * Exam provider contract.
 *
 * @package   block_endsemesterexam
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_endsemesterexam\local\provider;

defined('MOODLE_INTERNAL') || die();

/**
 * One exam portal's sign-in handshake.
 *
 * The button is the same everywhere -- the same block, the same entitlement
 * gate, the same place on the dashboard. What differs per site is only which
 * portal sits behind it and how that portal expects to be handed a candidate:
 * one wants an email exchanged for an access token, another wants a student
 * code exchanged for a bearer token and then asked for a URL, and the next one
 * will want something else again.
 *
 * That variation lives here, one implementation per portal, so adding a site
 * with a different provider is a new class and a registry entry rather than
 * another branch in the redirect script.
 *
 * Implementations must:
 *   - read every endpoint and credential from settings, never from a literal,
 *     because this plugin ships in an image shared by unrelated institutions;
 *   - throw \moodle_exception('examnotconfigured', ...) when required settings
 *     are missing, so a half-configured site fails closed instead of reaching
 *     whichever portal happens to be the default.
 */
interface provider {

    /**
     * The URL to send this candidate to, signed in.
     *
     * Called only after the entitlement gate has passed. Implementations do the
     * provider's handshake here and return an absolute URL for the browser.
     *
     * The return value is redirected to as a plain string and is NOT passed
     * through moodle_url, because providers commonly return a signed URL whose
     * query string must arrive byte-for-byte.
     *
     * @param string $identifier Exam identifier from the gate; '' when the gate
     *                           authorises without producing one.
     * @param \stdClass $user The candidate (the session user).
     * @return string Absolute URL.
     * @throws \moodle_exception On missing configuration or a failed handshake.
     */
    public function exam_url(string $identifier, \stdClass $user): string;
}
