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
 * This file defines the admin settings for this plugin
 *
 * @package   assignsubmission_file
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


// Note: This is on by default.
$settings->add(new admin_setting_configcheckbox('assignsubmission_file/default',
                   new lang_string('default', 'assignsubmission_file'),
                   new lang_string('default_help', 'assignsubmission_file'), 1));

$settings->add(new admin_setting_configtext('assignsubmission_file/maxfiles',
                   new lang_string('maxfiles', 'assignsubmission_file'),
                   new lang_string('maxfiles_help', 'assignsubmission_file'), 20, PARAM_INT));

$settings->add(new admin_setting_filetypes('assignsubmission_file/filetypes',
                   new lang_string('defaultacceptedfiletypes', 'assignsubmission_file'),
                   new lang_string('acceptedfiletypes_help', 'assignsubmission_file'), ''));

// Jain customisation: allow faculty to preview submitted files before downloading.
$settings->add(new admin_setting_configcheckbox('assignsubmission_file/enablepreview',
                   new lang_string('enablepreview', 'assignsubmission_file'),
                   new lang_string('enablepreview_help', 'assignsubmission_file'), 1));

// Preview Office documents (.docx/.xlsx/.pptx) via the Microsoft Office Online viewer.
// Note: this sends a tokenised file URL to Microsoft, so submission content transits a
// third party. Disable to keep all previews same-origin (PDF/image/text only).
$settings->add(new admin_setting_configcheckbox('assignsubmission_file/enableofficepreview',
                   new lang_string('enableofficepreview', 'assignsubmission_file'),
                   new lang_string('enableofficepreview_help', 'assignsubmission_file'), 1));

if (isset($CFG->maxbytes)) {

    $name = new lang_string('maximumsubmissionsize', 'assignsubmission_file');
    $description = new lang_string('configmaxbytes', 'assignsubmission_file');

    $maxbytes = get_config('assignsubmission_file', 'maxbytes');
    $element = new admin_setting_configselect('assignsubmission_file/maxbytes',
                                              $name,
                                              $description,
                                              $CFG->maxbytes,
                                              get_max_upload_sizes($CFG->maxbytes, 0, 0, $maxbytes));
    $settings->add($element);
}
