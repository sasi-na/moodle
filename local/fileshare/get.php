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
 * Public endpoint for a share link. Serves exactly one file to an anonymous caller.
 *
 * There is no session and no $USER here on purpose. \local_fileshare\manager::resolve() calls
 * validate_user_key(), which checks the token, expiry and IP range but does NOT log anybody in -
 * unlike require_user_key_login(), which tokenpluginfile.php uses and which is what makes those
 * tokens equivalent to the owner's account. The file served is named by our own table, never by
 * the request, so there is no path to traverse.
 *
 * @package   local_fileshare
 * @copyright 2026 onlinejain.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// No cookies: this must never touch the caller's Moodle session.
define('NO_MOODLE_COOKIES', true);
// Never leak a stack trace to an anonymous caller.
define('NO_DEBUG_DISPLAY', true);

require_once(__DIR__ . '/../../config.php');

/**
 * Refuse, identically for every reason.
 *
 * Expired, revoked, spent, wrong token, never existed - all look the same from outside, so the
 * endpoint cannot be probed to learn which share ids are real.
 *
 * @return void
 */
function local_fileshare_deny(): void {
    header('HTTP/1.1 410 Gone');
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Robots-Tag: noindex, nofollow');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">' .
        '<meta name="viewport" content="width=device-width, initial-scale=1">' .
        '<title>Link unavailable</title></head><body style="font:16px/1.6 system-ui,sans-serif;' .
        'margin:0;display:grid;place-items:center;min-height:100vh;color:#1b2b2a;background:#f2f5f4">' .
        '<div style="max-width:32rem;padding:2rem;text-align:center">' .
        '<h1 style="font-size:1.25rem;margin:0 0 .5rem">This link is no longer available</h1>' .
        '<p style="margin:0;color:#5a6b69">It may have expired, been used its maximum number of times, ' .
        'or been withdrawn. Ask whoever sent it for a new one.</p></div></body></html>';
    exit;
}

$id = optional_param('id', 0, PARAM_INT);
$token = optional_param('k', '', PARAM_ALPHANUM);

if (empty($id) || $token === '') {
    local_fileshare_deny();
}

try {
    [$share, $file] = \local_fileshare\manager::resolve($id, $token);
} catch (\Throwable $e) {
    local_fileshare_deny();
}

// Counted before sending, because send_stored_file() does not return.
\local_fileshare\manager::record_access($share);

// Always an attachment, never inline: these are untrusted user uploads and this is the main
// origin. An inline HTML or SVG upload would otherwise run as same-origin script.
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
send_stored_file($file, 0, 0, true, ['cacheability' => 'private']);
