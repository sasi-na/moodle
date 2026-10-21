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

namespace theme_baz\local;

defined('MOODLE_INTERNAL') || die();

/**
 * "New theme" switch: the two-level top bar dashboards (student + admin).
 *
 * One theme, two layouts: on when BAZ's "Navigation layout" setting (Top Bar tab) is
 * "Top bar", or when the "Ednxt Top Bar" child theme is selected. With the default
 * "Left sidebar" the current sidebar design is used. Anyone can also preview it on their own dashboard
 * with /my/?newtheme=1 (and leave with ?newtheme=0) without changing the site.
 *
 * @package   theme_baz
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class newtheme {

    /** @var string The child theme that turns the new design on. */
    const THEME = 'ednxt';

    /** @var string Per-session preview flag. */
    const PREVIEW = 'theme_baz_newtheme_preview';

    /** @var string Per-session design preview (Options 3-5). */
    const VPREVIEW = 'theme_baz_newtheme_vpreview';

    /** @var string[] Navigation choices: Option 1 .. Option 5. */
    const LAYOUTS = ['left', 'top', 'rail', 'dock', 'campus'];

    /** @var string User preference holding a person's own Navigation choice (Options 1-5). */
    const USERPREF = 'theme_baz_navlayout';

    /** @var string[] The new designs built on the top bar shell. */
    const VARIANTS = ['rail', 'dock', 'campus'];

    /**
     * Should the new design be used for this request?
     *
     * @return bool
     */
    public static function is_on(): bool {
        global $SESSION, $PAGE;
        try {
            $isadmin = function_exists('is_siteadmin') && isloggedin() && is_siteadmin();
            $param = $isadmin ? optional_param('newtheme', null, PARAM_INT) : null;
            if ($param !== null) {
                $SESSION->{self::PREVIEW} = (int) $param;
            }
            if ($isadmin && isset($SESSION->{self::PREVIEW})) {
                return (int) $SESSION->{self::PREVIEW} === 1;
            }
            if (isset($PAGE->theme->name) && $PAGE->theme->name === self::THEME) {
                return true;
            }
            return self::layout() !== 'left';
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * BAZ "Navigation layout" setting (Top Bar tab): 'left' sidebar (default) or 'top' bar.
     *
     * @return string
     */
    public static function layout(): string {
        $own = self::user_layout();
        return $own !== '' ? $own : self::site_layout();
    }

    /**
     * The site-wide Navigation option set by the admin (the default for everyone).
     *
     * @return string
     */
    public static function site_layout(): string {
        $v = get_config('theme_baz', 'navlayout');
        return in_array($v, self::LAYOUTS, true) ? $v : 'left';
    }

    /**
     * The Navigation option this user chose for themselves, or '' to follow the site default.
     *
     * @return string
     */
    public static function user_layout(): string {
        try {
            if (!isloggedin() || isguestuser()) {
                return '';
            }
            $v = get_user_preferences(self::USERPREF, '');
            // An admin's "Apply to all users" overrides choices made before it (nothing is deleted).
            $forced = (int) get_config('theme_baz', 'navforced');
            if ($forced && (int) get_user_preferences(self::USERPREF . '_t', 0) < $forced) {
                return '';
            }
            return in_array($v, self::LAYOUTS, true) ? $v : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Design of the new layout: '' for Option 2 (top bar), or 'rail' (Option 3), 'dock' (Option 4),
     * 'campus' (Option 5). A per-session preview is possible with ?ntvariant=rail|dock|campus|top|off.
     *
     * @return string
     */
    public static function variant(): string {
        global $SESSION;
        $cache = '';
        try {
            $isadmin = function_exists('is_siteadmin') && isloggedin() && is_siteadmin();
            $p = $isadmin ? optional_param('ntvariant', null, PARAM_ALPHA) : null;
            if ($p !== null) {
                if ($p === 'off') {
                    unset($SESSION->{self::VPREVIEW});
                } else {
                    $SESSION->{self::VPREVIEW} = $p;
                }
            }
            $v = ($isadmin && isset($SESSION->{self::VPREVIEW})) ? (string) $SESSION->{self::VPREVIEW} : self::layout();
            if (self::is_on() && in_array($v, self::VARIANTS, true)) {
                $cache = $v;
            }
        } catch (\Throwable $e) {
            $cache = '';
        }
        return $cache;
    }

    /**
     * Cache-busting URL of a file in theme/baz/newtheme/.
     *
     * @param string $name
     * @return string
     */
    public static function asseturl(string $name): string {
        global $CFG;
        $v = @filemtime($CFG->dirroot . '/theme/baz/newtheme/' . $name) ?: 1;
        return $CFG->wwwroot . '/theme/baz/newtheme/' . $name . '?v=' . $v;
    }

    /**
     * Cache-busting version for the static stylesheet.
     *
     * @return string
     */
    public static function cssurl(): string {
        global $CFG;
        $file = $CFG->dirroot . '/theme/baz/newtheme/newtheme.css';
        $v = @filemtime($file) ?: 1;
        return $CFG->wwwroot . '/theme/baz/newtheme/newtheme.css?v=' . $v;
    }
}
