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

namespace local_cagrades\task;

use local_cagrades\past_ca;
use local_cagrades\run_lock;

defined('MOODLE_INTERNAL') || die();

/**
 * Keeps previous-semester CA marks hidden from learners.
 *
 * This has to recur rather than run once, for two reasons. Marks are graded continuously, so a
 * semester that has just passed keeps producing rows to hide. And, less obviously, staff can undo
 * the hiding without meaning to: the gradebook's show/hide control calls
 * grade_item::set_hidden($hidden, true) - grade/edit/tree/action.php:68 and :83 - and that cascade
 * rewrites the hidden flag on EVERY grade_grade row of the item, wiping the per-learner values this
 * sets. A daily pass repairs that drift.
 *
 * Because the rule only ever hides, the repair is safe: a deliberate staff hide survives it, and a
 * staff reveal of a previous semester's CA is undone the next day rather than leaking indefinitely.
 *
 * @package    local_cagrades
 * @copyright  2026 JAIN Online
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hide_past_ca extends \core\task\scheduled_task {

    /**
     * @var int Most rows one run will hide.
     *
     * The first pass has ~265,000 rows to get through, which is not something to do inside a cron
     * run competing with everything else. Steady state is a few hundred, so this ceiling is only
     * ever reached while catching up - and whatever is left is reported and picked up tomorrow. Use
     * the CLI for the initial sweep.
     */
    public const MAX_PER_RUN = 50000;

    /**
     * @return string
     */
    public function get_name(): string {
        return get_string('task_hide_past_ca', 'local_cagrades');
    }

    /**
     * Hide every previous-semester CA mark that is not hidden yet.
     *
     * @return void
     */
    public function execute(): void {
        // The CLI sweep rewrites a journal file whole, so the two must not overlap.
        $lock = run_lock::acquire(past_ca::RUN_NAME);
        if ($lock === null) {
            mtrace('local_cagrades: ' . past_ca::RUN_NAME . ' is already running - skipping this run.');
            return;
        }
        run_lock::release_on_shutdown($lock);

        $hidden = 0;
        while ($hidden < self::MAX_PER_RUN) {
            $batch = min(past_ca::BATCH_SIZE, self::MAX_PER_RUN - $hidden);
            $ids = past_ca::candidate_ids($batch);
            if (!$ids) {
                break;
            }
            $hidden += past_ca::hide($ids);
        }

        if ($hidden === 0) {
            mtrace('local_cagrades: no previous-semester CA marks needed hiding.');
            return;
        }

        mtrace("local_cagrades: hid {$hidden} previous-semester CA mark(s).");

        // Never let a ceiling pass silently as "done".
        $remaining = past_ca::count_candidates();
        if ($remaining > 0) {
            mtrace("local_cagrades: {$remaining} still to hide, deferred to the next run "
                . '(ceiling ' . self::MAX_PER_RUN . ' per run).');
        }
    }
}
