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
 * Shared off / pilot / all gate for the bespoke surfaces.
 *
 * @package   theme_baz
 * @copyright 2026 onlinejain.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_baz\local;

defined('MOODLE_INTERNAL') || die();

/**
 * One three-state gate, reused by every surface that can be piloted.
 *
 * A setting reads '0' (off), 'pilot' (only the roster) or '1' (everyone the
 * surface's own audience check admits). The legacy checkbox values are the same
 * strings, so a site already storing '0' or '1' keeps exactly the behaviour it
 * had before its setting became a select.
 *
 * All piloted surfaces share ONE roster, so an operator maintains a single list
 * of pilot people and switches each surface between off / pilot / all
 * independently. The roster keeps its historical setting name
 * (admin_view_pages_pilot) because it is already populated in production and
 * renaming it would orphan that value.
 */
class gate {

    /** @var string The shared pilot roster setting. Named for the first surface that used it. */
    const ROSTER = 'admin_view_pages_pilot';

    /**
     * Is a piloted surface switched on for this user?
     *
     * @param string $name the theme_baz setting holding the mode
     * @param bool $defaulton what an unconfigured setting means -- the surfaces
     *                        differ here, so it is explicit rather than assumed
     * @param int|null $userid defaults to the current user
     * @return bool
     */
    public static function allows(string $name, bool $defaulton = false, ?int $userid = null): bool {
        $mode = get_config('theme_baz', $name);

        // Never configured: each surface keeps whatever it defaulted to before.
        if ($mode === false || $mode === null || $mode === '') {
            return $defaulton;
        }
        if ($mode === '1') {
            return true;
        }
        if ($mode !== 'pilot') {
            return false;
        }
        return self::in_pilot($userid);
    }

    /**
     * Is this user on the pilot roster?
     *
     * Matched against the session's own user record rather than a lookup, because
     * these gates run on page loads that must not pay for a query. An empty roster
     * in pilot mode admits nobody, which is the safe reading of "pilot with nobody
     * in it".
     *
     * @param int|null $userid
     * @return bool
     */
    public static function in_pilot(?int $userid = null): bool {
        global $USER;

        $roster = self::pilot_list();
        if (!$roster) {
            return false;
        }

        // Only the session user's own identifiers are available without a query, so
        // a lookup is needed when asking about somebody else.
        if ($userid !== null && (int) $userid !== (int) $USER->id) {
            return in_array((string) $userid, $roster, true);
        }

        foreach ([(string) ($USER->username ?? ''), (string) ($USER->email ?? ''),
                (string) ($USER->id ?? '')] as $identity) {
            if ($identity !== '' && in_array(\core_text::strtolower($identity), $roster, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * The roster, normalised to a lowercase lookup list.
     *
     * Accepts usernames, email addresses or numeric user ids separated by commas,
     * semicolons or newlines, so it can be pasted in from a spreadsheet.
     *
     * @return string[]
     */
    public static function pilot_list(): array {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $raw = (string) get_config('theme_baz', self::ROSTER);
        $out = [];
        foreach (preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $entry) {
            $out[] = \core_text::strtolower(trim($entry));
        }
        $cache = array_values(array_unique($out));
        return $cache;
    }

    /**
     * The three choices, for a settings page.
     *
     * @return array
     */
    public static function mode_choices(): array {
        return [
            '0' => get_string('gate_off', 'theme_baz'),
            'pilot' => get_string('gate_pilot', 'theme_baz'),
            '1' => get_string('gate_all', 'theme_baz'),
        ];
    }
}
