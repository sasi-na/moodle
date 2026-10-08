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
 * Gate for the bespoke "student experience" views (dashboard, my courses, …).
 *
 * @package   theme_baz
 * @copyright 2026 onlinejain.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_baz\local;

defined('MOODLE_INTERNAL') || die();

/**
 * One master switch governs all bespoke student views, with per-view safety
 * toggles to disable an individual page without taking down the rest.
 *
 * Admin-controlled only (no student-facing toggle):
 *   theme_baz/enable_student_experience  (master, default off)
 *   theme_baz/student_view_dashboard      (per-view, default on)
 *   theme_baz/student_view_courses        (per-view, default on)
 *
 * Migration: when the master has never been configured (fresh deploy), we fall
 * back to the legacy `enable_student_dashboard` flag so existing behaviour is
 * preserved — only the dashboard shows, and the brand-new pages stay classic
 * until an admin explicitly turns the master on.
 */
class experience {

    /**
     * Is the bespoke view enabled for this user right now?
     *
     * @param string $view one of 'dashboard', 'courses', …
     * @param int|null $userid defaults to the current user
     * @return bool
     */
    public static function is_on(string $view, ?int $userid = null): bool {
        global $USER;
        $userid = $userid ?? (int) $USER->id;

        static $cache = [];
        $key = $view . ':' . $userid;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $master = get_config('theme_baz', 'enable_student_experience');

        if ($master === false || $master === null || $master === '') {
            // Legacy mode: master never configured. Mirror the old behaviour —
            // only the dashboard is governed (by enable_student_dashboard); every
            // other (newer) view stays on the classic page until an admin opts in.
            if ($view !== 'dashboard' || get_config('theme_baz', 'enable_student_dashboard') !== '1') {
                return $cache[$key] = false;
            }
        } else {
            // New mode: the master is the gate, each view has its own toggle (on
            // unless explicitly switched off).
            if ($master !== '1') {
                return $cache[$key] = false;
            }
            $perview = get_config('theme_baz', 'student_view_' . $view);
            if ($perview !== false && $perview !== null && $perview !== '' && $perview !== '1') {
                return $cache[$key] = false;
            }
        }

        return $cache[$key] = self::eligible_user($userid);
    }

    /**
     * Student-only, logged-in, non-guest, non-verifier — the audience for the
     * bespoke views. Teachers/managers/admins/verifiers keep the standard pages.
     *
     * @param int $userid
     * @return bool
     */
    protected static function eligible_user(int $userid): bool {
        if (empty($userid) || !isloggedin() || isguestuser()) {
            return false;
        }
        if (!roles::is_student_only($userid)) {
            return false;
        }
        if (class_exists('\local_studentid\manager')) {
            try {
                if (\local_studentid\manager::is_verifier($userid)) {
                    return false;
                }
            } catch (\Throwable $e) {
                return false;
            }
        }
        return true;
    }
}
