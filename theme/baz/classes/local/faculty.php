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
 * Resolves the single "faculty" shown for a course in the bespoke student views
 * (course cards and course page).
 *
 * A course may have several teachers. By default Moodle's course contacts are
 * ordered by role sortorder then user name, so the first one wins alphabetically.
 * To let a course explicitly nominate its lead, this helper first honours a
 * "Primary faculty" course custom field (short name {@see self::FIELD_SHORTNAME},
 * a text field holding the teacher's email or username). The nominated user is
 * only used when they are an actual course contact of that course; otherwise —
 * and whenever the field is unset, mistyped, or the user has left — it falls back
 * to the standard first-course-contact behaviour. Any failure degrades silently.
 *
 * @package   theme_baz
 * @copyright 2026 onlinejain.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class faculty {

    /** @var string Course custom field short name holding the primary faculty (email/username). */
    const FIELD_SHORTNAME = 'primaryfaculty';

    /** @var array<int,array> Per-request cache: courseid => ['name','abbr','id']. */
    protected static $cache = [];

    /**
     * The faculty to display for a course.
     *
     * @param int $courseid
     * @return array ['name' => string, 'abbr' => string, 'id' => int] (empty name if none)
     */
    public static function for_course(int $courseid): array {
        if (isset(self::$cache[$courseid])) {
            return self::$cache[$courseid];
        }

        $result = ['name' => '', 'abbr' => '', 'id' => 0];
        try {
            global $DB;
            $record = $DB->get_record('course', ['id' => $courseid]);
            if (!$record) {
                return self::$cache[$courseid] = $result;
            }

            $element = new \core_course_list_element($record);
            // Keyed by user id; ordered by role sortorder then user name (core).
            $contacts = $element->get_course_contacts();

            // 1. Nominated primary faculty, but only if they are a course contact.
            $primaryid = self::designated_userid($courseid);
            if ($primaryid && isset($contacts[$primaryid])) {
                $built = self::from_contact($primaryid, $contacts[$primaryid]);
                if ($built['name'] !== '') {
                    return self::$cache[$courseid] = $built;
                }
            }

            // 2. Fallback: the first usable course contact (existing behaviour).
            foreach ($contacts as $cid => $contact) {
                $built = self::from_contact($cid, $contact);
                if ($built['name'] !== '') {
                    $result = $built;
                    break;
                }
            }
        } catch (\Throwable $e) {
            // Degrade silently — the card just shows no faculty.
        }

        return self::$cache[$courseid] = $result;
    }

    /**
     * Build the display tuple from a single course-contact entry.
     *
     * @param int|string $cid contact key (user id)
     * @param array $contact core course-contact record
     * @return array ['name','abbr','id']
     */
    protected static function from_contact($cid, array $contact): array {
        $name = isset($contact['username']) ? (string) $contact['username'] : '';
        if ($name === '' && isset($contact['user'])) {
            $name = fullname($contact['user']);
        }
        if ($name === '') {
            return ['name' => '', 'abbr' => '', 'id' => 0];
        }
        $uid = isset($contact['user']->id) ? (int) $contact['user']->id : (int) $cid;
        return ['name' => $name, 'abbr' => self::initials($name), 'id' => $uid];
    }

    /**
     * Resolve the "Primary faculty" custom field value (email, then username) to a user id.
     *
     * @param int $courseid
     * @return int user id, or 0 if unset/unresolved
     */
    protected static function designated_userid(int $courseid): int {
        global $DB;
        $value = self::custom_field_value($courseid);
        if ($value === '') {
            return 0;
        }
        $value = \core_text::strtolower($value);
        $rec = $DB->get_record('user', ['email' => $value, 'deleted' => 0], 'id', IGNORE_MULTIPLE);
        if (!$rec) {
            $rec = $DB->get_record('user', ['username' => $value, 'deleted' => 0], 'id', IGNORE_MULTIPLE);
        }
        return $rec ? (int) $rec->id : 0;
    }

    /**
     * Read the raw "Primary faculty" custom field value for a course.
     * Uses $returnall = true so the field is found even when its visibility is "Nobody".
     *
     * @param int $courseid
     * @return string normalised value, or '' if the field is undefined/empty
     */
    protected static function custom_field_value(int $courseid): string {
        try {
            $handler = \core_course\customfield\course_handler::create();
            foreach ($handler->get_instance_data($courseid, true) as $data) {
                if ($data->get_field()->get('shortname') === self::FIELD_SHORTNAME) {
                    return self::normalise_value($data->get_value());
                }
            }
        } catch (\Throwable $e) {
            return '';
        }
        return '';
    }

    /**
     * Reduce a stored field value to the bare email/username we can look a user up by.
     *
     * The field may be a textarea/editor type, in which case the value arrives wrapped
     * in HTML ("<p>someone@example.com</p>") and would never match a user record. Only
     * the first entry is used, so a field someone has filled in as a list still resolves.
     *
     * @param mixed $value raw custom field value
     * @return string
     */
    protected static function normalise_value($value): string {
        if (!is_string($value) || $value === '') {
            return '';
        }
        $value = preg_replace('~<\s*(br|/p|/div|/li)\s*/?\s*>~i', "\n", $value);
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace("\xc2\xa0", ' ', $value);
        foreach (preg_split('/[\r\n,;]+/', $value) as $entry) {
            $entry = trim($entry);
            if ($entry !== '') {
                return $entry;
            }
        }
        return '';
    }

    /**
     * Two-letter initials for an avatar chip (matches the exporters' previous logic).
     *
     * @param string $name
     * @return string
     */
    protected static function initials(string $name): string {
        $words = preg_split('/\s+/', trim($name));
        $words = array_values(array_filter($words, fn($w) => $w !== '' && ctype_alpha($w[0])));
        if (!$words) {
            return \core_text::strtoupper(\core_text::substr(trim($name), 0, 2));
        }
        $first = \core_text::strtoupper(\core_text::substr($words[0], 0, 1));
        $last = count($words) > 1 ? \core_text::strtoupper(\core_text::substr(end($words), 0, 1)) : '';
        return $first . $last;
    }
}
