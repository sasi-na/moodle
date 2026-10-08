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
 * Settings access and provider registry for block_endsemesterexam.
 *
 * @package   block_endsemesterexam
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_endsemesterexam\local;

use block_endsemesterexam\local\provider\provider;

defined('MOODLE_INTERNAL') || die();

/**
 * How this site decides who sits the exam, and which portal they are sent to.
 *
 * Every endpoint and credential in this plugin used to be a literal in
 * redirect.php pointing at one institution's exam provider. On an image shared
 * by unrelated institutions that is a cross-tenant data path: any site showing
 * the button would send its own learners' details to that institution's ERP and
 * then hand the browser to its exam portal.
 *
 * The button stays common; the portal behind it is a per-site choice. Each
 * portal is a {@see provider} implementation, registered below.
 */
class config {

    /** @var string Gate: learners enrolled anywhere below a course category. */
    const GATE_CATEGORY = 'category';

    /** @var string Gate: learners whose register-number profile field is filled. */
    const GATE_PROFILEFIELD = 'profilefield';

    /** @var int Category this plugin shipped with, before the value was a setting. */
    const LEGACY_CATEGORY = 174;

    /**
     * The exam portals this plugin can hand a candidate to.
     *
     * Adding an institution with a different portal means adding a class
     * implementing {@see provider} and one entry here -- plus its settings in
     * settings.php and its name in the language file. Nothing in the block, the
     * gate or the redirect needs to know about it.
     *
     * The first entry is the default for a site that has configured nothing,
     * which keeps existing sites on the behaviour they already had.
     *
     * @var array<string, class-string<provider>>
     */
    // Fully qualified deliberately. The interface is `provider` inside namespace
    // `...\local\provider`, so the `use` above binds `provider` as an alias and a
    // relative `provider\examstack` would resolve through it to
    // `...\local\provider\provider\examstack`, which does not exist.
    const PROVIDERS = [
        'examstack'   => \block_endsemesterexam\local\provider\examstack::class,
        'gianveshana' => \block_endsemesterexam\local\provider\gianveshana::class,
    ];

    /**
     * A trimmed string setting.
     *
     * @param string $name
     * @return string
     */
    public static function str(string $name): string {
        return trim((string) get_config('block_endsemesterexam', $name));
    }

    /**
     * Which learners see the button.
     *
     * @return string One of the GATE_* constants.
     */
    public static function gatemode(): string {
        return self::str('gatemode') === self::GATE_PROFILEFIELD
            ? self::GATE_PROFILEFIELD
            : self::GATE_CATEGORY;
    }

    /**
     * Top-level course category whose learners sit the exam (category gate).
     *
     * @return int
     */
    public static function category(): int {
        $value = (int) self::str('examcategory');
        return $value > 0 ? $value : self::LEGACY_CATEGORY;
    }

    /**
     * Shortname of the profile field holding the register number.
     *
     * @return string
     */
    public static function registerfield(): string {
        $value = self::str('registerfield');
        return $value !== '' ? $value : 'register_no';
    }

    /**
     * The configured provider's key.
     *
     * @return string
     */
    public static function provider_name(): string {
        $name = self::str('provider');
        return isset(self::PROVIDERS[$name]) ? $name : array_key_first(self::PROVIDERS);
    }

    /**
     * The configured provider.
     *
     * @return provider
     */
    public static function provider(): provider {
        $class = self::PROVIDERS[self::provider_name()];
        return new $class();
    }
}
