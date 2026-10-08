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
 * Custom admin setting for avatar position with live preview
 *
 * @package    local_dttutor
 * @copyright  2025 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dttutor\admin_settings;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');

/**
 * Admin setting for avatar position with live preview
 *
 * Stores position as JSON with preset, coordinates, drawer side and reference edges
 *
 * @package    local_dttutor
 * @copyright  2025 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_position_preview extends \admin_setting {
    /**
     * Return the current setting
     *
     * @return mixed
     */
    public function get_setting() {
        return $this->config_read($this->name);
    }

    /**
     * Save the position settings
     *
     * @param mixed $data
     * @return string Empty string if ok, error message otherwise
     */
    public function write_setting($data) {
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if ($decoded === null) {
                return get_string('error_invalid_position', 'local_dttutor');
            }

            if (
                !isset($decoded['preset']) || !isset($decoded['x']) || !isset($decoded['y']) ||
                !isset($decoded['drawerside']) || !isset($decoded['xref']) || !isset($decoded['yref'])
            ) {
                return get_string('error_invalid_position', 'local_dttutor');
            }

            if (!in_array($decoded['preset'], ['right', 'left', 'custom'])) {
                return get_string('error_invalid_position', 'local_dttutor');
            }

            if (!in_array($decoded['drawerside'], ['right', 'left'])) {
                return get_string('error_invalid_position', 'local_dttutor');
            }

            if (!in_array($decoded['xref'], ['left', 'right'])) {
                return get_string('error_invalid_position', 'local_dttutor');
            }

            if (!in_array($decoded['yref'], ['bottom', 'top'])) {
                return get_string('error_invalid_position', 'local_dttutor');
            }

            if ($decoded['preset'] === 'custom') {
                if (!$this->validate_css_value($decoded['x']) || !$this->validate_css_value($decoded['y'])) {
                    return get_string('error_invalid_coordinates', 'local_dttutor');
                }
            }
        }
        return $this->config_write($this->name, $data) ? '' : get_string('errorsetting', 'admin');
    }

    /**
     * Validate CSS value (e.g., "10px", "2rem", "50%")
     *
     * @param string $value
     * @return bool
     */
    private function validate_css_value($value) {
        return preg_match('/^-?\d+(\.\d+)?(px|rem|em|%|vh|vw)$/', $value) === 1;
    }

    /**
     * Return HTML for the setting with live preview
     *
     * @param mixed $data
     * @param string $query
     * @return string HTML
     */
    public function output_html($data, $query = '') {
        global $OUTPUT, $CFG, $PAGE;

        $default = $this->get_defaultsetting();
        $current = $this->get_setting();
        if ($current === null) {
            $current = $default;
        }

        $currentdata = json_decode($current, true);
        if ($currentdata === null) {
            $currentdata = [
                'preset' => 'right',
                'x' => '2rem',
                'y' => '6rem',
                'drawerside' => 'right',
                'xref' => 'right',
                'yref' => 'bottom',
            ];
        }

        $preset = $currentdata['preset'] ?? 'right';
        $xvalue = $currentdata['x'] ?? '2rem';
        $yvalue = $currentdata['y'] ?? '6rem';
        $drawerside = $currentdata['drawerside'] ?? 'right';
        $xref = $currentdata['xref'] ?? (strpos($xvalue, '-') === 0 ? 'right' : 'left');
        $yref = $currentdata['yref'] ?? (strpos($yvalue, '-') === 0 ? 'top' : 'bottom');

        $avatar = get_config('local_dttutor', 'avatar') ?? '01';
        $avatarurl = $CFG->wwwroot . '/local/dttutor/pix/avatars/avatar_profesor_' . $avatar . '.png';

        $customavatarurl = $this->get_custom_avatar_url();
        if ($customavatarurl) {
            $avatarurl = $customavatarurl;
        }

        // Load CSS for admin settings.
        $html = '<link rel="stylesheet" href="' . $CFG->wwwroot . '/local/dttutor/styles_admin.css">';

        // Load AMD module properly via $PAGE.
        $PAGE->requires->js_call_amd('local_dttutor/position_configurator', 'init');

        // Prepare preset options for template.
        $presets = [
            ['value' => 'right', 'label' => get_string('position_right', 'local_dttutor'), 'selected' => ($preset === 'right')],
            ['value' => 'left', 'label' => get_string('position_left', 'local_dttutor'), 'selected' => ($preset === 'left')],
            ['value' => 'custom', 'label' => get_string('position_custom', 'local_dttutor'), 'selected' => ($preset === 'custom')],
        ];

        // Prepare drawer side options.
        $drawersideoptions = [
            ['value' => 'right', 'label' => get_string('drawer_side_right', 'local_dttutor'),
                'selected' => ($drawerside === 'right')],
            ['value' => 'left', 'label' => get_string('drawer_side_left', 'local_dttutor'),
                'selected' => ($drawerside === 'left')],
        ];

        // Prepare reference edge options.
        $xrefoptions = [
            ['value' => 'left', 'label' => get_string('ref_left', 'local_dttutor'), 'selected' => ($xref === 'left')],
            ['value' => 'right', 'label' => get_string('ref_right', 'local_dttutor'), 'selected' => ($xref === 'right')],
        ];

        $yrefoptions = [
            ['value' => 'bottom', 'label' => get_string('ref_bottom', 'local_dttutor'), 'selected' => ($yref === 'bottom')],
            ['value' => 'top', 'label' => get_string('ref_top', 'local_dttutor'), 'selected' => ($yref === 'top')],
        ];

        // Prepare template context.
        $templatecontext = [
            'presets' => $presets,
            'drawerside_options' => $drawersideoptions,
            'xref_options' => $xrefoptions,
            'yref_options' => $yrefoptions,
            'xvalue' => s($xvalue),
            'yvalue' => s($yvalue),
            'avatarurl' => $avatarurl,
            'hiddenvalue' => s($current),
            'str_position_preset' => get_string('position_preset', 'local_dttutor'),
            'str_drawer_side' => get_string('drawer_side', 'local_dttutor'),
            'str_drawer_side_help' => get_string('drawer_side_help', 'local_dttutor'),
            'str_position_x' => get_string('position_x', 'local_dttutor'),
            'str_position_y' => get_string('position_y', 'local_dttutor'),
            'str_position_x_help' => get_string('position_x_help', 'local_dttutor'),
            'str_position_y_help' => get_string('position_y_help', 'local_dttutor'),
            'str_reference_edge_x' => get_string('reference_edge_x', 'local_dttutor'),
            'str_reference_edge_y' => get_string('reference_edge_y', 'local_dttutor'),
            'str_preview' => get_string('preview', 'local_dttutor'),
            'custom_active' => ($preset === 'custom'),
        ];

        // Render template.
        $html .= $OUTPUT->render_from_template('local_dttutor/admin_position_preview', $templatecontext);

        return format_admin_setting($this, $this->visiblename, $html, $this->description, true, '', $default, $query);
    }

    /**
     * Get custom avatar URL if it exists
     *
     * @return string|null
     */
    private function get_custom_avatar_url() {
        global $CFG;
        $fs = get_file_storage();
        $context = \context_system::instance();
        $files = $fs->get_area_files($context->id, 'local_dttutor', 'customavatar', 0, 'timemodified DESC', false);

        if (!empty($files)) {
            $file = reset($files);
            return \moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename()
            )->out();
        }

        return null;
    }
}
