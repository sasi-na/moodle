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

declare(strict_types=1);

namespace local_cagrades;

use core\lock\lock;
use core\lock\lock_config;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * The one-writer guard shared by this plugin's rollout tools.
 *
 * Both tools rewrite their journal file whole, so two overlapping runs silently discard each other's
 * entries. The lock stops that. What it must not do is stop everything else: on this site the lock
 * factory is db_record_lock_factory, which records a lock as a row in lock_db with an expiry, and a
 * run that is killed never reaches its release. With Moodle's default 24-hour lifetime that row locked
 * out every subsequent run for a day - which is exactly what happened on 2026-07-31, mid-rollout.
 *
 * So the lifetime here is an hour, not a day, and there is a way to clear a lock by hand.
 *
 * @package    local_cagrades
 * @copyright  2026 JAIN Online
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class run_lock {

    /**
     * @var int How long a held lock survives its owner, in seconds.
     *
     * Long enough that no sane invocation outlives it - the tools are meant to be driven in batches
     * with --limit, and a batch takes minutes - and short enough that a killed run heals itself
     * within the hour instead of blocking the next one until tomorrow.
     */
    public const MAXLIFETIME = 3600;

    /** @var string The lock factory type this plugin books its locks under. */
    protected const TYPE = 'local_cagrades';

    /**
     * Take the lock for a named run, without waiting.
     *
     * @param string $name the run's name, e.g. "apply_ca_category"
     * @return lock|null null when somebody else holds it
     */
    public static function acquire(string $name): ?lock {
        $factory = lock_config::get_lock_factory(self::TYPE);
        $lock = $factory->get_lock($name, 0, self::MAXLIFETIME);

        return $lock === false ? null : $lock;
    }

    /**
     * Release a lock on every exit path, cli_error() included.
     *
     * The lock destructor does not release outside PHPUnit, and a db_record_lock_factory lock is a row
     * that outlives the process rather than a database session that ends with it.
     *
     * @param lock $lock
     * @return void
     */
    public static function release_on_shutdown(lock $lock): void {
        \core_shutdown_manager::register_function(static function() use ($lock): void {
            $lock->release();
        });
    }

    /**
     * Who holds a named lock, if anyone.
     *
     * Reads lock_db directly, so it only tells the truth under db_record_lock_factory. Other factories
     * tie a lock to the database session, where a dead process cannot leave one behind - so there is
     * nothing to report and nothing to force.
     *
     * @param string $name
     * @return stdClass|null {owner, expires, expired} or null when free, absent or not applicable
     */
    public static function describe(string $name): ?stdClass {
        global $DB;

        if (!self::is_row_based()) {
            return null;
        }
        $record = $DB->get_record('lock_db', ['resourcekey' => self::resourcekey($name)],
            'id, resourcekey, owner, expires');
        if (!$record || $record->owner === null) {
            return null;
        }

        $info = new stdClass();
        $info->owner = (string) $record->owner;
        $info->expires = (int) $record->expires;
        $info->expired = $info->expires > 0 && $info->expires < time();

        return $info;
    }

    /**
     * Clear a named lock somebody else is holding.
     *
     * For recovering from a killed run. Does the same thing db_record_lock_factory::release_lock()
     * does - blanks the owner and the expiry - rather than deleting the row, so the factory's own
     * bookkeeping still holds.
     *
     * The caller is responsible for being sure no run is genuinely active: this cannot tell a killed
     * process from a working one.
     *
     * @param string $name
     * @return bool whether a held lock was cleared
     */
    public static function force_release(string $name): bool {
        global $DB;

        if (!self::is_row_based()) {
            return false;
        }
        $record = $DB->get_record('lock_db', ['resourcekey' => self::resourcekey($name)], 'id, owner');
        if (!$record || $record->owner === null) {
            return false;
        }
        $DB->execute('UPDATE {lock_db} SET expires = NULL, owner = NULL WHERE id = :id',
            ['id' => $record->id]);

        return true;
    }

    /**
     * Whether locks are rows that can outlive their owner.
     *
     * @return bool
     */
    public static function is_row_based(): bool {
        global $DB;

        return lock_config::get_lock_factory_class() === '\core\lock\db_record_lock_factory'
            && $DB->get_manager()->table_exists('lock_db');
    }

    /**
     * The lock_db key the factory builds for one of our locks.
     *
     * Mirrors db_record_lock_factory::get_lock(), which keys on "<type>_<resource>".
     *
     * @param string $name
     * @return string
     */
    protected static function resourcekey(string $name): string {
        return self::TYPE . '_' . $name;
    }
}
