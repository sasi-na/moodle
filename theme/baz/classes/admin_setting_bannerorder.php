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

namespace theme_baz;

defined('MOODLE_INTERNAL') || die();

/**
 * Drag-to-reorder admin setting for the dashboard banner slides.
 *
 * Renders the configured slides as draggable thumbnail cards (core/sortable_list)
 * and stores their sequence in a single config value: a comma-separated list of
 * slot indices in display order, e.g. "3,1,2". The order is written to a hidden
 * input that the theme_baz/bannerorder AMD module updates on drop, so it saves
 * with the normal settings form — no web service required.
 *
 * Slots (the per-slide image/link/alt fields) never move; only their display
 * position changes, so uploaded images stay put. theme_baz_get_dashboard_banners()
 * reads this same value to order the slides for students.
 *
 * @package   theme_baz
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_bannerorder extends \admin_setting {

    /**
     * @param string $name       Setting name, e.g. 'theme_baz/dashboardbannerorder'.
     * @param string $visiblename Setting title.
     * @param string $description Setting help text.
     * @param string $defaultsetting Default order list.
     */
    public function __construct($name, $visiblename, $description, $defaultsetting = '') {
        parent::__construct($name, $visiblename, $description, $defaultsetting);
    }

    /**
     * @return mixed The stored order list, or null if never saved.
     */
    public function get_setting() {
        return $this->config_read($this->name);
    }

    /**
     * Persist the (normalised) slot order.
     *
     * @param string $data Comma-separated slot indices from the hidden input.
     * @return string '' on success, error string otherwise.
     */
    public function write_setting($data) {
        $order = self::normalise_order((string) $data, self::bannercount());
        return $this->config_write($this->name, implode(',', $order)) ? '' : get_string('errorsetting', 'admin');
    }

    /**
     * Render the draggable card board plus the hidden order input.
     *
     * @param mixed $data Current stored value.
     * @param string $query
     * @return string
     */
    public function output_html($data, $query = '') {
        global $PAGE, $OUTPUT;

        $count = self::bannercount();
        $order = self::normalise_order((string) ($data ?? ''), $count);

        $theme = \theme_config::load('baz');
        $slides = [];
        foreach ($order as $slot) {
            $img = $theme->setting_file_url('dashboardbannerimg' . $slot, 'dashboardbannerimg' . $slot);
            $slides[] = [
                'slot'      => $slot,
                'hasimg'    => !empty($img),
                'img'       => $img ?: '',
                'label'     => get_string('dashboardbannerslidelabel', 'theme_baz', $slot),
                'movetitle' => get_string('dashboardbannermove', 'theme_baz', $slot),
            ];
        }

        $context = [
            'id'     => $this->get_id(),
            'name'   => $this->get_full_name(),
            'value'  => implode(',', $order),
            'slides' => $slides,
        ];
        $element = $OUTPUT->render_from_template('theme_baz/banner_order', $context);
        $PAGE->requires->js_call_amd('theme_baz/bannerorder', 'init', [$this->get_id()]);

        return format_admin_setting($this, $this->visiblename, $element, $this->description,
            true, '', $this->get_defaultsetting(), $query);
    }

    /**
     * Configured slide count, clamped to the supported 1..5 range.
     *
     * @return int
     */
    protected static function bannercount(): int {
        $count = (int) get_config('theme_baz', 'dashboardbannercount');
        return max(1, min($count ?: 1, 5));
    }

    /**
     * Normalise an order list: keep valid, unique slot indices (1..count) in the
     * given order, then append any missing slots in natural order so the result
     * is always a complete, well-formed permutation of 1..count.
     *
     * @param string $raw Comma-separated slot indices.
     * @param int $count Number of slides.
     * @return int[] Ordered slot indices.
     */
    protected static function normalise_order(string $raw, int $count): array {
        $seen = [];
        foreach (explode(',', $raw) as $token) {
            $n = (int) trim($token);
            if ($n >= 1 && $n <= $count && !isset($seen[$n])) {
                $seen[$n] = true;
            }
        }
        for ($i = 1; $i <= $count; $i++) {
            $seen[$i] = true;
        }
        return array_keys($seen);
    }
}
