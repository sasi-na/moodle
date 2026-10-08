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
 * Handles file serving from the zoomonline block.
 *
 * @package   block_zoomonline
 * @category  files
 * @copyright 2024 Ciaran Mac Donncha
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
/**
 * Serve the files from the zoomonline block.
 *
 * @param stdClass $course The course object.
 * @param stdClass $cm The course module record, or null if not used.
 * @param context $context The context object for the block.
 * @param string $filearea The name of the file area.
 * @param array $args The extra arguments (itemid, path).
 * @param bool $forcedownload Whether or not force download of file.
 * @param array $options Additional options affecting the file serving.
 * @return bool False if file not found, does not return if found - just sends the file.
 */
function block_zoomonline_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $CFG;

    if ($context->contextlevel != CONTEXT_BLOCK) {
        send_file_not_found();
    }

    if ($filearea !== 'zoom_videos') {
        send_file_not_found();
    }

    require_login($course);

    $itemid = array_shift($args);
    $filename = array_pop($args);
    $filepath = '/' . implode('/', $args) . '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'block_zoomonline', $filearea, $itemid, $filepath, $filename);

    if (!$file || $file->is_directory()) {
        send_file_not_found();
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}
