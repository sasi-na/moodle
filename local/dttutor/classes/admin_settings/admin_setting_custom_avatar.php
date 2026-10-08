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
 * Custom admin setting for uploading custom avatar
 *
 * @package    local_dttutor
 * @copyright  2025 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dttutor\admin_settings;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');

/**
 * Admin setting for uploading custom avatar image
 *
 * @package    local_dttutor
 * @copyright  2025 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_custom_avatar extends \admin_setting_configstoredfile {
    /**
     * Constructor
     *
     * @param string $name
     * @param string $visiblename
     * @param string $description
     */
    public function __construct($name, $visiblename, $description) {
        // Call parent constructor with correct parameters.
        // Parameters: name, visiblename, description, filearea, itemid, options.
        parent::__construct(
            $name,
            $visiblename,
            $description . ' ' . get_string('customavatar_dimensions', 'local_dttutor'),
            'customavatar', // Filearea name.
            0, // Itemid (0 for single file).
            [
                'maxfiles' => 1,
                'accepted_types' => ['web_image'], // Use web_image instead of extensions.
                'subdirs' => 0,
                'maxbytes' => 512000, // 512KB max size.
            ]
        );
    }

    /**
     * Return HTML for the setting
     *
     * @param mixed $data
     * @param string $query
     * @return string HTML
     */
    public function output_html($data, $query = '') {
        global $OUTPUT;

        $html = parent::output_html($data, $query);

        // Check if custom avatar exists and show preview.
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            \context_system::instance()->id,
            'local_dttutor',
            'customavatar',
            0,
            'timemodified DESC',
            false  // Exclude directories.
        );

        if (!empty($files)) {
            $file = reset($files);
            $url = \moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename()
            );

            // Render preview using template.
            $templatecontext = [
                'url' => $url->out(),
                'str_preview' => get_string('preview'),
            ];
            $html .= $OUTPUT->render_from_template('local_dttutor/admin_custom_avatar_preview', $templatecontext);
        }

        return $html;
    }
}
