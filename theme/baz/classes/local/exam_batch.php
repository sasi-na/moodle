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
 * Exam-batch audience helpers for theme_baz.
 *
 * @package   theme_baz
 * @copyright 2026 onlinejain.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_baz\local;

defined('MOODLE_INTERNAL') || die();

/**
 * The batches allowed to see the End-Sem Exam button.
 *
 * The audience used to be a hardcoded constant, so every exam cycle needed a
 * code change and a deploy. It is now the admin setting
 * theme_baz/endsem_exam_batches — a checkbox per batch, where the batch list is
 * the distinct set of values actually present in the "Cohort/Batch" profile
 * field (shortname cohort_batch), which is free text rather than a menu.
 *
 * Batch values contain commas ("July, 2025") and configmulticheckbox stores its
 * selection as one comma-separated string, so the checkbox KEY is a normalised
 * slug ("july2025") and the raw value is only the label. Matching normalises the
 * learner's stored value the same way, which also means spelling and punctuation
 * variants of one batch collapse onto a single checkbox.
 */
class exam_batch {

    /** @var string Profile field holding the platform-wide batch identifier. */
    const FIELD = 'cohort_batch';

    /** @var string Selection when the setting has never been saved: July 2025 only. */
    const DEFAULT_BATCHES = 'july2025';

    /**
     * Lower-case a batch value and strip everything that is not a letter or
     * digit, giving a key that is safe in a comma-separated config value:
     * "July, 2025" => "july2025", "Jan, 2026 - GL" => "jan2026gl".
     *
     * @param string $value
     * @return string
     */
    public static function normalise(string $value): string {
        return preg_replace('/[^a-z0-9]/', '', strtolower(trim($value)));
    }

    /**
     * Every batch present in the cohort_batch profile field, as
     * normalised key => human label with its active-learner count.
     *
     * Runs one grouped query, so only call it while building the theme settings
     * page (theme/baz/settings.php is already wrapped in $ADMIN->fulltree).
     * Returns an empty list on a tenant with no cohort_batch field.
     *
     * @return array
     */
    public static function choices(): array {
        global $DB;

        $fieldid = $DB->get_field('user_info_field', 'id', ['shortname' => self::FIELD]);
        if (!$fieldid) {
            return [];
        }

        $rows = $DB->get_records_sql("
                SELECT d.data AS val, COUNT(u.id) AS n
                  FROM {user_info_data} d
                  JOIN {user} u ON u.id = d.userid
                 WHERE d.fieldid = :fieldid
                   AND u.deleted = 0
                   AND u.suspended = 0
                   AND d.data <> ''
              GROUP BY d.data
              ORDER BY d.data", ['fieldid' => $fieldid]);

        // Variant spellings of one batch share a key, so merge their counts and
        // list every spelling in the label.
        $merged = [];
        foreach ($rows as $row) {
            $key = self::normalise($row->val);
            if ($key === '') {
                continue;
            }
            if (!isset($merged[$key])) {
                $merged[$key] = ['labels' => [], 'n' => 0];
            }
            $merged[$key]['labels'][] = trim($row->val);
            $merged[$key]['n'] += (int) $row->n;
        }

        $choices = [];
        foreach ($merged as $key => $batch) {
            // Plain text, no markup: the label is rendered unescaped by the
            // checkbox template, but format_admin_setting() also reuses it
            // ESCAPED in the "Default:" hint, where tags would show literally.
            // Still escape the value itself — it comes from a profile field.
            $choices[$key] = get_string('endsem_exam_batch_choice', 'theme_baz', [
                'batch' => s(implode(' / ', $batch['labels'])),
                'count' => number_format($batch['n']),
            ]);
        }
        return $choices;
    }

    /**
     * The currently selected batches as normalised keys. Falls back to the
     * shipped default while the setting is unsaved; an explicitly empty
     * selection means nobody, and stays empty.
     *
     * @return string[]
     */
    public static function selected(): array {
        $raw = get_config('theme_baz', 'endsem_exam_batches');
        if ($raw === false) {
            $raw = self::DEFAULT_BATCHES;
        }
        return array_values(array_filter(array_map('trim', explode(',', (string) $raw)), 'strlen'));
    }

    /**
     * Whether a learner's stored cohort_batch value is in the selected batches.
     * Fails closed on an empty value.
     *
     * @param string $value Raw profile field value, e.g. "July, 2025".
     * @return bool
     */
    public static function matches(string $value): bool {
        $key = self::normalise($value);
        return $key !== '' && in_array($key, self::selected(), true);
    }
}
